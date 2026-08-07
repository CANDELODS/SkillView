<?php

namespace Controllers;

use Classes\ChallengeAIService;
use Model\HabilidadesBlandas;
use Model\Logros;
use Model\Retos;
use Model\usuarios_habilidades;
use Model\usuarios_retos;
use MVC\Router;

// Controlador responsable de toda la lógica del detalle del reto con IA.
// Aquí se maneja:
// - la vista individual del reto,
// - el inicio del flujo,
// - los turnos de interacción,
// - la persistencia del resultado,
// - los helpers auxiliares.
class RetoController
{
    // Porcentaje mínimo de puntos que debe alcanzar el usuario para aprobar un reto.
    private const MIN_PASSING_RATIO = 0.70;

    // Cantidad máxima de oportunidades disponibles en cada reto.
    private const MAX_CHALLENGE_ATTEMPTS = 3;

    /*
     * Condiciones mínimas aplicadas antes de consultar OpenAI.
     * Estas reglas evitan consumir la API con respuestas vacías
     * o claramente insuficientes.
     */
    private const MIN_ANSWER_LENGTH = 12;
    private const MIN_ANSWER_WORDS = 3;

    // Límites admitidos para la proporción de puntaje devuelta por IA.
    private const MIN_SCORE_RATIO = 0.0;
    private const MAX_SCORE_RATIO = 1.0;

    /*
     * Respuestas demasiado genéricas que no contienen información
     * suficiente para evaluar el desempeño del usuario.
     */
    private const GENERIC_INVALID_ANSWERS = [
        'si',
        'sí',
        'no',
        'ok',
        'bien',
        'normal',
        'pensaria mejor',
        'pensaría mejor',
        'lo haria bien',
        'lo haría bien'
    ];

