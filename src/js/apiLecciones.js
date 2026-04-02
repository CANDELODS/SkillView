(function () {
    // Referencias principales del DOM para la interfaz del chat
    let messagesContainer = null;
    let composerForm = null;
    let textInput = null;
    let sendButton = null;
    let micButton = null;

    // Loader visual de la lección
    let lessonLoader = null;

    // Elementos del modal de resultado final de la lección
    let lessonResultModal = null;
    let lessonResultModalBody = null;
    let lessonResultModalTitle = null;
    let lessonResultModalContinue = null;

    // Variables asociadas al reconocimiento de voz
    let speechRecognition = null;
    let finalTranscript = '';

    // Elementos raíz de la vista y contenedor del avatar
    let lessonRoot = null;
    let avatarContainer = null;

    // Variable para guardar un modal final pendiente de abrir
    // hasta que termine la narración del avatar
    let pendingCompletionModal = null;

    // Bandera para saber si el avatar está disponible
    let avatarAvailable = false;

    // ---------------------------------------------------------------------
    // ESTADO GLOBAL DEL MÓDULO
    // ---------------------------------------------------------------------
    // Aquí se concentra el estado funcional de toda la experiencia:
    // etapa actual, si el input está habilitado, si el avatar habla,
    // si el sistema está cargando, etc.
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
        avatarIsSpeaking: false,
        avatarIsPendingSpeech: false,
        pendingAutoAdvance: false
    };

    // ---------------------------------------------------------------------
    // FALLBACKS SEGUROS
    // ---------------------------------------------------------------------
    // Si por alguna razón AvatarService no está cargado en window,
    // este fallback evita que el sistema se rompa.
    // Expone la misma interfaz mínima, pero sin comportamiento real.
    const AvatarService = window.AvatarService || (() => {
        const listeners = {
            ready: [],
            speechStart: [],
            speechEnd: [],
            error: [],
            sessionClosed: []
        };

        // Emite eventos simulados a los listeners registrados
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
    // Este módulo define qué mensajes sí deben ser narrados por el avatar
    // y cuáles no. Por ejemplo, los retries no se narran.
    const NarrationPolicy = window.NarrationPolicy || (() => {

        // Etapas que NO deben narrarse
        const NON_NARRABLE_STAGES = new Set([
            'micro_practice_answer_retry',
            'mini_eval_retry',
            'complete'
        ]);

        // Normaliza el texto recibido
        function normalizeText(text) {
            return String(text || '').replace(/\s+/g, ' ').trim();
        }

        // Verifica si el mensaje proviene del asistente
        function isAssistantMessage(message) {
            return Boolean(message) && message.role === 'assistant';
        }

        // Verifica si la etapa actual corresponde a un retry
        function isRetryStage(stage) {
            return stage === 'micro_practice_answer_retry' || stage === 'mini_eval_retry';
        }

        // Decide si un mensaje debe narrarse o no
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

        // Selecciona mensajes narrables del flujo principal
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

        // Selecciona mensajes narrables del modal final
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

    // ---------------------------------------------------------------------
    // COLA DE NARRACIÓN
    // ---------------------------------------------------------------------
    // Evita que varios mensajes se narren al mismo tiempo.
    // El avatar habla uno por uno en orden.
    const NarrationQueue = window.NarrationQueue || (() => {
        let items = [];
        let processing = false;

        // Agrega un lote de mensajes a la cola
        function enqueue(batch = []) {
            if (!Array.isArray(batch) || batch.length === 0) return;

            batch.forEach((item) => {
                if (!item || !item.text) return;
                items.push(item);
            });
        }

        // Procesa la cola de narración secuencialmente
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

                // Cuando la cola termina, avisamos al sistema
                if (!items.length) {
                    handleNarrationQueueDrained();
                }
            }
        }

        // Vacía la cola por completo
        function clear() {
            items = [];
            processing = false;
        }

        // Retorna cuántos elementos faltan por narrar
        function pendingCount() {
            return items.length;
        }

        // Retorna si actualmente la cola está procesando
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
    // CACHEAR ELEMENTOS DEL DOM
    // ---------------------------------------------------------------------
    // Busca y guarda referencias a todos los elementos necesarios en la vista.
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

        // Leemos los ids de la lección y la habilidad desde data attributes
        state.lessonId = Number(lessonRoot.dataset.leccionId || 0);
        state.skillId = Number(lessonRoot.dataset.habilidadId || 0);

        if (!state.lessonId || !state.skillId) {
            console.error('No se pudieron leer leccionId o habilidadId desde data attributes.');
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
            console.error('La vista de lección aún no está lista o faltan elementos del DOM.');
            return;
        }

        showLessonLoader();
        initSpeechRecognition();
        initAvatarIntegration();
        bindEvents();
        startLesson();
    }

    // Se inicializa al cargar el DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // ---------------------------------------------------------------------
    // ENLACE DE EVENTOS
    // ---------------------------------------------------------------------
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

    // Antes de cerrar o recargar la página, detenemos reconocimiento y avatar
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

    // Inicializa la integración del avatar con el contenedor de la vista
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

    // Cuando el avatar ya está listo
    function handleAvatarReady() {
        avatarAvailable = true;
        setAvatarVisualState('idle');
    }

    // Cuando el avatar empieza a hablar
    function handleAvatarSpeechStart() {
        state.avatarIsPendingSpeech = false;
        state.avatarIsSpeaking = true;

        // Si el micrófono estaba activo, lo detenemos
        if (state.isListening) {
            stopSpeechRecognition();
        }

        applyUiState({});
        updateMicButtonState();
        setAvatarVisualState('speaking');
    }

    // Cuando el avatar termina una narración
    function handleAvatarSpeechEnd() {
        state.avatarIsSpeaking = false;

        // Si todavía quedan mensajes por narrar, dejamos pendiente el bloqueo
        if (NarrationQueue.pendingCount() > 0 || NarrationQueue.isProcessing()) {
            state.avatarIsPendingSpeech = true;
        }

        applyUiState({});
        updateMicButtonState();
    }

    // Cuando la cola de narración termina completamente
    async function handleNarrationQueueDrained() {
        state.avatarIsSpeaking = false;

        // Si hay un autoavance pendiente, NO habilitamos inputs aún
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

    // Determina si el flujo debe autoavanzar sin intervención del usuario
    function shouldAutoAdvance() {
        return (
            state.nextExpectedAction === 'advance' &&
            !state.requiresUserResponse &&
            !state.completed
        );
    }

    // Marca el autoavance como pendiente y bloquea temporalmente la UI
    function queueAutoAdvanceIfNeeded() {
        if (!shouldAutoAdvance()) return;

        state.pendingAutoAdvance = true;
        state.avatarIsPendingSpeech = true;
        applyUiState({});
        updateMicButtonState();
    }

    // Ejecuta el autoavance pendiente si ya no hay narración en curso
    async function maybeRunPendingAutoAdvance() {
        if (!state.pendingAutoAdvance) return;
        if (state.isLoading) return;
        if (state.avatarIsSpeaking) return;
        if (NarrationQueue.isProcessing() || NarrationQueue.pendingCount() > 0) return;

        state.pendingAutoAdvance = false;
        await sendAdvanceTurn();
    }

    // Manejo de error del avatar
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

    // Manejo cuando la sesión del avatar se cierra
    function handleAvatarSessionClosed() {
        state.avatarIsPendingSpeech = false;
        state.avatarIsSpeaking = false;
        avatarAvailable = false;

        applyUiState({});
        updateMicButtonState();
        setAvatarVisualState('unavailable');
    }

    // Cambia el estado visual del avatar en la UI
    function setAvatarVisualState(status) {
        if (!avatarContainer) return;

        avatarContainer.classList.remove(
            'lesson__avatar--idle',
            'lesson__avatar--speaking',
            'lesson__avatar--loading',
            'lesson__avatar--unavailable',
            'lesson__avatar--reconnecting'
        );

        const safeStatus = status || 'idle';
        avatarContainer.classList.add(`lesson__avatar--${safeStatus}`);
        avatarContainer.setAttribute('data-avatar-state', safeStatus);
    }

    // Procesa los mensajes narrables y los manda a la cola del avatar
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

        state.avatarIsPendingSpeech = true;
        applyUiState({});
        updateMicButtonState();

        NarrationQueue.enqueue(batch);
        NarrationQueue.processNext();
    }

    // Guarda temporalmente el modal final
    function queueCompletionModal(modalData) {
        pendingCompletionModal = modalData || null;
    }

    // Abre el modal final si existe uno pendiente
    function maybeOpenPendingCompletionModal() {
        if (!pendingCompletionModal) return;
        renderLessonResultModal(pendingCompletionModal);
        pendingCompletionModal = null;
    }

    // Devuelve foco al input si corresponde
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

    // --------------------- MODAL ----------------------------------------
    // Abre el modal final y bloquea scroll del body
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

    // Cierra el modal final y restaura scroll
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

    // Renderiza el contenido del modal final
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

    // Acción del botón continuar en el modal final
    function handleLessonResultContinue() {
        const redirectTo = state.modalRedirectTo || '/aprendizaje';
        closeLessonResultModal();
        window.location.href = redirectTo;
    }
    // --------------------- FIN MODAL ------------------------------------

    // --------------------- LOADER ------------------------------------
    // Muestra el loader de la lección
    function showLessonLoader() {
        if (!lessonLoader) return;
        lessonLoader.classList.add('is-visible');
        lessonLoader.setAttribute('aria-hidden', 'false');
    }

    // Oculta el loader
    function hideLessonLoader() {
        if (!lessonLoader) return;
        lessonLoader.classList.remove('is-visible');
        lessonLoader.setAttribute('aria-hidden', 'true');
    }

    // Oculta el loader y espera un pequeño tiempo para suavizar la transición
    async function hideLessonLoaderAndWait() {
        hideLessonLoader();
        await delay(250);
    }
    // --------------------- FIN LOADER ------------------------------------

    // ---------------------------------------------------------------------
    // FLUJO PRINCIPAL DE LA LECCIÓN
    // ---------------------------------------------------------------------

    // Inicia la lección en el backend
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
            await hideLessonLoaderAndWait();

            if (shouldAutoAdvance()) {
                queueAutoAdvanceIfNeeded();
            }

            handleAvatarNarration(data);
        } catch (error) {
            console.error('Error al iniciar la lección:', error);
            renderSystemMessage('Ocurrió un error al iniciar la lección.');
            hideLessonLoader();
            return;
        } finally {
            setLoading(false);
        }
    }

    // Envía al backend la acción "advance" para pasar a la siguiente parte del flujo
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
            await hideLessonLoaderAndWait();

            if (shouldAutoAdvance()) {
                queueAutoAdvanceIfNeeded();
            }

            handleAvatarNarration(data);

        } catch (error) {
            removeTypingIndicator();
            console.error('Error en advance:', error);
            renderSystemMessage('No se pudo avanzar en la lección.');
            hideLessonLoader();
        } finally {
            setLoading(false);
        }
    }

    // Envía al backend una respuesta escrita por el usuario
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
        }
    }

    // Maneja el envío manual del formulario por parte del usuario
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

    // Renderiza un lote de mensajes
    function renderMessages(messages) {
        if (!Array.isArray(messages) || messages.length === 0) return;

        messages.forEach(msg => {
            const messageElement = buildMessageElement(msg);
            messagesContainer.appendChild(messageElement);
        });

        scrollMessagesToBottom();
    }

    // Construye el elemento correcto según el tipo de mensaje
    function buildMessageElement(message) {
        const role = message.role || 'assistant';
        const text = message.text || '';

        if (role === 'user') return buildUserMessage(text);
        if (role === 'system') return buildSystemMessageElement(text);
        return buildAssistantMessage(text);
    }

    // Construye mensaje del asistente
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

    // Construye mensaje del usuario
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

    // Construye mensaje del sistema
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

    // Inserta un mensaje de sistema directamente
    function renderSystemMessage(text) {
        const el = buildSystemMessageElement(text);
        messagesContainer.appendChild(el);
        scrollMessagesToBottom();
    }

    // Limpia el área de mensajes
    function clearMessages() {
        messagesContainer.innerHTML = '';
    }

    // Baja automáticamente el scroll del chat
    function scrollMessagesToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Muestra el indicador de "pensando..."
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

    // Elimina el indicador de "pensando..."
    function removeTypingIndicator() {
        const existing = messagesContainer.querySelector('[data-typing-indicator="true"]');
        if (existing) {
            existing.remove();
        }
    }

    // Espera utilitaria
    function delay(ms) {
        return new Promise(function (resolve) {
            setTimeout(resolve, ms);
        });
    }

    // ---------------------------------------------------------------------
    // ACTUALIZACIÓN DE ESTADO Y UI
    // ---------------------------------------------------------------------

    // Toma la información de session del backend y actualiza el estado global
    function applySessionState(session) {
        if (!session) return;

        state.currentStage = session.currentStage ?? state.currentStage;
        state.nextExpectedAction = session.nextExpectedAction ?? state.nextExpectedAction;
        state.inputEnabled = Boolean(session.inputEnabled);
        state.requiresUserResponse = Boolean(session.requiresUserResponse);
        state.completed = Boolean(session.completed);
    }

    // Aplica el estado de UI al input, botón enviar y micrófono
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

    // Define qué placeholder debe mostrarse en el composer
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

    // Marca si el sistema está cargando
    function setLoading(isLoading) {
        state.isLoading = isLoading;
        applyUiState({});
    }

    // Procesa información de finalización de la lección
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

    // Maneja errores devueltos por la API
    function handleApiError(data) {
        state.avatarIsPendingSpeech = false;
        hideLessonLoader();
        removeTypingIndicator();
        console.error('Error API:', data);

        let message = 'Ocurrió un error inesperado.';

        if (data && data.error && data.error.message) {
            message = data.error.message;
        }

        renderSystemMessage(message);
        applyUiState({});

        if (data && data.redirectTo) {
            setTimeout(function () {
                window.location.href = data.redirectTo;
            }, 1500);
        }
    }

    // ---------------------------------------------------------------------
    // RECONOCIMIENTO DE VOZ
    // ---------------------------------------------------------------------

    // Inicializa Web Speech API si está soportada
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

        // Cuando el reconocimiento empieza
        speechRecognition.onstart = function () {
            state.isListening = true;
            updateMicButtonState();

            if (textInput) {
                textInput.placeholder = 'Escuchando... habla ahora';
            }
        };

        // Mientras llegan resultados de voz
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

        // Si ocurre error en reconocimiento
        speechRecognition.onerror = function (event) {
            console.error('Error reconocimiento de voz:', event.error);
            state.isListening = false;
            updateMicButtonState();

            if (textInput) {
                textInput.placeholder = 'No se pudo usar el micrófono. Intenta de nuevo.';
            }
        };

        // Cuando termina el reconocimiento
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

    // Activa o detiene el micrófono
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

    // Inicia el reconocimiento de voz
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

    // Detiene el reconocimiento de voz
    function stopSpeechRecognition() {
        if (!speechRecognition || !state.isListening) return;

        speechRecognition.stop();
    }

    // Decide si se puede usar entrada por voz
    function canUseVoiceInput() {
        return (
            state.inputEnabled &&
            !state.completed &&
            !state.isLoading &&
            !state.avatarIsSpeaking &&
            !state.avatarIsPendingSpeech
        );
    }

    // Actualiza el estado visual del botón de micrófono
    function updateMicButtonState() {
        if (!micButton) return;

        const disabled = !state.speechRecognitionSupported || !canUseVoiceInput();

        micButton.disabled = disabled;

        micButton.classList.toggle('lesson__iconBtn--listening', state.isListening);
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