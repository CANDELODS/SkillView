<?php

namespace Controllers;

use Classes\LessonAIService;
use Model\HabilidadesBlandas;
use Model\Lecciones;
use Model\usuarios_habilidades;
use Model\usuarios_lecciones;
use MVC\Router;

class LeccionesController
{
    public static function leccion(Router $router)
    {
        if (!isAuth()) {
            header('Location: /');
            exit;
        }

        $login = false;
        $datosUsuario = obtenerDatosUsuarioHeader($_SESSION['id']);

        $idLeccion = $_GET['id'] ?? null;
        if (!$idLeccion) {
            header('Location: /aprendizaje');
            exit;
        }

        $leccion = Lecciones::find($idLeccion);
        if (!$leccion) {
            header('Location: /aprendizaje');
            exit;
        }

        $habilidad = HabilidadesBlandas::find($leccion->id_habilidades);
        $leccion->nombreHabilidad = $habilidad ? $habilidad->nombre : 'Habilidad';

        $router->render('paginas/aprendizaje/leccion', [
            'titulo' => $leccion->titulo,
            'login' => $login,
            'nombreUsuario'    => $datosUsuario['nombreUsuario'],
            'inicialesUsuario' => $datosUsuario['inicialesUsuario'],
            'leccion' => $leccion
        ]);
    }

