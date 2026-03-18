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

    function cacheDom() {
        lessonRoot = document.querySelector('.lesson');

        if (!lessonRoot) return false;

        messagesContainer = document.querySelector('.lesson__messages');
        composerForm = document.querySelector('.lesson__composer');
        textInput = document.querySelector('.lesson__input');
        sendButton = document.querySelector('.lesson__sendBtn');
        micButton = document.querySelector('.lesson__iconBtn');

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

        if (!state.inputEnabled || !state.requiresUserResponse || state.isLoading) return;

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
        const placeholder = ui.composerPlaceholder || 'Escribe tu respuesta...';
        const focusInput = Boolean(ui.focusInput);

        textInput.placeholder = placeholder;
        textInput.disabled = !state.inputEnabled || state.completed;
        sendButton.disabled = !state.inputEnabled || state.completed;

        if (micButton) {
            micButton.disabled = !state.inputEnabled || state.completed;
        }

        if (focusInput && state.inputEnabled && !state.completed) {
            textInput.focus();
        }

        updateMicButtonState();
    }

    function setLoading(isLoading) {
        state.isLoading = isLoading;

        const shouldDisable = isLoading || !state.inputEnabled || state.completed;

        textInput.disabled = shouldDisable;
        sendButton.disabled = shouldDisable;

        if (micButton) {
            micButton.disabled = shouldDisable;
        }

        if (isLoading) {
            textInput.placeholder = 'Espera la respuesta del asistente...';
        }

        updateMicButtonState();
    }

    function handleCompletion(data) {
        if (!data || !data.progress) return;

        if (data.progress.lessonCompleted || data.progress.failed) {
            state.completed = true;
            textInput.disabled = true;
            sendButton.disabled = true;

            if (state.isListening) {
                stopSpeechRecognition();
            }

            updateMicButtonState();
        }

        if (data.completionModal) {
            renderLessonResultModal(data.completionModal);
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

    //Iniciarlizar reconocimiento de voz
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

            if (textInput && !state.completed && !state.isLoading) {
                textInput.placeholder = 'Escribe tu respuesta...';
            }
        };
    }


    //Toggle del microfono
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
        if (!speechRecognition || state.isListening) return;

        finalTranscript = textInput ? textInput.value.trim() + (textInput.value.trim() ? ' ' : '') : '';

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

    //Saber si se puede usar el input de voz
    function canUseVoiceInput() {
        return (
            state.inputEnabled &&
            !state.completed &&
            !state.isLoading &&
            !state.avatarIsSpeaking
        );
    }

    //Estado visual del botón de microfono
    function updateMicButtonState() {
        if (!micButton) return;

        const disabled = !state.speechRecognitionSupported || !canUseVoiceInput();

        micButton.disabled = disabled;

        micButton.classList.toggle('lesson__iconBtn--listening', state.isListening);
        micButton.setAttribute('aria-pressed', state.isListening ? 'true' : 'false');

        if (state.isListening) {
            micButton.title = 'Detener grabación';
        } else if (disabled) {
            micButton.title = 'Micrófono no disponible en este momento';
        } else {
            micButton.title = 'Hablar';
        }
    }
})();