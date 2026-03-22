(function (global) {
    'use strict';

    const DEFAULTS = {
        provider: 'heygen',
        context: 'lesson',
        debug: false,

        endpoints: {
            createSession: '/api/avatar/session',
            closeSession: '/api/avatar/session/close'
        },

        sessionStartTimeoutMs: 20000,
        speechTimeoutMs: 45000,

        avatarId: null,
        voiceId: null
    };

    const EVENTS = Object.freeze({
        READY: 'ready',
        SPEECH_START: 'speechStart',
        SPEECH_END: 'speechEnd',
        ERROR: 'error',
        SESSION_CLOSED: 'sessionClosed'
    });

    const state = {
        options: { ...DEFAULTS },
        provider: 'heygen',

        containerEl: null,
        mounted: false,

        ready: false,
        speaking: false,
        sessionActive: false,
        unavailable: false,

        currentSpeechId: null,
        sessionInfo: null,
        providerClient: null,

        sessionStartPromise: null,
        speechPromise: null,

        sessionTimeoutId: null,
        speechTimeoutId: null,

        listeners: {
            ready: new Set(),
            speechStart: new Set(),
            speechEnd: new Set(),
            error: new Set(),
            sessionClosed: new Set()
        }
    };

    function log(...args) {
        if (!state.options.debug) return;
        console.log('[AvatarService]', ...args);
    }

    function normalizeText(text) {
        return String(text || '').replace(/\s+/g, ' ').trim();
    }

    function emit(eventName, payload = {}) {
        const listeners = state.listeners[eventName];
        if (!listeners || !listeners.size) return;

        listeners.forEach((callback) => {
            try {
                callback(payload);
            } catch (error) {
                console.error(`[AvatarService] Error en listener "${eventName}":`, error);
            }
        });
    }

    function on(eventName, callback) {
        if (!state.listeners[eventName] || typeof callback !== 'function') return;
        state.listeners[eventName].add(callback);
    }

    function off(eventName, callback) {
        if (!state.listeners[eventName] || typeof callback !== 'function') return;
        state.listeners[eventName].delete(callback);
    }

    function clearTimeoutSafe(timeoutId) {
        if (timeoutId) clearTimeout(timeoutId);
        return null;
    }

    function resetSpeechState() {
        state.speaking = false;
        state.currentSpeechId = null;
        state.speechPromise = null;
        state.speechTimeoutId = clearTimeoutSafe(state.speechTimeoutId);
    }

    function resetSessionState() {
        state.ready = false;
        state.sessionActive = false;
        state.providerClient = null;
        state.sessionInfo = null;
        state.sessionStartPromise = null;
        state.sessionTimeoutId = clearTimeoutSafe(state.sessionTimeoutId);
        resetSpeechState();
    }

    function setUnavailable(reason, extra = {}) {
        state.unavailable = true;
        resetSessionState();
        emit(EVENTS.ERROR, {
            reason,
            provider: state.provider,
            context: state.options.context || null,
            ...extra
        });
    }

    function isReady() {
        return state.ready === true;
    }

    function isSpeaking() {
        return state.speaking === true;
    }

    function isUnavailable() {
        return state.unavailable === true;
    }

    function init(options = {}) {
        state.options = {
            ...DEFAULTS,
            ...options,
            endpoints: {
                ...DEFAULTS.endpoints,
                ...(options.endpoints || {})
            }
        };

        state.provider = state.options.provider || 'heygen';
        state.ready = false;
        state.speaking = false;
        state.sessionActive = false;
        state.unavailable = false;
        state.currentSpeechId = null;
        state.sessionInfo = null;
        state.providerClient = null;
        state.sessionStartPromise = null;
        state.speechPromise = null;
        state.sessionTimeoutId = clearTimeoutSafe(state.sessionTimeoutId);
        state.speechTimeoutId = clearTimeoutSafe(state.speechTimeoutId);

        log('init()', state.options);
    }

    function mount(container) {
        if (!(container instanceof HTMLElement)) {
            setUnavailable('avatar_container_invalid');
            return false;
        }

        state.containerEl = container;
        state.mounted = true;
        state.containerEl.innerHTML = '';
        state.containerEl.setAttribute('data-avatar-mounted', 'true');

        ensureVideoShell();
        return true;
    }

    async function startSession() {
        if (state.unavailable) return false;
        if (state.sessionActive && state.ready) return true;
        if (state.sessionStartPromise) return state.sessionStartPromise;

        if (!state.containerEl || !state.mounted) {
            setUnavailable('avatar_container_missing');
            return false;
        }

        state.sessionStartPromise = (async () => {
            state.sessionTimeoutId = setTimeout(() => {
                setUnavailable('avatar_session_timeout');
            }, state.options.sessionStartTimeoutMs);

            try {
                const sessionInfo = await createSessionFromBackend();
                state.sessionInfo = sessionInfo;

                const providerClient = await startHeyGenProviderSession(sessionInfo);

                state.providerClient = providerClient;
                state.sessionActive = true;
                state.ready = true;
                state.unavailable = false;
                state.sessionTimeoutId = clearTimeoutSafe(state.sessionTimeoutId);

                emit(EVENTS.READY, {
                    provider: state.provider,
                    sessionInfo: safeSessionInfoForEvent(sessionInfo)
                });

                return true;
            } catch (error) {
                state.sessionTimeoutId = clearTimeoutSafe(state.sessionTimeoutId);
                setUnavailable('avatar_session_start_failed', { error });
                return false;
            } finally {
                state.sessionStartPromise = null;
            }
        })();

        return state.sessionStartPromise;
    }

    async function speak(text, meta = {}) {
        const cleanText = normalizeText(text);

        if (!cleanText) return false;
        if (state.unavailable || !state.ready || !state.sessionActive) return false;
        if (state.speaking) return false;

        const speechId = `speech_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
        state.currentSpeechId = speechId;
        state.speaking = true;

        emit(EVENTS.SPEECH_START, {
            speechId,
            text: cleanText,
            meta
        });

        state.speechPromise = (async () => {
            state.speechTimeoutId = setTimeout(() => {
                emit(EVENTS.ERROR, {
                    reason: 'avatar_speech_timeout',
                    speechId,
                    meta
                });
            }, state.options.speechTimeoutMs);

            try {
                await speakWithHeyGen(cleanText, meta, speechId);

                state.speechTimeoutId = clearTimeoutSafe(state.speechTimeoutId);

                emit(EVENTS.SPEECH_END, {
                    speechId,
                    text: cleanText,
                    meta
                });

                resetSpeechState();
                return true;
            } catch (error) {
                state.speechTimeoutId = clearTimeoutSafe(state.speechTimeoutId);

                emit(EVENTS.ERROR, {
                    reason: 'avatar_speech_failed',
                    error,
                    speechId,
                    meta
                });

                resetSpeechState();
                return false;
            }
        })();

        return state.speechPromise;
    }

    async function stop() {
        if (!state.sessionActive) return;

        try {
            await stopHeyGenSpeech();
        } catch (error) {
            emit(EVENTS.ERROR, {
                reason: 'avatar_stop_failed',
                error
            });
        } finally {
            resetSpeechState();
        }
    }

    async function destroy() {
        try {
            await stop();
            await destroyHeyGenProviderSession();
        } catch (error) {
            emit(EVENTS.ERROR, {
                reason: 'avatar_destroy_failed',
                error
            });
        } finally {
            resetSessionState();
            state.unavailable = false;

            if (state.containerEl) {
                state.containerEl.innerHTML = '';
                state.containerEl.removeAttribute('data-avatar-mounted');
            }

            state.containerEl = null;
            state.mounted = false;

            emit(EVENTS.SESSION_CLOSED, {
                provider: state.provider
            });
        }
    }

    async function createSessionFromBackend() {
        const endpoint = state.options.endpoints.createSession;

        const payload = {
            provider: 'heygen',
            context: state.options.context || 'lesson',
            avatarId: state.options.avatarId || null,
            voiceId: state.options.voiceId || null
        };

        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        let data = null;
        try {
            data = await response.json();
        } catch (error) {
            throw new Error('La respuesta del endpoint del avatar no fue JSON válido.');
        }

        if (!response.ok || !data || !data.ok) {
            const message = data && data.error && data.error.message
                ? data.error.message
                : 'No se pudo crear la sesión del avatar.';
            throw new Error(message);
        }

        return data;
    }

    function safeSessionInfoForEvent(sessionInfo) {
        if (!sessionInfo || typeof sessionInfo !== 'object') return null;

        return {
            provider: sessionInfo.provider || 'heygen',
            sessionId: sessionInfo.sessionId || null,
            avatarId: sessionInfo.avatarId || null,
            voiceId: sessionInfo.voiceId || null
        };
    }

    function ensureVideoShell() {
        if (!state.containerEl) return;

        const existing = state.containerEl.querySelector('[data-avatar-video-shell="true"]');
        if (existing) return existing;

        const shell = document.createElement('div');
        shell.className = 'lesson__avatarShell';
        shell.setAttribute('data-avatar-video-shell', 'true');

        state.containerEl.appendChild(shell);
        return shell;
    }

    async function startHeyGenProviderSession(sessionInfo) {
        const token = sessionInfo && sessionInfo.token ? sessionInfo.token : null;
        if (!token) {
            throw new Error('No se recibió token para iniciar HeyGen.');
        }

        const shell = ensureVideoShell();

        // -----------------------------------------------------------------
        // PUNTO DE INTEGRACIÓN REAL CON HEYGEN
        // -----------------------------------------------------------------
        // Aquí vas a reemplazar este bloque por la inicialización del SDK real.
        //
        // Ejemplo conceptual:
        // const avatar = new StreamingAvatar({ token });
        // await avatar.createStartAvatar({...});
        // const mediaStream = avatar.mediaStream;
        // bindStreamToVideo(mediaStream, shell);
        // return avatar;
        // -----------------------------------------------------------------

        const client = {
            provider: 'heygen',
            token,
            sessionId: sessionInfo.sessionId || null,
            avatarId: sessionInfo.avatarId || null,
            voiceId: sessionInfo.voiceId || null,
            shell
        };

        renderIdlePlaceholder(shell);
        return client;
    }

    async function speakWithHeyGen(text, meta, speechId) {
        if (!state.providerClient) {
            throw new Error('No existe providerClient activo.');
        }

        // -----------------------------------------------------------------
        // PUNTO DE INTEGRACIÓN REAL CON HEYGEN
        // -----------------------------------------------------------------
        // Aquí conectas la función de "speak" real del SDK.
        //
        // Ejemplo conceptual:
        // await state.providerClient.speak({
        //   text,
        //   taskType: 'talk'
        // });
        // await waitForAvatarPlaybackEnd();
        // -----------------------------------------------------------------

        renderSpeakingPlaceholder(text);
        await wait(estimateSpeechDurationMs(text));
        renderIdlePlaceholder(state.providerClient.shell);

        return {
            ok: true,
            speechId,
            meta
        };
    }

    async function stopHeyGenSpeech() {
        if (!state.providerClient) return true;

        // Aquí luego llamarías al stop del SDK real si existe
        renderIdlePlaceholder(state.providerClient.shell);
        return true;
    }

    async function destroyHeyGenProviderSession() {
        if (!state.providerClient) return true;

        try {
            const closeEndpoint = state.options.endpoints.closeSession;
            const sessionId =
                state.providerClient.sessionId ||
                (state.sessionInfo ? state.sessionInfo.sessionId : null);

            if (closeEndpoint && sessionId) {
                await fetch(closeEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        provider: 'heygen',
                        sessionId
                    })
                });
            }
        } catch (error) {
            console.warn('[AvatarService] No se pudo cerrar la sesión remota del avatar:', error);
        }
    }

    function renderIdlePlaceholder(shell) {
        if (!shell) return;
        shell.innerHTML = `
            <div class="lesson__avatarFallback" data-avatar-fallback="idle">
                <span class="lesson__avatarFallbackLabel">Avatar listo</span>
            </div>
        `;
    }

    function renderSpeakingPlaceholder(text) {
        const shell = state.providerClient ? state.providerClient.shell : null;
        if (!shell) return;

        shell.innerHTML = `
            <div class="lesson__avatarFallback" data-avatar-fallback="speaking">
                <span class="lesson__avatarFallbackLabel">Avatar hablando...</span>
            </div>
        `;
    }

    function estimateSpeechDurationMs(text) {
        const words = normalizeText(text).split(' ').filter(Boolean).length || 1;
        const minutes = words / 155;
        const ms = Math.ceil(minutes * 60 * 1000);
        return Math.min(Math.max(ms, 1200), 12000);
    }

    function wait(ms) {
        return new Promise((resolve) => {
            setTimeout(resolve, ms);
        });
    }

    global.AvatarService = {
        init,
        mount,
        startSession,
        speak,
        stop,
        destroy,
        isReady,
        isSpeaking,
        isUnavailable,
        on,
        off,
        EVENTS
    };

})(window);