(function (global) {
    'use strict';

    const DEFAULTS = {
        provider: 'heygen',
        context: 'lesson',
        debug: false,
        endpoints: {
            createSession: '/api/avatar/session',
            sendTask: '/api/avatar/task',
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
        options: {
            provider: DEFAULTS.provider,
            context: DEFAULTS.context,
            debug: DEFAULTS.debug,
            endpoints: {
                createSession: DEFAULTS.endpoints.createSession,
                sendTask: DEFAULTS.endpoints.sendTask,
                closeSession: DEFAULTS.endpoints.closeSession
            },
            sessionStartTimeoutMs: DEFAULTS.sessionStartTimeoutMs,
            speechTimeoutMs: DEFAULTS.speechTimeoutMs,
            avatarId: DEFAULTS.avatarId,
            voiceId: DEFAULTS.voiceId
        },
        provider: 'heygen',

        containerEl: null,
        mounted: false,

        ready: false,
        speaking: false,
        sessionActive: false,
        unavailable: false,

        currentSpeechId: null,
        currentTaskId: null,
        sessionInfo: null,
        providerClient: null,

        sessionStartPromise: null,
        speechPromise: null,

        sessionTimeoutId: null,
        speechTimeoutId: null,
        videoEl: null,
        room: null,

        listeners: {
            ready: new Set(),
            speechStart: new Set(),
            speechEnd: new Set(),
            error: new Set(),
            sessionClosed: new Set()
        }
    };

    function log() {
        if (!state.options.debug) return;
        console.log.apply(console, ['[AvatarService]'].concat(Array.prototype.slice.call(arguments)));
    }

    function normalizeText(text) {
        return String(text || '').replace(/\s+/g, ' ').trim();
    }

    function emit(eventName, payload) {
        var listeners = state.listeners[eventName];
        if (!listeners || !listeners.size) return;

        listeners.forEach(function (callback) {
            try {
                callback(payload || {});
            } catch (error) {
                console.error('[AvatarService] Error en listener "' + eventName + '":', error);
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
        state.currentTaskId = null;
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

    function setUnavailable(reason, extra) {
        state.unavailable = true;
        resetSessionState();

        var payload = {
            reason: reason,
            provider: state.provider,
            context: state.options.context || null
        };

        if (extra) {
            for (var key in extra) {
                if (Object.prototype.hasOwnProperty.call(extra, key)) {
                    payload[key] = extra[key];
                }
            }
        }

        emit(EVENTS.ERROR, payload);
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

    function init(options) {
        options = options || {};

        state.options = {
            provider: options.provider || DEFAULTS.provider,
            context: options.context || DEFAULTS.context,
            debug: Boolean(options.debug),
            endpoints: {
                createSession: (options.endpoints && options.endpoints.createSession) || DEFAULTS.endpoints.createSession,
                sendTask: (options.endpoints && options.endpoints.sendTask) || DEFAULTS.endpoints.sendTask,
                closeSession: (options.endpoints && options.endpoints.closeSession) || DEFAULTS.endpoints.closeSession
            },
            sessionStartTimeoutMs: options.sessionStartTimeoutMs || DEFAULTS.sessionStartTimeoutMs,
            speechTimeoutMs: options.speechTimeoutMs || DEFAULTS.speechTimeoutMs,
            avatarId: options.avatarId || DEFAULTS.avatarId,
            voiceId: options.voiceId || DEFAULTS.voiceId
        };

        state.provider = state.options.provider || 'heygen';
        state.ready = false;
        state.speaking = false;
        state.sessionActive = false;
        state.unavailable = false;
        state.currentSpeechId = null;
        state.currentTaskId = null;
        state.sessionInfo = null;
        state.providerClient = null;
        state.sessionStartPromise = null;
        state.speechPromise = null;
        state.sessionTimeoutId = clearTimeoutSafe(state.sessionTimeoutId);
        state.speechTimeoutId = clearTimeoutSafe(state.speechTimeoutId);
        state.videoEl = null;
        state.room = null;

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

        ensureVideoElement();
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

        if (!global.LivekitClient) {
            setUnavailable('livekit_client_missing');
            return false;
        }

        state.sessionStartPromise = (async function () {
            state.sessionTimeoutId = setTimeout(function () {
                setUnavailable('avatar_session_timeout');
            }, state.options.sessionStartTimeoutMs);

            try {
                var sessionInfo = await createSessionFromBackend();
                state.sessionInfo = sessionInfo;

                var providerClient = await startHeyGenProviderSession(sessionInfo);

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
                setUnavailable('avatar_session_start_failed', { error: error });
                return false;
            } finally {
                state.sessionStartPromise = null;
            }
        })();

        return state.sessionStartPromise;
    }

    async function speak(text, meta) {
        meta = meta || {};

        var cleanText = normalizeText(text);

        if (!cleanText) return false;
        if (state.unavailable || !state.ready || !state.sessionActive) return false;
        if (state.speaking) return false;

        var speechId = 'speech_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
        state.currentSpeechId = speechId;
        state.speaking = true;

        emit(EVENTS.SPEECH_START, {
            speechId: speechId,
            text: cleanText,
            meta: meta
        });

        state.speechPromise = (async function () {
            state.speechTimeoutId = setTimeout(function () {
                emit(EVENTS.ERROR, {
                    reason: 'avatar_speech_timeout',
                    speechId: speechId,
                    meta: meta
                });
            }, state.options.speechTimeoutMs);

            try {
                var taskId = await speakWithHeyGen(cleanText, meta, speechId);
                state.currentTaskId = taskId || null;

                await waitForSpeechCompletion(cleanText);

                state.speechTimeoutId = clearTimeoutSafe(state.speechTimeoutId);

                emit(EVENTS.SPEECH_END, {
                    speechId: speechId,
                    text: cleanText,
                    meta: meta
                });

                resetSpeechState();
                return true;
            } catch (error) {
                state.speechTimeoutId = clearTimeoutSafe(state.speechTimeoutId);

                emit(EVENTS.ERROR, {
                    reason: 'avatar_speech_failed',
                    error: error,
                    speechId: speechId,
                    meta: meta
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
                error: error
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
                error: error
            });
        } finally {
            resetSessionState();
            state.unavailable = false;

            if (state.room) {
                try {
                    await state.room.disconnect();
                } catch (e) { }
            }

            state.room = null;

            if (state.containerEl) {
                state.containerEl.innerHTML = '';
                state.containerEl.removeAttribute('data-avatar-mounted');
            }

            state.videoEl = null;
            state.containerEl = null;
            state.mounted = false;

            emit(EVENTS.SESSION_CLOSED, {
                provider: state.provider
            });
        }
    }

    async function createSessionFromBackend() {
        var endpoint = state.options.endpoints.createSession;

        var payload = {
            provider: 'heygen',
            context: state.options.context || 'lesson',
            avatarId: state.options.avatarId || null,
            voiceId: state.options.voiceId || null
        };

        var response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        var data = null;

        try {
            data = await response.json();
        } catch (error) {
            throw new Error('La respuesta del endpoint del avatar no fue JSON válido.');
        }

        if (!response.ok || !data || !data.ok) {
            var message = data && data.error && data.error.message
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

    function ensureVideoElement() {
        if (!state.containerEl) return null;

        var video = state.containerEl.querySelector('[data-avatar-video="true"]');

        if (!video) {
            video = document.createElement('video');
            video.setAttribute('data-avatar-video', 'true');
            video.setAttribute('playsinline', 'true');
            video.setAttribute('autoplay', 'true');
            video.muted = false;
            video.autoplay = true;
            video.playsInline = true;
            video.className = 'lesson__avatarVideo';
            state.containerEl.appendChild(video);
        }

        state.videoEl = video;
        return video;
    }

    async function startHeyGenProviderSession(sessionInfo) {
        var sessionId = sessionInfo && sessionInfo.sessionId ? sessionInfo.sessionId : null;
        var url = sessionInfo && sessionInfo.url ? sessionInfo.url : null;
        var accessToken = sessionInfo && sessionInfo.accessToken ? sessionInfo.accessToken : null;

        if (!sessionId || !url || !accessToken) {
            throw new Error('La sesión de HeyGen no devolvió sessionId, url o accessToken.');
        }

        var videoEl = ensureVideoElement();
        var room = new global.LivekitClient.Room();

        state.room = room;

        room.on(global.LivekitClient.RoomEvent.TrackSubscribed, function (track) {
            var mediaStream = new MediaStream([track.mediaStreamTrack]);

            if (track.kind === 'video') {
                videoEl.srcObject = mediaStream;
                videoEl.play().catch(function (error) {
                    console.warn('[AvatarService] No se pudo reproducir video automáticamente:', error);
                });
            }

            if (track.kind === 'audio') {
                var audioEl = document.createElement('audio');
                audioEl.autoplay = true;
                audioEl.srcObject = mediaStream;
                audioEl.play().catch(function (error) {
                    console.warn('[AvatarService] No se pudo reproducir audio automáticamente:', error);
                });
            }
        });

        room.on(global.LivekitClient.RoomEvent.Disconnected, function () {
            emit(EVENTS.ERROR, {
                reason: 'avatar_room_disconnected'
            });
        });

        await room.connect(url, accessToken);

        return {
            provider: 'heygen',
            sessionId: sessionId,
            avatarId: sessionInfo.avatarId || null,
            voiceId: sessionInfo.voiceId || null,
            room: room
        };
    }

    async function speakWithHeyGen(text, meta, speechId) {
        if (!state.providerClient || !state.providerClient.sessionId) {
            throw new Error('No existe providerClient activo o sessionId.');
        }

        var response = await fetch(state.options.endpoints.sendTask, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                provider: 'heygen',
                sessionId: state.providerClient.sessionId,
                text: text,
                taskType: 'repeat'
            })
        });

        var data = null;

        try {
            data = await response.json();
        } catch (error) {
            throw new Error('La respuesta del endpoint task no fue JSON válido.');
        }

        if (!response.ok || !data || !data.ok) {
            var message = data && data.error && data.error.message
                ? data.error.message
                : 'No se pudo enviar la tarea al avatar.';
            throw new Error(message);
        }

        var taskId = null;

        if (data && data.taskId) {
            taskId = data.taskId;
        } else if (data && data.remoteTask && data.remoteTask.data && data.remoteTask.data.task_id) {
            taskId = data.remoteTask.data.task_id;
        } else if (data && data.remoteTask && data.remoteTask.task_id) {
            taskId = data.remoteTask.task_id;
        }

        return taskId;
    }

    async function waitForSpeechCompletion(text) {
        var estimatedMs = estimateSpeechDurationMs(text);
        await wait(estimatedMs);
    }

    async function stopHeyGenSpeech() {
        return true;
    }

    async function destroyHeyGenProviderSession() {
        if (!state.providerClient) return true;

        try {
            var closeEndpoint = state.options.endpoints.closeSession;
            var sessionId =
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
                        sessionId: sessionId
                    })
                });
            }
        } catch (error) {
            console.warn('[AvatarService] No se pudo cerrar la sesión remota del avatar:', error);
        }
    }

    function estimateSpeechDurationMs(text) {
        var words = normalizeText(text).split(' ').filter(Boolean).length || 1;
        var minutes = words / 155;
        var ms = Math.ceil(minutes * 60 * 1000);
        return Math.min(Math.max(ms, 1200), 12000);
    }

    function wait(ms) {
        return new Promise(function (resolve) {
            setTimeout(resolve, ms);
        });
    }

    global.AvatarService = {
        init: init,
        mount: mount,
        startSession: startSession,
        speak: speak,
        stop: stop,
        destroy: destroy,
        isReady: isReady,
        isSpeaking: isSpeaking,
        isUnavailable: isUnavailable,
        on: on,
        off: off,
        EVENTS: EVENTS
    };

})(window);