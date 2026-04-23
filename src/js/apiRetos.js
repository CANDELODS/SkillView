(function () {
    // ---------------------------------------------------------------------
    // REFERENCIAS DEL DOM
    // ---------------------------------------------------------------------

    // Contenedor donde se renderizan todos los mensajes del chat.
    let messagesContainer = null;

    // Formulario del composer (input + botón enviar).
    let composerForm = null;

    // Campo de texto donde el usuario escribe su respuesta.
    let textInput = null;

    // Botón de enviar respuesta.
    let sendButton = null;

    // Botón del micrófono para reconocimiento de voz.
    let micButton = null;

    // Loader visual que aparece mientras el reto está cargando o iniciando.
    let challengeLoader = null;

    // Referencias al modal de resultado del reto.
    let challengeResultModal = null;
    let challengeResultModalBody = null;
    let challengeResultModalTitle = null;
    let challengeResultModalContinue = null;

    // Elemento donde se muestra el puntaje en el modal final.
    let challengeResultModalScore = null;

    // Instancia del reconocimiento de voz del navegador.
    let speechRecognition = null;

    // Texto final acumulado cuando el usuario dicta por voz.
    let finalTranscript = '';

    // Elemento raíz del reto en el DOM.
    let challengeRoot = null;

    // Contenedor visual del avatar dentro de la vista del reto.
    let avatarContainer = null;

    // Modal final pendiente de abrir cuando termine la narración.
    let pendingCompletionModal = null;

    // Indica si el avatar está disponible.
    let avatarAvailable = false;

    // Variable para activar o desactivar la integración del avatar (útil para pruebas sin el).
    //Cambiar a true para activar el avatar, false para desactivarlo y probar sin él.
    const AVATAR_ENABLED = true;

    // ---------------------------------------------------------------------
    // ESTADO GLOBAL DEL FLUJO DEL RETO
    // ---------------------------------------------------------------------
    // Aquí se guarda toda la información que el frontend necesita
    // para saber en qué etapa está el reto y cómo debe comportarse la UI.
    const state = {
        challengeId: 0,
        skillId: 0,
        currentStage: null,
        nextExpectedAction: null,
        inputEnabled: false,
        requiresUserResponse: false,
        completed: false,
        isLoading: false,
        modalRedirectTo: null,
        isListening: false,
        speechRecognitionSupported: false,
        avatarIsSpeaking: false,
        avatarIsPendingSpeech: false,
        pendingAutoAdvance: false
    };

    // ---------------------------------------------------------------------
    // FALLBACKS SEGUROS
    // ---------------------------------------------------------------------
    // Si AvatarService no está cargado en window, este fallback evita
    // que el reto se rompa. No hace nada real, pero mantiene la interfaz.
    const AvatarService = window.AvatarService || (() => {
        const listeners = {
            ready: [],
            speechStart: [],
            speechEnd: [],
            error: [],
            sessionClosed: []
        };

        function emit(eventName, payload = {}) {
            if (!listeners[eventName]) return;

            listeners[eventName].forEach((callback) => {
                try {
                    callback(payload);
                } catch (error) {
                    console.error(`Error en listener ${eventName}:`, error);
                }
            });
        }

        return {
            init() { },
            mount() { },
            async startSession() {
                return false;
            },
            async speak() {
                return false;
            },
            async stop() { },
            async destroy() { },
            isReady() {
                return false;
            },
            isSpeaking() {
                return false;
            },
            isUnavailable() {
                return true;
            },
            on(eventName, callback) {
                if (!listeners[eventName]) listeners[eventName] = [];
                listeners[eventName].push(callback);
            },
            off(eventName, callback) {
                if (!listeners[eventName]) return;
                listeners[eventName] = listeners[eventName].filter(fn => fn !== callback);
            },
            emit
        };
    })();

    // ---------------------------------------------------------------------
    // POLÍTICA DE NARRACIÓN
    // ---------------------------------------------------------------------
    // Decide qué mensajes sí se narran y cuáles no.
    // En retos queremos narrar los mensajes del asistente, excepto retries.
    const NarrationPolicy = window.NarrationPolicy || (() => {
        // Etapas que no deben narrarse.
        const NON_NARRABLE_STAGES = new Set([
            'attempt_retry',
            'complete'
        ]);

        function normalizeText(text) {
            return String(text || '').replace(/\s+/g, ' ').trim();
        }

        function isAssistantMessage(message) {
            return Boolean(message) && message.role === 'assistant';
        }

        function isRetryStage(stage) {
            return stage === 'attempt_retry';
        }

        function shouldNarrateMessage(message, context = {}) {
            const stage = context.stage || null;
            const ui = context.ui || {};

            if (!ui.showAvatarSpeaking) return false;
            if (!isAssistantMessage(message)) return false;
            if (NON_NARRABLE_STAGES.has(stage)) return false;
            if (isRetryStage(stage)) return false;

            const text = normalizeText(message.text);
            if (!text) return false;
            if (text.length < 3) return false;

            return true;
        }

        function select(messages = [], context = {}) {
            if (!Array.isArray(messages) || messages.length === 0) return [];

            return messages
                .filter(message => shouldNarrateMessage(message, context))
                .map((message, index) => ({
                    id: message.id || `challenge_narration_${Date.now()}_${index}`,
                    text: normalizeText(message.text),
                    source: 'messages',
                    role: message.role || 'assistant',
                    stage: context.stage || null,
                    priority: 'normal'
                }));
        }

        function selectCompletionModalMessages(completionModal, context = {}) {
            if (!completionModal || !Array.isArray(completionModal.messages)) return [];

            return completionModal.messages
                .map((message, index) => ({
                    id: `challenge_completion_modal_${Date.now()}_${index}`,
                    text: normalizeText(message.text),
                    source: 'completionModal',
                    role: 'assistant',
                    stage: context.stage || 'final_feedback',
                    priority: 'high'
                }))
                .filter(item => Boolean(item.text));
        }

        return {
            select,
            selectCompletionModalMessages
        };
    })();

    // ---------------------------------------------------------------------
    // COLA DE NARRACIÓN
    // ---------------------------------------------------------------------
    // Evita que varios mensajes del reto se narren al mismo tiempo.
    const NarrationQueue = window.NarrationQueue || (() => {
        let items = [];
        let processing = false;

        function enqueue(batch = []) {
            if (!Array.isArray(batch) || batch.length === 0) return;

            batch.forEach((item) => {
                if (!item || !item.text) return;
                items.push(item);
            });
        }

        async function processNext() {
            if (processing) return;
            if (!items.length) return;
            if (!AvatarService.isReady || !AvatarService.isReady()) return;
            if (AvatarService.isUnavailable && AvatarService.isUnavailable()) return;

            processing = true;

            try {
                while (items.length > 0) {
                    const item = items.shift();

                    const spoke = await AvatarService.speak(item.text, {
                        stage: item.stage,
                        source: item.source,
                        priority: item.priority
                    });

                    if (!spoke) {
                        continue;
                    }
                }
            } finally {
                processing = false;

                if (!items.length) {
                    handleNarrationQueueDrained();
                }
            }
        }

        function clear() {
            items = [];
            processing = false;
        }

        function pendingCount() {
            return items.length;
        }

        function isProcessing() {
            return processing;
        }

        return {
            enqueue,
            processNext,
            clear,
            pendingCount,
            isProcessing
        };
    })();

    // ---------------------------------------------------------------------
    // CACHE DEL DOM
    // ---------------------------------------------------------------------
    // Busca y guarda las referencias a todos los elementos necesarios.
    function cacheDom() {
        // Busca el contenedor raíz del reto.
        challengeRoot = document.querySelector('.challenge');

        if (!challengeRoot) return false;

        // Busca todos los elementos necesarios de la interfaz.
        messagesContainer = document.querySelector('.challenge__messages');
        composerForm = document.querySelector('.challenge__composer');
        textInput = document.querySelector('.challenge__input');
        sendButton = document.querySelector('.challenge__sendBtn');
        micButton = document.querySelector('.challenge__iconBtn');
        avatarContainer = document.querySelector('.challenge__avatar');

        // Busca el loader principal del reto.
        challengeLoader = document.getElementById('challenge-loader');

        // Busca el modal de resultado final.
        challengeResultModal = document.getElementById('sv-challenge-result-modal');
        challengeResultModalBody = challengeResultModal
            ? challengeResultModal.querySelector('[data-sv-challenge-result-body]')
            : null;
        challengeResultModalTitle = challengeResultModal
            ? challengeResultModal.querySelector('#sv-challenge-result-modal-title')
            : null;
        challengeResultModalContinue = challengeResultModal
            ? challengeResultModal.querySelector('[data-sv-challenge-result-continue]')
            : null;
        challengeResultModalScore = challengeResultModal
            ? challengeResultModal.querySelector('[data-sv-challenge-result-score]')
            : null;

        // Si faltan elementos esenciales, no se puede continuar.
        if (!messagesContainer || !composerForm || !textInput || !sendButton) {
            return false;
        }

        // Lee los ids del reto y de la habilidad desde atributos data-*.
        state.challengeId = Number(challengeRoot.dataset.retoId || 0);
        state.skillId = Number(challengeRoot.dataset.habilidadId || 0);

        // Si no se pudieron leer, se detiene la inicialización.
        if (!state.challengeId || !state.skillId) {
            console.error('No se pudieron leer challengeId o skillId desde data attributes.');
            return false;
        }

        return true;
    }

    // ---------------------------------------------------------------------
    // INICIALIZACIÓN GENERAL
    // ---------------------------------------------------------------------
    function init() {
        const ready = cacheDom();

        if (!ready) {
            console.error('La vista de reto aún no está lista o faltan elementos del DOM.');
            return;
        }

        showChallengeLoader();
        initSpeechRecognition();
        initAvatarIntegration();
        bindEvents();
        startChallenge();
    }

    // Espera a que cargue el DOM si aún no está listo.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // ---------------------------------------------------------------------
    // EVENTOS
    // ---------------------------------------------------------------------
    function bindEvents() {
        composerForm.addEventListener('submit', onSubmit);

        textInput.addEventListener('input', () => {
            // reservado para mejoras futuras
        });

        if (challengeResultModalContinue) {
            challengeResultModalContinue.addEventListener('click', handleChallengeResultContinue);
        }

        if (micButton) {
            micButton.addEventListener('click', function () {
                toggleSpeechRecognition();
            });
        }

        window.addEventListener('beforeunload', handleBeforeUnload);
    }

    // Antes de salir de la vista, detenemos micrófono y avatar.
    function handleBeforeUnload() {
        if (state.isListening) {
            stopSpeechRecognition();
        }

        if (AvatarService && typeof AvatarService.destroy === 'function') {
            AvatarService.destroy();
        }
    }

    // ---------------------------------------------------------------------
    // AVATAR
    // ---------------------------------------------------------------------
    function initAvatarIntegration() {
        //APAGAR AVATAR PARA PRUEBAS SIN EL
        if (!AVATAR_ENABLED) {
            avatarAvailable = false;
            setAvatarVisualState('unavailable');
            return;
        }

        if (!avatarContainer || !AvatarService) {
            setAvatarVisualState('unavailable');
            return;
        }

        try {
            AvatarService.init({
                provider: 'heygen',
                context: 'challenge'
            });

            AvatarService.mount(avatarContainer);

            AvatarService.on('ready', handleAvatarReady);
            AvatarService.on('speechStart', handleAvatarSpeechStart);
            AvatarService.on('speechEnd', handleAvatarSpeechEnd);
            AvatarService.on('error', handleAvatarError);
            AvatarService.on('sessionClosed', handleAvatarSessionClosed);

            AvatarService.startSession()
                .then((started) => {
                    avatarAvailable = Boolean(started);
                    setAvatarVisualState(started ? 'idle' : 'unavailable');
                })
                .catch((error) => {
                    avatarAvailable = false;
                    console.error('No se pudo iniciar la sesión del avatar del reto:', error);
                    setAvatarVisualState('unavailable');
                });
        } catch (error) {
            avatarAvailable = false;
            console.error('Error al inicializar el avatar del reto:', error);
            setAvatarVisualState('unavailable');
        }
    }

    function handleAvatarReady() {
        avatarAvailable = true;
        setAvatarVisualState('idle');
    }

    function handleAvatarSpeechStart() {
        state.avatarIsPendingSpeech = false;
        state.avatarIsSpeaking = true;

        if (state.isListening) {
            stopSpeechRecognition();
        }

        applyUiState({});
        updateMicButtonState();
        setAvatarVisualState('speaking');
    }

    function handleAvatarSpeechEnd() {
        state.avatarIsSpeaking = false;

        if (NarrationQueue.pendingCount() > 0 || NarrationQueue.isProcessing()) {
            state.avatarIsPendingSpeech = true;
        }

        applyUiState({});
        updateMicButtonState();
    }

    async function handleNarrationQueueDrained() {
        state.avatarIsSpeaking = false;

        // Si hay un autoavance pendiente, todavía no habilitamos input.
        if (state.pendingAutoAdvance) {
            state.avatarIsPendingSpeech = true;
            applyUiState({});
            updateMicButtonState();

            await maybeRunPendingAutoAdvance();
            return;
        }

        state.avatarIsPendingSpeech = false;

        applyUiState({});
        updateMicButtonState();

        setAvatarVisualState(avatarAvailable ? 'idle' : 'unavailable');
        maybeOpenPendingCompletionModal();
        maybeFocusInputAfterSpeech();
    }

    function shouldAutoAdvance() {
        return (
            state.nextExpectedAction === 'advance' &&
            !state.requiresUserResponse &&
            !state.completed
        );
    }

    function queueAutoAdvanceIfNeeded() {
        if (!shouldAutoAdvance()) return;

        state.pendingAutoAdvance = true;
        state.avatarIsPendingSpeech = true;
        applyUiState({});
        updateMicButtonState();
    }

    async function maybeRunPendingAutoAdvance() {
        if (!state.pendingAutoAdvance) return;
        if (state.isLoading) return;
        if (state.avatarIsSpeaking) return;
        if (NarrationQueue.isProcessing() || NarrationQueue.pendingCount() > 0) return;

        state.pendingAutoAdvance = false;
        await sendAdvanceTurn();
    }

    async function handleAvatarError(payload) {
        if (payload && payload.reason === 'avatar_reconnecting') {
            setAvatarVisualState('reconnecting');
            return;
        }
        state.avatarIsPendingSpeech = false;
        state.avatarIsSpeaking = false;

        console.error('Avatar error:', payload);

        applyUiState({});
        updateMicButtonState();

        if (payload && payload.reason === 'avatar_room_disconnected') {
            try {
                const restarted = await AvatarService.startSession();
                avatarAvailable = Boolean(restarted);
                setAvatarVisualState(restarted ? 'idle' : 'unavailable');
                return;
            } catch (error) {
                console.error('No se pudo reactivar el avatar:', error);
            }
        }

        avatarAvailable = false;
        setAvatarVisualState('unavailable');
        maybeOpenPendingCompletionModal();
    }

    function handleAvatarSessionClosed() {
        state.avatarIsPendingSpeech = false;
        state.avatarIsSpeaking = false;
        avatarAvailable = false;

        applyUiState({});
        updateMicButtonState();
        setAvatarVisualState('unavailable');
    }

    function setAvatarVisualState(status) {
        if (!avatarContainer) return;

        avatarContainer.classList.remove(
            'challenge__avatar--idle',
            'challenge__avatar--speaking',
            'challenge__avatar--loading',
            'challenge__avatar--unavailable',
            'challenge__avatar--reconnecting'
        );

        const safeStatus = status || 'idle';
        avatarContainer.classList.add(`challenge__avatar--${safeStatus}`);
        avatarContainer.setAttribute('data-avatar-state', safeStatus);
    }

    function handleAvatarNarration(data = {}) {
        if (!avatarAvailable) {
            // Si el avatar está apagado o no disponible, no debe dejar
            // la UI bloqueada esperando una narración que nunca ocurrirá.
            state.avatarIsSpeaking = false;
            state.avatarIsPendingSpeech = false;

            if (data.completionModal) {
                queueCompletionModal(data.completionModal);
                maybeOpenPendingCompletionModal();
            }

            applyUiState({});
            updateMicButtonState();

            // Si el flujo necesita autoavance, lo ejecutamos de inmediato
            // porque no habrá cola de narración que lo dispare.
            if (state.pendingAutoAdvance) {
                maybeRunPendingAutoAdvance();
                return;
            }

            maybeFocusInputAfterSpeech();
            return;
        }

        const stage = state.currentStage;
        const ui = data.ui || {};

        const narrableMessages = NarrationPolicy.select(data.messages || [], {
            stage,
            ui,
            completed: state.completed
        });

        const narrableCompletionMessages = NarrationPolicy.selectCompletionModalMessages(
            data.completionModal,
            {
                stage: 'final_feedback',
                ui: { showAvatarSpeaking: true },
                completed: true
            }
        );

        const batch = [...narrableMessages, ...narrableCompletionMessages];

        if (data.completionModal) {
            queueCompletionModal(data.completionModal);
        }

        if (!batch.length) {
            maybeOpenPendingCompletionModal();
            return;
        }

        state.avatarIsPendingSpeech = true;
        applyUiState({});
        updateMicButtonState();

        NarrationQueue.enqueue(batch);
        NarrationQueue.processNext();
    }

    function queueCompletionModal(modalData) {
        pendingCompletionModal = modalData || null;
    }

    function maybeOpenPendingCompletionModal() {
        if (!pendingCompletionModal) return;
        renderChallengeResultModal(pendingCompletionModal);
        pendingCompletionModal = null;
    }

    function maybeFocusInputAfterSpeech() {
        const shouldFocus =
            state.inputEnabled &&
            state.requiresUserResponse &&
            !state.completed &&
            !state.isLoading &&
            !state.avatarIsSpeaking &&
            !state.avatarIsPendingSpeech;

        if (shouldFocus) {
            textInput.focus();
        }
    }

    // ---------------------------------------------------------------------
    // MODAL FINAL
    // ---------------------------------------------------------------------

    // Abre el modal final del reto.
    function openChallengeResultModal() {
        if (!challengeResultModal) return;

        challengeResultModal.classList.add('is-open');
        challengeResultModal.setAttribute('aria-hidden', 'false');

        if (window.SV && typeof window.SV.lockScroll === 'function') {
            window.SV.lockScroll();
        } else {
            document.body.classList.add('no-scroll');
        }
    }

    // Cierra el modal final del reto.
    function closeChallengeResultModal() {
        if (!challengeResultModal) return;

        challengeResultModal.classList.remove('is-open');
        challengeResultModal.setAttribute('aria-hidden', 'true');

        if (window.SV && typeof window.SV.unlockScroll === 'function') {
            window.SV.unlockScroll();
        } else {
            document.body.classList.remove('no-scroll');
        }
    }

    // Renderiza el contenido del modal final.
    function renderChallengeResultModal(modalData) {
        if (!challengeResultModal || !challengeResultModalBody) return;

        challengeResultModalBody.innerHTML = '';

        if (challengeResultModalTitle) {
            challengeResultModalTitle.textContent = modalData && modalData.title
                ? modalData.title
                : 'Resultado del reto';
        }

        if (challengeResultModalContinue) {
            challengeResultModalContinue.textContent = modalData && modalData.buttonText
                ? modalData.buttonText
                : 'Continuar';
        }

        if (challengeResultModalScore) {
            const hasScore =
                modalData &&
                typeof modalData.scoreAwarded !== 'undefined' &&
                typeof modalData.maxScore !== 'undefined';

            if (hasScore) {
                challengeResultModalScore.textContent =
                    `Puntaje obtenido: ${modalData.scoreAwarded} / ${modalData.maxScore}`;
                challengeResultModalScore.hidden = false;
            } else {
                challengeResultModalScore.textContent = '';
                challengeResultModalScore.hidden = true;
            }
        }

        const messages = modalData && Array.isArray(modalData.messages)
            ? modalData.messages
            : [];

        messages.forEach(function (msg) {
            const p = document.createElement('p');
            p.className = 'sv-challenge-result-modal__text';
            p.textContent = msg.text || '';
            challengeResultModalBody.appendChild(p);
        });

        state.modalRedirectTo = modalData && modalData.redirectTo
            ? modalData.redirectTo
            : '/retos';

        openChallengeResultModal();
    }

    function handleChallengeResultContinue() {
        const redirectTo = state.modalRedirectTo || '/retos';
        closeChallengeResultModal();
        window.location.href = redirectTo;
    }

    // ---------------------------------------------------------------------
    // LOADER
    // ---------------------------------------------------------------------

    // Muestra el loader principal del reto.
    function showChallengeLoader() {
        if (!challengeLoader) return;
        challengeLoader.classList.add('is-visible');
        challengeLoader.setAttribute('aria-hidden', 'false');
    }

    // Oculta el loader principal del reto.
    function hideChallengeLoader() {
        if (!challengeLoader) return;
        challengeLoader.classList.remove('is-visible');
        challengeLoader.setAttribute('aria-hidden', 'true');
    }

    // Oculta el loader y espera un poco para suavizar la transición.
    async function hideChallengeLoaderAndWait() {
        hideChallengeLoader();
        await delay(250);
    }

    // ---------------------------------------------------------------------
    // FLUJO PRINCIPAL DEL RETO
    // ---------------------------------------------------------------------

    // Inicia el reto pidiendo al backend el flujo inicial.
    async function startChallenge() {
        setLoading(true);
        clearMessages();

        try {
            const response = await fetch('/api/retos/start', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    challengeId: state.challengeId,
                    skillId: state.skillId
                })
            });

            const data = await response.json();
            console.log('Respuesta /api/retos/start:', data);

            if (!response.ok || !data.ok) {
                handleApiError(data);
                return;
            }

            applySessionState(data.session);
            renderMessages(data.messages || []);
            applyUiState(data.ui || {});
            await hideChallengeLoaderAndWait();

            if (shouldAutoAdvance()) {
                queueAutoAdvanceIfNeeded();
            }

            handleAvatarNarration(data);
        } catch (error) {
            console.error('Error al iniciar el reto:', error);
            renderSystemMessage('Ocurrió un error al iniciar el reto.');
            hideChallengeLoader();
            return;
        } finally {
            setLoading(false);
            // Si el avatar está apagado y quedó un autoavance pendiente,
            // lo disparamos después de salir del loading.
            if (!avatarAvailable && state.pendingAutoAdvance) {
                maybeRunPendingAutoAdvance();
            }
        }
    }

    // Envía al backend la acción "advance".
    async function sendAdvanceTurn() {
        if (state.isLoading || state.completed) return;

        setLoading(true);
        renderTypingIndicator();

        try {
            const response = await fetch('/api/retos/turn', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    challengeId: state.challengeId,
                    action: 'advance'
                })
            });

            const data = await response.json();
            console.log('Respuesta /api/retos/turn (advance):', data);
            await delay(500);

            removeTypingIndicator();

            if (!response.ok || !data.ok) {
                handleApiError(data);
                return;
            }

            applySessionState(data.session);
            renderMessages(data.messages || []);
            applyUiState(data.ui || {});
            handleCompletion(data);
            await hideChallengeLoaderAndWait();

            if (shouldAutoAdvance()) {
                queueAutoAdvanceIfNeeded();
            }

            handleAvatarNarration(data);

        } catch (error) {
            removeTypingIndicator();
            console.error('Error en advance:', error);
            renderSystemMessage('No se pudo avanzar en el reto.');
            hideChallengeLoader();
        } finally {
            setLoading(false);
            // Si el avatar está apagado y quedó un autoavance pendiente,
            // lo disparamos después de salir del loading.
            if (!avatarAvailable && state.pendingAutoAdvance) {
                maybeRunPendingAutoAdvance();
            }
        }
    }

    // Envía la respuesta del usuario al backend.
    async function sendReplyTurn(userMessage) {
        if (state.isLoading || state.completed) return;

        setLoading(true);
        renderTypingIndicator();

        try {
            const response = await fetch('/api/retos/turn', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    challengeId: state.challengeId,
                    action: 'reply',
                    message: userMessage,
                    inputMode: 'text'
                })
            });

            const data = await response.json();
            console.log('Respuesta /api/retos/turn (reply):', data);
            await delay(500);

            removeTypingIndicator();

            if (!response.ok || !data.ok) {
                handleApiError(data);
                return;
            }

            applySessionState(data.session);
            renderMessages(data.messages || []);
            applyUiState(data.ui || {});
            handleCompletion(data);

            if (shouldAutoAdvance()) {
                queueAutoAdvanceIfNeeded();
            }

            handleAvatarNarration(data);

        } catch (error) {
            removeTypingIndicator();
            console.error('Error en reply:', error);
            renderSystemMessage('No se pudo enviar tu respuesta.');
        } finally {
            setLoading(false);
            // Si el avatar está apagado y quedó un autoavance pendiente,
            // lo disparamos después de salir del loading.
            if (!avatarAvailable && state.pendingAutoAdvance) {
                maybeRunPendingAutoAdvance();
            }
        }
    }

    // Maneja el submit del formulario del composer.
    async function onSubmit(event) {
        event.preventDefault();

        if (
            !state.inputEnabled ||
            !state.requiresUserResponse ||
            state.isLoading ||
            state.avatarIsSpeaking ||
            state.avatarIsPendingSpeech
        ) {
            return;
        }

        const message = textInput.value.trim();
        if (!message) return;

        if (state.isListening) {
            stopSpeechRecognition();
        }

        textInput.value = '';
        await sendReplyTurn(message);
    }

    // ---------------------------------------------------------------------
    // RENDER DE MENSAJES
    // ---------------------------------------------------------------------

    // Renderiza un lote de mensajes.
    function renderMessages(messages) {
        if (!Array.isArray(messages) || messages.length === 0) return;

        messages.forEach(msg => {
            const messageElement = buildMessageElement(msg);
            messagesContainer.appendChild(messageElement);
        });

        scrollMessagesToBottom();
    }

    // Construye el elemento correcto según el role.
    function buildMessageElement(message) {
        const role = message.role || 'assistant';
        const text = message.text || '';

        if (role === 'user') return buildUserMessage(text);
        if (role === 'system') return buildSystemMessageElement(text);
        return buildAssistantMessage(text);
    }

    // Construye un mensaje visual del asistente.
    function buildAssistantMessage(text) {
        const article = document.createElement('article');
        article.className = 'challenge__msg challenge__msg--assistant';

        const bubble = document.createElement('div');
        bubble.className = 'challenge__bubble challenge__bubble--assistant';

        const paragraph = document.createElement('p');
        paragraph.className = 'challenge__text';
        paragraph.textContent = text;

        bubble.appendChild(paragraph);
        article.appendChild(bubble);

        return article;
    }

    // Construye un mensaje visual del usuario.
    function buildUserMessage(text) {
        const article = document.createElement('article');
        article.className = 'challenge__msg challenge__msg--user';

        const bubble = document.createElement('div');
        bubble.className = 'challenge__bubble challenge__bubble--user';

        const paragraph = document.createElement('p');
        paragraph.className = 'challenge__text';
        paragraph.textContent = text;

        bubble.appendChild(paragraph);
        article.appendChild(bubble);

        return article;
    }

    // Construye un mensaje visual del sistema.
    function buildSystemMessageElement(text) {
        const wrapper = document.createElement('div');
        wrapper.className = 'challenge__hint';

        const dot = document.createElement('span');
        dot.className = 'challenge__hintDot';

        const label = document.createElement('span');
        label.className = 'challenge__hintText';
        label.textContent = text;

        wrapper.appendChild(dot);
        wrapper.appendChild(label);

        return wrapper;
    }

    // Inserta un mensaje de sistema directamente.
    function renderSystemMessage(text) {
        const el = buildSystemMessageElement(text);
        messagesContainer.appendChild(el);
        scrollMessagesToBottom();
    }

    // Limpia todos los mensajes del chat.
    function clearMessages() {
        messagesContainer.innerHTML = '';
    }

    // Baja automáticamente el scroll al final.
    function scrollMessagesToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Renderiza el indicador de "pensando..."
    function renderTypingIndicator() {
        removeTypingIndicator();

        const wrapper = document.createElement('div');
        wrapper.className = 'challenge__hint challenge__hint--typing';
        wrapper.setAttribute('data-typing-indicator', 'true');

        const dot = document.createElement('span');
        dot.className = 'challenge__hintDot';

        const label = document.createElement('span');
        label.className = 'challenge__hintText';
        label.textContent = 'El asistente está pensando...';

        wrapper.appendChild(dot);
        wrapper.appendChild(label);

        messagesContainer.appendChild(wrapper);
        scrollMessagesToBottom();
    }

    // Elimina el indicador de typing.
    function removeTypingIndicator() {
        const existing = messagesContainer.querySelector('[data-typing-indicator="true"]');
        if (existing) {
            existing.remove();
        }
    }

    // Espera utilitaria.
    function delay(ms) {
        return new Promise(function (resolve) {
            setTimeout(resolve, ms);
        });
    }

    // ---------------------------------------------------------------------
    // ESTADO Y UI
    // ---------------------------------------------------------------------

    // Aplica al estado frontend los datos de sesión devueltos por backend.
    function applySessionState(session) {
        if (!session) return;

        state.currentStage = session.currentStage ?? state.currentStage;
        state.nextExpectedAction = session.nextExpectedAction ?? state.nextExpectedAction;
        state.inputEnabled = Boolean(session.inputEnabled);
        state.requiresUserResponse = Boolean(session.requiresUserResponse);
        state.completed = Boolean(session.completed);
    }

    // Aplica visualmente el estado actual a input, enviar y micrófono.
    function applyUiState(ui) {
        const safeUi = ui || {};
        const placeholder = resolveComposerPlaceholder(safeUi);
        const focusInput = Boolean(safeUi.focusInput);

        textInput.placeholder = placeholder;

        const shouldDisableComposer =
            state.isLoading ||
            !state.inputEnabled ||
            state.completed ||
            state.avatarIsSpeaking ||
            state.avatarIsPendingSpeech;

        textInput.disabled = shouldDisableComposer;
        sendButton.disabled = shouldDisableComposer;

        if (micButton) {
            micButton.disabled = shouldDisableComposer;
        }

        if (focusInput && !shouldDisableComposer && state.requiresUserResponse) {
            textInput.focus();
        }

        updateMicButtonState();
    }

    // Determina el placeholder correcto del composer.
    function resolveComposerPlaceholder(ui = {}) {
        if (state.avatarIsSpeaking) {
            return 'El avatar está hablando...';
        }

        if (state.avatarIsPendingSpeech) {
            return 'Espera un momento...';
        }

        if (state.isLoading) {
            return 'Espera la respuesta del asistente...';
        }

        if (ui.composerPlaceholder) {
            return ui.composerPlaceholder;
        }

        return 'Escribe tu respuesta...';
    }

    // Marca loading on/off y recalcula UI.
    function setLoading(isLoading) {
        state.isLoading = isLoading;
        applyUiState({});
    }

    // Procesa finalización del reto.
    function handleCompletion(data) {
        if (!data) return;

        if (data.progress) {
            if (data.progress.challengeCompleted || data.progress.failed) {
                state.completed = true;

                if (state.isListening) {
                    stopSpeechRecognition();
                }

                updateMicButtonState();
            }
        }

        if (data.completionModal) {
            queueCompletionModal(data.completionModal);
        }
    }

    // Maneja errores devueltos por backend.
    function handleApiError(data) {
        state.avatarIsPendingSpeech = false;
        hideChallengeLoader();
        removeTypingIndicator();
        console.error('Error API:', data);

        let message = 'Ocurrió un error inesperado.';

        if (data && data.error && data.error.message) {
            message = data.error.message;
        }

        renderSystemMessage(message);
        applyUiState({});

        const redirectTo =
            (data && data.error && data.error.redirectTo)
                ? data.error.redirectTo
                : (data && data.redirectTo ? data.redirectTo : null);

        if (redirectTo) {
            setTimeout(function () {
                window.location.href = redirectTo;
            }, 1500);
        }
    }

    // ---------------------------------------------------------------------
    // RECONOCIMIENTO DE VOZ
    // ---------------------------------------------------------------------

    // Inicializa Web Speech API si el navegador la soporta.
    function initSpeechRecognition() {
        const SpeechRecognitionAPI =
            window.SpeechRecognition || window.webkitSpeechRecognition;

        if (!SpeechRecognitionAPI) {
            state.speechRecognitionSupported = false;

            if (micButton) {
                micButton.disabled = true;
                micButton.title = 'Tu navegador no soporta reconocimiento de voz';
            }

            return;
        }

        state.speechRecognitionSupported = true;

        speechRecognition = new SpeechRecognitionAPI();
        speechRecognition.lang = 'es-CO';
        speechRecognition.continuous = true;
        speechRecognition.interimResults = true;
        speechRecognition.maxAlternatives = 1;

        speechRecognition.onstart = function () {
            state.isListening = true;
            updateMicButtonState();

            if (textInput) {
                textInput.placeholder = 'Escuchando... habla ahora';
            }
        };

        speechRecognition.onresult = function (event) {
            let interimTranscript = '';
            let accumulatedFinal = finalTranscript;

            for (let i = event.resultIndex; i < event.results.length; i++) {
                const transcript = event.results[i][0].transcript;

                if (event.results[i].isFinal) {
                    accumulatedFinal += transcript + ' ';
                } else {
                    interimTranscript += transcript;
                }
            }

            if (textInput) {
                textInput.value = (accumulatedFinal + interimTranscript).trim();
            }

            finalTranscript = accumulatedFinal;
        };

        speechRecognition.onerror = function (event) {
            console.error('Error reconocimiento de voz:', event.error);
            state.isListening = false;
            updateMicButtonState();

            if (textInput) {
                textInput.placeholder = 'No se pudo usar el micrófono. Intenta de nuevo.';
            }
        };

        speechRecognition.onend = function () {
            state.isListening = false;
            updateMicButtonState();

            if (
                textInput &&
                !state.completed &&
                !state.isLoading &&
                !state.avatarIsSpeaking &&
                !state.avatarIsPendingSpeech
            ) {
                textInput.placeholder = 'Escribe tu respuesta...';
            }
        };
    }

    // Alterna entre iniciar o detener el micrófono.
    function toggleSpeechRecognition() {
        if (!state.speechRecognitionSupported || !speechRecognition) {
            renderSystemMessage('Tu navegador no soporta reconocimiento de voz.');
            return;
        }

        if (!canUseVoiceInput()) {
            return;
        }

        if (state.isListening) {
            stopSpeechRecognition();
        } else {
            startSpeechRecognition();
        }
    }

    // Inicia reconocimiento de voz.
    function startSpeechRecognition() {
        if (
            !speechRecognition ||
            state.isListening ||
            state.avatarIsSpeaking ||
            state.avatarIsPendingSpeech
        ) {
            return;
        }

        finalTranscript = textInput
            ? textInput.value.trim() + (textInput.value.trim() ? ' ' : '')
            : '';

        try {
            speechRecognition.start();
        } catch (error) {
            console.error('No se pudo iniciar el reconocimiento:', error);
        }
    }

    // Detiene reconocimiento de voz.
    function stopSpeechRecognition() {
        if (!speechRecognition || !state.isListening) return;

        speechRecognition.stop();
    }

    // Determina si el micrófono puede usarse.
    function canUseVoiceInput() {
        return (
            state.inputEnabled &&
            !state.completed &&
            !state.isLoading &&
            !state.avatarIsSpeaking &&
            !state.avatarIsPendingSpeech
        );
    }

    // Actualiza visual y funcionalmente el botón del micrófono.
    function updateMicButtonState() {
        if (!micButton) return;

        const disabled = !state.speechRecognitionSupported || !canUseVoiceInput();

        micButton.disabled = disabled;
        micButton.classList.toggle('challenge__iconBtn--listening', state.isListening);
        micButton.setAttribute('aria-pressed', state.isListening ? 'true' : 'false');

        if (state.isListening) {
            micButton.title = 'Detener grabación';
        } else if (state.avatarIsSpeaking || state.avatarIsPendingSpeech) {
            micButton.title = 'Micrófono bloqueado mientras el avatar habla';
        } else if (disabled) {
            micButton.title = 'Micrófono no disponible en este momento';
        } else {
            micButton.title = 'Hablar';
        }
    }
})();