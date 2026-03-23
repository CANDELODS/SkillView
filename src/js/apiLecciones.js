(function () {
    let messagesContainer = null;
    let composerForm = null;
    let textInput = null;
    let sendButton = null;
    let micButton = null;

    let lessonLoader = null;

    let lessonResultModal = null;
    let lessonResultModalBody = null;
    let lessonResultModalTitle = null;
    let lessonResultModalContinue = null;

    let speechRecognition = null;
    let finalTranscript = '';

    let lessonRoot = null;
    let avatarContainer = null;

    let pendingCompletionModal = null;
    let avatarAvailable = false;

    const state = {
        lessonId: 0,
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
        avatarIsSpeaking: false
    };

    // ---------------------------------------------------------------------
    // FALLBACKS SEGUROS
    // ---------------------------------------------------------------------
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

    const NarrationPolicy = window.NarrationPolicy || (() => {
        const NARRABLE_STAGES = new Set([
            'intro',
            'micro_practice_prompt',
            'mini_eval_prompt',
            'final_feedback'
        ]);

        const NON_NARRABLE_STAGES = new Set([
            'micro_practice_answer_retry',
            'mini_eval_retry',
            'complete'
        ]);

        function normalizeText(text) {
            return String(text || '').replace(/\s+/g, ' ').trim();
        }

        function isAssistantMessage(message) {
            return Boolean(message) && message.role === 'assistant';
        }

        function isNarrableStage(stage) {
            return NARRABLE_STAGES.has(stage);
        }

        function isRetryStage(stage) {
            return stage === 'micro_practice_answer_retry' || stage === 'mini_eval_retry';
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
                    id: message.id || `narration_message_${Date.now()}_${index}`,
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
                    id: `completion_modal_${Date.now()}_${index}`,
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

    function cacheDom() {
        lessonRoot = document.querySelector('.lesson');

        if (!lessonRoot) return false;

        messagesContainer = document.querySelector('.lesson__messages');
        composerForm = document.querySelector('.lesson__composer');
        textInput = document.querySelector('.lesson__input');
        sendButton = document.querySelector('.lesson__sendBtn');
        micButton = document.querySelector('.lesson__iconBtn');
        avatarContainer = document.querySelector('.lesson__avatar');

        lessonLoader = document.getElementById('lesson-loader');

        lessonResultModal = document.getElementById('sv-lesson-result-modal');
        lessonResultModalBody = lessonResultModal
            ? lessonResultModal.querySelector('[data-sv-lesson-result-body]')
            : null;
        lessonResultModalTitle = lessonResultModal
            ? lessonResultModal.querySelector('#sv-lesson-result-modal-title')
            : null;
        lessonResultModalContinue = lessonResultModal
            ? lessonResultModal.querySelector('[data-sv-lesson-result-continue]')
            : null;

        if (!messagesContainer || !composerForm || !textInput || !sendButton) {
            return false;
        }

        state.lessonId = Number(lessonRoot.dataset.leccionId || 0);
        state.skillId = Number(lessonRoot.dataset.habilidadId || 0);

        if (!state.lessonId || !state.skillId) {
            console.error('No se pudieron leer leccionId o habilidadId desde data attributes.');
            return false;
        }

        return true;
    }

    function init() {
        const ready = cacheDom();

        if (!ready) {
            console.error('La vista de lección aún no está lista o faltan elementos del DOM.');
            return;
        }

        showLessonLoader();
        initSpeechRecognition();
        initAvatarIntegration();
        bindEvents();
        startLesson();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function bindEvents() {
        composerForm.addEventListener('submit', onSubmit);

        textInput.addEventListener('input', () => {
            // reservado para mejoras futuras
        });

        if (lessonResultModalContinue) {
            lessonResultModalContinue.addEventListener('click', handleLessonResultContinue);
        }

        if (micButton) {
            micButton.addEventListener('click', function () {
                toggleSpeechRecognition();
            });
        }

        window.addEventListener('beforeunload', handleBeforeUnload);
    }

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
        if (!avatarContainer || !AvatarService) {
            setAvatarVisualState('unavailable');
            return;
        }

        try {
            AvatarService.init({
                provider: 'heygen',
                context: 'lesson'
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
                    console.error('No se pudo iniciar la sesión del avatar:', error);
                    setAvatarVisualState('unavailable');
                });
        } catch (error) {
            avatarAvailable = false;
            console.error('Error al inicializar el avatar:', error);
            setAvatarVisualState('unavailable');
        }
    }

    function handleAvatarReady() {
        avatarAvailable = true;
        setAvatarVisualState('idle');
    }

    function handleAvatarSpeechStart() {
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

        applyUiState({});
        updateMicButtonState();

        if (NarrationQueue.pendingCount() > 0) {
            NarrationQueue.processNext();
            return;
        }

        setAvatarVisualState(avatarAvailable ? 'idle' : 'unavailable');
        maybeOpenPendingCompletionModal();
        maybeFocusInputAfterSpeech();
    }

    function handleAvatarError(payload) {
        state.avatarIsSpeaking = false;
        avatarAvailable = false;

        console.error('Avatar error:', payload);

        applyUiState({});
        updateMicButtonState();
        setAvatarVisualState('unavailable');

        maybeOpenPendingCompletionModal();
    }

    function handleAvatarSessionClosed() {
        state.avatarIsSpeaking = false;
        avatarAvailable = false;
        setAvatarVisualState('unavailable');
    }

    function setAvatarVisualState(status) {
        if (!avatarContainer) return;

        avatarContainer.classList.remove(
            'lesson__avatar--idle',
            'lesson__avatar--speaking',
            'lesson__avatar--loading',
            'lesson__avatar--unavailable'
        );

        const safeStatus = status || 'idle';
        avatarContainer.classList.add(`lesson__avatar--${safeStatus}`);
        avatarContainer.setAttribute('data-avatar-state', safeStatus);
    }

    function handleAvatarNarration(data = {}) {
        if (!avatarAvailable) {
            if (data.completionModal) {
                queueCompletionModal(data.completionModal);
                maybeOpenPendingCompletionModal();
            }
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

        NarrationQueue.enqueue(batch);
        NarrationQueue.processNext();
    }

    function queueCompletionModal(modalData) {
        pendingCompletionModal = modalData || null;
    }

    function maybeOpenPendingCompletionModal() {
        if (!pendingCompletionModal) return;
        renderLessonResultModal(pendingCompletionModal);
        pendingCompletionModal = null;
    }

    function maybeFocusInputAfterSpeech() {
        const shouldFocus =
            state.inputEnabled &&
            state.requiresUserResponse &&
            !state.completed &&
            !state.isLoading &&
            !state.avatarIsSpeaking;

        if (shouldFocus) {
            textInput.focus();
        }
    }

    // --------------------- MODAL ----------------------------------------
    function openLessonResultModal() {
        if (!lessonResultModal) return;

        lessonResultModal.classList.add('is-open');
        lessonResultModal.setAttribute('aria-hidden', 'false');

        if (window.SV && typeof window.SV.lockScroll === 'function') {
            window.SV.lockScroll();
        } else {
            document.body.classList.add('no-scroll');
        }
    }

    function closeLessonResultModal() {
        if (!lessonResultModal) return;

        lessonResultModal.classList.remove('is-open');
        lessonResultModal.setAttribute('aria-hidden', 'true');

        if (window.SV && typeof window.SV.unlockScroll === 'function') {
            window.SV.unlockScroll();
        } else {
            document.body.classList.remove('no-scroll');
        }
    }

    function renderLessonResultModal(modalData) {
        if (!lessonResultModal || !lessonResultModalBody) return;

        lessonResultModalBody.innerHTML = '';

        if (lessonResultModalTitle) {
            lessonResultModalTitle.textContent = modalData && modalData.title
                ? modalData.title
                : 'Resultado de la lección';
        }

        if (lessonResultModalContinue) {
            lessonResultModalContinue.textContent = modalData && modalData.buttonText
                ? modalData.buttonText
                : 'Continuar';
        }

        const messages = modalData && Array.isArray(modalData.messages)
            ? modalData.messages
            : [];

        messages.forEach(function (msg) {
            const p = document.createElement('p');
            p.className = 'sv-lesson-result-modal__text';
            p.textContent = msg.text || '';
            lessonResultModalBody.appendChild(p);
        });

        state.modalRedirectTo = modalData && modalData.redirectTo
            ? modalData.redirectTo
            : '/aprendizaje';

        openLessonResultModal();
    }

    function handleLessonResultContinue() {
        const redirectTo = state.modalRedirectTo || '/aprendizaje';
        closeLessonResultModal();
        window.location.href = redirectTo;
    }
    // --------------------- FIN MODAL ------------------------------------

    // --------------------- LOADER ------------------------------------
    function showLessonLoader() {
        if (!lessonLoader) return;
        lessonLoader.classList.add('is-visible');
        lessonLoader.setAttribute('aria-hidden', 'false');
    }

    function hideLessonLoader() {
        if (!lessonLoader) return;
        lessonLoader.classList.remove('is-visible');
        lessonLoader.setAttribute('aria-hidden', 'true');
    }
    // --------------------- FIN LOADER ------------------------------------

    async function startLesson() {
        setLoading(true);
        clearMessages();

        try {
            const response = await fetch('/api/lecciones/start', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    lessonId: state.lessonId,
                    skillId: state.skillId
                })
            });

            const data = await response.json();
            console.log('Respuesta /api/lecciones/start:', data);

            if (!response.ok || !data.ok) {
                handleApiError(data);
                return;
            }

            applySessionState(data.session);
            renderMessages(data.messages || []);
            applyUiState(data.ui || {});
            handleAvatarNarration(data);
        } catch (error) {
            console.error('Error al iniciar la lección:', error);
            renderSystemMessage('Ocurrió un error al iniciar la lección.');
            hideLessonLoader();
            return;
        } finally {
            setLoading(false);
        }

        if (state.nextExpectedAction === 'advance' && !state.requiresUserResponse) {
            await sendAdvanceTurn();
        }
    }

    async function sendAdvanceTurn() {
        if (state.isLoading || state.completed) return;

        setLoading(true);
        renderTypingIndicator();

        try {
            const response = await fetch('/api/lecciones/turn', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    lessonId: state.lessonId,
                    action: 'advance'
                })
            });

            const data = await response.json();
            console.log('Respuesta /api/lecciones/turn (advance):', data);
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
            handleAvatarNarration(data);
            hideLessonLoader();

        } catch (error) {
            removeTypingIndicator();
            console.error('Error en advance:', error);
            renderSystemMessage('No se pudo avanzar en la lección.');
            hideLessonLoader();
        } finally {
            setLoading(false);
        }
    }

    async function sendReplyTurn(userMessage) {
        if (state.isLoading || state.completed) return;

        setLoading(true);
        renderTypingIndicator();

        try {
            const response = await fetch('/api/lecciones/turn', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    lessonId: state.lessonId,
                    action: 'reply',
                    message: userMessage,
                    inputMode: 'text'
                })
            });

            const data = await response.json();
            console.log('Respuesta /api/lecciones/turn (reply):', data);
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
            handleAvatarNarration(data);

        } catch (error) {
            removeTypingIndicator();
            console.error('Error en reply:', error);
            renderSystemMessage('No se pudo enviar tu respuesta.');
        } finally {
            setLoading(false);
        }
    }

    async function onSubmit(event) {
        event.preventDefault();

        if (!state.inputEnabled || !state.requiresUserResponse || state.isLoading || state.avatarIsSpeaking) {
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

    function renderMessages(messages) {
        if (!Array.isArray(messages) || messages.length === 0) return;

        messages.forEach(msg => {
            const messageElement = buildMessageElement(msg);
            messagesContainer.appendChild(messageElement);
        });

        scrollMessagesToBottom();
    }

    function buildMessageElement(message) {
        const role = message.role || 'assistant';
        const text = message.text || '';

        if (role === 'user') return buildUserMessage(text);
        if (role === 'system') return buildSystemMessageElement(text);
        return buildAssistantMessage(text);
    }

    function buildAssistantMessage(text) {
        const article = document.createElement('article');
        article.className = 'lesson__msg lesson__msg--assistant';

        const bubble = document.createElement('div');
        bubble.className = 'lesson__bubble lesson__bubble--assistant';

        const paragraph = document.createElement('p');
        paragraph.className = 'lesson__text';
        paragraph.textContent = text;

        bubble.appendChild(paragraph);
        article.appendChild(bubble);

        return article;
    }

    function buildUserMessage(text) {
        const article = document.createElement('article');
        article.className = 'lesson__msg lesson__msg--user';

        const bubble = document.createElement('div');
        bubble.className = 'lesson__bubble lesson__bubble--user';

        const paragraph = document.createElement('p');
        paragraph.className = 'lesson__text';
        paragraph.textContent = text;

        bubble.appendChild(paragraph);
        article.appendChild(bubble);

        return article;
    }

    function buildSystemMessageElement(text) {
        const wrapper = document.createElement('div');
        wrapper.className = 'lesson__hint';

        const dot = document.createElement('span');
        dot.className = 'lesson__hintDot';

        const label = document.createElement('span');
        label.className = 'lesson__hintText';
        label.textContent = text;

        wrapper.appendChild(dot);
        wrapper.appendChild(label);

        return wrapper;
    }

    function renderSystemMessage(text) {
        const el = buildSystemMessageElement(text);
        messagesContainer.appendChild(el);
        scrollMessagesToBottom();
    }

    function clearMessages() {
        messagesContainer.innerHTML = '';
    }

    function scrollMessagesToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function renderTypingIndicator() {
        removeTypingIndicator();

        const wrapper = document.createElement('div');
        wrapper.className = 'lesson__hint lesson__hint--typing';
        wrapper.setAttribute('data-typing-indicator', 'true');

        const dot = document.createElement('span');
        dot.className = 'lesson__hintDot';

        const label = document.createElement('span');
        label.className = 'lesson__hintText';
        label.textContent = 'El asistente está pensando...';

        wrapper.appendChild(dot);
        wrapper.appendChild(label);

        messagesContainer.appendChild(wrapper);
        scrollMessagesToBottom();
    }

    function removeTypingIndicator() {
        const existing = messagesContainer.querySelector('[data-typing-indicator="true"]');
        if (existing) {
            existing.remove();
        }
    }

    function delay(ms) {
        return new Promise(function (resolve) {
            setTimeout(resolve, ms);
        });
    }

    function applySessionState(session) {
        if (!session) return;

        state.currentStage = session.currentStage ?? state.currentStage;
        state.nextExpectedAction = session.nextExpectedAction ?? state.nextExpectedAction;
        state.inputEnabled = Boolean(session.inputEnabled);
        state.requiresUserResponse = Boolean(session.requiresUserResponse);
        state.completed = Boolean(session.completed);
    }

    function applyUiState(ui) {
        const placeholder = resolveComposerPlaceholder(ui);
        const focusInput = Boolean(ui.focusInput);

        textInput.placeholder = placeholder;

        const shouldDisableComposer =
            state.isLoading ||
            !state.inputEnabled ||
            state.completed ||
            state.avatarIsSpeaking;

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

    function resolveComposerPlaceholder(ui = {}) {
        if (state.avatarIsSpeaking) {
            return 'El avatar está hablando...';
        }

        if (state.isLoading) {
            return 'Espera la respuesta del asistente...';
        }

        if (ui.composerPlaceholder) {
            return ui.composerPlaceholder;
        }

        return 'Escribe tu respuesta...';
    }

    function setLoading(isLoading) {
        state.isLoading = isLoading;
        applyUiState({});
    }

    function handleCompletion(data) {
        if (!data || !data.progress) return;

        if (data.progress.lessonCompleted || data.progress.failed) {
            state.completed = true;

            if (state.isListening) {
                stopSpeechRecognition();
            }

            updateMicButtonState();
        }

        if (data.completionModal) {
            queueCompletionModal(data.completionModal);
        }
    }

    function handleApiError(data) {
        hideLessonLoader();
        removeTypingIndicator();
        console.error('Error API:', data);

        let message = 'Ocurrió un error inesperado.';

        if (data && data.error && data.error.message) {
            message = data.error.message;
        }

        renderSystemMessage(message);

        if (data && data.redirectTo) {
            setTimeout(function () {
                window.location.href = data.redirectTo;
            }, 1500);
        }
    }

    // Inicializar reconocimiento de voz
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

            if (textInput && !state.completed && !state.isLoading && !state.avatarIsSpeaking) {
                textInput.placeholder = 'Escribe tu respuesta...';
            }
        };
    }

    // Toggle del micrófono
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

    function startSpeechRecognition() {
        if (!speechRecognition || state.isListening || state.avatarIsSpeaking) return;

        finalTranscript = textInput
            ? textInput.value.trim() + (textInput.value.trim() ? ' ' : '')
            : '';

        try {
            speechRecognition.start();
        } catch (error) {
            console.error('No se pudo iniciar el reconocimiento:', error);
        }
    }

    function stopSpeechRecognition() {
        if (!speechRecognition || !state.isListening) return;

        speechRecognition.stop();
    }

    // Saber si se puede usar el input de voz
    function canUseVoiceInput() {
        return (
            state.inputEnabled &&
            !state.completed &&
            !state.isLoading &&
            !state.avatarIsSpeaking
        );
    }

    // Estado visual del botón de micrófono
    function updateMicButtonState() {
        if (!micButton) return;

        const disabled = !state.speechRecognitionSupported || !canUseVoiceInput();

        micButton.disabled = disabled;

        micButton.classList.toggle('lesson__iconBtn--listening', state.isListening);
        micButton.setAttribute('aria-pressed', state.isListening ? 'true' : 'false');

        if (state.isListening) {
            micButton.title = 'Detener grabación';
        } else if (state.avatarIsSpeaking) {
            micButton.title = 'Micrófono bloqueado mientras el avatar habla';
        } else if (disabled) {
            micButton.title = 'Micrófono no disponible en este momento';
        } else {
            micButton.title = 'Hablar';
        }
    }
})();