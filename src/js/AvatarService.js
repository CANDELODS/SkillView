(function (global) {
    'use strict';

    // -----------------------------------------------------------------
    // CONFIGURACIÓN POR DEFECTO DEL SERVICIO
    // -----------------------------------------------------------------
    // Aquí definimos los valores base que usará el servicio del avatar
    // si desde afuera no se envían opciones personalizadas.
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

    // -----------------------------------------------------------------
    // EVENTOS EXPUESTOS POR EL SERVICIO
    // -----------------------------------------------------------------
    // Estos nombres de eventos permiten que otros módulos (por ejemplo
    // apiLecciones.js) escuchen lo que está ocurriendo con el avatar:
    // cuando está listo, cuando empieza a hablar, cuando termina, etc.
    const EVENTS = Object.freeze({
        READY: 'ready',
        SPEECH_START: 'speechStart',
        SPEECH_END: 'speechEnd',
        ERROR: 'error',
        SESSION_CLOSED: 'sessionClosed'
    });

    // -----------------------------------------------------------------
    // ESTADO INTERNO DEL SERVICIO
    // -----------------------------------------------------------------
    // Este objeto concentra toda la información temporal del avatar:
    // configuración, sesión, reproducción actual, referencias DOM,
    // conexión LiveKit y listeners suscritos.
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

        // Contenedor HTML donde se va a montar el video del avatar
        containerEl: null,
        mounted: false,

        // Estados lógicos de la sesión y del habla
        ready: false,
        speaking: false,
        sessionActive: false,
        unavailable: false,

        // Identificadores de la narración actual y de la tarea remota
        currentSpeechId: null,
        currentTaskId: null,
        sessionInfo: null,
        providerClient: null,

        // Promesas activas para controlar el inicio de sesión y la narración
        sessionStartPromise: null,
        speechPromise: null,

        // Referencias a timeouts y a elementos multimedia
        sessionTimeoutId: null,
        speechTimeoutId: null,
        videoEl: null,
        room: null,

        // Colección de listeners por tipo de evento
        listeners: {
            ready: new Set(),
            speechStart: new Set(),
            speechEnd: new Set(),
            error: new Set(),
            sessionClosed: new Set()
        }
    };

    // -----------------------------------------------------------------
    // LOG CONDICIONAL
    // -----------------------------------------------------------------
    // Solo imprime mensajes en consola si el modo debug está activado.
    function log() {
        if (!state.options.debug) return;
        console.log.apply(console, ['[AvatarService]'].concat(Array.prototype.slice.call(arguments)));
    }

    // -----------------------------------------------------------------
    // NORMALIZAR TEXTO
    // -----------------------------------------------------------------
    // Limpia el texto recibido: lo convierte a string, quita espacios
    // repetidos y elimina espacios al inicio y final.
    function normalizeText(text) {
        return String(text || '').replace(/\s+/g, ' ').trim();
    }

    // -----------------------------------------------------------------
    // EMITIR EVENTOS
    // -----------------------------------------------------------------
    // Ejecuta todos los callbacks suscritos a un evento específico.
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

    // -----------------------------------------------------------------
    // SUSCRIBIRSE A UN EVENTO
    // -----------------------------------------------------------------
    // Permite que otros módulos escuchen eventos del avatar.
    function on(eventName, callback) {
        if (!state.listeners[eventName] || typeof callback !== 'function') return;
        state.listeners[eventName].add(callback);
    }

    // -----------------------------------------------------------------
    // DESUSCRIBIRSE DE UN EVENTO
    // -----------------------------------------------------------------
    // Elimina un listener previamente registrado.
    function off(eventName, callback) {
        if (!state.listeners[eventName] || typeof callback !== 'function') return;
        state.listeners[eventName].delete(callback);
    }

    // -----------------------------------------------------------------
    // LIMPIEZA SEGURA DE TIMEOUTS
    // -----------------------------------------------------------------
    // Si existe un timeout, lo cancela y devuelve null para facilitar
    // la reasignación posterior.
    function clearTimeoutSafe(timeoutId) {
        if (timeoutId) clearTimeout(timeoutId);
        return null;
    }

    // -----------------------------------------------------------------
    // RESETEAR ESTADO DE LA NARRACIÓN
    // -----------------------------------------------------------------
    // Se usa cuando termina de hablar, falla una narración o se detiene.
    function resetSpeechState() {
        state.speaking = false;
        state.currentSpeechId = null;
        state.currentTaskId = null;
        state.speechPromise = null;
        state.speechTimeoutId = clearTimeoutSafe(state.speechTimeoutId);
    }

    // -----------------------------------------------------------------
    // RESETEAR ESTADO DE SESIÓN
    // -----------------------------------------------------------------
    // Limpia la sesión activa del avatar y también el estado de narración.
    function resetSessionState() {
        state.ready = false;
        state.sessionActive = false;
        state.providerClient = null;
        state.sessionInfo = null;
        state.sessionStartPromise = null;
        state.sessionTimeoutId = clearTimeoutSafe(state.sessionTimeoutId);
        resetSpeechState();
    }

    // -----------------------------------------------------------------
    // MARCAR SERVICIO COMO NO DISPONIBLE
    // -----------------------------------------------------------------
    // Se usa cuando ocurre un error crítico. Resetea sesión y emite un
    // evento de error con detalles del motivo.
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

    // -----------------------------------------------------------------
    // HELPERS DE ESTADO
    // -----------------------------------------------------------------
    // Métodos públicos para consultar el estado del servicio.
    function isReady() {
        return state.ready === true;
    }

    function isSpeaking() {
        return state.speaking === true;
    }

    function isUnavailable() {
        return state.unavailable === true;
    }

    // -----------------------------------------------------------------
    // INICIALIZAR EL SERVICIO
    // -----------------------------------------------------------------
    // Sobrescribe la configuración por defecto con opciones personalizadas
    // y deja el estado limpio antes de comenzar a usar el avatar.
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

    // -----------------------------------------------------------------
    // MONTAR EL AVATAR EN EL DOM
    // -----------------------------------------------------------------
    // Recibe el contenedor HTML en el que se renderizará el avatar.
    function mount(container) {
        if (!(container instanceof HTMLElement)) {
            setUnavailable('avatar_container_invalid');
            return false;
        }

        state.containerEl = container;
        state.mounted = true;
        state.containerEl.innerHTML = '';
        state.containerEl.setAttribute('data-avatar-mounted', 'true');

        // Aseguramos que exista el <video> donde se reproducirá el stream.
        ensureVideoElement();
        return true;
    }

    // -----------------------------------------------------------------
    // INICIAR SESIÓN DEL AVATAR
    // -----------------------------------------------------------------
    // 1. Valida estado básico del servicio
    // 2. Pide al backend que cree la sesión con HeyGen
    // 3. Conecta LiveKit con la URL y access token devueltos
    async function startSession() {
        if (state.unavailable) return false;
        if (state.sessionActive && state.ready) return true;
        if (state.sessionStartPromise) return state.sessionStartPromise;

        if (!state.containerEl || !state.mounted) {
            setUnavailable('avatar_container_missing');
            return false;
        }

        // El cliente LiveKit debe estar cargado en window.
        if (!global.LivekitClient) {
            setUnavailable('livekit_client_missing');
            return false;
        }

        state.sessionStartPromise = (async function () {
            // Timeout defensivo por si la sesión tarda demasiado.
            state.sessionTimeoutId = setTimeout(function () {
                setUnavailable('avatar_session_timeout');
            }, state.options.sessionStartTimeoutMs);

            try {
                // Pedimos al backend la creación de sesión.
                var sessionInfo = await createSessionFromBackend();
                state.sessionInfo = sessionInfo;

                // Iniciamos el proveedor real (HeyGen + LiveKit).
                var providerClient = await startHeyGenProviderSession(sessionInfo);

                state.providerClient = providerClient;
                state.sessionActive = true;
                state.ready = true;
                state.unavailable = false;
                state.sessionTimeoutId = clearTimeoutSafe(state.sessionTimeoutId);

                // Avisamos que el avatar está listo.
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

    // -----------------------------------------------------------------
    // HACER HABLAR AL AVATAR
    // -----------------------------------------------------------------
    // 1. Valida que haya sesión activa
    // 2. Envía la tarea al backend
    // 3. Espera un tiempo estimado de narración
    // 4. Emite eventos de inicio y fin de speech
    async function speak(text, meta) {
        meta = meta || {};

        var cleanText = normalizeText(text);

        if (!cleanText) return false;
        if (state.unavailable || !state.ready || !state.sessionActive) return false;
        if (state.speaking) return false;

        var speechId = 'speech_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
        state.currentSpeechId = speechId;
        state.speaking = true;

        // Avisamos que el avatar empezó a hablar.
        emit(EVENTS.SPEECH_START, {
            speechId: speechId,
            text: cleanText,
            meta: meta
        });

        state.speechPromise = (async function () {
            // Timeout defensivo por si la narración se queda colgada.
            state.speechTimeoutId = setTimeout(function () {
                emit(EVENTS.ERROR, {
                    reason: 'avatar_speech_timeout',
                    speechId: speechId,
                    meta: meta
                });
            }, state.options.speechTimeoutMs);

            try {
                // Enviamos la tarea de voz a HeyGen.
                var taskId = await speakWithHeyGen(cleanText, meta, speechId);
                state.currentTaskId = taskId || null;

                // Esperamos un tiempo estimado mientras el avatar “habla”.
                await waitForSpeechCompletion(cleanText);

                state.speechTimeoutId = clearTimeoutSafe(state.speechTimeoutId);

                // Avisamos que terminó la narración.
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

    // -----------------------------------------------------------------
    // DETENER NARRACIÓN ACTUAL
    // -----------------------------------------------------------------
    // Por ahora no hace una interrupción real en HeyGen, pero sí limpia
    // el estado local del servicio.
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

    // -----------------------------------------------------------------
    // DESTRUIR EL SERVICIO COMPLETO
    // -----------------------------------------------------------------
    // Detiene la narración, cierra la sesión remota, desconecta LiveKit
    // y limpia referencias internas y del DOM.
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

    // -----------------------------------------------------------------
    // PEDIR AL BACKEND LA CREACIÓN DE SESIÓN
    // -----------------------------------------------------------------
    // Se comunica con AvatarController.php para obtener sessionId, url
    // y accessToken necesarios para LiveKit.
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

    // -----------------------------------------------------------------
    // RESUMEN SEGURO DE LOS DATOS DE SESIÓN
    // -----------------------------------------------------------------
    // Devuelve solo los datos esenciales para emitirlos en el evento READY.
    function safeSessionInfoForEvent(sessionInfo) {
        if (!sessionInfo || typeof sessionInfo !== 'object') return null;

        return {
            provider: sessionInfo.provider || 'heygen',
            sessionId: sessionInfo.sessionId || null,
            avatarId: sessionInfo.avatarId || null,
            voiceId: sessionInfo.voiceId || null
        };
    }

    // -----------------------------------------------------------------
    // ASEGURAR EXISTENCIA DEL VIDEO
    // -----------------------------------------------------------------
    // Crea, si no existe, el shell y el elemento <video> donde se adjunta
    // el stream de LiveKit.
    function ensureVideoElement() {
        if (!state.containerEl) return null;

        var shell = state.containerEl.querySelector('[data-avatar-shell="true"]');

        if (!shell) {
            shell = document.createElement('div');
            shell.className = 'lesson__avatarShell';
            shell.setAttribute('data-avatar-shell', 'true');
            state.containerEl.appendChild(shell);
        }

        var video = shell.querySelector('[data-avatar-video="true"]');

        if (!video) {
            video = document.createElement('video');
            video.setAttribute('data-avatar-video', 'true');
            video.setAttribute('playsinline', 'true');
            video.setAttribute('autoplay', 'true');
            video.className = 'lesson__avatarVideo';
            video.autoplay = true;
            video.playsInline = true;
            video.muted = false;
            shell.appendChild(video);
        }

        state.videoEl = video;
        return video;
    }

    // -----------------------------------------------------------------
    // INICIAR PROVEEDOR REAL (HEYGEN + LIVEKIT)
    // -----------------------------------------------------------------
    // Conecta la sala LiveKit y adjunta las pistas de audio/video al DOM.
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

        // Cuando llega una pista multimedia, la conectamos al video o al audio.
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

        // Si la sala se desconecta, emitimos error para que la app lo maneje.
        room.on(global.LivekitClient.RoomEvent.Disconnected, function () {
            emit(EVENTS.ERROR, {
                reason: 'avatar_room_disconnected'
            });
        });

        // Conectamos el cliente LiveKit con la URL y token devueltos por el backend.
        await room.connect(url, accessToken);

        return {
            provider: 'heygen',
            sessionId: sessionId,
            avatarId: sessionInfo.avatarId || null,
            voiceId: sessionInfo.voiceId || null,
            room: room
        };
    }

    // -----------------------------------------------------------------
    // ENVIAR TAREA DE VOZ AL AVATAR
    // -----------------------------------------------------------------
    // No llama directo a HeyGen desde el frontend, sino que usa el backend
    // para no exponer credenciales sensibles.
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

        // Intentamos encontrar el taskId en distintas estructuras posibles.
        if (data && data.taskId) {
            taskId = data.taskId;
        } else if (data && data.remoteTask && data.remoteTask.data && data.remoteTask.data.task_id) {
            taskId = data.remoteTask.data.task_id;
        } else if (data && data.remoteTask && data.remoteTask.task_id) {
            taskId = data.remoteTask.task_id;
        }

        return taskId;

        // Probar Sin Voz
        // await wait(1500);
        // return 'debug-task';
        // FIN Probar Sin Voz
    }

    // -----------------------------------------------------------------
    // ESPERA ESTIMADA DE FINALIZACIÓN DEL SPEECH
    // -----------------------------------------------------------------
    // Como no estamos escuchando un evento real de “fin exacto” desde HeyGen,
    // estimamos la duración con base en la cantidad de palabras.
    async function waitForSpeechCompletion(text) {
        var estimatedMs = estimateSpeechDurationMs(text);
        await wait(estimatedMs);
    }

    // -----------------------------------------------------------------
    // DETENER SPEECH REMOTO (PLACEHOLDER)
    // -----------------------------------------------------------------
    // De momento devuelve true directamente.
    async function stopHeyGenSpeech() {
        return true;
    }

    // -----------------------------------------------------------------
    // CERRAR SESIÓN REMOTA DEL AVATAR
    // -----------------------------------------------------------------
    // Llama al backend para que cierre la sesión de HeyGen.
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

    // -----------------------------------------------------------------
    // ESTIMAR DURACIÓN DEL SPEECH
    // -----------------------------------------------------------------
    // Calcula una duración aproximada en milisegundos a partir del número
    // de palabras. Limita el resultado entre 1200 ms y 12000 ms.
    function estimateSpeechDurationMs(text) {
        var words = normalizeText(text).split(' ').filter(Boolean).length || 1;
        var minutes = words / 155;
        var ms = Math.ceil(minutes * 60 * 1000);
        return Math.min(Math.max(ms, 1200), 12000);
    }

    // -----------------------------------------------------------------
    // ESPERA ASÍNCRONA SIMPLE
    // -----------------------------------------------------------------
    // Helper genérico para pausar durante cierta cantidad de milisegundos.
    function wait(ms) {
        return new Promise(function (resolve) {
            setTimeout(resolve, ms);
        });
    }

    // -----------------------------------------------------------------
    // API PÚBLICA DEL SERVICIO
    // -----------------------------------------------------------------
    // Exponemos el módulo en window para que otros archivos como
    // apiLecciones.js puedan usarlo.
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