    public static function startLeccion()
    {
        // 1) Autenticación
        if (!isAuth()) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Tu sesión ha expirado.'
                ],
                'redirectTo' => '/'
            ], 401); // 401 = Unauthorized (El usuario no está autenticado o su sesión ha expirado)
        }

        $idUsuario = (int) ($_SESSION['id'] ?? 0);
        if ($idUsuario <= 0) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'No se pudo identificar al usuario.'
                ],
                'redirectTo' => '/'
            ], 401);
        }

        // 2) Leer request JSON (o form-data como fallback)
        //Traemos todos los datos que vienen del frontend en un array asociativo
        $input = self::getRequestData();

        //Si existe lessonId y skillId dentro de $input, los convertimos a enteros, sino les asignamos 0
        $idLeccion = isset($input['lessonId']) ? (int) $input['lessonId'] : 0;
        $idHabilidad = isset($input['skillId']) ? (int) $input['skillId'] : 0;

        if ($idLeccion <= 0 || $idHabilidad <= 0) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'INVALID_PAYLOAD', //Payload es el término técnico para referirse a los datos que se envían en una petición HTTP
                    'message' => 'lessonId y skillId son obligatorios.'
                ]
            ], 422);
        }

        // 3) Buscar lección y habilidad
        $leccion = Lecciones::find($idLeccion);
        if (!$leccion) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'LESSON_NOT_FOUND',
                    'message' => 'La lección no existe.'
                ],
                'redirectTo' => '/aprendizaje'
            ], 404);
        }

        if ((int)$leccion->habilitado !== 1) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'LESSON_NOT_ALLOWED',
                    'message' => 'La lección no está habilitada.'
                ],
                'redirectTo' => '/aprendizaje'
            ], 403);
        }

        if ((int)$leccion->id_habilidades !== $idHabilidad) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'INVALID_PAYLOAD',
                    'message' => 'La lección no pertenece a la habilidad enviada.'
                ],
                'redirectTo' => '/aprendizaje'
            ], 422);
        }

        $habilidad = HabilidadesBlandas::find($idHabilidad);
        if (!$habilidad || (int)$habilidad->habilitado !== 1) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'LESSON_NOT_ALLOWED',
                    'message' => 'La habilidad asociada no está disponible.'
                ],
                'redirectTo' => '/aprendizaje'
            ], 403);
        }

        // 4) Validar que sea la lección actual permitida para el usuario
        $leccionEsperada = Lecciones::leccionActualPorUsuarioYHabilidad($idUsuario, $idHabilidad);

        if (!$leccionEsperada) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'LESSON_ALREADY_COMPLETED',
                    'message' => 'Ya completaste todas las lecciones de esta habilidad.'
                ],
                'redirectTo' => '/aprendizaje'
            ], 409);
        }

        if ((int)$leccionEsperada->id !== $idLeccion) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'LESSON_NOT_ALLOWED',
                    'message' => 'No puedes acceder a esta lección en este momento.'
                ],
                'redirectTo' => '/aprendizaje'
            ], 403);
        }

        // 5) Parsear descripción
        $contenido = self::parseLessonDescription((string) $leccion->descripcion);
        $tipoLeccion = self::inferLessonType($contenido);

        // 6) Crear estado temporal de la lección en sesión
        $_SESSION['lesson_flow'] = [
            'lessonId' => (int)$leccion->id,
            'skillId' => (int)$habilidad->id,
            'currentStage' => 'intro',
            'nextExpectedAction' => 'advance',
            'inputEnabled' => false,
            'requiresUserResponse' => false,
            'completed' => false,
            'answers' => [
                'microPractice' => null,
                'miniEvaluation' => null
            ],
            'attempts' => [
                'microPractice' => 0,
                'miniEvaluation' => 0
            ],
            'limits' => [
                'microPractice' => 3,
                'miniEvaluation' => 3
            ],
            'failed' => false,
            'content' => $contenido,
            'lessonType' => $tipoLeccion
        ];

        // 7) Mensajes iniciales fijos (sin IA todavía)
        try {
            $lessonAIService = new LessonAIService();
            $userName = $_SESSION['nombres'] ?? 'Estudiante';

            $messages = $lessonAIService->generateInitialMessages(
                $leccion->titulo,
                $habilidad->nombre,
                $contenido,
                $userName
            );
        } catch (\Exception $e) {
            error_log('Error IA startLeccion: ' . $e->getMessage());

            // Fallback local para no romper la experiencia si OpenAI falla
            $messages = self::buildInitialMessages(
                $leccion->titulo,
                $habilidad->nombre,
                $contenido
            );
        }

        // 8) Responder JSON
        self::jsonResponse([
            'ok' => true,
            'error' => null,
            'lesson' => [
                'id' => (int)$leccion->id,
                'title' => $leccion->titulo,
                'order' => (int)$leccion->orden,
                'type' => $tipoLeccion,
                'skill' => [
                    'id' => (int)$habilidad->id,
                    'name' => $habilidad->nombre
                ]
            ],
            'session' => [
                'lessonId' => (int)$leccion->id,
                'skillId' => (int)$habilidad->id,
                'currentStage' => 'intro',
                'nextExpectedAction' => 'advance',
                'inputEnabled' => false,
                'requiresUserResponse' => false,
                'completed' => false,
                'attempts' => [
                    'microPractice' => 0,
                    'miniEvaluation' => 0
                ],
                'limits' => [
                    'microPractice' => 3,
                    'miniEvaluation' => 3
                ],
                'failed' => false
            ],
            'content' => $contenido,
            'messages' => $messages,
            'ui' => [
                'showTyping' => true,
                'showAvatarSpeaking' => true,
                'composerPlaceholder' => 'Espera la explicación del asistente...',
                'focusInput' => false,
                'showReturnButton' => false
            ]
        ], 200);
    }

    public static function turnLeccion()
    {
        // -------------------- 1) VALIDAR AUTENTICACIÓN --------------------
        // Verificamos que el usuario siga autenticado antes de procesar cualquier turno.
        // Si no hay sesión válida, devolvemos error JSON 401.
        if (!isAuth()) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Tu sesión ha expirado.'
                ],
                'redirectTo' => '/'
            ], 401);
        }

        // Obtenemos el id del usuario desde la sesión.
        // Si no existe o no es válido, también devolvemos 401.
        $idUsuario = (int) ($_SESSION['id'] ?? 0);
        if ($idUsuario <= 0) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'No se pudo identificar al usuario.'
                ],
                'redirectTo' => '/'
            ], 401);
        }

        // -------------------- 2) LEER LOS DATOS ENVIADOS POR EL FRONTEND --------------------
        // Obtenemos el request payload (normalmente JSON enviado desde fetch).
        $input = self::getRequestData();

        // lessonId = id de la lección que el frontend dice estar trabajando
        // action   = acción que quiere ejecutar el frontend (advance o reply)
        // message  = texto del usuario, solo aplica cuando action = reply
        $lessonId = isset($input['lessonId']) ? (int) $input['lessonId'] : 0;
        $action   = isset($input['action']) ? trim((string)$input['action']) : '';
        $message  = isset($input['message']) ? trim((string)$input['message']) : '';

        // Validamos que al menos lleguen lessonId y action.
        // Si no llegan, el request está incompleto.
        if ($lessonId <= 0 || $action === '') {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'INVALID_PAYLOAD',
                    'message' => 'lessonId y action son obligatorios.'
                ]
            ], 422);
        }

        // -------------------- 3) VALIDAR QUE EXISTA EL ESTADO TEMPORAL DE LA LECCIÓN --------------------
        // startLeccion() creó un estado en $_SESSION['lesson_flow'].
        // Si no existe, significa que el usuario intentó usar turnLeccion()
        // sin haber iniciado correctamente la lección.
        if (!isset($_SESSION['lesson_flow']) || !is_array($_SESSION['lesson_flow'])) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'SESSION_STATE_MISSING',
                    'message' => 'No existe una sesión activa de lección. Inicia la lección nuevamente.'
                ]
            ], 409);
        }

        // Guardamos una referencia al estado de la lección.
        // Ojo: el & significa que $flow NO es una copia,
        // sino una referencia directa a $_SESSION['lesson_flow'].
        // Entonces, si modificamos $flow, también se modifica la sesión.
        $flow = &$_SESSION['lesson_flow'];

        // Validamos que la lección enviada por el frontend coincida
        // con la lección guardada en la sesión activa.
        // Esto evita que el frontend "inyecte" otro lessonId.
        if ((int)($flow['lessonId'] ?? 0) !== $lessonId) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'INVALID_STAGE',
                    'message' => 'La lección enviada no coincide con la sesión activa.'
                ]
            ], 409);
        }

        // Si la lección ya estaba completada, no dejamos seguir procesando turnos.
        if (!empty($flow['completed'])) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'LESSON_ALREADY_COMPLETED',
                    'message' => 'Esta lección ya fue completada.'
                ],
                'redirectTo' => '/aprendizaje'
            ], 409);
        }

        // -------------------- 4) LEER EL ESTADO ACTUAL DE LA MÁQUINA --------------------
        // currentStage = en qué etapa exacta va la lección
        // content      = contenido parseado de la descripción de la lección
        // lessonType   = tipo de lección: standard o integrator
        $currentStage = $flow['currentStage'] ?? 'intro';
        $content      = $flow['content'] ?? [];
        $lessonType   = $flow['lessonType'] ?? 'standard';

        // Variables auxiliares que luego devolveremos al frontend.
        $messages = [];
        $evaluation = null;
        $progress = null;

        //Datos reales de la lección y habilidad
        $lesson = Lecciones::find($lessonId);
        $skillName = 'Habilidad';
        $lessonTittle = 'Lección';

        if ($lesson) {
            $lessonTittle = $lesson->titulo ?? 'Lección';

            $skill = HabilidadesBlandas::find((int)$lesson->id_habilidades);
            if ($skill) {
                $skillName = $skill->nombre ?? 'Habilidad';
            }
        }

        // -------------------- 5) MÁQUINA DE ESTADOS --------------------
        // Aquí definimos qué hacer dependiendo de la etapa actual.
        switch ($currentStage) {

            // ============================================================
            // ETAPA: intro
            // ============================================================
            case 'intro':
                // En intro el frontend debe pedir avanzar, no responder.
                if ($action !== 'advance') {
                    self::invalidActionResponse($currentStage, 'advance');
                }

                // Construimos los mensajes que lanzan la micro-práctica.
                try {
                    $lessonAIService = new LessonAIService();
                    $userName = $_SESSION['nombres'] ?? 'Estudiante';

                    $messages = $lessonAIService->generateMicroPracticePromptMessages(
                        $lessonTittle,
                        $skillName,
                        $content,
                        $lessonType,
                        $userName
                    );
                } catch (\Exception $e) {
                    error_log('Error IA turnLeccion intro: ' . $e->getMessage());

                    // Fallback local para no romper el flujo si OpenAI falla
                    $messages = self::buildMicroPracticePromptMessages($content, $lessonType);
                }

                // Actualizamos el estado:
                // ahora el sistema espera una respuesta del usuario.
                $flow['currentStage'] = 'micro_practice_answer';
                $flow['nextExpectedAction'] = 'reply';
                $flow['inputEnabled'] = true;
                $flow['requiresUserResponse'] = true;

                // Respondemos al frontend con el nuevo estado y los mensajes.
                self::jsonResponse([
                    'ok' => true,
                    'error' => null,
                    'session' => self::sessionPayload($flow),
                    'messages' => $messages,
                    'ui' => [
                        'showTyping' => true,
                        'showAvatarSpeaking' => true,
                        'composerPlaceholder' => 'Escribe tu respuesta...',
                        'focusInput' => true,
                        'showReturnButton' => false
                    ]
                ]);
                break;

            // ============================================================
            // ETAPA: micro_practice_answer y micro_practice_answer_retry
            // ============================================================
            case 'micro_practice_answer':
            case 'micro_practice_answer_retry':
                if ($action !== 'reply') {
                    self::invalidActionResponse($currentStage, 'reply');
                }

                $basicValidation = self::validateBasicMicroPracticeAnswer($message);

                // Si falla el filtro local, no gastamos tokens
                if (!$basicValidation['valid']) {
                    $flow['attempts']['microPractice'] = (int)($flow['attempts']['microPractice'] ?? 0) + 1;

                    $remainingAttempts = (int)$flow['limits']['microPractice'] - (int)$flow['attempts']['microPractice'];

                    if ($remainingAttempts <= 0) {
                        self::buildFailedLessonResponse(
                            $flow,
                            'No se pudo completar esta lección porque no se alcanzó una respuesta válida en la micro-práctica. Puedes volver a intentarlo más adelante.',
                            $message
                        );
                    }

                    $flow['currentStage'] = 'micro_practice_answer_retry';
                    $flow['nextExpectedAction'] = 'reply';
                    $flow['inputEnabled'] = true;
                    $flow['requiresUserResponse'] = true;

                    $warning = self::buildAttemptsWarningMessage($remainingAttempts);

                    self::jsonResponse([
                        'ok' => true,
                        'error' => null,
                        'session' => self::sessionPayload($flow),
                        'evaluation' => [
                            'accepted' => false,
                            'needsRetry' => true,
                            'retryReason' => $basicValidation['reason']
                        ],
                        'messages' => [
                            [
                                'id' => 'msg_u_' . uniqid(),
                                'role' => 'user',
                                'type' => 'text',
                                'text' => $message
                            ],
                            [
                                'id' => 'msg_a_' . uniqid(),
                                'role' => 'assistant',
                                'type' => 'text',
                                'text' => 'Tu respuesta todavía no cumple con lo mínimo que necesito para continuar. Intenta responder de forma más clara y concreta.'
                            ],
                            [
                                'id' => 'msg_a_' . uniqid(),
                                'role' => 'assistant',
                                'type' => 'text',
                                'text' => $warning
                            ]
                        ],
                        'ui' => [
                            'showTyping' => true,
                            'showAvatarSpeaking' => true,
                            'composerPlaceholder' => 'Amplía tu respuesta...',
                            'focusInput' => true,
                            'showReturnButton' => false
                        ]
                    ]);
                }

                try {
                    $lessonAIService = new LessonAIService();
                    $userName = $_SESSION['nombres'] ?? 'Estudiante';

                    $aiEvaluation = $lessonAIService->evaluateMicroPracticeAnswer(
                        $lessonTittle,
                        $skillName,
                        $message,
                        $content,
                        $lessonType,
                        $userName
                    );
                    if (!$aiEvaluation['accepted']) {
                        $flow['attempts']['microPractice'] = (int)($flow['attempts']['microPractice'] ?? 0) + 1;

                        $remainingAttempts = (int)$flow['limits']['microPractice'] - (int)$flow['attempts']['microPractice'];

                        if ($remainingAttempts <= 0) {
                            self::buildFailedLessonResponse(
                                $flow,
                                'No se pudo completar esta lección porque no se alcanzó una respuesta válida en la micro-práctica. Puedes volver a intentarlo más adelante.',
                                $message
                            );
                        }

                        $flow['currentStage'] = 'micro_practice_answer_retry';
                        $flow['nextExpectedAction'] = 'reply';
                        $flow['inputEnabled'] = true;
                        $flow['requiresUserResponse'] = true;

                        $messages = $lessonAIService->normalizeMessages($aiEvaluation['messages'], 'msg_ai_micro_retry_');
                        $messages[] = [
                            'id' => 'msg_a_' . uniqid(),
                            'role' => 'assistant',
                            'type' => 'text',
                            'text' => self::buildAttemptsWarningMessage($remainingAttempts)
                        ];

                        self::jsonResponse([
                            'ok' => true,
                            'error' => null,
                            'session' => self::sessionPayload($flow),
                            'evaluation' => [
                                'accepted' => false,
                                'needsRetry' => true,
                                'retryReason' => $aiEvaluation['retryReason'],
                                'detectedIssues' => $aiEvaluation['detectedIssues']
                            ],
                            'messages' => $messages,
                            'ui' => [
                                'showTyping' => true,
                                'showAvatarSpeaking' => true,
                                'composerPlaceholder' => 'Intenta responder mejor...',
                                'focusInput' => true,
                                'showReturnButton' => false
                            ]
                        ]);
                    }

                    // Si la IA acepta la respuesta, recién ahí avanzamos
                    $flow['answers']['microPractice'] = $message;
                    $flow['currentStage'] = 'mini_eval_answer';
                    $flow['nextExpectedAction'] = 'reply';
                    $flow['inputEnabled'] = true;
                    $flow['requiresUserResponse'] = true;

                    $evaluation = [
                        'accepted' => true,
                        'needsRetry' => false,
                        'retryReason' => null,
                        'detectedIssues' => []
                    ];

                    try {
                        $messages = $lessonAIService->generateMiniEvaluationMessages(
                            $lessonTittle,
                            $skillName,
                            $message,
                            $content,
                            $lessonType,
                            $userName
                        );
                    } catch (\Exception $e) {
                        error_log('Error IA turnLeccion mini-evaluación: ' . $e->getMessage());
                        $messages = self::buildMiniEvaluationMessages($message, $content, $lessonType);
                    }
                    self::jsonResponse([
                        'ok' => true,
                        'error' => null,
                        'session' => self::sessionPayload($flow),
                        'evaluation' => $evaluation,
                        'messages' => $messages,
                        'ui' => [
                            'showTyping' => true,
                            'showAvatarSpeaking' => true,
                            'composerPlaceholder' => 'Responde la mini-evaluación...',
                            'focusInput' => true,
                            'showReturnButton' => false
                        ]
                    ]);
                } catch (\Exception $e) {
                    error_log('Error IA evaluación micro-práctica: ' . $e->getMessage());

                    // fallback si falla OpenAI: usar validación local conservadora
                    $flow['attempts']['microPractice'] = (int)($flow['attempts']['microPractice'] ?? 0) + 1;
                    $remainingAttempts = (int)$flow['limits']['microPractice'] - (int)$flow['attempts']['microPractice'];

                    if ($remainingAttempts <= 0) {
                        self::buildFailedLessonResponse(
                            $flow,
                            'No se pudo completar esta lección porque no se alcanzó una respuesta válida en la micro-práctica. Puedes volver a intentarlo más adelante.',
                            $message
                        );
                    }
                    $flow['currentStage'] = 'micro_practice_answer_retry';
                    $flow['nextExpectedAction'] = 'reply';
                    $flow['inputEnabled'] = true;
                    $flow['requiresUserResponse'] = true;

                    self::jsonResponse([
                        'ok' => true,
                        'error' => null,
                        'session' => self::sessionPayload($flow),
                        'evaluation' => [
                            'accepted' => false,
                            'needsRetry' => true,
                            'retryReason' => 'EVALUATION_ERROR'
                        ],
                        'messages' => [
                            [
                                'id' => 'msg_u_' . uniqid(),
                                'role' => 'user',
                                'type' => 'text',
                                'text' => $message
                            ],
                            [
                                'id' => 'msg_a_' . uniqid(),
                                'role' => 'assistant',
                                'type' => 'text',
                                'text' => 'No pude evaluar correctamente tu respuesta en este momento. Intenta reformularla de manera más clara y concreta.'
                            ],
                            [
                                'id' => 'msg_a_' . uniqid(),
                                'role' => 'assistant',
                                'type' => 'text',
                                'text' => self::buildAttemptsWarningMessage($remainingAttempts)
                            ]
                        ],
                        'ui' => [
                            'showTyping' => true,
                            'showAvatarSpeaking' => true,
                            'composerPlaceholder' => 'Reformula tu respuesta...',
                            'focusInput' => true,
                            'showReturnButton' => false
                        ]
                    ]);
                }
                break;
            // ============================================================
            // ETAPA: mini_eval_answer
            // ============================================================
            case 'mini_eval_answer':
                // En esta etapa el frontend también debe responder.
                if ($action !== 'reply') {
                    self::invalidActionResponse($currentStage, 'reply');
                }

                // Validamos que la mini-evaluación no venga vacía.
                if ($message === '') {
                    self::jsonResponse([
                        'ok' => false,
                        'error' => [
                            'code' => 'EMPTY_MESSAGE',
                            'message' => 'Debes responder la mini-evaluación.'
                        ]
                    ], 422);
                }

                // -------------------- VALIDACIÓN BÁSICA (SIN IA) --------------------
                // Esto evita gastar tokens si la respuesta es demasiado corta o inválida.
                if (strlen($message) < 8) {

                    $flow['attempts']['miniEvaluation'] = (int)($flow['attempts']['miniEvaluation'] ?? 0) + 1;

                    $remainingAttempts =
                        (int)$flow['limits']['miniEvaluation'] -
                        (int)$flow['attempts']['miniEvaluation'];

                    if ($remainingAttempts <= 0) {

                        self::buildFailedLessonResponse(
                            $flow,
                            'No se pudo completar esta lección porque no se alcanzó una respuesta válida en la mini-evaluación.',
                            $message
                        );
                    }

                    $warning = self::buildAttemptsWarningMessage($remainingAttempts);

                    self::jsonResponse([
                        'ok' => true,
                        'error' => null,
                        'session' => self::sessionPayload($flow),
                        'evaluation' => [
                            'accepted' => false,
                            'needsRetry' => true,
                            'retryReason' => 'ANSWER_TOO_SHORT'
                        ],
                        'messages' => [
                            [
                                'id' => 'msg_u_' . uniqid(),
                                'role' => 'user',
                                'type' => 'text',
                                'text' => $message
                            ],
                            [
                                'id' => 'msg_a_' . uniqid(),
                                'role' => 'assistant',
                                'type' => 'text',
                                'text' => 'Tu respuesta es demasiado breve. Intenta explicar mejor tu idea.'
                            ],
                            [
                                'id' => 'msg_a_' . uniqid(),
                                'role' => 'assistant',
                                'type' => 'text',
                                'text' => $warning
                            ]
                        ],
                        'ui' => [
                            'showTyping' => true,
                            'showAvatarSpeaking' => true,
                            'composerPlaceholder' => 'Amplía tu respuesta...',
                            'focusInput' => true,
                            'showReturnButton' => false
                        ]
                    ]);
                }

                // -------------------- EVALUACIÓN CON IA --------------------
                try {

                    $lessonAIService = new LessonAIService();
                    $userName = $_SESSION['nombres'] ?? 'Estudiante';

                    $aiEvaluation = $lessonAIService->evaluateMiniEvaluationAnswer(
                        $lessonTittle,
                        $skillName,
                        $message,
                        $content,
                        $flow['answers'],
                        $lessonType,
                        $userName
                    );

                    // Si la IA dice que NO es válida
                    if (!$aiEvaluation['accepted']) {

                        $flow['attempts']['miniEvaluation'] =
                            (int)($flow['attempts']['miniEvaluation'] ?? 0) + 1;

                        $remainingAttempts =
                            (int)$flow['limits']['miniEvaluation'] -
                            (int)$flow['attempts']['miniEvaluation'];

                        if ($remainingAttempts <= 0) {

                            self::buildFailedLessonResponse(
                                $flow,
                                'No se pudo completar esta lección porque no se alcanzó una respuesta válida en la mini-evaluación.',
                                $message
                            );
                        }

                        $messages = $lessonAIService->normalizeMessages(
                            $aiEvaluation['messages'],
                            'msg_ai_mini_retry_'
                        );

                        $messages[] = [
                            'id' => 'msg_a_' . uniqid(),
                            'role' => 'assistant',
                            'type' => 'text',
                            'text' => self::buildAttemptsWarningMessage($remainingAttempts)
                        ];

                        self::jsonResponse([
                            'ok' => true,
                            'error' => null,
                            'session' => self::sessionPayload($flow),
                            'evaluation' => [
                                'accepted' => false,
                                'needsRetry' => true,
                                'retryReason' => $aiEvaluation['retryReason'] ?? null,
                                'detectedIssues' => $aiEvaluation['detectedIssues'] ?? []
                            ],
                            'messages' => $messages,
                            'ui' => [
                                'showTyping' => true,
                                'showAvatarSpeaking' => true,
                                'composerPlaceholder' => 'Intenta responder mejor...',
                                'focusInput' => true,
                                'showReturnButton' => false
                            ]
                        ]);
                    }

                    // -------------------- RESPUESTA ACEPTADA --------------------
                    // Guardamos la respuesta del usuario.
                    $flow['answers']['miniEvaluation'] = $message;

                    // Marcamos la lección como terminada en la sesión.
                    $flow['currentStage'] = 'complete';
                    $flow['nextExpectedAction'] = null;
                    $flow['inputEnabled'] = false;
                    $flow['requiresUserResponse'] = false;
                    $flow['completed'] = true;

                    // Persistimos el completado en la BD.
                    self::markLessonAsCompleted($idUsuario, $lessonId);

                    // Generamos evaluación final estructurada.
                    $evaluation = self::buildFinalEvaluation($flow['answers'], $content);

                    // Mensaje real del usuario para que se vea en el chat
                    $userFinalMessage = [
                        [
                            'id' => 'msg_u_' . uniqid(),
                            'role' => 'user',
                            'type' => 'text',
                            'text' => $message
                        ]
                    ];

                    // Generamos los mensajes finales del asistente.
                    try {

                        $finalAssistantMessages = $lessonAIService->generateFinalFeedbackMessages(
                            $lessonTittle,
                            $skillName,
                            $flow['answers'],
                            $content,
                            $lessonType,
                            $userName
                        );
                    } catch (\Exception $e) {

                        error_log('Error IA turnLeccion feedback final: ' . $e->getMessage());

                        // Fallback local para no romper el flujo si OpenAI falla
                        $finalAssistantMessages = self::buildFinalFeedbackMessages($flow['answers'], $content);
                    }
                    // El chat solo mostrará el último mensaje real del usuario.
                    // El feedback final se mostrará en el modal.
                    $messages = $userFinalMessage;

                    $progress = [
                        'lessonCompleted' => true,
                        'completedAt' => date('Y-m-d H:i:s'),
                        'redirectTo' => '/aprendizaje'
                    ];

                    self::jsonResponse([
                        'ok' => true,
                        'error' => null,
                        'session' => self::sessionPayload($flow),
                        'evaluation' => $evaluation,
                        'messages' => $messages,
                        'completionModal' => [
                            'type' => 'success',
                            'title' => 'Lección completada',
                            'messages' => $finalAssistantMessages,
                            'buttonText' => 'Continuar',
                            'redirectTo' => '/aprendizaje'
                        ],
                        'progress' => $progress,
                        'ui' => [
                            'showTyping' => true,
                            'showAvatarSpeaking' => true,
                            'composerPlaceholder' => 'Lección completada',
                            'focusInput' => false,
                            'showReturnButton' => true
                        ]
                    ]);
                } catch (\Exception $e) {

                    error_log('Error IA evaluación mini-evaluación: ' . $e->getMessage());

                    $flow['attempts']['miniEvaluation'] =
                        (int)($flow['attempts']['miniEvaluation'] ?? 0) + 1;

                    $remainingAttempts =
                        (int)$flow['limits']['miniEvaluation'] -
                        (int)$flow['attempts']['miniEvaluation'];

                    if ($remainingAttempts <= 0) {

                        self::buildFailedLessonResponse(
                            $flow,
                            'No se pudo completar esta lección porque no se alcanzó una respuesta válida en la mini-evaluación.',
                            $message
                        );
                    }

                    self::jsonResponse([
                        'ok' => true,
                        'error' => null,
                        'session' => self::sessionPayload($flow),
                        'messages' => [
                            [
                                'id' => 'msg_u_' . uniqid(),
                                'role' => 'user',
                                'type' => 'text',
                                'text' => $message
                            ],
                            [
                                'id' => 'msg_a_' . uniqid(),
                                'role' => 'assistant',
                                'type' => 'text',
                                'text' => 'No pude evaluar correctamente tu respuesta. Intenta reformularla.'
                            ],
                            [
                                'id' => 'msg_a_' . uniqid(),
                                'role' => 'assistant',
                                'type' => 'text',
                                'text' => self::buildAttemptsWarningMessage($remainingAttempts)
                            ]
                        ],
                        'ui' => [
                            'showTyping' => true,
                            'showAvatarSpeaking' => true,
                            'composerPlaceholder' => 'Reformula tu respuesta...',
                            'focusInput' => true,
                            'showReturnButton' => false
                        ]
                    ]);
                }

                break;

            // ============================================================
            // ETAPA: complete
            // ============================================================
            case 'complete':
                // Si el frontend intenta seguir interactuando después de completar,
                // respondemos con error controlado.
                self::jsonResponse([
                    'ok' => false,
                    'error' => [
                        'code' => 'LESSON_ALREADY_COMPLETED',
                        'message' => 'La lección ya fue completada.'
                    ],
                    'redirectTo' => '/aprendizaje'
                ], 409);
                break;

            // ============================================================
            // ESTADO NO RECONOCIDO
            // ============================================================
            default:
                self::jsonResponse([
                    'ok' => false,
                    'error' => [
                        'code' => 'INVALID_STAGE',
                        'message' => 'El estado actual de la lección no es válido.'
                    ]
                ], 409);
        }
    }
    //---------------------------HELPERS startLeccion---------------------------//
    /**
     * Esta función permite devolver una respuesta en formato JSON desde el backend.
     * Recibe un array de datos y un código de estado HTTP opcional (por defecto 200).
     */
    private static function jsonResponse(array $data, int $statusCode = 200): void
    {
        //Código HTTP
        http_response_code($statusCode);
        //Encabezado para indicar que la respuesta es JSON
        header('Content-Type: application/json; charset=UTF-8');
        //Codificar el array de datos a JSON y devolverlo
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Lee application/json y usa $_POST como fallback.
     */
    private static function getRequestData(): array
    {
        //Intentamos leer el cuerpo de la petición como JSON
        $raw = file_get_contents('php://input');
        if ($raw) {
            /*Convertimos el JSON en array asociativo [
                'lessonId' => 5,
                'skillId' => 2
            ]*/
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return $_POST ?? [];
    }

    /**
     * Convierte la descripción de la lección (Texto plano) en bloques estructurados (Array).
     */
    private static function parseLessonDescription(string $descripcion): array
    {
        //Separamos el texto en un arreglo línea por línea.
        $lineas = preg_split('/\R/u', trim($descripcion));
        //Creamos un array vacío con la estructura final, nos permitirpa que siempre exista la misma estructura aunque alguna sección no venga.
        $resultado = [
            'objective' => '',
            'keyConcepts' => [],
            'commonMistakes' => [],
            'microPractice' => '',
            'miniEvaluation' => '',
            'expectedSummary' => '',
            'scenario' => '',
            'evaluationCriteria' => [],
            'resultFormat' => ''
        ];
        //Definimos un mapa de etiquetas a claves del resultado para facilitar la detección de secciones.
        $mapaSecciones = [
            'Objetivo:' => 'objective',
            'Conceptos clave:' => 'keyConcepts',
            'Errores comunes:' => 'commonMistakes',
            'Micro-práctica (Avatar):' => 'microPractice',
            'Micro-práctica:' => 'microPractice',
            'Mini-evaluación:' => 'miniEvaluation',
            'Resumen esperado:' => 'expectedSummary',
            'Escenario:' => 'scenario',
            'Evaluación:' => 'evaluationCriteria',
            'Resultado:' => 'resultFormat'
        ];
        //Recorremos linea por linea para detectar las secciones y llenar el resultado. Mantenemos una variable para saber cuál es la sección actual mientras recorremos las líneas.
        //Cada linea se analiza para saber si es el inicio de una nueva sección o si es contenido que pertenece a la sección actual.
        $seccionActual = null;

        foreach ($lineas as $linea) {
            $linea = trim($linea);
            //Si la linea está vacía, la ignoramos
            if ($linea === '') {
                continue;
            }
            //Reiniciamos el detecto de sección (Cada línea empieza suponiendo que no es una sección nueva.)
            $seccionDetectada = null;
            //Recorremos el mapa de secciones
            foreach ($mapaSecciones as $etiqueta => $clave) {
                //Detectar si la linea empieza con una etiqueta (Ejemplo: "Objetivo:") usando str_starts_with, esto nos dice que encontramos el inicio de una nueva sección.
                if (str_starts_with($linea, $etiqueta)) { //Si la linea si es una etiqueta entonces...
                    //Sirve para marcar que esta línea sí abrió una nueva sección
                    $seccionDetectada = $clave;
                    //Actualizamos la sección actual.
                    $seccionActual = $clave;
                    //Quitamos la etiqueta y devolvemos solo el contenido que viene después
                    //mb_strlen calcula cuantos caracteres tiene la etiqueta, y mb_substr devuelve el texto a partir de esa posición, o sea, lo que viene después de la etiqueta.
                    $contenido = trim(mb_substr($linea, mb_strlen($etiqueta)));
                    //Si había contenido en esa misma linea después de la etiqueta, lo agregamos a la sección actual. Esto es útil para casos como "Objetivo: Aprender a usar el asistente", donde el objetivo viene en la misma línea que la etiqueta.
                    if ($contenido !== '') {
                        self::appendToSection($resultado, $seccionActual, $contenido);
                    }
                    //Rompemos el ciclo de comparación al encontrar la etiqueta
                    break;
                }
            }
            /*Esto devuelve:
            $etiqueta = "Objetivo:"
            $clave = "objective"
            Luego:
            $etiqueta = "Conceptos clave:"
            $clave = "keyConcepts"
            y así sucesivamente.
            */
            if ($seccionDetectada !== null) {
                continue;
            }

            if ($seccionActual !== null) {
                self::appendToSection($resultado, $seccionActual, $linea);
            }
        }

        return $resultado;
    }

    /**
     * Agrega una línea a la sección correcta dentro del array $resultado.
     */
    private static function appendToSection(array &$resultado, string $seccion, string $valor): void
    {
        $valor = trim($valor);
        //Eliminamos simbolos al inicio de la línea como • - * que suelen usarse para listas, esto nos ayuda a tener el contenido más limpio. Solo se eliminan si están al inicio de la línea seguidos de un espacio.
        $valor = preg_replace('/^[•\-\*]\s*/u', '', $valor);
        //¿Cómo sabe si guardar como texto o como array?
        if ($valor === '') {
            return;
        }

        $seccionesArray = ['keyConcepts', 'commonMistakes', 'evaluationCriteria'];
        //Si la sección actual está ahí, entonces hace: $resultado[$seccion][] = $valor; (O sea, lo metemos como un elemento de array)
        if (in_array($seccion, $seccionesArray, true)) {
            $resultado[$seccion][] = $valor;
            return;
        }
        //Si la sección no es de tipo array, entonces concatenamos el texto: $resultado[$seccion] .= ' ' . $valor;
        if ($resultado[$seccion] === '') {
            $resultado[$seccion] = $valor;
        } else {
            $resultado[$seccion] .= ' ' . $valor;
        }
    }

    /**
     * Detecta si la lección es estándar o integradora.
     * Si tiene escenario, criterios de evaluación o formato de resultado, la consideramos integradora, sino estándar.
     */
    private static function inferLessonType(array $contenido): string
    {
        if (!empty($contenido['scenario']) || !empty($contenido['evaluationCriteria']) || !empty($contenido['resultFormat'])) {
            return 'integrator';
        }

        return 'standard';
    }

    /**
     * Genera mensajes iniciales sin IA real.
     */
    private static function buildInitialMessages(string $tituloLeccion, string $nombreHabilidad, array $contenido): array
    {
        $mensajes = [];

        $mensajes[] = [
            'id' => 'msg_a1',
            'role' => 'assistant',
            'type' => 'text',
            'text' => "Hola, bienvenido a la lección \"{$tituloLeccion}\" de {$nombreHabilidad}."
        ];

        if (!empty($contenido['objective'])) {
            $mensajes[] = [
                'id' => 'msg_a2',
                'role' => 'assistant',
                'type' => 'text',
                'text' => "Objetivo de esta lección: {$contenido['objective']}"
            ];
        }

        if (!empty($contenido['keyConcepts'])) {
            $conceptos = implode(', ', $contenido['keyConcepts']);
            $mensajes[] = [
                'id' => 'msg_a3',
                'role' => 'assistant',
                'type' => 'text',
                'text' => "Primero trabajaremos estos conceptos clave: {$conceptos}."
            ];
        } elseif (!empty($contenido['scenario'])) {
            $mensajes[] = [
                'id' => 'msg_a3',
                'role' => 'assistant',
                'type' => 'text',
                'text' => "En esta lección trabajaremos con un escenario práctico que te ayudará a aplicar lo aprendido."
            ];
        }

        if (!empty($contenido['commonMistakes'])) {
            $errores = implode(', ', $contenido['commonMistakes']);
            $mensajes[] = [
                'id' => 'msg_a4',
                'role' => 'assistant',
                'type' => 'text',
                'text' => "También revisaremos errores comunes como: {$errores}."
            ];
        }

        return $mensajes;
    }
    //---------------------------FIN HELPERS startLeccion---------------------------//

    //---------------------------HELPERS turnLeccion---------------------------//
    /**
     * Devuelve un error cuando el frontend manda una acción que no corresponde a la etapa actual.
     */
    private static function invalidActionResponse(string $currentStage, string $expectedAction): void
    {
        self::jsonResponse([
            'ok' => false,
            'error' => [
                'code' => 'INVALID_ACTION',
                'message' => "La etapa actual ({$currentStage}) esperaba la acción {$expectedAction}."
            ]
        ], 409);
    }

    /**
     * Toma el estado interno de la sesión ($flow) y lo convierte en un array limpio para enviarlo al frontend.
     */
    private static function sessionPayload(array $flow): array
    {
        return [
            'lessonId' => (int)($flow['lessonId'] ?? 0),
            'skillId' => (int)($flow['skillId'] ?? 0),
            'currentStage' => $flow['currentStage'] ?? null,
            'nextExpectedAction' => $flow['nextExpectedAction'] ?? null,
            'inputEnabled' => (bool)($flow['inputEnabled'] ?? false),
            'requiresUserResponse' => (bool)($flow['requiresUserResponse'] ?? false),
            'completed' => (bool)($flow['completed'] ?? false),
            'answers' => $flow['answers'] ?? [],
            'attempts' => $flow['attempts'] ?? []
        ];
    }

    /**
     * Decide si una respuesta del usuario es suficientemente buena para seguir avanzando.
     */
    private static function isAnswerAcceptable(string $message): bool
    {
        $message = trim($message);
        //mínimo 12 caracteres
        if (mb_strlen($message) < 12) {
            return false;
        }
        //mínimo 4 palabras
        $palabras = preg_split('/\s+/u', $message);
        return count($palabras) >= 4;
    }


    private static function validateBasicMicroPracticeAnswer(string $message): array
    {
        $message = trim(mb_strtolower($message));

        $invalidShortAnswers = [
            'no',
            'si',
            'sí',
            'nose',
            'no se',
            'no sé',
            'xd',
            'asdf',
            '123',
            'ok',
            'idk'
        ];

        if ($message === '') {
            return [
                'valid' => false,
                'reason' => 'EMPTY'
            ];
        }

        if (in_array($message, $invalidShortAnswers, true)) {
            return [
                'valid' => false,
                'reason' => 'TOO_GENERIC'
            ];
        }

        if (mb_strlen($message) < 12) {
            return [
                'valid' => false,
                'reason' => 'TOO_SHORT'
            ];
        }

        $words = preg_split('/\s+/u', $message);
        if (count($words) < 4) {
            return [
                'valid' => false,
                'reason' => 'TOO_SHORT'
            ];
        }

        return [
            'valid' => true,
            'reason' => null
        ];
    }

    private static function buildAttemptsWarningMessage(int $remainingAttempts): string
    {
        if ($remainingAttempts <= 0) {
            return 'Has agotado los intentos disponibles para esta fase de la lección.';
        }

        if ($remainingAttempts === 1) {
            return 'Te queda 1 intento más para responder correctamente esta parte.';
        }

        return "Te quedan {$remainingAttempts} intentos más para responder correctamente esta parte.";
    }

    private static function buildFailedLessonResponse(array $flow, string $assistantMessage, string $userMessage = ''): void
    {
        $flow['currentStage'] = 'failed';
        $flow['nextExpectedAction'] = null;
        $flow['inputEnabled'] = false;
        $flow['requiresUserResponse'] = false;
        $flow['completed'] = false;
        $flow['failed'] = true;

        $messages = [];

        if ($userMessage !== '') {
            $messages[] = [
                'id' => 'msg_u_' . uniqid(),
                'role' => 'user',
                'type' => 'text',
                'text' => $userMessage
            ];
        }

        self::jsonResponse([
            'ok' => true,
            'error' => null,
            'session' => self::sessionPayload($flow),
            'messages' => $messages,
            'completionModal' => [
                'type' => 'failed',
                'title' => 'Lección no completada',
                'messages' => [
                    [
                        'id' => 'msg_a_' . uniqid(),
                        'role' => 'assistant',
                        'type' => 'text',
                        'text' => $assistantMessage
                    ]
                ],
                'buttonText' => 'Continuar',
                'redirectTo' => '/aprendizaje'
            ],
            'progress' => [
                'lessonCompleted' => false,
                'failed' => true,
                'redirectTo' => '/aprendizaje'
            ],
            'ui' => [
                'showTyping' => true,
                'showAvatarSpeaking' => true,
                'composerPlaceholder' => 'Lección no completada',
                'focusInput' => false,
                'showReturnButton' => true
            ]
        ]);
    }

    /**
     * Construye los mensajes que lanzan la primera actividad práctica (Genera el bloque de mensajes que abre la primera interacción del usuario.)
     */
    private static function buildMicroPracticePromptMessages(array $content, string $lessonType): array
    {
        if ($lessonType === 'integrator' && !empty($content['scenario'])) {
            return [
                [
                    'id' => 'msg_a_' . uniqid(),
                    'role' => 'assistant',
                    'type' => 'text',
                    'text' => 'Ahora vamos a pasar a una situación práctica.'
                ],
                [
                    'id' => 'msg_a_' . uniqid(),
                    'role' => 'assistant',
                    'type' => 'text',
                    'text' => $content['scenario']
                ],
                [
                    'id' => 'msg_a_' . uniqid(),
                    'role' => 'assistant',
                    'type' => 'text',
                    'text' => '¿Cómo responderías tú ante esta situación?'
                ]
            ];
        }

        $pregunta = $content['microPractice'] ?? 'Cuéntame tu respuesta con un ejemplo concreto.';

        return [
            [
                'id' => 'msg_a_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => 'Muy bien, ahora pasemos a la parte práctica.'
            ],
            [
                'id' => 'msg_a_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => $pregunta
            ]
        ];
    }

    /**
     * Genera los mensajes que conectan la primera respuesta del usuario con la mini evaluación.
     */
    private static function buildMiniEvaluationMessages(string $userMessage, array $content, string $lessonType): array
    {
        $feedbackBreve = 'Bien. Lo positivo es que ya conectaste tu respuesta con una experiencia o idea concreta.';

        if ($lessonType === 'integrator' && !empty($content['evaluationCriteria'])) {
            $criterios = implode(', ', $content['evaluationCriteria']);
            $pregunta = "Ahora evalúa tu propia respuesta usando estos criterios: {$criterios}. ¿Qué hiciste bien y qué mejorarías?";
        } else {
            $pregunta = $content['miniEvaluation'] ?? 'Ahora responde esta mini-evaluación con base en lo aprendido.';
        }

        return [
            [
                'id' => 'msg_u_' . uniqid(),
                'role' => 'user',
                'type' => 'text',
                'text' => $userMessage
            ],
            [
                'id' => 'msg_a_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => $feedbackBreve
            ],
            [
                'id' => 'msg_a_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => $pregunta
            ]
        ];
    }

    /**
     * Crea una estructura de evaluación final que genera fortalezas, mejoras y resumen final
     */
    private static function buildFinalEvaluation(array $answers, array $content): array
    {
        $strengths = [];
        $improvements = [];

        if (!empty($answers['microPractice']) && mb_strlen($answers['microPractice']) >= 20) {
            $strengths[] = 'Tu primera respuesta tuvo mejor desarrollo y mayor claridad.';
        } else {
            $improvements[] = 'Puedes ampliar más tus ejemplos para dar respuestas más sólidas.';
        }

        if (!empty($answers['miniEvaluation']) && mb_strlen($answers['miniEvaluation']) >= 20) {
            $strengths[] = 'Mostraste una reflexión adecuada en la mini-evaluación.';
        } else {
            $improvements[] = 'La mini-evaluación puede mejorar con una justificación más completa.';
        }

        if (empty($strengths)) {
            $strengths[] = 'Participaste activamente en la lección.';
        }

        if (empty($improvements)) {
            $improvements[] = 'Sigue practicando para responder con más seguridad y estructura.';
        }

        return [
            'accepted' => true,
            'needsRetry' => false,
            'finalFeedback' => [
                'strengths' => $strengths,
                'improvements' => $improvements,
                'summary' => $content['expectedSummary'] ?? 'Has completado la lección correctamente.'
            ]
        ];
    }

    /**
     * Genera los mensajes finales que el usuario verá al terminar la lección
     * Devolviendo el último mensaje del usuario, un mensaje del asistente diciendo que completó la parte práctica y un resumen final usando expectedSummary
     */
    private static function buildFinalFeedbackMessages(array $answers, array $content): array
    {
        $summary = $content['expectedSummary'] ?? 'Has completado la lección correctamente.';

        return [
            [
                'id' => 'msg_a_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => 'Muy bien. Ya completaste la parte práctica y la mini-evaluación de esta lección.'
            ],
            [
                'id' => 'msg_a_' . uniqid(),
                'role' => 'assistant',
                'type' => 'text',
                'text' => "Resumen final: {$summary}"
            ]
        ];
    }

    /**
     * Llama al modelo usuarios_lecciones para guardar en la base de datos que la lección ya fue completada.
     */
    private static function markLessonAsCompleted(int $idUsuario, int $lessonId): void
    {
        // 1) Marcar la lección como completada
        usuarios_lecciones::marcarComoCompletada($idUsuario, $lessonId);

        // 2) Obtener la habilidad de esa lección
        $lesson = Lecciones::find($lessonId);
        if(!$lesson){
            return;
        }
        $idHabilidad = (int)$lesson->id_habilidades;

        // 3) Recalcular progreso de habilidad
        usuarios_habilidades::recalcularProgresoHabilidad($idUsuario, $idHabilidad);
    }
    //---------------------------FIN HELPERS turnLeccion---------------------------//
}
