(function () {
    // Contenedor donde se renderizan todos los mensajes del chat.
    let messagesContainer = null;

    // Formulario del composer (input + botón de envío).
    let composerForm = null;

    // Input de texto donde el usuario escribe la respuesta.
    let textInput = null;

    // Botón para enviar la respuesta.
    let sendButton = null;

    // Botón del micrófono para usar reconocimiento de voz.
    let micButton = null;

    // Loader visual que se muestra mientras la lección inicia o carga.
    let lessonLoader = null;

    // Referencias al modal final de resultado de la lección.
    let lessonResultModal = null;
    let lessonResultModalBody = null;
    let lessonResultModalTitle = null;
    let lessonResultModalContinue = null;

    // Instancia de reconocimiento de voz del navegador.
    let speechRecognition = null;

    // Texto final acumulado cuando el usuario dicta por voz.
    let finalTranscript = '';

    // Elemento raíz de la vista de lección.
    let lessonRoot = null;

    // Estado global del flujo de la lección en frontend.
    // Aquí se guarda la información mínima necesaria para controlar:
    // - la etapa actual,
    // - si se puede responder,
    // - si ya terminó,
    // - si hay loading,
    // - y si el micrófono está activo.
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

    // Busca y cachea todos los elementos necesarios del DOM.
    function cacheDom() {
        // Busca el contenedor principal de la lección.
        lessonRoot = document.querySelector('.lesson');

        // Si no existe, la vista aún no está lista o el script no corresponde a esta página.
        if (!lessonRoot) return false;

        // Busca elementos del chat.
        messagesContainer = document.querySelector('.lesson__messages');
        composerForm = document.querySelector('.lesson__composer');
        textInput = document.querySelector('.lesson__input');
        sendButton = document.querySelector('.lesson__sendBtn');
        micButton = document.querySelector('.lesson__iconBtn');

        // Busca el loader.
        lessonLoader = document.getElementById('lesson-loader');

        // Busca el modal final de la lección.
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

        // Si faltan elementos esenciales, no se puede continuar.
        if (!messagesContainer || !composerForm || !textInput || !sendButton) {
            return false;
        }

        // Lee los ids de la lección y la habilidad desde atributos data-* de la vista.
        state.lessonId = Number(lessonRoot.dataset.leccionId || 0);
        state.skillId = Number(lessonRoot.dataset.habilidadId || 0);

        // Si no se pudieron leer correctamente, se registra error.
        if (!state.lessonId || !state.skillId) {
            console.error('No se pudieron leer leccionId o habilidadId desde data attributes.');
            return false;
        }

        return true;
    }

    // Método principal de inicialización.
    function init() {
        const ready = cacheDom();

        // Si no se pudo preparar el DOM, se detiene la ejecución.
        if (!ready) {
            console.error('La vista de lección aún no está lista o faltan elementos del DOM.');
            return;
        }

        // Muestra el loader inicial.
        showLessonLoader();

        // Inicializa reconocimiento de voz si el navegador lo soporta.
        initSpeechRecognition();

        // Registra todos los eventos necesarios.
        bindEvents();

        // Inicia la lección llamando al backend.
        startLesson();
    }

    // Si el DOM aún no está listo, espera a DOMContentLoaded.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // Si ya está listo, inicializa directamente.
        init();
    }

    // Registra los listeners de la interfaz.
    function bindEvents() {
        // Evento submit del formulario del composer.
        composerForm.addEventListener('submit', onSubmit);

        // Evento input reservado para mejoras futuras.
        textInput.addEventListener('input', () => {
            // reservado para mejoras futuras
        });

        // Evento del botón de continuar del modal.
        if (lessonResultModalContinue) {
            lessonResultModalContinue.addEventListener('click', handleLessonResultContinue);
        }

        // Evento del botón de micrófono.
        if (micButton) {
            micButton.addEventListener('click', function () {
                toggleSpeechRecognition();
            });
        }
    }

    // --------------------- MODAL ----------------------------------------

    // Abre el modal final de la lección.
    function openLessonResultModal() {
        if (!lessonResultModal) return;

        lessonResultModal.classList.add('is-open');
        lessonResultModal.setAttribute('aria-hidden', 'false');

        // Si existe helper global para bloquear scroll, lo usa.
        if (window.SV && typeof window.SV.lockScroll === 'function') {
            window.SV.lockScroll();
        } else {
            // Fallback manual.
            document.body.classList.add('no-scroll');
        }
    }

    // Cierra el modal final de la lección.
    function closeLessonResultModal() {
        if (!lessonResultModal) return;

        lessonResultModal.classList.remove('is-open');
        lessonResultModal.setAttribute('aria-hidden', 'true');

        // Si existe helper global para liberar scroll, lo usa.
        if (window.SV && typeof window.SV.unlockScroll === 'function') {
            window.SV.unlockScroll();
        } else {
            // Fallback manual.
            document.body.classList.remove('no-scroll');
        }
    }

    // Renderiza el contenido del modal final.
    function renderLessonResultModal(modalData) {
        if (!lessonResultModal || !lessonResultModalBody) return;

        // Limpia contenido anterior.
        lessonResultModalBody.innerHTML = '';

        // Inserta el título del modal.
        if (lessonResultModalTitle) {
            lessonResultModalTitle.textContent = modalData && modalData.title
                ? modalData.title
                : 'Resultado de la lección';
        }

        // Inserta el texto del botón del modal.
        if (lessonResultModalContinue) {
            lessonResultModalContinue.textContent = modalData && modalData.buttonText
                ? modalData.buttonText
                : 'Continuar';
        }

        // Obtiene los mensajes que se mostrarán dentro del modal.
        const messages = modalData && Array.isArray(modalData.messages)
            ? modalData.messages
            : [];

        // Inserta cada mensaje como un párrafo dentro del body del modal.
        messages.forEach(function (msg) {
            const p = document.createElement('p');
            p.className = 'sv-lesson-result-modal__text';
            p.textContent = msg.text || '';
            lessonResultModalBody.appendChild(p);
        });

        // Guarda la ruta de redirección posterior.
        state.modalRedirectTo = modalData && modalData.redirectTo
            ? modalData.redirectTo
            : '/aprendizaje';

        // Finalmente abre el modal.
        openLessonResultModal();
    }

    // Maneja el clic del botón "Continuar" del modal.
    function handleLessonResultContinue() {
        const redirectTo = state.modalRedirectTo || '/aprendizaje';
        closeLessonResultModal();
        window.location.href = redirectTo;
    }

    // --------------------- FIN MODAL ------------------------------------

    // --------------------- LOADER ------------------------------------

    // Muestra el loader.
    function showLessonLoader() {
        if (!lessonLoader) return;
        lessonLoader.classList.add('is-visible');
        lessonLoader.setAttribute('aria-hidden', 'false');
    }

    // Oculta el loader.
    function hideLessonLoader() {
        if (!lessonLoader) return;
        lessonLoader.classList.remove('is-visible');
        lessonLoader.setAttribute('aria-hidden', 'true');
    }

    // --------------------- FIN LOADER ------------------------------------

    // Inicia la lección pidiendo al backend el flujo inicial.
    async function startLesson() {
        setLoading(true);
        clearMessages();

        try {
            // Llama al endpoint que inicia la lección.
            const response = await fetch('/api/lecciones/start', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                // Envía lessonId y skillId.
                body: JSON.stringify({
                    lessonId: state.lessonId,
                    skillId: state.skillId
                })
            });

            const data = await response.json();
            console.log('Respuesta /api/lecciones/start:', data);

            // Si hay error HTTP o de backend, se maneja.
            if (!response.ok || !data.ok) {
                handleApiError(data);
                return;
            }

            // Aplica sesión, renderiza mensajes y actualiza UI.
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

        // Si el backend indica que debe avanzar automáticamente,
        // se envía el turno "advance".
        if (state.nextExpectedAction === 'advance' && !state.requiresUserResponse) {
            await sendAdvanceTurn();
        }
    }

    // Envía al backend la acción "advance".
    async function sendAdvanceTurn() {
        // Si ya terminó o está cargando, no hace nada.
        if (state.isLoading || state.completed) return;

        setLoading(true);
        renderTypingIndicator();

        try {
            // Llama al endpoint de turn con acción advance.
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

            // Delay visual para suavizar transición.
            await delay(500);

            removeTypingIndicator();

            if (!response.ok || !data.ok) {
                handleApiError(data);
                return;
            }

            // Actualiza sesión, mensajes y UI.
            applySessionState(data.session);
            renderMessages(data.messages || []);
            applyUiState(data.ui || {});
            handleCompletion(data);

            // Cuando la lección ya arrancó, oculta el loader principal.
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

    // Envía al backend una respuesta escrita por el usuario.
    async function sendReplyTurn(userMessage) {
        if (state.isLoading || state.completed) return;

        setLoading(true);
        renderTypingIndicator();

        try {
            // Llama al endpoint de turn con acción reply.
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

            // Delay visual para suavizar transición.
            await delay(500);

            removeTypingIndicator();

            if (!response.ok || !data.ok) {
                handleApiError(data);
                return;
            }

            // Aplica sesión, renderiza mensajes y actualiza UI.
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

    // Maneja el submit del formulario del composer.
    async function onSubmit(event) {
        // Evita recargar la página.
        event.preventDefault();

        // Solo se permite enviar si:
        // - input está habilitado,
        // - se espera respuesta del usuario,
        // - no hay loading.
        if (!state.inputEnabled || !state.requiresUserResponse || state.isLoading) return;

        const message = textInput.value.trim();

        // Si el input está vacío, no se envía.
        if (!message) return;

        // Si se estaba dictando por voz, se detiene primero.
        if (state.isListening) {
            stopSpeechRecognition();
        }

        // Limpia el input antes de enviar.
        textInput.value = '';

        // Envía la respuesta.
        await sendReplyTurn(message);
    }

    // Renderiza un arreglo de mensajes en el chat.
    function renderMessages(messages) {
        if (!Array.isArray(messages) || messages.length === 0) return;

        messages.forEach(msg => {
            const messageElement = buildMessageElement(msg);
            messagesContainer.appendChild(messageElement);
        });

        scrollMessagesToBottom();
    }

    // Decide qué tipo de mensaje construir según el role.
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

    // Construye un mensaje visual del usuario.
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

    // Construye un mensaje visual de sistema.
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

    // Inserta un mensaje de sistema directamente en el chat.
    function renderSystemMessage(text) {
        const el = buildSystemMessageElement(text);
        messagesContainer.appendChild(el);
        scrollMessagesToBottom();
    }

    // Limpia todos los mensajes del chat.
    function clearMessages() {
        messagesContainer.innerHTML = '';
    }

    // Baja el scroll del chat hasta el final.
    function scrollMessagesToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Muestra el indicador temporal de typing del asistente.
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

    // Elimina el indicador de typing si existe.
    function removeTypingIndicator() {
        const existing = messagesContainer.querySelector('[data-typing-indicator="true"]');
        if (existing) {
            existing.remove();
        }
    }

    // Helper para pausar la ejecución unos milisegundos.
    function delay(ms) {
        return new Promise(function (resolve) {
            setTimeout(resolve, ms);
        });
    }

    // Aplica el estado de sesión que devuelve el backend.
    function applySessionState(session) {
        if (!session) return;

        state.currentStage = session.currentStage ?? state.currentStage;
        state.nextExpectedAction = session.nextExpectedAction ?? state.nextExpectedAction;
        state.inputEnabled = Boolean(session.inputEnabled);
        state.requiresUserResponse = Boolean(session.requiresUserResponse);
        state.completed = Boolean(session.completed);
    }

    // Aplica cambios visuales a la UI según la respuesta del backend.
    function applyUiState(ui) {
        const placeholder = ui.composerPlaceholder || 'Escribe tu respuesta...';
        const focusInput = Boolean(ui.focusInput);

        textInput.placeholder = placeholder;
        textInput.disabled = !state.inputEnabled || state.completed;
        sendButton.disabled = !state.inputEnabled || state.completed;

        if (micButton) {
            micButton.disabled = !state.inputEnabled || state.completed;
        }

        // Si se pide foco y el input está activo, se posiciona cursor.
        if (focusInput && state.inputEnabled && !state.completed) {
            textInput.focus();
        }

        updateMicButtonState();
    }

    // Marca o desmarca loading en frontend.
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

    // Verifica si la lección terminó y si debe abrir modal final.
    function handleCompletion(data) {
        if (!data || !data.progress) return;

        // Si la lección ya fue completada o falló, bloquea el input.
        if (data.progress.lessonCompleted || data.progress.failed) {
            state.completed = true;
            textInput.disabled = true;
            sendButton.disabled = true;

            if (state.isListening) {
                stopSpeechRecognition();
            }

            updateMicButtonState();
        }

        // Si hay modal final, lo muestra.
        if (data.completionModal) {
            renderLessonResultModal(data.completionModal);
        }
    }

    // Manejo centralizado de errores de backend.
    function handleApiError(data) {
        hideLessonLoader();
        removeTypingIndicator();
        console.error('Error API:', data);

        let message = 'Ocurrió un error inesperado.';

        if (data && data.error && data.error.message) {
            message = data.error.message;
        }

        renderSystemMessage(message);

        // Si backend envió redirectTo, se redirige luego de una breve pausa.
        if (data && data.redirectTo) {
            setTimeout(function () {
                window.location.href = data.redirectTo;
            }, 1500);
        }
    }

    //Iniciarlizar reconocimiento de voz
    function initSpeechRecognition() {
        // Toma la API compatible según el navegador.
        const SpeechRecognitionAPI =
            window.SpeechRecognition || window.webkitSpeechRecognition;

        // Si no existe soporte, desactiva el micrófono.
        if (!SpeechRecognitionAPI) {
            state.speechRecognitionSupported = false;

            if (micButton) {
                micButton.disabled = true;
                micButton.title = 'Tu navegador no soporta reconocimiento de voz';
            }

            return;
        }

        state.speechRecognitionSupported = true;

        // Crea la instancia del reconocimiento.
        speechRecognition = new SpeechRecognitionAPI();
        speechRecognition.lang = 'es-CO';
        speechRecognition.continuous = true;
        speechRecognition.interimResults = true;
        speechRecognition.maxAlternatives = 1;

        // Cuando empieza a escuchar.
        speechRecognition.onstart = function () {
            state.isListening = true;
            updateMicButtonState();

            if (textInput) {
                textInput.placeholder = 'Escuchando... habla ahora';
            }
        };

        // Cuando llegan resultados de dictado.
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

            // Actualiza el input en vivo con lo que se reconoce.
            if (textInput) {
                textInput.value = (accumulatedFinal + interimTranscript).trim();
            }

            // Guarda lo ya confirmado como final.
            finalTranscript = accumulatedFinal;
        };

        // Si ocurre error en el reconocimiento.
        speechRecognition.onerror = function (event) {
            console.error('Error reconocimiento de voz:', event.error);
            state.isListening = false;
            updateMicButtonState();

            if (textInput) {
                textInput.placeholder = 'No se pudo usar el micrófono. Intenta de nuevo.';
            }
        };

        // Cuando el reconocimiento termina.
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

    // Inicia la grabación de voz.
    function startSpeechRecognition() {
        if (!speechRecognition || state.isListening) return;

        // Si ya había texto en el input, lo conserva y sigue dictando encima.
        finalTranscript = textInput ? textInput.value.trim() + (textInput.value.trim() ? ' ' : '') : '';

        try {
            speechRecognition.start();
        } catch (error) {
            console.error('No se pudo iniciar el reconocimiento:', error);
        }
    }

    // Detiene la grabación de voz.
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