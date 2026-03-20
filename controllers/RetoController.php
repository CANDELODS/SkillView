<?php

namespace Controllers;

use Classes\ChallengeAIService;
use Model\HabilidadesBlandas;
use Model\Retos;
use Model\usuarios_habilidades;
use Model\usuarios_retos;
use MVC\Router;

class RetoController
{
    public static function reto(Router $router)
    {
        if (!isAuth()) {
            header('Location: /');
            exit;
        }

        $login = false;
        $datosUsuario = obtenerDatosUsuarioHeader($_SESSION['id']);

        $idReto = $_GET['id'] ?? null;
        if (!$idReto) {
            header('Location: /retos');
            exit;
        }

        $reto = Retos::find($idReto);
        if (!$reto) {
            header('Location: /retos');
            exit;
        }

        $habilidad = HabilidadesBlandas::find($reto->id_habilidades);
        $reto->nombreHabilidad = $habilidad ? $habilidad->nombre : 'Habilidad';

        $router->render('paginas/retos/reto', [
            'titulo' => $reto->nombre,
            'login' => $login,
            'nombreUsuario'    => $datosUsuario['nombreUsuario'],
            'inicialesUsuario' => $datosUsuario['inicialesUsuario'],
            'reto' => $reto
        ]);
    }

    public static function startChallenge()
    {
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

        $idUsuario = (int)$_SESSION['id'];
        $input = self::getRequestData();

        $challengeId = (int)($input['challengeId'] ?? 0);
        $skillId = (int)($input['skillId'] ?? 0);

        if ($challengeId <= 0 || $skillId <= 0) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'INVALID_PAYLOAD',
                    'message' => 'Los datos del reto son inválidos.'
                ]
            ], 422);
        }

        $reto = Retos::find($challengeId);

        if (!$reto) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'CHALLENGE_NOT_FOUND',
                    'message' => 'El reto solicitado no existe.'
                ]
            ], 404);
        }

        $habilidad = HabilidadesBlandas::find($reto->id_habilidades ?? 0);

        if (!$habilidad) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'SKILL_NOT_FOUND',
                    'message' => 'La habilidad asociada al reto no existe.'
                ]
            ], 404);
        }

        if ((int)$reto->id_habilidades !== $skillId) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'CHALLENGE_SKILL_MISMATCH',
                    'message' => 'El reto no pertenece a la habilidad indicada.'
                ]
            ], 409);
        }

        if ((int)($reto->habilitado ?? 0) !== 1) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'CHALLENGE_DISABLED',
                    'message' => 'Este reto no está disponible en este momento.'
                ]
            ], 409);
        }

        if ((int)($habilidad->habilitado ?? 0) !== 1) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'SKILL_DISABLED',
                    'message' => 'La habilidad asociada no está disponible.'
                ]
            ], 409);
        }

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

        self::clearChallengeFlow();

        $flow = self::buildChallengeFlow($reto, $habilidad, $idUsuario);
        $content = $flow['content'] ?? [];

        $challengeAI = new ChallengeAIService();
        $userName = trim((string)($_SESSION['nombre'] ?? $_SESSION['nombres'] ?? 'Estudiante'));

        try {
            $messages = $challengeAI->generateInitialMessages(
                $content['title'] ?? '',
                $content['skillName'] ?? 'Habilidad',
                $content,
                $userName
            );
        } catch (\Throwable $e) {
            $messages = self::buildInitialChallengeMessagesFallback($content, $userName);
        }

        self::saveChallengeFlow($flow);

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

    public static function turnChallenge()
    {
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

        $idUsuario = (int)$_SESSION['id'];
        $input = self::getRequestData();

        $challengeId = (int)($input['challengeId'] ?? 0);
        $action = trim((string)($input['action'] ?? ''));
        $message = trim((string)($input['message'] ?? ''));

        if ($challengeId <= 0 || $action === '') {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'INVALID_PAYLOAD',
                    'message' => 'La solicitud del reto es inválida.'
                ]
            ], 422);
        }

        $flow = self::getActiveChallengeFlow();

        if (!$flow) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'NO_ACTIVE_CHALLENGE_FLOW',
                    'message' => 'No hay un flujo de reto activo.'
                ]
            ], 409);
        }

        if ((int)($flow['userId'] ?? 0) !== $idUsuario) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'FLOW_USER_MISMATCH',
                    'message' => 'El flujo activo no pertenece al usuario autenticado.'
                ]
            ], 409);
        }

        if ((int)($flow['challengeId'] ?? 0) !== $challengeId) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'FLOW_CHALLENGE_MISMATCH',
                    'message' => 'El flujo activo no corresponde al reto solicitado.'
                ]
            ], 409);
        }

        if (!empty($flow['completed'])) {
            self::jsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'CHALLENGE_ALREADY_FINISHED',
                    'message' => 'Este flujo de reto ya finalizó.'
                ]
            ], 409);
        }

        $currentStage = (string)($flow['currentStage'] ?? 'intro');
        $content = $flow['content'] ?? [];
        $userName = trim((string)($_SESSION['nombre'] ?? $_SESSION['nombres'] ?? 'Estudiante'));
        $challengeAI = new ChallengeAIService();

        switch ($currentStage) {
            case 'intro':
                if ($action !== 'advance') {
                    self::invalidActionResponse($currentStage, 'advance');
                }

                try {
                    $messages = $challengeAI->generateChallengePromptMessages(
                        $content['title'] ?? '',
                        $content['skillName'] ?? 'Habilidad',
                        $content,
                        $userName
                    );
                } catch (\Throwable $e) {
                    $messages = self::buildChallengePromptFallback($content);
                }

                $flow['currentStage'] = 'challenge_answer';
                $flow['nextExpectedAction'] = 'reply';
                $flow['inputEnabled'] = true;
                $flow['requiresUserResponse'] = true;

                self::saveChallengeFlow($flow);

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
                if ($action !== 'reply') {
                    self::invalidActionResponse($currentStage, 'reply');
                }

                $basicValidation = self::validateBasicChallengeAnswer($message);

                if (!$basicValidation['valid']) {
                    $flow['attempts']['challengeAnswer'] = (int)($flow['attempts']['challengeAnswer'] ?? 0) + 1;
                    $remainingAttempts = self::remainingAttempts($flow);

                    $evaluation = [
                        'accepted' => false,
                        'needsRetry' => true,
                        'retryReason' => self::mapBasicValidationReasonToRetryReason($basicValidation['reason'] ?? null),
                        'detectedIssues' => [],
                        'scoreRatio' => 0,
                        'performanceLevel' => 'INSUFFICIENT',
                        'feedbackSummary' => $basicValidation['message'] ?? 'Tu respuesta necesita más desarrollo.'
                    ];

                    $flow['evaluation'] = $evaluation;

                    $userMessagePayload = [[
                        'id' => 'msg_u_' . uniqid(),
                        'role' => 'user',
                        'type' => 'text',
                        'text' => $message
                    ]];

                    if ($remainingAttempts <= 0) {
                        $flow = self::markChallengeFlowAsFailed($flow);
                        self::saveChallengeFlow($flow);

                        $chatMessages = $userMessagePayload;

                        $modalMessages = self::buildFailedChallengeModalMessages($flow, $evaluation);

                        self::jsonResponse(
                            self::buildFailedChallengeResponse($flow, $chatMessages, $modalMessages, $evaluation)
                        );
                    }

                    $flow['currentStage'] = 'challenge_answer_retry';
                    $flow['nextExpectedAction'] = 'reply';
                    $flow['inputEnabled'] = true;
                    $flow['requiresUserResponse'] = true;

                    self::saveChallengeFlow($flow);

                    $retryMessages = array_merge(
                        $userMessagePayload,
                        self::buildRetryMessagesFallback($evaluation, $remainingAttempts)
                    );

                    self::jsonResponse(
                        self::buildRetryResponse($flow, $retryMessages, $evaluation)
                    );
                }

                try {
                    $aiEvaluation = $challengeAI->evaluateChallengeAnswer(
                        $content['title'] ?? '',
                        $content['skillName'] ?? 'Habilidad',
                        $message,
                        $content,
                        [
                            'attemptNumber' => (int)($flow['attempts']['challengeAnswer'] ?? 0) + 1,
                            'maxAttempts' => (int)($flow['limits']['challengeAnswer'] ?? 3),
                            'maxPoints' => (int)($flow['maxScore'] ?? 0),
                        ],
                        $userName
                    );
                } catch (\Throwable $e) {
                    self::jsonResponse(
                        self::buildTemporaryAIErrorResponse($flow),
                        503
                    );
                }

                if (empty($aiEvaluation['accepted'])) {
                    $flow['attempts']['challengeAnswer'] = (int)($flow['attempts']['challengeAnswer'] ?? 0) + 1;
                    $flow['evaluation'] = [
                        'accepted' => false,
                        'needsRetry' => true,
                        'retryReason' => $aiEvaluation['retryReason'] ?? 'TOO_GENERIC',
                        'detectedIssues' => $aiEvaluation['detectedIssues'] ?? [],
                        'scoreRatio' => 0,
                        'performanceLevel' => 'INSUFFICIENT',
                        'feedbackSummary' => $aiEvaluation['feedbackSummary'] ?? null
                    ];

                    $remainingAttempts = self::remainingAttempts($flow);

                    $userMessagePayload = [[
                        'id' => 'msg_u_' . uniqid(),
                        'role' => 'user',
                        'type' => 'text',
                        'text' => $message
                    ]];

                    if ($remainingAttempts <= 0) {
                        $flow = self::markChallengeFlowAsFailed($flow);
                        self::saveChallengeFlow($flow);

                        $chatMessages = $userMessagePayload;

                        $modalMessages = self::buildFailedChallengeModalMessages($flow, $flow['evaluation']);

                        self::jsonResponse(
                            self::buildFailedChallengeResponse($flow, $chatMessages, $modalMessages, $flow['evaluation'])
                        );
                    }

                    $flow['currentStage'] = 'challenge_answer_retry';
                    $flow['nextExpectedAction'] = 'reply';
                    $flow['inputEnabled'] = true;
                    $flow['requiresUserResponse'] = true;

                    $assistantRetryMessages = !empty($aiEvaluation['messages'])
                        ? $challengeAI->normalizeMessages($aiEvaluation['messages'], 'msg_ai_retry_')
                        : self::buildRetryMessagesFallback($flow['evaluation'], $remainingAttempts);

                    if (!empty($aiEvaluation['messages'])) {
                        $assistantRetryMessages[] = [
                            'id' => 'msg_ai_attempts_' . uniqid(),
                            'role' => 'assistant',
                            'type' => 'text',
                            'text' => self::buildAttemptsWarningMessage($remainingAttempts)
                        ];
                    }

                    $retryMessages = array_merge($userMessagePayload, $assistantRetryMessages);

                    self::saveChallengeFlow($flow);

                    self::jsonResponse(
                        self::buildRetryResponse($flow, $retryMessages, $flow['evaluation'])
                    );
                }

                $scoreAwarded = self::calculateChallengeScore(
                    (float)($aiEvaluation['scoreRatio'] ?? 0),
                    (int)($flow['maxScore'] ?? 0),
                    true
                );

                $flow['answers']['challengeAnswer'] = $message;
                $flow['evaluation'] = [
                    'accepted' => true,
                    'needsRetry' => false,
                    'retryReason' => null,
                    'detectedIssues' => [],
                    'scoreRatio' => (float)($aiEvaluation['scoreRatio'] ?? 0),
                    'performanceLevel' => $aiEvaluation['performanceLevel'] ?? 'ACCEPTABLE',
                    'feedbackSummary' => $aiEvaluation['feedbackSummary'] ?? null
                ];

                $flow['scoreAwarded'] = $scoreAwarded;
                $flow['currentStage'] = 'complete';
                $flow['nextExpectedAction'] = null;
                $flow['inputEnabled'] = false;
                $flow['requiresUserResponse'] = false;
                $flow['completed'] = true;
                $flow['passed'] = true;
                $flow['failed'] = false;

                $saved = self::persistCompletedChallenge($idUsuario, $flow);

                if (!$saved) {
                    self::jsonResponse([
                        'ok' => false,
                        'error' => [
                            'code' => 'PERSISTENCE_ERROR',
                            'message' => 'No fue posible guardar el progreso del reto.'
                        ]
                    ], 500);
                }

                $userMessages = [[
                    'id' => 'msg_u_' . uniqid(),
                    'role' => 'user',
                    'type' => 'text',
                    'text' => $message
                ]];

                try {
                    $finalMessages = $challengeAI->generateFinalFeedbackMessages(
                        $content['title'] ?? '',
                        $content['skillName'] ?? 'Habilidad',
                        $message,
                        [
                            ...$flow['evaluation'],
                            'scoreAwarded' => $flow['scoreAwarded'],
                            'maxScore' => $flow['maxScore']
                        ],
                        $content,
                        $userName
                    );
                } catch (\Throwable $e) {
                    $finalMessages = self::buildFinalChallengeFeedbackFallback($flow, $flow['evaluation']);
                }

                self::saveChallengeFlow($flow);

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
                self::invalidStageResponse($currentStage);
                break;
        }
    }

    private static function getRequestData(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        return $_POST ?? [];
    }

    private static function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

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

    private static function buildChallengeFlow(object $reto, object $habilidad, int $userId): array
    {
        $content = self::buildChallengeContent($reto, $habilidad);

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
                'challengeAnswer' => 3
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

            'scoreAwarded' => 0,
            'maxScore' => (int)$content['maxPoints'],

            'startedAt' => date('Y-m-d H:i:s'),
            'lastInteractionAt' => date('Y-m-d H:i:s'),
        ];
    }

    private static function getActiveChallengeFlow(): ?array
    {
        $flow = $_SESSION['challenge_flow'] ?? null;
        return is_array($flow) ? $flow : null;
    }

    private static function saveChallengeFlow(array $flow): void
    {
        $flow['lastInteractionAt'] = date('Y-m-d H:i:s');
        $_SESSION['challenge_flow'] = $flow;
    }

    private static function clearChallengeFlow(): void
    {
        unset($_SESSION['challenge_flow']);
    }

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
                'challengeAnswer' => (int)($flow['limits']['challengeAnswer'] ?? 3)
            ],

            'scoreAwarded' => (int)($flow['scoreAwarded'] ?? 0),
            'maxScore' => (int)($flow['maxScore'] ?? 0)
        ];
    }

    private static function calculateChallengeScore(float $scoreRatio, int $maxPoints, bool $accepted): int
    {
        if (!$accepted || $maxPoints <= 0) {
            return 0;
        }

        $ratio = max(0, min(1, $scoreRatio));
        $score = (int) floor($maxPoints * $ratio);
        $score = max(0, min($maxPoints, $score));

        if ($accepted && $score === 0) {
            return 1;
        }

        return $score;
    }

    private static function remainingAttempts(array $flow): int
    {
        $used = (int)($flow['attempts']['challengeAnswer'] ?? 0);
        $limit = (int)($flow['limits']['challengeAnswer'] ?? 3);

        return max(0, $limit - $used);
    }

    private static function persistCompletedChallenge(int $idUsuario, array $flow): bool
    {
        $idUsuario = (int)$idUsuario;
        $challengeId = (int)($flow['challengeId'] ?? 0);
        $skillId = (int)($flow['skillId'] ?? 0);
        $scoreAwarded = (int)($flow['scoreAwarded'] ?? 0);
        $completed = (bool)($flow['completed'] ?? false);
        $passed = (bool)($flow['passed'] ?? false);

        if ($idUsuario <= 0 || $challengeId <= 0 || $skillId <= 0) {
            return false;
        }

        if (!$completed || !$passed) {
            return false;
        }

        $saved = usuarios_retos::marcarComoCompletado($idUsuario, $challengeId, $scoreAwarded);

        if (!$saved) {
            return false;
        }

        return self::recalculateUserSkillProgress($idUsuario, $skillId);
    }

    private static function recalculateUserSkillProgress(int $idUsuario, int $idHabilidad): bool
    {
        if ($idUsuario <= 0 || $idHabilidad <= 0) {
            return false;
        }

        usuarios_habilidades::recalcularProgresoHabilidad($idUsuario, $idHabilidad);
        return true;
    }

    private static function difficultyLabel(int $difficulty): string
    {
        return match ($difficulty) {
            2 => 'Intermedio',
            3 => 'Avanzado',
            default => 'Básico',
        };
    }

    private static function parseTags(string $rawTags): array
    {
        $parts = array_map('trim', explode(',', $rawTags));
        $parts = array_filter($parts, fn($tag) => $tag !== '');

        return array_values($parts);
    }

    private static function buildChallengeObjective(object $reto, object $habilidad): string
    {
        $skillName = trim((string)($habilidad->nombre ?? 'la habilidad'));
        return "Aplicar la habilidad de {$skillName} en una situación breve relacionada con entrevistas.";
    }

    private static function buildExpectedAction(object $reto, object $habilidad): string
    {
        $skillName = trim((string)($habilidad->nombre ?? 'la habilidad'));
        return "Responder con claridad, coherencia, reflexión y una acción concreta alineada con {$skillName}.";
    }

    private static function validateBasicChallengeAnswer(string $message): array
    {
        $message = trim($message);

        if ($message === '') {
            return [
                'valid' => false,
                'reason' => 'EMPTY_RESPONSE',
                'message' => 'Tu respuesta está vacía. Intenta escribir una idea completa.'
            ];
        }

        if (mb_strlen($message) < 12) {
            return [
                'valid' => false,
                'reason' => 'TOO_SHORT',
                'message' => 'Tu respuesta es demasiado corta. Intenta desarrollar mejor tu idea.'
            ];
        }

        $words = preg_split('/\s+/u', $message, -1, PREG_SPLIT_NO_EMPTY);
        if (!$words || count($words) < 3) {
            return [
                'valid' => false,
                'reason' => 'INSUFFICIENT_DEVELOPMENT',
                'message' => 'Tu respuesta necesita un poco más de desarrollo.'
            ];
        }

        if (!preg_match('/[a-záéíóúñ0-9]/iu', $message)) {
            return [
                'valid' => false,
                'reason' => 'INVALID_CONTENT',
                'message' => 'Tu respuesta no contiene contenido válido. Intenta escribir una idea clara.'
            ];
        }

        $generic = [
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

        if (in_array(mb_strtolower($message), $generic, true)) {
            return [
                'valid' => false,
                'reason' => 'TOO_GENERIC',
                'message' => 'Tu respuesta es demasiado general. Intenta ser más específico.'
            ];
        }

        return [
            'valid' => true,
            'reason' => null,
            'message' => null
        ];
    }

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

    private static function markChallengeFlowAsFailed(array $flow): array
    {
        $flow['currentStage'] = 'failed';
        $flow['nextExpectedAction'] = null;
        $flow['inputEnabled'] = false;
        $flow['requiresUserResponse'] = false;
        $flow['completed'] = true;
        $flow['passed'] = false;
        $flow['failed'] = true;
        $flow['scoreAwarded'] = 0;

        return $flow;
    }

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

    private static function buildTemporaryAIErrorResponse(array $flow): array
    {
        return [
            'ok' => false,
            'error' => [
                'code' => 'AI_TEMPORARY_ERROR',
                'message' => 'Hubo un problema temporal al evaluar tu respuesta. Intenta enviarla nuevamente.'
            ],
            'session' => self::sessionPayload($flow),
            'ui' => [
                'showTyping' => false,
                'showAvatarSpeaking' => false,
                'composerPlaceholder' => 'Vuelve a enviar tu respuesta...',
                'focusInput' => true,
                'showReturnButton' => false
            ]
        ];
    }

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
                'scoreRatio' => 0,
                'performanceLevel' => 'INSUFFICIENT',
                'scoreAwarded' => 0,
                'maxScore' => (int)($flow['maxScore'] ?? 0)
            ],
            'messages' => $chatMessages,
            'completionModal' => [
                'type' => 'error',
                'title' => 'Reto no completado',
                'messages' => $modalMessages,
                'scoreAwarded' => 0,
                'maxScore' => (int)($flow['maxScore'] ?? 0),
                'buttonText' => 'Volver a retos',
                'redirectTo' => '/retos'
            ],
            'progress' => [
                'challengeCompleted' => false,
                'failed' => true,
                'scoreAwarded' => 0,
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

    private static function buildFailedChallengeModalMessages(array $flow, array $evaluation = []): array
    {
        $skillName = $flow['content']['skillName'] ?? 'esta habilidad';
        $retryReason = $evaluation['retryReason'] ?? null;

        $reasonText = match ($retryReason) {
            'TOO_GENERIC' => 'Tu respuesta fue demasiado general y no desarrolló con suficiente claridad la idea principal del reto.',
            'OFF_TOPIC' => 'Tu respuesta se alejó de la consigna principal del reto.',
            'INCOHERENT' => 'Tu respuesta necesitaba más coherencia y claridad para demostrar la habilidad trabajada.',
            'LOW_REFLECTION' => 'Tu respuesta necesitaba mayor reflexión para mostrar mejor tu criterio.',
            'NO_ACTIONABLE_IDEA' => 'Tu respuesta no incluyó una acción concreta o una reformulación suficientemente útil.',
            'INSUFFICIENT_DEVELOPMENT' => 'Tu respuesta quedó corta y no alcanzó a desarrollar lo necesario para completar el reto.',
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
