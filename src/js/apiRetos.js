(function () {
    let messagesContainer = null;
    let composerForm = null;
    let textInput = null;
    let sendButton = null;
    let micButton = null;

    let challengeLoader = null;

    let challengeResultModal = null;
    let challengeResultModalBody = null;
    let challengeResultModalTitle = null;
    let challengeResultModalContinue = null;

    let speechRecognition = null;
    let finalTranscript = '';

    let challengeRoot = null;

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
        avatarIsSpeaking: false
    };

    function cacheDom() {
        challengeRoot = document.querySelector('.challenge');

        if (!challengeRoot) return false;

        messagesContainer = document.querySelector('.challenge__messages');
        composerForm = document.querySelector('.challenge__composer');
        textInput = document.querySelector('.challenge__input');
        sendButton = document.querySelector('.challenge__sendBtn');
        micButton = document.querySelector('.challenge__iconBtn');

        challengeLoader = document.getElementById('challenge-loader');

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

        if (!messagesContainer || !composerForm || !textInput || !sendButton) {
            return false;
        }

        state.challengeId = Number(challengeRoot.dataset.retoId || 0);
        state.skillId = Number(challengeRoot.dataset.habilidadId || 0);

        if (!state.challengeId || !state.skillId) {
            console.error('No se pudieron leer challengeId o skillId desde data attributes.');
            return false;
        }

        return true;
    }

    function init() {
        const ready = cacheDom();

        if (!ready) {
            console.error('La vista de reto aún no está lista o faltan elementos del DOM.');
            return;
        }

        showChallengeLoader();
        initSpeechRecognition();
        bindEvents();
        startChallenge();
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

        if (challengeResultModalContinue) {
            challengeResultModalContinue.addEventListener('click', handleChallengeResultContinue);
        }

        if (micButton) {
            micButton.addEventListener('click', function () {
                toggleSpeechRecognition();
            });
        }
    }

    // --------------------- MODAL ----------------------------------------
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
    // --------------------- FIN MODAL ------------------------------------

    // --------------------- LOADER ------------------------------------
    function showChallengeLoader() {
        if (!challengeLoader) return;
        challengeLoader.classList.add('is-visible');
        challengeLoader.setAttribute('aria-hidden', 'false');
    }

    function hideChallengeLoader() {
        if (!challengeLoader) return;
        challengeLoader.classList.remove('is-visible');
        challengeLoader.setAttribute('aria-hidden', 'true');
    }
    // --------------------- FIN LOADER ------------------------------------

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
        } catch (error) {
            console.error('Error al iniciar el reto:', error);
            renderSystemMessage('Ocurrió un error al iniciar el reto.');
            hideChallengeLoader();
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
            hideChallengeLoader();

        } catch (error) {
            removeTypingIndicator();
            console.error('Error en advance:', error);
            renderSystemMessage('No se pudo avanzar en el reto.');
            hideChallengeLoader();
        } finally {
            setLoading(false);
        }
    }

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
        if (!data) return;

        const hasProgress = !!data.progress;
        const hasCompletionModal = !!data.completionModal;

        if (hasProgress) {
            if (data.progress.challengeCompleted || data.progress.failed) {
                state.completed = true;
                textInput.disabled = true;
                sendButton.disabled = true;

                if (state.isListening) {
                    stopSpeechRecognition();
                }

                updateMicButtonState();
            }
        }

        if (hasCompletionModal) {
            state.completed = true;
            textInput.disabled = true;
            sendButton.disabled = true;

            if (state.isListening) {
                stopSpeechRecognition();
            }

            updateMicButtonState();
            renderChallengeResultModal(data.completionModal);
        }
    }

    function handleApiError(data) {
        hideChallengeLoader();
        removeTypingIndicator();
        console.error('Error API:', data);

        let message = 'Ocurrió un error inesperado.';

        if (data && data.error && data.error.message) {
            message = data.error.message;
        }

        renderSystemMessage(message);

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

    // --------------------- RECONOCIMIENTO DE VOZ ------------------------
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

    function canUseVoiceInput() {
        return (
            state.inputEnabled &&
            !state.completed &&
            !state.isLoading &&
            !state.avatarIsSpeaking
        );
    }

    function updateMicButtonState() {
        if (!micButton) return;

        const disabled = !state.speechRecognitionSupported || !canUseVoiceInput();

        micButton.disabled = disabled;

        micButton.classList.toggle('challenge__iconBtn--listening', state.isListening);
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