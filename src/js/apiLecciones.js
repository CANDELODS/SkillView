(function () {
    // -------------------- SELECTORES BASE --------------------
    const messagesContainer = document.querySelector('.lesson__messages');
    const composerForm = document.querySelector('.lesson__composer');
    const textInput = document.querySelector('.lesson__input');
    const sendButton = document.querySelector('.lesson__sendBtn');
    const micButton = document.querySelector('.lesson__iconBtn');

    // Si no estamos en la vista de lección, no hacemos nada
    if (!messagesContainer || !composerForm || !textInput || !sendButton) return;

    // Validamos que las variables globales existan
    if (typeof leccionId === 'undefined' || typeof habilidadId === 'undefined') {
        console.error('leccionId o habilidadId no están definidos en la vista.');
        return;
    }

    // -------------------- ESTADO LOCAL DEL FRONTEND --------------------
    const state = {
        lessonId: Number(leccionId),
        skillId: Number(habilidadId),
        currentStage: null,
        nextExpectedAction: null,
        inputEnabled: false,
        requiresUserResponse: false,
        completed: false,
        isLoading: false
    };

    // -------------------- INIT --------------------
    document.addEventListener('DOMContentLoaded', init);

    function init() {
        bindEvents();
        startLesson();
    }

    function bindEvents() {
        composerForm.addEventListener('submit', onSubmit);

        // Enter ya lo maneja submit, pero por claridad dejamos el comportamiento natural
        textInput.addEventListener('input', () => {
            // Si quieres luego puedes poner contador, validación visual, etc.
        });
    }

    // -------------------- API: START --------------------
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
            return;
        } finally {
            setLoading(false);
        }

        // Hacemos el advance automático DESPUÉS de liberar loading
        if (state.nextExpectedAction === 'advance' && !state.requiresUserResponse) {
            await sendAdvanceTurn();
        }
    }

    // -------------------- API: TURN / ADVANCE --------------------
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
            await delay(500); // SOLO PARA PRUEBAS VISUALES

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
            console.error('Error en advance:', error);
            renderSystemMessage('No se pudo avanzar en la lección.');
        } finally {
            setLoading(false);
        }
    }

    // -------------------- API: TURN / REPLY --------------------
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
            await delay(500); // SOLO PARA PRUEBAS VISUALES

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

    // -------------------- SUBMIT DEL FORM --------------------
    async function onSubmit(event) {
        event.preventDefault();

        if (!state.inputEnabled || !state.requiresUserResponse || state.isLoading) return;

        const message = textInput.value.trim();
        if (!message) return;

        textInput.value = '';
        await sendReplyTurn(message);
    }

    // -------------------- RENDER DE MENSAJES --------------------
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

        // Si quieres diferenciar system luego, aquí puedes hacerlo distinto
        if (role === 'user') {
            return buildUserMessage(text);
        }

        if (role === 'system') {
            return buildSystemMessageElement(text);
        }

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

    // -------------------- TYPING INDICATOR --------------------
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

    // -------------------- ESTADO DE UI --------------------
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
    }

    // -------------------- COMPLETADO --------------------
    function handleCompletion(data) {
        if (!data || !data.progress) return;

        // Caso lección completada
        if (data.progress.lessonCompleted) {
            state.completed = true;
            textInput.disabled = true;
            sendButton.disabled = true;
            if (micButton) micButton.disabled = true;
        }

        // Caso lección fallida
        if (data.progress.failed) {
            state.completed = true;
            textInput.disabled = true;
            sendButton.disabled = true;
            if (micButton) micButton.disabled = true;
        }

        // Redirección para ambos casos
        if (data.progress.redirectTo) {
            setTimeout(() => {
                window.location.href = data.progress.redirectTo;
            }, 5000);
        }
    }

    // -------------------- ERRORES API --------------------
    function handleApiError(data) {
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
})();