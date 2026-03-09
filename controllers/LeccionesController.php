<?php

namespace Controllers;

use Model\HabilidadesBlandas;
use Model\Lecciones;
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
            ], 401);
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
        $input = self::getRequestData();

        $idLeccion = isset($input['lessonId']) ? (int) $input['lessonId'] : 0;
        $idHabilidad = isset($input['skillId']) ? (int) $input['skillId'] : 0;

        if ($idLeccion <= 0 || $idHabilidad <= 0) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'INVALID_PAYLOAD',
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
                'microPracticeRetry' => 0
            ],
            'content' => $contenido,
            'lessonType' => $tipoLeccion
        ];

        // 7) Mensajes iniciales fijos (sin IA todavía)
        $messages = self::buildInitialMessages(
            $leccion->titulo,
            $habilidad->nombre,
            $contenido
        );

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
                    'microPracticeRetry' => 0
                ]
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

        $input = self::getRequestData();

        $lessonId = isset($input['lessonId']) ? (int) $input['lessonId'] : 0;
        $action   = isset($input['action']) ? trim((string)$input['action']) : '';
        $message  = isset($input['message']) ? trim((string)$input['message']) : '';

        if ($lessonId <= 0 || $action === '') {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'INVALID_PAYLOAD',
                    'message' => 'lessonId y action son obligatorios.'
                ]
            ], 422);
        }

        if (!isset($_SESSION['lesson_flow']) || !is_array($_SESSION['lesson_flow'])) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'SESSION_STATE_MISSING',
                    'message' => 'No existe una sesión activa de lección. Inicia la lección nuevamente.'
                ]
            ], 409);
        }

        $flow = &$_SESSION['lesson_flow'];

        if ((int)($flow['lessonId'] ?? 0) !== $lessonId) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'INVALID_STAGE',
                    'message' => 'La lección enviada no coincide con la sesión activa.'
                ]
            ], 409);
        }

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

        $currentStage = $flow['currentStage'] ?? 'intro';
        $content      = $flow['content'] ?? [];
        $lessonType   = $flow['lessonType'] ?? 'standard';

        $messages = [];
        $evaluation = null;
        $progress = null;

        switch ($currentStage) {
            case 'intro':
                if ($action !== 'advance') {
                    self::invalidActionResponse($currentStage, 'advance');
                }

                $messages = self::buildMicroPracticePromptMessages($content, $lessonType);

                $flow['currentStage'] = 'micro_practice_answer';
                $flow['nextExpectedAction'] = 'reply';
                $flow['inputEnabled'] = true;
                $flow['requiresUserResponse'] = true;

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

            case 'micro_practice_answer':
            case 'micro_practice_answer_retry':
                if ($action !== 'reply') {
                    self::invalidActionResponse($currentStage, 'reply');
                }

                if ($message === '') {
                    self::jsonResponse([
                        'ok' => false,
                        'error' => [
                            'code' => 'EMPTY_MESSAGE',
                            'message' => 'Debes escribir una respuesta.'
                        ]
                    ], 422);
                }

                $isValid = self::isAnswerAcceptable($message);

                if (!$isValid && (int)($flow['attempts']['microPracticeRetry'] ?? 0) < 1) {
                    $flow['attempts']['microPracticeRetry'] = 1;
                    $flow['currentStage'] = 'micro_practice_answer_retry';
                    $flow['nextExpectedAction'] = 'reply';
                    $flow['inputEnabled'] = true;
                    $flow['requiresUserResponse'] = true;

                    $evaluation = [
                        'accepted' => false,
                        'needsRetry' => true,
                        'retryReason' => 'TOO_SHORT'
                    ];

                    $messages = [
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
                            'text' => 'Vas bien, pero necesito un poco más de detalle. Responde con un ejemplo más concreto o explica mejor tu idea.'
                        ]
                    ];

                    self::jsonResponse([
                        'ok' => true,
                        'error' => null,
                        'session' => self::sessionPayload($flow),
                        'evaluation' => $evaluation,
                        'messages' => $messages,
                        'ui' => [
                            'showTyping' => true,
                            'showAvatarSpeaking' => true,
                            'composerPlaceholder' => 'Amplía tu respuesta...',
                            'focusInput' => true,
                            'showReturnButton' => false
                        ]
                    ]);
                }

                $flow['answers']['microPractice'] = $message;
                $flow['currentStage'] = 'mini_eval_answer';
                $flow['nextExpectedAction'] = 'reply';
                $flow['inputEnabled'] = true;
                $flow['requiresUserResponse'] = true;

                $evaluation = [
                    'accepted' => true,
                    'needsRetry' => false
                ];

                $messages = self::buildMiniEvaluationMessages($message, $content, $lessonType);

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
                break;

            case 'mini_eval_answer':
                if ($action !== 'reply') {
                    self::invalidActionResponse($currentStage, 'reply');
                }

                if ($message === '') {
                    self::jsonResponse([
                        'ok' => false,
                        'error' => [
                            'code' => 'EMPTY_MESSAGE',
                            'message' => 'Debes responder la mini-evaluación.'
                        ]
                    ], 422);
                }

                $flow['answers']['miniEvaluation'] = $message;
                $flow['currentStage'] = 'complete';
                $flow['nextExpectedAction'] = null;
                $flow['inputEnabled'] = false;
                $flow['requiresUserResponse'] = false;
                $flow['completed'] = true;

                self::markLessonAsCompleted($idUsuario, $lessonId);

                $evaluation = self::buildFinalEvaluation($flow['answers'], $content);

                $messages = self::buildFinalFeedbackMessages($flow['answers'], $content);

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
                    'progress' => $progress,
                    'ui' => [
                        'showTyping' => true,
                        'showAvatarSpeaking' => true,
                        'composerPlaceholder' => 'Lección completada',
                        'focusInput' => false,
                        'showReturnButton' => true
                    ]
                ]);
                break;

            case 'complete':
                self::jsonResponse([
                    'ok' => false,
                    'error' => [
                        'code' => 'LESSON_ALREADY_COMPLETED',
                        'message' => 'La lección ya fue completada.'
                    ],
                    'redirectTo' => '/aprendizaje'
                ], 409);
                break;

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
     * Devuelve JSON consistente y finaliza ejecución.
     */
    private static function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Lee application/json y usa $_POST como fallback.
     */
    private static function getRequestData(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return $_POST ?? [];
    }

    /**
     * Convierte la descripción de la lección en bloques estructurados.
     */
    private static function parseLessonDescription(string $descripcion): array
    {
        $lineas = preg_split('/\R/u', trim($descripcion));
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

        $seccionActual = null;

        foreach ($lineas as $linea) {
            $linea = trim($linea);

            if ($linea === '') {
                continue;
            }

            $seccionDetectada = null;
            foreach ($mapaSecciones as $etiqueta => $clave) {
                if (str_starts_with($linea, $etiqueta)) {
                    $seccionDetectada = $clave;
                    $seccionActual = $clave;

                    $contenido = trim(mb_substr($linea, mb_strlen($etiqueta)));
                    if ($contenido !== '') {
                        self::appendToSection($resultado, $seccionActual, $contenido);
                    }
                    break;
                }
            }

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
     * Agrega una línea a la sección correcta.
     */
    private static function appendToSection(array &$resultado, string $seccion, string $valor): void
    {
        $valor = trim($valor);
        $valor = preg_replace('/^[•\-\*]\s*/u', '', $valor);

        if ($valor === '') {
            return;
        }

        $seccionesArray = ['keyConcepts', 'commonMistakes', 'evaluationCriteria'];

        if (in_array($seccion, $seccionesArray, true)) {
            $resultado[$seccion][] = $valor;
            return;
        }

        if ($resultado[$seccion] === '') {
            $resultado[$seccion] = $valor;
        } else {
            $resultado[$seccion] .= ' ' . $valor;
        }
    }

    /**
     * Detecta si la lección es estándar o integradora.
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

    private static function isAnswerAcceptable(string $message): bool
    {
        $message = trim($message);

        if (mb_strlen($message) < 12) {
            return false;
        }

        $palabras = preg_split('/\s+/u', $message);
        return count($palabras) >= 4;
    }

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

    private static function buildFinalFeedbackMessages(array $answers, array $content): array
    {
        $summary = $content['expectedSummary'] ?? 'Has completado la lección correctamente.';

        return [
            [
                'id' => 'msg_u_' . uniqid(),
                'role' => 'user',
                'type' => 'text',
                'text' => $answers['miniEvaluation'] ?? ''
            ],
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
    private static function markLessonAsCompleted(int $idUsuario, int $lessonId): void
    {
        usuarios_lecciones::marcarComoCompletada($idUsuario, $lessonId);
    }
    //---------------------------FINHELPERS turnLeccion---------------------------//
}