    // Método que renderiza la vista principal de un reto individual.
    // Este método NO ejecuta la IA todavía.
    // Solo prepara la vista /retos/reto?id=...
    public static function reto(
        Router $router
    ): void {
        // Si el usuario no está autenticado, se redirige al inicio.
        if (!isAuth()) {
            header('Location: /');
            exit;
        }

        /*
         * Todas las comprobaciones del reto dependen del identificador
         * del usuario. Una sesión incompleta no debe continuar hacia
         * consultas de retos o progreso.
         */
        $idUsuario = (int) (
            $_SESSION['id']
            ?? 0
        );

        if ($idUsuario <= 0) {
            header('Location: /');
            exit;
        }

        /*
         * Se valida que el id recibido por GET sea un entero positivo.
         *
         * Se rechazan valores como:
         * - id vacío;
         * - texto;
         * - cero;
         * - números negativos.
         */
        $idReto = filter_var(
            $_GET['id']
                ?? null,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1
                ]
            ]
        );

        if ($idReto === false) {
            self::redirigirRetos();
        }

        // Busca el reto en base de datos.
        $reto = Retos::find(
            (int) $idReto
        );

        /*
         * El detalle no debe mostrarse cuando el reto:
         * - no existe;
         * - está deshabilitado.
         *
         * startChallenge() ya aplica esta comprobación al iniciar
         * el flujo. También se valida aquí para impedir que una URL
         * manipulada muestre una actividad no disponible.
         */
        if (
            !$reto
            || (int) ($reto->habilitado ?? 0) !== 1
        ) {
            self::redirigirRetos();
        }

        // Busca la habilidad a la que pertenece el reto.
        $idHabilidad = (int) (
            $reto->id_habilidades
            ?? 0
        );

        $habilidad = $idHabilidad > 0
            ? HabilidadesBlandas::find(
                $idHabilidad
            )
            : null;

        /*
         * Un reto no puede mostrarse cuando su habilidad asociada
         * no existe o se encuentra deshabilitada.
         */
        if (
            !$habilidad
            || (int) ($habilidad->habilitado ?? 0) !== 1
        ) {
            self::redirigirRetos();
        }

        /*
         * SkillView no permite repetir retos aprobados.
         * Esta misma regla se aplica en startChallenge(), pero se
         * comprueba antes de renderizar para evitar mostrar una página
         * cuyo flujo no puede volver a iniciarse.
         */
        if (
            usuarios_retos::yaCompletado(
                $idUsuario,
                (int) $reto->id
            )
        ) {
            self::redirigirRetos();
        }

        // Variable de compatibilidad con el layout.
        $login = false;

        // Obtiene datos para el encabezado del sistema:
        // nombre de usuario e iniciales.
        $datosUsuario =
            obtenerDatosUsuarioHeader(
                $idUsuario
            );

        // Se agrega el nombre legible de la habilidad al objeto reto
        // para mostrarlo cómodamente en la vista.
        $reto->nombreHabilidad =
            (string) $habilidad->nombre;

        // Renderiza la vista del reto enviando todas las variables necesarias.
        $router->render(
            'paginas/retos/reto',
            [
                'titulo' =>
                    (string) $reto->nombre,
                'login' =>
                    $login,
                'nombreUsuario' =>
                    $datosUsuario[
                        'nombreUsuario'
                    ],
                'inicialesUsuario' =>
                    $datosUsuario[
                        'inicialesUsuario'
                    ],
                'reto' =>
                    $reto
            ]
        );
    }

    // Método API que inicia un reto con IA.
    // Este método:
    // 1. valida autenticación,
    // 2. valida ids,
    // 3. verifica si el reto existe y está habilitado,
    // 4. construye el flujo inicial,
    // 5. solicita a la IA los mensajes iniciales,
    // 6. guarda el flujo en sesión,
    // 7. responde JSON al frontend.
    public static function startChallenge()
    {
        // Si el usuario no está autenticado o no existe id de sesión,
        // responde error JSON con código 401.
        if (!isAuth() || empty($_SESSION['id'])) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Debes iniciar sesión para comenzar el reto.',
                    'redirectTo' => '/'
                ]
            ], 401);
        }

        // Id del usuario autenticado.
        $idUsuario = (int)$_SESSION['id'];

        // Obtiene los datos enviados por fetch/AJAX.
        $input = self::getRequestData();

        // Id del reto y de la habilidad enviados desde frontend.
        $challengeId = (int)($input['challengeId'] ?? 0);
        $skillId = (int)($input['skillId'] ?? 0);

        // Validación básica del payload.
        // Si los ids no son válidos, no se puede iniciar el reto.
        if ($challengeId <= 0 || $skillId <= 0) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'INVALID_PAYLOAD',
                    'message' => 'Los datos del reto son inválidos.'
                ]
            ], 422);
        }

        // Busca el reto en base de datos.
        $reto = Retos::find($challengeId);

        // Si el reto no existe, responde 404.
        if (!$reto) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'CHALLENGE_NOT_FOUND',
                    'message' => 'El reto solicitado no existe.'
                ]
            ], 404);
        }

        // Busca la habilidad asociada al reto.
        $habilidad = HabilidadesBlandas::find($reto->id_habilidades ?? 0);

        // Si la habilidad no existe, responde 404.
        if (!$habilidad) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'SKILL_NOT_FOUND',
                    'message' => 'La habilidad asociada al reto no existe.'
                ]
            ], 404);
        }

        // Verifica que el reto realmente pertenezca a la habilidad enviada por frontend.
        // Esto evita inconsistencias o manipulaciones del request.
        if ((int)$reto->id_habilidades !== $skillId) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'CHALLENGE_SKILL_MISMATCH',
                    'message' => 'El reto no pertenece a la habilidad indicada.'
                ]
            ], 409);
        }

        // Verifica que el reto esté habilitado.
        if ((int)($reto->habilitado ?? 0) !== 1) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'CHALLENGE_DISABLED',
                    'message' => 'Este reto no está disponible en este momento.'
                ]
            ], 409);
        }

        // Verifica que la habilidad también esté habilitada.
        if ((int)($habilidad->habilitado ?? 0) !== 1) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'SKILL_DISABLED',
                    'message' => 'La habilidad asociada no está disponible.'
                ]
            ], 409);
        }

        // Si el usuario ya completó este reto, no se permite iniciarlo de nuevo.
        if (usuarios_retos::yaCompletado($idUsuario, $challengeId)) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'ALREADY_COMPLETED',
                    'message' => 'Ya completaste este reto.',
                    'redirectTo' => '/retos'
                ]
            ], 409);
        }

        // Borra cualquier flujo anterior activo de reto.
        // Esto evita que se mezclen sesiones viejas con el nuevo reto.
        self::clearChallengeFlow();

        // Construye la estructura completa del flujo inicial del reto.
        $flow = self::buildChallengeFlow($reto, $habilidad, $idUsuario);

        // Extrae el contenido normalizado del reto desde el flow.
        $content = $flow['content'] ?? [];

        // Instancia el servicio que orquesta la IA del reto.
        $challengeAI = new ChallengeAIService();

        // Toma el nombre del usuario para personalizar mensajes.
        $userName = trim((string)($_SESSION['nombre'] ?? $_SESSION['nombres'] ?? 'Estudiante'));

        try {
            // Intenta pedir a la IA los mensajes iniciales del reto.
            $messages = $challengeAI->generateInitialMessages(
                $content['title'] ?? '',
                $content['skillName'] ?? 'Habilidad',
                $content,
                $userName
            );
        } catch (\Throwable $e) {
            error_log('Error IA startChallenge: ' . $e->getMessage());

            // No se simulan mensajes de OpenAI con contenido local.
            // Se bloquea el reto y el aviso se muestra dentro del chat.
            $response = self::buildAIUnavailableResponse(
                $flow,
                '/retos'
            );

            // Conservamos la estructura normal de startChallenge para que
            // apiRetos.js pueda sincronizar la información del reto.
            $response['challenge'] = [
                'id' => (int)$reto->id,
                'title' => $content['title'] ?? '',
                'skill' => [
                    'id' => (int)$habilidad->id,
                    'name' => $content['skillName'] ?? 'Habilidad'
                ],
                'difficulty' => $content['difficultyLabel'] ?? 'Básico',
                'timeMin' => (int)($content['timeMin'] ?? 0),
                'timeMax' => (int)($content['timeMax'] ?? 0),
                'maxPoints' => (int)($content['maxPoints'] ?? 0)
            ];

            self::saveChallengeFlow($flow);
            self::jsonResponse($response);
        }

        // Guarda el flujo inicial en sesión.
        self::saveChallengeFlow($flow);

        // Devuelve al frontend toda la información necesaria para renderizar el inicio.
        self::jsonResponse([
            'ok' => true,
            'error' => null,
            'challenge' => [
                'id' => (int)$reto->id,
                'title' => $content['title'] ?? '',
                'skill' => [
                    'id' => (int)$habilidad->id,
                    'name' => $content['skillName'] ?? 'Habilidad'
                ],
                'difficulty' => $content['difficultyLabel'] ?? 'Básico',
                'timeMin' => (int)($content['timeMin'] ?? 0),
                'timeMax' => (int)($content['timeMax'] ?? 0),
                'maxPoints' => (int)($content['maxPoints'] ?? 0)
            ],
            'session' => self::sessionPayload($flow),
            'messages' => $messages,
            'ui' => [
                'showTyping' => true,
                'showAvatarSpeaking' => true,
                'composerPlaceholder' => 'Espera la consigna del reto...',
                'focusInput' => false,
                'showReturnButton' => false
            ]
        ]);
    }

    // Método API que procesa cada turno del reto.
    // Aquí se controla el flujo completo de interacción:
    // - avanzar del intro a la consigna,
    // - recibir respuesta del usuario,
    // - validar localmente,
    // - evaluar con IA,
    // - permitir reintentos,
    // - finalizar con éxito o fracaso.
    public static function turnChallenge()
    {
        // Valida autenticación.
        if (!isAuth() || empty($_SESSION['id'])) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Debes iniciar sesión para continuar el reto.',
                    'redirectTo' => '/'
                ]
            ], 401);
        }

        // Id del usuario autenticado.
        $idUsuario = (int)$_SESSION['id'];

        // Datos enviados desde el frontend.
        $input = self::getRequestData();

        // Id del reto, acción y mensaje del usuario.
        $challengeId = (int)($input['challengeId'] ?? 0);
        $action = trim((string)($input['action'] ?? ''));
        $message = trim((string)($input['message'] ?? ''));

        // Valida que la solicitud tenga lo mínimo necesario.
        if ($challengeId <= 0 || $action === '') {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'INVALID_PAYLOAD',
                    'message' => 'La solicitud del reto es inválida.'
                ]
            ], 422);
        }

        // Recupera el flujo activo desde sesión.
        $flow = self::getActiveChallengeFlow();

        // Si no hay flujo, no se puede continuar el reto.
        if (!$flow) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'NO_ACTIVE_CHALLENGE_FLOW',
                    'message' => 'No hay un flujo de reto activo.'
                ]
            ], 409);
        }

        // Verifica que el flujo activo pertenezca al usuario autenticado.
        if ((int)($flow['userId'] ?? 0) !== $idUsuario) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'FLOW_USER_MISMATCH',
                    'message' => 'El flujo activo no pertenece al usuario autenticado.'
                ]
            ], 409);
        }

        // Verifica que el reto del flujo coincida con el reto solicitado.
        if ((int)($flow['challengeId'] ?? 0) !== $challengeId) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'FLOW_CHALLENGE_MISMATCH',
                    'message' => 'El flujo activo no corresponde al reto solicitado.'
                ]
            ], 409);
        }

        /*
         * Un reto que agotó sus intentos permanece cerrado.
         * Se comprueba antes de completed porque los retos fallidos
         * también quedan marcados como finalizados.
         */
        if (!empty($flow['failed'])) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'CHALLENGE_FAILED',
                    'message' =>
                        'Este reto finalizó porque se agotaron los intentos disponibles.',
                    'redirectTo' => '/retos'
                ]
            ], 409);
        }

        // Si el reto ya fue completado, no se permiten más acciones.
        if (!empty($flow['completed'])) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'CHALLENGE_ALREADY_FINISHED',
                    'message' => 'Este flujo de reto ya finalizó.',
                    'redirectTo' => '/retos'
                ]
            ], 409);
        }

        // Variables base del flujo actual.
        $currentStage = (string)($flow['currentStage'] ?? 'intro');
        $content = $flow['content'] ?? [];
        $userName = trim((string)($_SESSION['nombre'] ?? $_SESSION['nombres'] ?? 'Estudiante'));
        $challengeAI = new ChallengeAIService();

        // Máquina de estados del reto.
        switch ($currentStage) {
            case 'intro':
                // En la etapa intro, solo se permite la acción "advance".
                if ($action !== 'advance') {
                    self::invalidActionResponse($currentStage, 'advance');
                }

                try {
                    // La IA genera la consigna principal del reto.
                    $messages = $challengeAI->generateChallengePromptMessages(
                        $content['title'] ?? '',
                        $content['skillName'] ?? 'Habilidad',
                        $content,
                        $userName
                    );
                } catch (\Throwable $e) {
                    error_log('Error IA turnChallenge intro: ' . $e->getMessage());

                    // El reto permanece en intro y el usuario no puede
                    // responder una consigna que no fue generada por OpenAI.
                    $response = self::buildAIUnavailableResponse(
                        $flow,
                        '/retos'
                    );

                    self::saveChallengeFlow($flow);
                    self::jsonResponse($response);
                }

                // Se avanza el flujo a la etapa donde el usuario ya puede responder.
                $flow['currentStage'] = 'challenge_answer';
                $flow['nextExpectedAction'] = 'reply';
                $flow['inputEnabled'] = true;
                $flow['requiresUserResponse'] = true;

                // Guarda el flujo actualizado.
                self::saveChallengeFlow($flow);

                // Responde al frontend con la consigna y el nuevo estado del flujo.
                self::jsonResponse([
                    'ok' => true,
                    'error' => null,
                    'session' => self::sessionPayload($flow),
                    'messages' => $messages,
                    'ui' => [
                        'showTyping' => true,
                        'showAvatarSpeaking' => true,
                        'composerPlaceholder' => 'Escribe tu respuesta al reto...',
                        'focusInput' => true,
                        'showReturnButton' => false
                    ]
                ]);
                break;

            case 'challenge_answer':
            case 'challenge_answer_retry':
                // En estas etapas solo se permite la acción "reply".
                if ($action !== 'reply') {
                    self::invalidActionResponse($currentStage, 'reply');
                }

                // Primero se valida la respuesta localmente antes de llamar a la IA.
                $basicValidation = self::validateBasicChallengeAnswer($message);

                // Si falla la validación básica, no se consume API.
                if (!$basicValidation['valid']) {
                    /*
                     * Registra el intento fallido y conserva la última
                     * respuesta. La validación local no asigna puntos.
                     */
                    $remainingAttempts =
                        self::registerFailedChallengeAttempt(
                            $flow,
                            $message,
                            0
                        );

                    // Estructura de evaluación simulada para respuesta inválida local.
                    $evaluation = [
                        'accepted' => false,
                        'needsRetry' => true,
                        'retryReason' => self::mapBasicValidationReasonToRetryReason($basicValidation['reason'] ?? null),
                        'detectedIssues' => [],
                        'scoreRatio' => 0,
                        'performanceLevel' => 'INSUFFICIENT',
                        'feedbackSummary' => $basicValidation['message'] ?? 'Tu respuesta necesita más desarrollo.'
                    ];

                    // Guarda la evaluación en el flow.
                    $flow['evaluation'] = $evaluation;
                    $flow['scoreAwarded'] = 0;

                    // Prepara el mensaje del usuario para que aparezca en el chat.
                    $userMessagePayload = [[
                        'id' => 'msg_u_' . uniqid(),
                        'role' => 'user',
                        'type' => 'text',
                        'text' => $message
                    ]];

                    // Si ya no quedan intentos, se marca el reto como fallido.
                    if ($remainingAttempts <= 0) {
                        $flow = self::markChallengeFlowAsFailed($flow);
                        self::saveChallengeFlow($flow);

                        // En el chat se muestra solo el último mensaje del usuario.
                        $chatMessages = $userMessagePayload;

                        // En el modal se muestra feedback estructurado del asistente.
                        $modalMessages = self::buildFailedChallengeModalMessages($flow, $evaluation);

                        self::jsonResponse(
                            self::buildFailedChallengeResponse($flow, $chatMessages, $modalMessages, $evaluation)
                        );
                    }

                    // Si aún quedan intentos, el flujo entra en retry.
                    $flow['currentStage'] = 'challenge_answer_retry';
                    $flow['nextExpectedAction'] = 'reply';
                    $flow['inputEnabled'] = true;
                    $flow['requiresUserResponse'] = true;

                    self::saveChallengeFlow($flow);

                    // Mensajes de retry: mensaje del usuario + feedback del asistente.
                    $retryMessages = array_merge(
                        $userMessagePayload,
                        self::buildRetryMessagesFallback($evaluation, $remainingAttempts)
                    );

                    self::jsonResponse(
                        self::buildRetryResponse($flow, $retryMessages, $evaluation)
                    );
                }

                try {
                    // Si la validación básica fue correcta, ahora sí se llama a la IA
                    // para evaluar semánticamente la respuesta.
                    $aiEvaluation = $challengeAI->evaluateChallengeAnswer(
                        $content['title'] ?? '',
                        $content['skillName'] ?? 'Habilidad',
                        $message,
                        $content,
                        [
                            'attemptNumber' => (int)($flow['attempts']['challengeAnswer'] ?? 0) + 1,
                            'maxAttempts' => (int)($flow['limits']['challengeAnswer'] ?? self::MAX_CHALLENGE_ATTEMPTS),
                            'maxPoints' => (int)($flow['maxScore'] ?? 0),
                        ],
                        $userName
                    );
                } catch (\Throwable $e) {
                    error_log('Error IA evaluación reto: ' . $e->getMessage());

                    // Un error de conexión no consume intentos ni permite
                    // continuar con una evaluación simulada.
                    $response = self::buildAIUnavailableResponse(
                        $flow,
                        '/retos',
                        $message
                    );

                    self::saveChallengeFlow($flow);
                    self::jsonResponse($response);
                }

                // Si la IA rechaza la respuesta.
                if (empty($aiEvaluation['accepted'])) {
                    /*
                     * El rechazo de la IA consume una oportunidad.
                     * Se conserva la respuesta del usuario y el puntaje
                     * se mantiene en cero porque no fue aceptada.
                     */
                    $remainingAttempts =
                        self::registerFailedChallengeAttempt(
                            $flow,
                            $message,
                            0
                        );

                    // Guarda la evaluación de IA en el flow.
                    $flow['evaluation'] = [
                        'accepted' => false,
                        'needsRetry' => true,
                        'retryReason' => $aiEvaluation['retryReason'] ?? 'TOO_GENERIC',
                        'detectedIssues' => $aiEvaluation['detectedIssues'] ?? [],
                        'scoreRatio' => 0,
                        'performanceLevel' => 'INSUFFICIENT',
                        'feedbackSummary' => $aiEvaluation['feedbackSummary'] ?? null
                    ];

                    // Prepara el mensaje del usuario.
                    $userMessagePayload = [[
                        'id' => 'msg_u_' . uniqid(),
                        'role' => 'user',
                        'type' => 'text',
                        'text' => $message
                    ]];

                    // Si ya no quedan intentos, el reto termina como fallido.
                    if ($remainingAttempts <= 0) {
                        $flow = self::markChallengeFlowAsFailed($flow);
                        self::saveChallengeFlow($flow);

                        $chatMessages = $userMessagePayload;

                        $modalMessages = self::buildFailedChallengeModalMessages($flow, $flow['evaluation']);

                        self::jsonResponse(
                            self::buildFailedChallengeResponse($flow, $chatMessages, $modalMessages, $flow['evaluation'])
                        );
                    }

                    // Si aún quedan intentos, sigue en retry.
                    $flow['currentStage'] = 'challenge_answer_retry';
                    $flow['nextExpectedAction'] = 'reply';
                    $flow['inputEnabled'] = true;
                    $flow['requiresUserResponse'] = true;

                    // Si la IA devolvió mensajes, se normalizan.
                    // Si no, se usa fallback local.
                    $assistantRetryMessages = !empty($aiEvaluation['messages'])
                        ? $challengeAI->normalizeMessages($aiEvaluation['messages'], 'msg_ai_retry_')
                        : self::buildRetryMessagesFallback($flow['evaluation'], $remainingAttempts);

                    // Si la IA devolvió mensajes, se agrega un mensaje extra indicando intentos restantes.
                    if (!empty($aiEvaluation['messages'])) {
                        $assistantRetryMessages[] = [
                            'id' => 'msg_ai_attempts_' . uniqid(),
                            'role' => 'assistant',
                            'type' => 'text',
                            'text' => self::buildAttemptsWarningMessage($remainingAttempts)
                        ];
                    }

                    // Se combinan mensaje usuario + feedback asistente.
                    $retryMessages = array_merge($userMessagePayload, $assistantRetryMessages);

                    self::saveChallengeFlow($flow);

                    self::jsonResponse(
                        self::buildRetryResponse($flow, $retryMessages, $flow['evaluation'])
                    );
                }

                // Si la respuesta fue aceptada por la IA, se calcula el puntaje real.
                $scoreAwarded = self::calculateChallengeScore(
                    (float)($aiEvaluation['scoreRatio'] ?? 0),
                    (int)($flow['maxScore'] ?? 0),
                    true
                );

                // Obtiene el puntaje mínimo necesario para aprobar.
                $minimumScore = (int)($flow['minimumScore'] ?? 0);

                // true si alcanzó el mínimo; false si quedó por debajo.
                $meetsMinimumScore =
                    $scoreAwarded >= $minimumScore;

                /*
                 * Ejemplo:
                 * scoreAwarded = 18
                 * minimumScore = 21
                 * 18 >= 21 => false
                 */

                // La IA consideró válida la respuesta, pero el puntaje
                // todavía no alcanza el mínimo requerido para aprobar.
                if (!$meetsMinimumScore) {
                    /*
                     * Una respuesta válida con puntaje insuficiente
                     * consume un intento, pero conserva tanto el texto
                     * como el puntaje real alcanzado.
                     */
                    $remainingAttempts =
                        self::registerFailedChallengeAttempt(
                            $flow,
                            $message,
                            $scoreAwarded
                        );

                    // Convierte el resultado en un reintento controlado
                    // por una regla del backend.
                    $flow['evaluation'] = [
                        'accepted' => false,
                        'needsRetry' => true,
                        'retryReason' => 'BELOW_MINIMUM_SCORE',
                        'detectedIssues' => $aiEvaluation['detectedIssues'] ?? [],
                        'scoreRatio' => (float)($aiEvaluation['scoreRatio'] ?? 0),
                        'performanceLevel' => $aiEvaluation['performanceLevel'] ?? 'ACCEPTABLE',
                        'feedbackSummary' => $aiEvaluation['feedbackSummary'] ?? null
                    ];

                    // Prepara el mensaje del usuario para mostrarlo en el chat.
                    $userMessagePayload = [[
                        'id' => 'msg_u_' . uniqid(),
                        'role' => 'user',
                        'type' => 'text',
                        'text' => $message
                    ]];

                    // Si ya agotó los intentos, el reto termina como fallido.
                    if ($remainingAttempts <= 0) {
                        /*
                         * Se conserva el puntaje del último intento para
                         * mostrarlo en el resultado final del reto.
                         */
                        $flow =
                            self::markChallengeFlowAsFailed(
                                $flow,
                                $scoreAwarded
                            );

                        self::saveChallengeFlow($flow);

                        $chatMessages = $userMessagePayload;

                        $modalMessages = self::buildFailedChallengeModalMessages(
                            $flow,
                            $flow['evaluation']
                        );

                        self::jsonResponse(
                            self::buildFailedChallengeResponse(
                                $flow,
                                $chatMessages,
                                $modalMessages,
                                $flow['evaluation']
                            )
                        );
                    }

                    // Si todavía quedan intentos, se mantiene activo el reto.
                    $flow['currentStage'] = 'challenge_answer_retry';
                    $flow['nextExpectedAction'] = 'reply';
                    $flow['inputEnabled'] = true;
                    $flow['requiresUserResponse'] = true;

                    // Combina el mensaje del usuario con la explicación
                    // del puntaje insuficiente.
                    $retryMessages = array_merge(
                        $userMessagePayload,
                        self::buildBelowMinimumScoreRetryMessages(
                            $flow,
                            $flow['evaluation'],
                            $remainingAttempts
                        )
                    );

                    self::saveChallengeFlow($flow);

                    self::jsonResponse(
                        self::buildRetryResponse(
                            $flow,
                            $retryMessages,
                            $flow['evaluation']
                        )
                    );
                }

                // Preparamos la evaluación exitosa sin modificar todavía
                // el flujo ni persistir el reto.
                $successfulEvaluation = [
                    'accepted' => true,
                    'needsRetry' => false,
                    'retryReason' => null,
                    'detectedIssues' => [],
                    'scoreRatio' => (float)($aiEvaluation['scoreRatio'] ?? 0),
                    'performanceLevel' => $aiEvaluation['performanceLevel'] ?? 'ACCEPTABLE',
                    'feedbackSummary' => $aiEvaluation['feedbackSummary'] ?? null
                ];

                try {
                    // El feedback final debe provenir de OpenAI antes de
                    // completar y guardar el reto.
                    $finalMessages = $challengeAI->generateFinalFeedbackMessages(
                        $content['title'] ?? '',
                        $content['skillName'] ?? 'Habilidad',
                        $message,
                        [
                            ...$successfulEvaluation,
                            'scoreAwarded' => $scoreAwarded,
                            'maxScore' => (int)($flow['maxScore'] ?? 0)
                        ],
                        $content,
                        $userName
                    );
                } catch (\Throwable $e) {
                    error_log('Error IA feedback final reto: ' . $e->getMessage());

                    $response = self::buildAIUnavailableResponse(
                        $flow,
                        '/retos',
                        $message
                    );

                    self::saveChallengeFlow($flow);
                    self::jsonResponse($response);
                }

                // Solo después de recibir el feedback final se completa el
                // flujo y se guarda el resultado.
                $flow['answers']['challengeAnswer'] = $message;
                $flow['evaluation'] = $successfulEvaluation;
                $flow['scoreAwarded'] = $scoreAwarded;
                $flow['currentStage'] = 'complete';
                $flow['nextExpectedAction'] = null;
                $flow['inputEnabled'] = false;
                $flow['requiresUserResponse'] = false;
                $flow['completed'] = true;
                $flow['passed'] = true;
                $flow['failed'] = false;

                // Se persiste en base de datos el resultado del reto y sus efectos asociados.
                $saved = self::persistCompletedChallenge($idUsuario, $flow);

                // Si falla la persistencia, se responde error 500.
                if (!$saved) {
                    self::jsonResponse([
                        'ok' => false,
                        'error' => [
                            'code' => 'PERSISTENCE_ERROR',
                            'message' => 'No fue posible guardar el progreso del reto.'
                        ]
                    ], 500);
                }

                // Mensaje del usuario que se mostrará en el chat final.
                $userMessages = [[
                    'id' => 'msg_u_' . uniqid(),
                    'role' => 'user',
                    'type' => 'text',
                    'text' => $message
                ]];

                // Guarda el flow final actualizado.
                self::saveChallengeFlow($flow);

                // Respuesta final exitosa:
                // - el chat recibe el mensaje del usuario,
                // - el modal recibe el feedback del asistente.
                self::jsonResponse(
                    self::buildCompletedChallengeResponse(
                        $flow,
                        $userMessages,
                        $finalMessages,
                        [
                            ...$flow['evaluation'],
                            'scoreAwarded' => $flow['scoreAwarded'],
                            'maxScore' => $flow['maxScore']
                        ]
                    )
                );
                break;

            default:
                // Si el stage no existe o no es válido, responde error controlado.
                self::invalidStageResponse($currentStage);
                break;
        }
    }

    /**
     * Redirige a la página principal de retos y detiene la ejecución.
     *
     * Se utiliza cuando el reto solicitado no existe, está
     * deshabilitado, pertenece a una habilidad no disponible o ya
     * fue completado por el usuario.
     */
    private static function redirigirRetos(): void
    {
        header('Location: /retos');
        exit;
    }

    // Lee los datos del request.
    // Primero intenta leer JSON desde php://input.
    // Si no hay JSON válido, cae como fallback a $_POST.
    private static function getRequestData(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        return $_POST ?? [];
    }

    // Helper general para responder JSON.
    // Define status HTTP, content-type y finaliza la ejecución.
    private static function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Construye un arreglo normalizado con todos los datos del reto
    // que serán útiles durante el flujo y para los prompts de IA.
    private static function buildChallengeContent(object $reto, object $habilidad): array
    {
        $difficultyValue = (int)($reto->dificultad ?? 1);

        return [
            'title' => trim((string)($reto->nombre ?? '')),
            'skillName' => trim((string)($habilidad->nombre ?? 'Habilidad')),
            'description' => trim((string)($reto->descripcion ?? '')),
            'tags' => self::parseTags($reto->tag ?? ''),
            'difficultyLabel' => self::difficultyLabel($difficultyValue),
            'difficultyValue' => $difficultyValue,
            'timeMin' => (int)($reto->tiempo_min ?? 0),
            'timeMax' => (int)($reto->tiempo_max ?? 0),
            'maxPoints' => (int)($reto->puntos ?? 0),
            'objective' => self::buildChallengeObjective($reto, $habilidad),
            'expectedAction' => self::buildExpectedAction($reto, $habilidad),
        ];
    }

    // Construye el estado inicial del flujo del reto.
    // Aquí se define toda la máquina de estados base.
    private static function buildChallengeFlow(object $reto, object $habilidad, int $userId): array
    {
        // Construye y normaliza la información del reto.
        $content = self::buildChallengeContent($reto, $habilidad);

        // Obtiene el puntaje máximo definido para el reto.  Ejmplo: $maxScore = 30 puntos
        $maxScore = (int)($content['maxPoints'] ?? 0);

        // Calcula el puntaje mínimo necesario para aprobarlo. Ejmplo: $minimumScore = 21
        $minimumScore = self::calculateMinimumPassingScore($maxScore);

        return [
            'challengeId' => (int)$reto->id,
            'skillId' => (int)$habilidad->id,
            'userId' => (int)$userId,

            'currentStage' => 'intro',
            'nextExpectedAction' => 'advance',

            'inputEnabled' => false,
            'requiresUserResponse' => false,

            'completed' => false,
            'passed' => false,
            'failed' => false,

            'attempts' => [
                'challengeAnswer' => 0
            ],

            'limits' => [
                'challengeAnswer' =>
                    self::MAX_CHALLENGE_ATTEMPTS
            ],

            'answers' => [
                'challengeAnswer' => null
            ],

            'content' => $content,

            'evaluation' => [
                'accepted' => false,
                'needsRetry' => false,
                'retryReason' => null,
                'detectedIssues' => [],
                'scoreRatio' => 0,
                'performanceLevel' => null,
                'feedbackSummary' => null
            ],

            // Puntaje obtenido por el usuario.
            // Inicialmente es 0 porque todavía no ha respondido.
            'scoreAwarded' => 0,

            // Puntaje máximo disponible en el reto.
            'maxScore' => $maxScore,

            // Puntaje mínimo que debe alcanzar para aprobar.
            'minimumScore' => $minimumScore,

            'startedAt' => date('Y-m-d H:i:s'),
            'lastInteractionAt' => date('Y-m-d H:i:s'),
        ];
    }

    // Devuelve el flujo activo del reto desde sesión.
    // Si no existe o no es array, retorna null.
    private static function getActiveChallengeFlow(): ?array
    {
        $flow = $_SESSION['challenge_flow'] ?? null;
        return is_array($flow) ? $flow : null;
    }

    // Guarda el flujo en sesión y actualiza el timestamp de última interacción.
    private static function saveChallengeFlow(array $flow): void
    {
        $flow['lastInteractionAt'] = date('Y-m-d H:i:s');
        $_SESSION['challenge_flow'] = $flow;
    }

    // Elimina completamente el flujo del reto desde sesión.
    private static function clearChallengeFlow(): void
    {
        unset($_SESSION['challenge_flow']);
    }

    // Devuelve una versión reducida del flow para el frontend.
    // No expone todos los datos internos, solo los necesarios para la UI.
    private static function sessionPayload(array $flow): array
    {
        return [
            'challengeId' => (int)($flow['challengeId'] ?? 0),
            'skillId' => (int)($flow['skillId'] ?? 0),

            'currentStage' => (string)($flow['currentStage'] ?? 'intro'),
            'nextExpectedAction' => $flow['nextExpectedAction'] ?? null,

            'inputEnabled' => (bool)($flow['inputEnabled'] ?? false),
            'requiresUserResponse' => (bool)($flow['requiresUserResponse'] ?? false),

            'completed' => (bool)($flow['completed'] ?? false),
            'passed' => (bool)($flow['passed'] ?? false),
            'failed' => (bool)($flow['failed'] ?? false),

            'attempts' => [
                'challengeAnswer' => (int)($flow['attempts']['challengeAnswer'] ?? 0)
            ],

            'limits' => [
                'challengeAnswer' =>
                    (int) (
                        $flow['limits']['challengeAnswer']
                        ?? self::MAX_CHALLENGE_ATTEMPTS
                    )
            ],

            'scoreAwarded' => (int)($flow['scoreAwarded'] ?? 0),
            'maxScore' => (int)($flow['maxScore'] ?? 0),
            'minimumScore' => (int)($flow['minimumScore'] ?? 0)
        ];
    }

    /*
     * Calcula el puntaje mínimo necesario para aprobar un reto.
     *
     * La regla de SkillView establece que el usuario debe alcanzar
     * como mínimo el 70 % de los puntos disponibles. Se utiliza ceil()
     * para garantizar que nunca se apruebe con menos del porcentaje
     * establecido cuando el resultado contiene decimales.
     *
     * Ejemplo:
     * 25 puntos × 0.70 = 17.5
     * ceil(17.5) = 18 puntos mínimos.
     */
    private static function calculateMinimumPassingScore(
        int $maxPoints
    ): int {
        if ($maxPoints <= 0) {
            return 0;
        }

        $minimumScore =
            (int) ceil(
                $maxPoints
                * self::MIN_PASSING_RATIO
            );

        return max(
            1,
            min(
                $maxPoints,
                $minimumScore
            )
        );
    }

    // Convierte el scoreRatio devuelto por IA en el puntaje real del reto.
    // Regla:
    // - una respuesta rechazada obtiene 0 puntos;
    // - una respuesta aceptada se calcula proporcionalmente al máximo.
    private static function calculateChallengeScore(
        float $scoreRatio,
        int $maxPoints,
        bool $accepted
    ): int {
        if (!$accepted || $maxPoints <= 0) {
            return 0;
        }

        /*
         * La proporción de IA se limita al intervalo [0, 1].
         * Esto evita puntajes negativos o superiores al máximo
         * ante una respuesta externa inconsistente.
         */
        $ratio =
            max(
                self::MIN_SCORE_RATIO,
                min(
                    self::MAX_SCORE_RATIO,
                    $scoreRatio
                )
            );

        $score =
            (int) round(
                $maxPoints * $ratio
            );

        $score =
            max(
                0,
                min(
                    $maxPoints,
                    $score
                )
            );

        /*
         * Si la IA aceptó la respuesta y el redondeo produjo cero,
         * se conserva al menos un punto.
         */
        return $score === 0
            ? 1
            : $score;
    }

    // Calcula cuántos intentos quedan según el flow.
    private static function remainingAttempts(
        array $flow
    ): int {
        $used =
            max(
                0,
                (int) (
                    $flow['attempts']['challengeAnswer']
                    ?? 0
                )
            );

        $limit =
            max(
                0,
                (int) (
                    $flow['limits']['challengeAnswer']
                    ?? self::MAX_CHALLENGE_ATTEMPTS
                )
            );

        return max(
            0,
            $limit - $used
        );
    }

    /**
     * Registra un intento que no aprobó el reto.
     *
     * Se conserva la última respuesta y el puntaje obtenido para que
     * el estado del flujo represente exactamente el último intento.
     * El contador nunca supera el límite configurado.
     */
    private static function registerFailedChallengeAttempt(
        array &$flow,
        string $message,
        int $scoreAwarded
    ): int {
        $limit =
            max(
                0,
                (int) (
                    $flow['limits']['challengeAnswer']
                    ?? self::MAX_CHALLENGE_ATTEMPTS
                )
            );

        $used =
            max(
                0,
                (int) (
                    $flow['attempts']['challengeAnswer']
                    ?? 0
                )
            );

        $flow['attempts']['challengeAnswer'] =
            $limit > 0
                ? min(
                    $limit,
                    $used + 1
                )
                : $used;

        $flow['answers']['challengeAnswer'] =
            trim($message);

        $maxScore =
            max(
                0,
                (int) (
                    $flow['maxScore']
                    ?? 0
                )
            );

        $flow['scoreAwarded'] =
            max(
                0,
                min(
                    $maxScore,
                    $scoreAwarded
                )
            );

        return self::remainingAttempts(
            $flow
        );
    }

    // Persiste un reto exitoso en base de datos y desencadena efectos secundarios:
    // - guarda usuarios_retos,
    // - recalcula progreso de habilidad,
    // - evalúa logros,
    // - guarda logros recientes en sesión.
    private static function persistCompletedChallenge(int $idUsuario, array $flow): bool
    {
        $idUsuario = (int)$idUsuario;
        $challengeId = (int)($flow['challengeId'] ?? 0);
        $skillId = (int)($flow['skillId'] ?? 0);
        $scoreAwarded = (int)($flow['scoreAwarded'] ?? 0);
        $completed = (bool)($flow['completed'] ?? false);
        $passed = (bool)($flow['passed'] ?? false);

        // Validación de integridad.
        if ($idUsuario <= 0 || $challengeId <= 0 || $skillId <= 0) {
            return false;
        }

        // Solo persiste si el reto realmente terminó y fue aprobado.
        if (!$completed || !$passed) {
            return false;
        }

        // Guarda o actualiza el reto en la tabla usuarios_retos.
        $saved = usuarios_retos::marcarComoCompletado($idUsuario, $challengeId, $scoreAwarded);

        if (!$saved) {
            return false;
        }

        // Recalcular progreso de la habilidad
        self::recalculateUserSkillProgress($idUsuario, $skillId);

        // Evaluar logros nuevos tipo 4 (desempeño)
        $nuevosLogros = Logros::evaluarYAsignarNuevosPorReto($idUsuario, $challengeId);

        if (!empty($nuevosLogros)) {
            // Garantiza que la sesión esté activa antes de usarla.
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            // Lee logros recientes ya existentes en sesión.
            $logrosSesionActual = $_SESSION['logros_recientes'] ?? [];

            // Formatea los logros nuevos para que puedan mostrarse fácilmente en frontend.
            $logrosNuevosFormateados = array_map(function ($logro) {
                return [
                    'id' => $logro->id,
                    'nombre' => $logro->nombre,
                    'descripcion' => $logro->descripcion,
                    'icono' => $logro->icono,
                    'tipo' => $logro->tipo,
                    'valor_objetivo' => $logro->valor_objetivo,
                    'fecha_obtenido' => date('Y-m-d')
                ];
            }, $nuevosLogros);

            // Fusiona logros anteriores + logros nuevos.
            $_SESSION['logros_recientes'] = array_merge($logrosSesionActual, $logrosNuevosFormateados);
        }

        return true;
    }

    // Recalcula el progreso consolidado de la habilidad del usuario.
    // Devuelve true si los ids son válidos y la operación se ejecuta.
    private static function recalculateUserSkillProgress(int $idUsuario, int $idHabilidad): bool
    {
        if ($idUsuario <= 0 || $idHabilidad <= 0) {
            return false;
        }

        usuarios_habilidades::recalcularProgresoHabilidad($idUsuario, $idHabilidad);
        return true;
    }

    // Convierte dificultad numérica a etiqueta legible para UI y prompts.
    private static function difficultyLabel(int $difficulty): string
    {
        return match ($difficulty) {
            2 => 'Intermedio',
            3 => 'Avanzado',
            default => 'Básico',
        };
    }

    // Convierte un string de tags separados por comas en un array limpio.
    private static function parseTags(string $rawTags): array
    {
        $parts = array_map('trim', explode(',', $rawTags));
        $parts = array_filter($parts, fn($tag) => $tag !== '');

        return array_values($parts);
    }

    // Construye una descripción estándar del objetivo del reto.
    private static function buildChallengeObjective(object $reto, object $habilidad): string
    {
        $skillName = trim((string)($habilidad->nombre ?? 'la habilidad'));
        return "Aplicar la habilidad de {$skillName} en una situación breve relacionada con entrevistas.";
    }

    // Construye la acción esperada del estudiante en el reto.
    private static function buildExpectedAction(object $reto, object $habilidad): string
    {
        $skillName = trim((string)($habilidad->nombre ?? 'la habilidad'));
        return "Responder con claridad, coherencia, reflexión y una acción concreta alineada con {$skillName}.";
    }

    // Validación local mínima antes de llamar a la IA.
    // Filtra respuestas vacías, demasiado cortas o sin contenido útil.
    private static function validateBasicChallengeAnswer(
        string $message
    ): array {
        $message =
            trim(
                $message
            );

        // Caso 1: respuesta vacía o compuesta únicamente por espacios.
        if ($message === '') {
            return [
                'valid' => false,
                'reason' => 'EMPTY_RESPONSE',
                'message' =>
                    'Tu respuesta está vacía. Intenta escribir una idea completa.'
            ];
        }

        // Caso 2: no contiene letras ni números útiles.
        if (
            !preg_match(
                '/[a-záéíóúñ0-9]/iu',
                $message
            )
        ) {
            return [
                'valid' => false,
                'reason' => 'INVALID_CONTENT',
                'message' =>
                    'Tu respuesta no contiene contenido válido. Intenta escribir una idea clara.'
            ];
        }

        // Caso 3: respuesta demasiado corta.
        if (
            mb_strlen(
                $message,
                'UTF-8'
            )
            < self::MIN_ANSWER_LENGTH
        ) {
            return [
                'valid' => false,
                'reason' => 'TOO_SHORT',
                'message' =>
                    'Tu respuesta es demasiado corta. Intenta desarrollar mejor tu idea.'
            ];
        }

        // Caso 4: cantidad insuficiente de palabras.
        $words =
            preg_split(
                '/\s+/u',
                $message,
                -1,
                PREG_SPLIT_NO_EMPTY
            );

        if (
            !$words
            || count($words)
                < self::MIN_ANSWER_WORDS
        ) {
            return [
                'valid' => false,
                'reason' =>
                    'INSUFFICIENT_DEVELOPMENT',
                'message' =>
                    'Tu respuesta necesita un poco más de desarrollo.'
            ];
        }

        // Caso 5: respuestas demasiado genéricas conocidas.
        $normalizedMessage =
            mb_strtolower(
                $message,
                'UTF-8'
            );

        if (
            in_array(
                $normalizedMessage,
                self::GENERIC_INVALID_ANSWERS,
                true
            )
        ) {
            return [
                'valid' => false,
                'reason' => 'TOO_GENERIC',
                'message' =>
                    'Tu respuesta es demasiado general. Intenta ser más específico.'
            ];
        }

        // Si supera el filtro local, la respuesta puede enviarse a la IA.
        return [
            'valid' => true,
            'reason' => null,
            'message' => null
        ];
    }

    // Traduce razones de validación local a razones compatibles con el sistema de retry.
    private static function mapBasicValidationReasonToRetryReason(?string $reason): string
    {
        return match ($reason) {
            'TOO_SHORT',
            'INSUFFICIENT_DEVELOPMENT' => 'INSUFFICIENT_DEVELOPMENT',
            'INVALID_CONTENT' => 'INCOHERENT',
            'TOO_GENERIC' => 'TOO_GENERIC',
            'EMPTY_RESPONSE' => 'INSUFFICIENT_DEVELOPMENT',
            default => 'TOO_GENERIC',
        };
    }

    // Respuesta estándar cuando la acción enviada no corresponde con la etapa actual del flujo.
    private static function invalidActionResponse(string $currentStage, string $expectedAction): void
    {
        self::jsonResponse([
            'ok' => false,
            'error' => [
                'code' => 'INVALID_ACTION_FOR_STAGE',
                'message' => 'La acción enviada no es válida para la etapa actual.',
                'currentStage' => $currentStage,
                'expectedAction' => $expectedAction
            ]
        ], 409);
    }

    // Respuesta estándar cuando el stage actual no existe o no es reconocido.
    private static function invalidStageResponse(string $currentStage): void
    {
        self::jsonResponse([
            'ok' => false,
            'error' => [
                'code' => 'INVALID_STAGE',
                'message' => 'La etapa actual del reto no es válida.',
                'currentStage' => $currentStage
            ]
        ], 409);
    }

    // Marca el flujo como fallido cuando el usuario agota sus intentos.
    private static function markChallengeFlowAsFailed(
        array $flow,
        ?int $scoreAwarded = null
    ): array {
        $flow['currentStage'] = 'failed';
        $flow['nextExpectedAction'] = null;
        $flow['inputEnabled'] = false;
        $flow['requiresUserResponse'] = false;
        $flow['completed'] = true;
        $flow['passed'] = false;
        $flow['failed'] = true;

        /*
         * Cuando el último intento fue rechazado, el puntaje será 0.
         * Cuando fue válido pero quedó por debajo del mínimo, se
         * conserva el puntaje real para mostrarlo en el resultado.
         */
        if ($scoreAwarded !== null) {
            $maxScore =
                max(
                    0,
                    (int) (
                        $flow['maxScore']
                        ?? 0
                    )
                );

            $flow['scoreAwarded'] =
                max(
                    0,
                    min(
                        $maxScore,
                        $scoreAwarded
                    )
                );
        }

        return $flow;
    }

    // Genera el texto que indica al usuario cuántos intentos le quedan.
    private static function buildAttemptsWarningMessage(int $remainingAttempts): string
    {
        if ($remainingAttempts <= 0) {
            return 'No te quedan más intentos en este reto.';
        }

        if ($remainingAttempts === 1) {
            return 'Te queda 1 intento.';
        }

        return "Te quedan {$remainingAttempts} intentos.";
    }

    // Mensajes iniciales fallback si la IA falla al iniciar el reto.
    private static function buildInitialChallengeMessagesFallback(array $content, string $userName = ''): array
    {
        $name = trim($userName);
        $nameText = $name !== '' ? ", {$name}" : '';
        $skillName = $content['skillName'] ?? 'esta habilidad';

        return [
            [
                'id' => 'msg_ai_intro_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => "Hola{$nameText}. Vamos a trabajar un reto de {$skillName}."
            ],
            [
                'id' => 'msg_ai_intro_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => 'Tu objetivo será resolver una situación breve aplicando esta habilidad con claridad y reflexión.'
            ]
        ];
    }

    // Consigna fallback si la IA falla al generar el prompt principal del reto.
    private static function buildChallengePromptFallback(array $content): array
    {
        $title = $content['title'] ?? 'Reto';
        $description = $content['description'] ?? 'Resuelve la situación planteada.';
        $skillName = $content['skillName'] ?? 'la habilidad';

        return [
            [
                'id' => 'msg_ai_prompt_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => "Reto: {$title}."
            ],
            [
                'id' => 'msg_ai_prompt_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => $description
            ],
            [
                'id' => 'msg_ai_prompt_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => "Responde con claridad y aplica la habilidad de {$skillName} en tu respuesta."
            ]
        ];
    }

    // Traduce una razón técnica de retry a un mensaje más natural para el usuario.
    private static function mapRetryReasonToMessage(?string $reason): string
    {
        return match ($reason) {
            'TOO_GENERIC' => 'Intenta ser más específico y desarrollar mejor tu idea.',
            'OFF_TOPIC' => 'Tu respuesta se aleja de la consigna del reto. Intenta enfocarte en lo que se pidió.',
            'INCOHERENT' => 'Tu respuesta necesita más claridad y coherencia.',
            'LOW_REFLECTION' => 'Profundiza un poco más en tu reflexión.',
            'NO_ACTIONABLE_IDEA' => 'Incluye una acción concreta o una reformulación más clara.',
            'INSUFFICIENT_DEVELOPMENT' => 'Desarrolla mejor tu respuesta.',
            default => 'Intenta responder con más claridad y detalle.',
        };
    }

    // Construye mensajes de retry locales cuando no se usan mensajes devueltos por IA.
    private static function buildRetryMessagesFallback(array $evaluation, int $remainingAttempts): array
    {
        return [
            [
                'id' => 'msg_ai_retry_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => 'Tu respuesta aún no cumple completamente con lo que pide el reto.'
            ],
            [
                'id' => 'msg_ai_retry_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => self::mapRetryReasonToMessage($evaluation['retryReason'] ?? null)
            ],
            [
                'id' => 'msg_ai_retry_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => self::buildAttemptsWarningMessage($remainingAttempts)
            ]
        ];
    }

    /**
     * Construye los mensajes que se muestran cuando la respuesta
     * fue válida, pero no alcanzó el puntaje mínimo del reto.
     */
    private static function buildBelowMinimumScoreRetryMessages(
        array $flow,
        array $evaluation,
        int $remainingAttempts
    ): array {
        // Puntajes del intento actual.
        $scoreAwarded = (int)($flow['scoreAwarded'] ?? 0);
        $maxScore = (int)($flow['maxScore'] ?? 0);
        $minimumScore = (int)($flow['minimumScore'] ?? 0);

        // Retroalimentación concreta generada previamente por la IA.
        $feedbackSummary = trim(
            (string)($evaluation['feedbackSummary'] ?? '')
        );

        $messages = [
            [
                'id' => 'msg_ai_minimum_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => 'Tu respuesta fue válida, pero todavía no alcanza el puntaje mínimo necesario para completar el reto.'
            ],
            [
                'id' => 'msg_ai_minimum_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => "Obtuviste {$scoreAwarded} de {$maxScore} puntos. Para aprobar necesitas al menos {$minimumScore} puntos."
            ]
        ];

        // Agrega la retroalimentación de la IA solamente
        // cuando tenga contenido útil.
        if ($feedbackSummary !== '') {
            $messages[] = [
                'id' => 'msg_ai_minimum_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => $feedbackSummary
            ];
        }

        // Informa cuántos intentos quedan.
        $messages[] = [
            'id' => 'msg_ai_minimum_' . uniqid(),
            'role' => 'assistant',
            'type' => 'text',
            'text' => self::buildAttemptsWarningMessage($remainingAttempts)
        ];

        return $messages;
    }

    // Feedback final fallback cuando la IA falla al generar el cierre exitoso.
    private static function buildFinalChallengeFeedbackFallback(array $flow, array $evaluation = []): array
    {
        $skillName = $flow['content']['skillName'] ?? 'esta habilidad';
        $scoreAwarded = (int)($flow['scoreAwarded'] ?? 0);
        $maxScore = (int)($flow['maxScore'] ?? 0);
        $performanceLevel = $evaluation['performanceLevel'] ?? 'ACCEPTABLE';

        $performanceText = match ($performanceLevel) {
            'EXCELLENT' => 'Tu desempeño fue excelente y demostró una aplicación muy sólida de la habilidad trabajada.',
            'GOOD' => 'Tu desempeño fue bueno y mostraste una respuesta clara y bien orientada.',
            default => 'Tu respuesta fue suficiente para completar correctamente el reto.',
        };

        return [
            [
                'id' => 'msg_ai_final_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => 'Buen trabajo. Has completado este reto.'
            ],
            [
                'id' => 'msg_ai_final_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => $performanceText
            ],
            [
                'id' => 'msg_ai_final_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => "Obtuviste {$scoreAwarded} de {$maxScore} puntos. Sigue fortaleciendo {$skillName} para mejorar aún más tu desempeño."
            ]
        ];
    }

    /**
     * Construye una respuesta conversacional cuando OpenAI no está disponible.
     *
     * Se mantiene ok=true para que apiRetos.js procese el payload como una
     * respuesta normal y renderice el mensaje en el chat. serviceError permite
     * reconocer que la actividad fue detenida por un problema técnico.
     *
     * El reto no avanza, no consume intentos y no guarda progreso.
     */
    private static function buildAIUnavailableResponse(
        array &$flow,
        string $returnUrl,
        ?string $userMessage = null
    ): array {
        $flow['nextExpectedAction'] = null;
        $flow['inputEnabled'] = false;
        $flow['requiresUserResponse'] = false;

        $messages = [];

        if ($userMessage !== null && trim($userMessage) !== '') {
            $messages[] = [
                'id' => 'msg_u_' . uniqid(),
                'role' => 'user',
                'type' => 'text',
                'text' => trim($userMessage)
            ];
        }

        $messages[] = [
            'id' => 'msg_ai_connection_' . uniqid(),
            'role' => 'assistant',
            'type' => 'text',
            'text' => 'En este momento no fue posible conectarse con el servicio de inteligencia artificial. Verifica tu conexión a internet e inténtalo nuevamente más tarde. Tu progreso y tus intentos no se verán afectados.'
        ];

        return [
            'ok' => true,
            'error' => null,
            'serviceError' => [
                'code' => 'AI_UNAVAILABLE',
                'message' => 'El servicio de inteligencia artificial no está disponible.'
            ],
            'session' => self::sessionPayload($flow),
            'messages' => $messages,
            'redirectTo' => $returnUrl,
            'ui' => [
                'showTyping' => true,
                'showAvatarSpeaking' => false,
                'composerPlaceholder' => 'Actividad pausada por falta de conexión',
                'focusInput' => false,
                'showReturnButton' => true
            ]
        ];
    }

    // Construye la respuesta estándar de retry.
    // Aquí el reto sigue vivo y el usuario aún puede volver a responder.
    private static function buildRetryResponse(array $flow, array $messages, array $evaluation): array
    {
        return [
            'ok' => true,
            'error' => null,
            'session' => self::sessionPayload($flow),
            'evaluation' => [
                'accepted' => false,
                'needsRetry' => true,
                'retryReason' => $evaluation['retryReason'] ?? null,
                'detectedIssues' => $evaluation['detectedIssues'] ?? []
            ],
            'messages' => $messages,
            'ui' => [
                'showTyping' => true,
                'showAvatarSpeaking' => true,
                'composerPlaceholder' => 'Intenta responder mejor...',
                'focusInput' => true,
                'showReturnButton' => false
            ]
        ];
    }

    // Construye la respuesta final cuando el reto termina fallido.
    // Distingue entre:
    // - mensajes del chat,
    // - mensajes del modal.
    private static function buildFailedChallengeResponse(
        array $flow,
        array $chatMessages,
        array $modalMessages,
        array $evaluation
    ): array {
        return [
            'ok' => true,
            'error' => null,
            'session' => self::sessionPayload($flow),
            'evaluation' => [
                'accepted' => false,
                'needsRetry' => false,
                'retryReason' => $evaluation['retryReason'] ?? null,
                'detectedIssues' => $evaluation['detectedIssues'] ?? [],
                'scoreRatio' => (float)($evaluation['scoreRatio'] ?? 0),
                'performanceLevel' =>  $evaluation['performanceLevel'] ?? 'INSUFFICIENT',
                'scoreAwarded' => (int)($flow['scoreAwarded'] ?? 0),
                'maxScore' => (int)($flow['maxScore'] ?? 0),
                'minimumScore' => (int)($flow['minimumScore'] ?? 0)
            ],
            'messages' => $chatMessages,
            'completionModal' => [
                'type' => 'error',
                'title' => 'Reto no completado',
                'messages' => $modalMessages,
                'scoreAwarded' => (int)($flow['scoreAwarded'] ?? 0),
                'maxScore' => (int)($flow['maxScore'] ?? 0),
                'minimumScore' => (int)($flow['minimumScore'] ?? 0),
                'buttonText' => 'Volver a retos',
                'redirectTo' => '/retos'
            ],
            'progress' => [
                'challengeCompleted' => false,
                'failed' => true,
                'scoreAwarded' => (int)($flow['scoreAwarded'] ?? 0),
                'redirectTo' => '/retos'
            ],
            'ui' => [
                'showTyping' => true,
                'showAvatarSpeaking' => true,
                'composerPlaceholder' => 'Reto finalizado',
                'focusInput' => false,
                'showReturnButton' => true
            ]
        ];
    }

    // Construye los mensajes específicos del modal de fallo.
    // Aquí se explica por qué no se completó y qué debería reforzar el usuario.
    private static function buildFailedChallengeModalMessages(array $flow, array $evaluation = []): array
    {
        $skillName = $flow['content']['skillName'] ?? 'esta habilidad';
        $retryReason = $evaluation['retryReason'] ?? null;

        $scoreAwarded = (int)($flow['scoreAwarded'] ?? 0);
        $maxScore = (int)($flow['maxScore'] ?? 0);
        $minimumScore = (int)($flow['minimumScore'] ?? 0);

        $reasonText = match ($retryReason) {
            'TOO_GENERIC' => 'Tu respuesta fue demasiado general y no desarrolló con suficiente claridad la idea principal del reto.',
            'OFF_TOPIC' => 'Tu respuesta se alejó de la consigna principal del reto.',
            'INCOHERENT' => 'Tu respuesta necesitaba más coherencia y claridad para demostrar la habilidad trabajada.',
            'LOW_REFLECTION' => 'Tu respuesta necesitaba mayor reflexión para mostrar mejor tu criterio.',
            'NO_ACTIONABLE_IDEA' => 'Tu respuesta no incluyó una acción concreta o una reformulación suficientemente útil.',
            'INSUFFICIENT_DEVELOPMENT' => 'Tu respuesta quedó corta y no alcanzó a desarrollar lo necesario para completar el reto.',
            'BELOW_MINIMUM_SCORE' => "Tu respuesta obtuvo {$scoreAwarded} de {$maxScore} puntos, pero necesitabas al menos {$minimumScore} puntos para completar el reto.",
            default => 'Tu respuesta no alcanzó el nivel mínimo esperado para completar este reto.',
        };

        return [
            [
                'id' => 'msg_ai_fail_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => 'No lograste completar este reto en esta ocasión.'
            ],
            [
                'id' => 'msg_ai_fail_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => $reasonText
            ],
            [
                'id' => 'msg_ai_fail_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => "Te recomiendo repasar y seguir practicando la habilidad de {$skillName} antes de intentarlo nuevamente."
            ]
        ];
    }

    // Construye la respuesta final cuando el reto fue completado con éxito.
    // Separa:
    // - messages => chat (mensaje del usuario),
    // - completionModal => feedback final del asistente.
    private static function buildCompletedChallengeResponse(
        array $flow,
        array $userMessages,
        array $finalMessages,
        array $evaluation
    ): array {
        return [
            'ok' => true,
            'error' => null,
            'session' => self::sessionPayload($flow),
            'evaluation' => [
                'accepted' => true,
                'needsRetry' => false,
                'retryReason' => null,
                'detectedIssues' => [],
                'scoreRatio' => (float)($evaluation['scoreRatio'] ?? 0),
                'performanceLevel' => $evaluation['performanceLevel'] ?? 'ACCEPTABLE',
                'scoreAwarded' => (int)($flow['scoreAwarded'] ?? 0),
                'maxScore' => (int)($flow['maxScore'] ?? 0)
            ],
            'messages' => $userMessages,
            'completionModal' => [
                'type' => 'success',
                'title' => 'Reto completado',
                'messages' => $finalMessages,
                'scoreAwarded' => (int)($flow['scoreAwarded'] ?? 0),
                'maxScore' => (int)($flow['maxScore'] ?? 0),
                'buttonText' => 'Continuar',
                'redirectTo' => '/retos'
            ],
            'progress' => [
                'challengeCompleted' => true,
                'failed' => false,
                'scoreAwarded' => (int)($flow['scoreAwarded'] ?? 0),
                'redirectTo' => '/retos'
            ],
            'ui' => [
                'showTyping' => true,
                'showAvatarSpeaking' => true,
                'composerPlaceholder' => 'Reto completado',
                'focusInput' => false,
                'showReturnButton' => true
            ]
        ];
    }
}