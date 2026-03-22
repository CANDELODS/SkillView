(function () {
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

    // Instancia del reconocimiento de voz del navegador.
    let speechRecognition = null;

    // Texto final acumulado cuando el usuario dicta por voz.
    let finalTranscript = '';

    // Elemento raíz del reto en el DOM.
    let challengeRoot = null;

    // Contenedor donde se muestra el puntaje dentro del modal.
    let challengeResultModalScore = null;

    // Estado global del flujo del reto en frontend.
    // Aquí se guarda información que el JS necesita para saber
    // en qué etapa está el reto, si el usuario puede escribir,
    // si el reto terminó, etc.
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

    // Método que cachea referencias a elementos del DOM.
    // Esto evita consultar el DOM muchas veces y centraliza la inicialización.
    function cacheDom() {
        // Busca el contenedor raíz del reto.
        challengeRoot = document.querySelector('.challenge');

        // Si no existe, no se puede continuar.
        if (!challengeRoot) return false;

        // Busca todos los elementos necesarios de la interfaz.
        messagesContainer = document.querySelector('.challenge__messages');
        composerForm = document.querySelector('.challenge__composer');
        textInput = document.querySelector('.challenge__input');
        sendButton = document.querySelector('.challenge__sendBtn');
        micButton = document.querySelector('.challenge__iconBtn');

        // Busca el loader.
        challengeLoader = document.getElementById('challenge-loader');

        // Busca el modal de resultado del reto.
        challengeResultModal = document.getElementById('sv-challenge-result-modal');

        // Busca el body del modal donde se insertan los mensajes finales.
        challengeResultModalBody = challengeResultModal
            ? challengeResultModal.querySelector('[data-sv-challenge-result-body]')
            : null;

        // Busca el título del modal.
        challengeResultModalTitle = challengeResultModal
            ? challengeResultModal.querySelector('#sv-challenge-result-modal-title')
            : null;

        // Busca el botón de continuar del modal.
        challengeResultModalContinue = challengeResultModal
            ? challengeResultModal.querySelector('[data-sv-challenge-result-continue]')
            : null;

        // Busca el contenedor del puntaje dentro del modal.
        challengeResultModalScore = challengeResultModal
            ? challengeResultModal.querySelector('[data-sv-challenge-result-score]')
            : null;

        // Si faltan elementos esenciales, no se puede inicializar correctamente.
        if (!messagesContainer || !composerForm || !textInput || !sendButton) {
            return false;
        }

        // Lee los ids del reto y de la habilidad desde atributos data-* en el HTML.
        state.challengeId = Number(challengeRoot.dataset.retoId || 0);
        state.skillId = Number(challengeRoot.dataset.habilidadId || 0);

        // Si no se pudieron leer, se detiene la inicialización.
        if (!state.challengeId || !state.skillId) {
            console.error('No se pudieron leer challengeId o skillId desde data attributes.');
            return false;
        }

        return true;
    }

    // Método principal de inicialización del archivo.
    function init() {
        const ready = cacheDom();

        // Si el DOM no está listo o faltan elementos, se detiene.
        if (!ready) {
            console.error('La vista de reto aún no está lista o faltan elementos del DOM.');
            return;
        }

        // Muestra el loader mientras arranca el flujo.
        showChallengeLoader();

        // Inicializa reconocimiento de voz si el navegador lo soporta.
        initSpeechRecognition();

        // Registra eventos del formulario, modal y micrófono.
        bindEvents();

        // Inicia el reto llamando al backend.
        startChallenge();
    }

    // Si el DOM aún está cargando, espera DOMContentLoaded.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // Si el DOM ya está listo, inicializa directamente.
        init();
    }

    // Registra los eventos de la interfaz.
    function bindEvents() {
        // Evento submit del formulario del composer.
        composerForm.addEventListener('submit', onSubmit);

        // Evento input reservado para posibles mejoras futuras.
        textInput.addEventListener('input', () => {
            // reservado para mejoras futuras
        });

        // Evento del botón continuar del modal final.
        if (challengeResultModalContinue) {
            challengeResultModalContinue.addEventListener('click', handleChallengeResultContinue);
        }

        // Evento del botón del micrófono.
        if (micButton) {
            micButton.addEventListener('click', function () {
                toggleSpeechRecognition();
            });
        }
    }

    // --------------------- MODAL ----------------------------------------

    // Abre el modal final del reto.
    function openChallengeResultModal() {
        if (!challengeResultModal) return;

        // Muestra el modal.
        challengeResultModal.classList.add('is-open');
        challengeResultModal.setAttribute('aria-hidden', 'false');

        // Si existe helper global de lockScroll, lo usa.
        if (window.SV && typeof window.SV.lockScroll === 'function') {
            window.SV.lockScroll();
        } else {
            // Si no, aplica clase al body para bloquear scroll.
            document.body.classList.add('no-scroll');
        }
    }

    // Cierra el modal final del reto.
    function closeChallengeResultModal() {
        if (!challengeResultModal) return;

        // Oculta el modal.
        challengeResultModal.classList.remove('is-open');
        challengeResultModal.setAttribute('aria-hidden', 'true');

        // Libera scroll usando helper global si existe.
        if (window.SV && typeof window.SV.unlockScroll === 'function') {
            window.SV.unlockScroll();
        } else {
            // Si no existe helper, elimina clase manual.
            document.body.classList.remove('no-scroll');
        }
    }

    // Renderiza el contenido del modal final.
    // Este modal puede ser de éxito o de error.
    function renderChallengeResultModal(modalData) {
        if (!challengeResultModal || !challengeResultModalBody) return;

        // Limpia el contenido previo del modal.
        challengeResultModalBody.innerHTML = '';

        // Título del modal.
        if (challengeResultModalTitle) {
            challengeResultModalTitle.textContent = modalData && modalData.title
                ? modalData.title
                : 'Resultado del reto';
        }

        // Texto del botón principal del modal.
        if (challengeResultModalContinue) {
            challengeResultModalContinue.textContent = modalData && modalData.buttonText
                ? modalData.buttonText
                : 'Continuar';
        }

        // Manejo del puntaje mostrado en el modal.
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

        // Mensajes del modal.
        const messages = modalData && Array.isArray(modalData.messages)
            ? modalData.messages
            : [];

        // Inserta cada mensaje como un párrafo dentro del body del modal.
        messages.forEach(function (msg) {
            const p = document.createElement('p');
            p.className = 'sv-challenge-result-modal__text';
            p.textContent = msg.text || '';
            challengeResultModalBody.appendChild(p);
        });

        // Guarda la URL de redirección que se usará al pulsar continuar.
        state.modalRedirectTo = modalData && modalData.redirectTo
            ? modalData.redirectTo
            : '/retos';

        // Finalmente abre el modal.
        openChallengeResultModal();
    }

    // Maneja el clic del botón "Continuar" del modal.
    function handleChallengeResultContinue() {
        const redirectTo = state.modalRedirectTo || '/retos';
        closeChallengeResultModal();
        window.location.href = redirectTo;
    }

    // --------------------- FIN MODAL ------------------------------------

    // --------------------- LOADER ------------------------------------

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

    // --------------------- FIN LOADER ------------------------------------

    // Inicia el reto pidiendo al backend el flujo inicial.
    async function startChallenge() {
        // Marca el frontend como cargando.
        setLoading(true);

        // Limpia mensajes anteriores.
        clearMessages();

        try {
            // Llama al endpoint que inicia el reto.
            const response = await fetch('/api/retos/start', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                // Envía challengeId y skillId al backend.
                body: JSON.stringify({
                    challengeId: state.challengeId,
                    skillId: state.skillId
                })
            });

            // Convierte la respuesta a JSON.
            const data = await response.json();
            console.log('Respuesta /api/retos/start:', data);

            // Si el backend respondió error, se maneja como error API.
            if (!response.ok || !data.ok) {
                handleApiError(data);
                return;
            }

            // Aplica el estado de sesión enviado por backend.
            applySessionState(data.session);

            // Renderiza mensajes iniciales.
            renderMessages(data.messages || []);

            // Aplica cambios de UI.
            applyUiState(data.ui || {});
        } catch (error) {
            // Manejo de error de red o fallo inesperado.
            console.error('Error al iniciar el reto:', error);
            renderSystemMessage('Ocurrió un error al iniciar el reto.');
            hideChallengeLoader();
            return;
        } finally {
            // Siempre quita el estado de loading.
            setLoading(false);
        }

        // Si el backend indicó que el siguiente paso es "advance"
        // y aún no se requiere respuesta del usuario,
        // el frontend lo ejecuta automáticamente.
        if (state.nextExpectedAction === 'advance' && !state.requiresUserResponse) {
            await sendAdvanceTurn();
        }
    }

    // Envía al backend la acción "advance".
    // Se usa para pasar del intro a la consigna principal del reto.
    async function sendAdvanceTurn() {
        // No hace nada si ya está cargando o si el reto terminó.
        if (state.isLoading || state.completed) return;

        setLoading(true);

        // Muestra indicador visual de "pensando".
        renderTypingIndicator();

        try {
            // Llama al endpoint de turn con acción advance.
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

            // Se agrega un delay pequeño para hacer la transición más natural visualmente.
            await delay(500);

            // Se elimina el indicador de typing.
            removeTypingIndicator();

            // Si la respuesta fue error, se maneja.
            if (!response.ok || !data.ok) {
                handleApiError(data);
                return;
            }

            // Se actualiza el estado del flujo.
            applySessionState(data.session);

            // Se renderizan mensajes nuevos.
            renderMessages(data.messages || []);

            // Se aplica el estado visual.
            applyUiState(data.ui || {});

            // Se verifica si el reto terminó o si debe mostrar modal.
            handleCompletion(data);

            // Oculta el loader principal.
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

    // Envía al backend la respuesta del usuario.
    async function sendReplyTurn(userMessage) {
        // Si está cargando o ya terminó, no envía nada.
        if (state.isLoading || state.completed) return;

        setLoading(true);

        // Muestra indicador de typing del asistente.
        renderTypingIndicator();

        try {
            // Llama al backend con la respuesta del usuario.
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

            // Delay visual para suavizar transición.
            await delay(500);

            // Quita indicador de typing.
            removeTypingIndicator();

            // Si la respuesta fue error, se maneja.
            if (!response.ok || !data.ok) {
                handleApiError(data);
                return;
            }

            // Se actualiza estado del flujo.
            applySessionState(data.session);

            // Se renderizan mensajes que llegan desde backend.
            renderMessages(data.messages || []);

            // Se actualiza la UI.
            applyUiState(data.ui || {});

            // Se revisa si el reto terminó o requiere modal.
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
        // Evita que el formulario recargue la página.
        event.preventDefault();

        // Solo se permite enviar si:
        // - el input está habilitado,
        // - el flujo espera respuesta,
        // - no está cargando.
        if (!state.inputEnabled || !state.requiresUserResponse || state.isLoading) return;

        const message = textInput.value.trim();

        // Si el input está vacío, no envía nada.
        if (!message) return;

        // Si el usuario estaba dictando por voz, se detiene antes de enviar.
        if (state.isListening) {
            stopSpeechRecognition();
        }

        // Limpia el input visualmente.
        textInput.value = '';

        // Envía la respuesta al backend.
        await sendReplyTurn(message);
    }

    // Renderiza una lista de mensajes dentro del chat.
    function renderMessages(messages) {
        if (!Array.isArray(messages) || messages.length === 0) return;

        messages.forEach(msg => {
            const messageElement = buildMessageElement(msg);
            messagesContainer.appendChild(messageElement);
        });

        scrollMessagesToBottom();
    }

    // Construye el elemento DOM correcto según el role del mensaje.
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

    // Construye un mensaje visual de sistema.
    // Se usa para errores o avisos simples.
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

    // Hace scroll al fondo del contenedor de mensajes.
    function scrollMessagesToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Renderiza el indicador temporal de "el asistente está pensando..."
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

    // Elimina el indicador de typing si existe.
    function removeTypingIndicator() {
        const existing = messagesContainer.querySelector('[data-typing-indicator="true"]');
        if (existing) {
            existing.remove();
        }
    }

    // Helper para generar una espera artificial.
    function delay(ms) {
        return new Promise(function (resolve) {
            setTimeout(resolve, ms);
        });
    }

    // Aplica al estado frontend los datos de sesión que devuelve el backend.
    function applySessionState(session) {
        if (!session) return;

        state.currentStage = session.currentStage ?? state.currentStage;
        state.nextExpectedAction = session.nextExpectedAction ?? state.nextExpectedAction;
        state.inputEnabled = Boolean(session.inputEnabled);
        state.requiresUserResponse = Boolean(session.requiresUserResponse);
        state.completed = Boolean(session.completed);
    }

    // Aplica cambios visuales a la UI según lo que indique el backend.
    function applyUiState(ui) {
        const placeholder = ui.composerPlaceholder || 'Escribe tu respuesta...';
        const focusInput = Boolean(ui.focusInput);

        // Actualiza placeholder y estados disabled.
        textInput.placeholder = placeholder;
        textInput.disabled = !state.inputEnabled || state.completed;
        sendButton.disabled = !state.inputEnabled || state.completed;

        if (micButton) {
            micButton.disabled = !state.inputEnabled || state.completed;
        }

        // Si el backend pide foco y el input está activo, se posiciona el cursor.
        if (focusInput && state.inputEnabled && !state.completed) {
            textInput.focus();
        }

        updateMicButtonState();
    }

    // Marca o desmarca el frontend como "loading".
    function setLoading(isLoading) {
        state.isLoading = isLoading;

        const shouldDisable = isLoading || !state.inputEnabled || state.completed;

        // Mientras está cargando, se bloquea input y botones.
        textInput.disabled = shouldDisable;
        sendButton.disabled = shouldDisable;

        if (micButton) {
            micButton.disabled = shouldDisable;
        }

        // Placeholder temporal durante la espera.
        if (isLoading) {
            textInput.placeholder = 'Espera la respuesta del asistente...';
        }

        updateMicButtonState();
    }

    // Procesa la finalización del reto si el backend lo indica.
    function handleCompletion(data) {
        if (!data) return;

        const hasProgress = !!data.progress;
        const hasCompletionModal = !!data.completionModal;

        // Si vino progress, se revisa si el reto terminó o falló.
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

        // Si vino completionModal, se muestra el modal final.
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

    // Maneja errores que vienen del backend.
    function handleApiError(data) {
        hideChallengeLoader();
        removeTypingIndicator();
        console.error('Error API:', data);

        let message = 'Ocurrió un error inesperado.';

        // Si el backend envió un mensaje de error, se usa ese.
        if (data && data.error && data.error.message) {
            message = data.error.message;
        }

        renderSystemMessage(message);

        // Si viene una redirección, se ejecuta después de una pequeña espera.
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

    // Inicializa el reconocimiento de voz si el navegador lo soporta.
    function initSpeechRecognition() {
        const SpeechRecognitionAPI =
            window.SpeechRecognition || window.webkitSpeechRecognition;

        // Si el navegador no soporta reconocimiento, se desactiva el botón.
        if (!SpeechRecognitionAPI) {
            state.speechRecognitionSupported = false;

            if (micButton) {
                micButton.disabled = true;
                micButton.title = 'Tu navegador no soporta reconocimiento de voz';
            }

            return;
        }

        state.speechRecognitionSupported = true;

        // Crea la instancia.
        speechRecognition = new SpeechRecognitionAPI();

        // Idioma configurado.
        speechRecognition.lang = 'es-CO';

        // Permite hablar de forma continua.
        speechRecognition.continuous = true;

        // Permite resultados intermedios mientras el usuario dicta.
        speechRecognition.interimResults = true;

        // Cantidad máxima de alternativas por resultado.
        speechRecognition.maxAlternatives = 1;

        // Evento cuando empieza a escuchar.
        speechRecognition.onstart = function () {
            state.isListening = true;
            updateMicButtonState();

            if (textInput) {
                textInput.placeholder = 'Escuchando... habla ahora';
            }
        };

        // Evento cuando llegan resultados del dictado.
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

            // Actualiza el input en vivo.
            if (textInput) {
                textInput.value = (accumulatedFinal + interimTranscript).trim();
            }

            // Guarda lo confirmado como final.
            finalTranscript = accumulatedFinal;
        };

        // Evento cuando ocurre un error en reconocimiento.
        speechRecognition.onerror = function (event) {
            console.error('Error reconocimiento de voz:', event.error);
            state.isListening = false;
            updateMicButtonState();

            if (textInput) {
                textInput.placeholder = 'No se pudo usar el micrófono. Intenta de nuevo.';
            }
        };

        // Evento cuando el reconocimiento se detiene.
        speechRecognition.onend = function () {
            state.isListening = false;
            updateMicButtonState();

            if (textInput && !state.completed && !state.isLoading) {
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

    // Inicia el reconocimiento de voz.
    function startSpeechRecognition() {
        if (!speechRecognition || state.isListening) return;

        // Si ya había texto en el input, lo conserva y continúa dictando encima.
        finalTranscript = textInput
            ? textInput.value.trim() + (textInput.value.trim() ? ' ' : '')
            : '';

        try {
            speechRecognition.start();
        } catch (error) {
            console.error('No se pudo iniciar el reconocimiento:', error);
        }
    }

    // Detiene el reconocimiento de voz.
    function stopSpeechRecognition() {
        if (!speechRecognition || !state.isListening) return;

        speechRecognition.stop();
    }

    // Verifica si el micrófono puede usarse en este momento.
    function canUseVoiceInput() {
        return (
            state.inputEnabled &&
            !state.completed &&
            !state.isLoading &&
            !state.avatarIsSpeaking
        );
    }

    // Actualiza visual y funcionalmente el botón del micrófono.
    function updateMicButtonState() {
        if (!micButton) return;

        const disabled = !state.speechRecognitionSupported || !canUseVoiceInput();

        micButton.disabled = disabled;

        // Marca visualmente si está escuchando.
        micButton.classList.toggle('challenge__iconBtn--listening', state.isListening);
        micButton.setAttribute('aria-pressed', state.isListening ? 'true' : 'false');

        // Actualiza tooltip del botón según el estado actual.
        if (state.isListening) {
            micButton.title = 'Detener grabación';
        } else if (disabled) {
            micButton.title = 'Micrófono no disponible en este momento';
        } else {
            micButton.title = 'Hablar';
        }
    }
})();