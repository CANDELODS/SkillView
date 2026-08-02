<?php

declare(strict_types=1);

namespace Classes;

/**
 * Administra la máquina de estados, los intentos
 * y el puntaje interno de los retos.
 *
 * Esta clase no realiza consultas, no consume IA
 * y no genera respuestas HTTP. Solo transforma
 * el estado almacenado en challenge_flow.
 */
final class RetoEstadoService
{
    public const MIN_PASSING_RATIO = 0.70;

    public const ETAPA_INTRO =
        'intro';

    public const ETAPA_RESPUESTA =
        'challenge_answer';

    public const ETAPA_REINTENTO =
        'challenge_answer_retry';

    public const ETAPA_COMPLETA =
        'complete';

    public const ETAPA_FALLIDA =
        'failed';

    public const ACCION_AVANZAR =
        'advance';

    public const ACCION_RESPONDER =
        'reply';

    /**
     * Acciones admitidas por los estados que
     * todavía permiten interacción.
     */
    private const ACCIONES_ESPERADAS = [
        self::ETAPA_INTRO =>
            self::ACCION_AVANZAR,

        self::ETAPA_RESPUESTA =>
            self::ACCION_RESPONDER,

        self::ETAPA_REINTENTO =>
            self::ACCION_RESPONDER,
    ];

    /**
     * Construye el estado inicial de un reto.
     */
    public static function crearFlujoInicial(
        int $challengeId,
        int $skillId,
        int $userId,
        array $content,
        ?string $startedAt = null
    ): array {
        $maxScore =
            (int) (
                $content['maxPoints']
                ?? 0
            );

        $minimumScore =
            self::calcularPuntajeMinimo(
                $maxScore
            );

        $timestamp =
            $startedAt
            ?? date('Y-m-d H:i:s');

        return [
            'challengeId' =>
                $challengeId,

            'skillId' =>
                $skillId,

            'userId' =>
                $userId,

            'currentStage' =>
                self::ETAPA_INTRO,

            'nextExpectedAction' =>
                self::ACCION_AVANZAR,

            'inputEnabled' =>
                false,

            'requiresUserResponse' =>
                false,

            'completed' =>
                false,

            'passed' =>
                false,

            'failed' =>
                false,

            'attempts' => [
                'challengeAnswer' =>
                    0,
            ],

            'limits' => [
                'challengeAnswer' =>
                    3,
            ],

            'answers' => [
                'challengeAnswer' =>
                    null,
            ],

            'content' =>
                $content,

            'evaluation' => [
                'accepted' =>
                    false,

                'needsRetry' =>
                    false,

                'retryReason' =>
                    null,

                'detectedIssues' =>
                    [],

                'scoreRatio' =>
                    0,

                'performanceLevel' =>
                    null,

                'feedbackSummary' =>
                    null,
            ],

            'scoreAwarded' =>
                0,

            'maxScore' =>
                $maxScore,

            'minimumScore' =>
                $minimumScore,

            'startedAt' =>
                $timestamp,

            'lastInteractionAt' =>
                $timestamp,
        ];
    }

    /**
     * Verifica si la acción recibida corresponde
     * con la etapa actual.
     */
    public static function validarAccion(
        array $flow,
        string $action
    ): array {
        $currentStage =
            (string) (
                $flow['currentStage']
                ?? ''
            );

        if (
            !array_key_exists(
                $currentStage,
                self::ACCIONES_ESPERADAS
            )
        ) {
            $closedState =
                in_array(
                    $currentStage,
                    [
                        self::ETAPA_COMPLETA,
                        self::ETAPA_FALLIDA,
                    ],
                    true
                );

            return [
                'valid' =>
                    false,

                'code' =>
                    $closedState
                        ? 'STATE_CLOSED'
                        : 'INVALID_STAGE',

                'currentStage' =>
                    $currentStage,

                'expectedAction' =>
                    null,
            ];
        }

        $expectedAction =
            self::ACCIONES_ESPERADAS[
                $currentStage
            ];

        $valid =
            trim($action)
            === $expectedAction;

        return [
            'valid' =>
                $valid,

            'code' =>
                $valid
                    ? null
                    : 'INVALID_ACTION',

            'currentStage' =>
                $currentStage,

            'expectedAction' =>
                $expectedAction,
        ];
    }

    /**
     * Abre la etapa en la que el usuario puede
     * responder la consigna del reto.
     */
    public static function abrirRespuesta(
        array $flow
    ): array {
        $flow['currentStage'] =
            self::ETAPA_RESPUESTA;

        $flow['nextExpectedAction'] =
            self::ACCION_RESPONDER;

        $flow['inputEnabled'] =
            true;

        $flow['requiresUserResponse'] =
            true;

        return $flow;
    }

    /**
     * Registra un intento que no permitió aprobar.
     *
     * El puntaje puede ser cero cuando la respuesta
     * fue rechazada o conservar el valor real cuando
     * fue válida, pero quedó por debajo del mínimo.
     */
    public static function registrarReintento(
        array $flow,
        array $evaluation,
        int $scoreAwarded = 0,
        ?string $answer = null
    ): array {
        $attempts =
            (int) (
                $flow['attempts']
                    ['challengeAnswer']
                ?? 0
            ) + 1;

        $flow['attempts']
            ['challengeAnswer'] =
                $attempts;

        $flow['evaluation'] =
            $evaluation;

        $flow['scoreAwarded'] =
            max(
                0,
                $scoreAwarded
            );

        if ($answer !== null) {
            $flow['answers']
                ['challengeAnswer'] =
                    trim($answer);
        }

        $remainingAttempts =
            self::intentosRestantes(
                $flow
            );

        $exhausted =
            $remainingAttempts === 0;

        if ($exhausted) {
            $flow =
                self::fallar(
                    $flow,
                    $flow['scoreAwarded']
                );
        } else {
            $flow['currentStage'] =
                self::ETAPA_REINTENTO;

            $flow['nextExpectedAction'] =
                self::ACCION_RESPONDER;

            $flow['inputEnabled'] =
                true;

            $flow['requiresUserResponse'] =
                true;

            $flow['completed'] =
                false;

            $flow['passed'] =
                false;

            $flow['failed'] =
                false;
        }

        return [
            'flow' =>
                $flow,

            'remainingAttempts' =>
                $remainingAttempts,

            'exhausted' =>
                $exhausted,
        ];
    }

    /**
     * Marca el reto como completado y aprobado.
     */
    public static function completar(
        array $flow,
        string $answer,
        array $evaluation,
        int $scoreAwarded
    ): array {
        $flow['answers']
            ['challengeAnswer'] =
                trim($answer);

        $flow['evaluation'] =
            $evaluation;

        $flow['scoreAwarded'] =
            max(
                0,
                min(
                    (int) (
                        $flow['maxScore']
                        ?? 0
                    ),
                    $scoreAwarded
                )
            );

        $flow['currentStage'] =
            self::ETAPA_COMPLETA;

        $flow['nextExpectedAction'] =
            null;

        $flow['inputEnabled'] =
            false;

        $flow['requiresUserResponse'] =
            false;

        $flow['completed'] =
            true;

        $flow['passed'] =
            true;

        $flow['failed'] =
            false;

        return $flow;
    }

    /**
     * Marca el reto como finalizado sin aprobar.
     */
    public static function fallar(
        array $flow,
        ?int $scoreAwarded = null
    ): array {
        $flow['currentStage'] =
            self::ETAPA_FALLIDA;

        $flow['nextExpectedAction'] =
            null;

        $flow['inputEnabled'] =
            false;

        $flow['requiresUserResponse'] =
            false;

        $flow['completed'] =
            true;

        $flow['passed'] =
            false;

        $flow['failed'] =
            true;

        if ($scoreAwarded !== null) {
            $flow['scoreAwarded'] =
                max(
                    0,
                    $scoreAwarded
                );
        }

        return $flow;
    }

    /**
     * Pausa el reto por un fallo técnico sin
     * modificar la etapa, los intentos, la
     * respuesta, el puntaje o el resultado.
     */
    public static function pausarPorIA(
        array $flow
    ): array {
        $flow['nextExpectedAction'] =
            null;

        $flow['inputEnabled'] =
            false;

        $flow['requiresUserResponse'] =
            false;

        return $flow;
    }

    /**
     * Calcula el puntaje mínimo de aprobación.
     */
    public static function calcularPuntajeMinimo(
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
            $minimumScore
        );
    }

    /**
     * Convierte el scoreRatio de la IA en puntos.
     */
    public static function calcularPuntaje(
        float $scoreRatio,
        int $maxPoints,
        bool $accepted
    ): int {
        if (
            !$accepted
            || $maxPoints <= 0
        ) {
            return 0;
        }

        $ratio =
            max(
                0,
                min(
                    1,
                    $scoreRatio
                )
            );

        $score =
            (int) round(
                $maxPoints
                * $ratio
            );

        $score =
            max(
                0,
                min(
                    $maxPoints,
                    $score
                )
            );

        if ($score === 0) {
            return 1;
        }

        return $score;
    }

    /**
     * Determina si el puntaje alcanzó el mínimo.
     */
    public static function cumplePuntajeMinimo(
        int $scoreAwarded,
        int $minimumScore
    ): bool {
        return
            $minimumScore > 0
            && $scoreAwarded
                >= $minimumScore;
    }

    /**
     * Calcula los intentos aún disponibles.
     */
    public static function intentosRestantes(
        array $flow
    ): int {
        $used =
            (int) (
                $flow['attempts']
                    ['challengeAnswer']
                ?? 0
            );

        $limit =
            (int) (
                $flow['limits']
                    ['challengeAnswer']
                ?? 3
            );

        return max(
            0,
            $limit - $used
        );
    }

    /**
     * Construye el estado público enviado
     * al frontend.
     */
    public static function sessionPayload(
        array $flow
    ): array {
        return [
            'challengeId' =>
                (int) (
                    $flow['challengeId']
                    ?? 0
                ),

            'skillId' =>
                (int) (
                    $flow['skillId']
                    ?? 0
                ),

            'currentStage' =>
                (string) (
                    $flow['currentStage']
                    ?? self::ETAPA_INTRO
                ),

            'nextExpectedAction' =>
                $flow['nextExpectedAction']
                ?? null,

            'inputEnabled' =>
                (bool) (
                    $flow['inputEnabled']
                    ?? false
                ),

            'requiresUserResponse' =>
                (bool) (
                    $flow['requiresUserResponse']
                    ?? false
                ),

            'completed' =>
                (bool) (
                    $flow['completed']
                    ?? false
                ),

            'passed' =>
                (bool) (
                    $flow['passed']
                    ?? false
                ),

            'failed' =>
                (bool) (
                    $flow['failed']
                    ?? false
                ),

            'attempts' => [
                'challengeAnswer' =>
                    (int) (
                        $flow['attempts']
                            ['challengeAnswer']
                        ?? 0
                    ),
            ],

            'limits' => [
                'challengeAnswer' =>
                    (int) (
                        $flow['limits']
                            ['challengeAnswer']
                        ?? 3
                    ),
            ],

            'scoreAwarded' =>
                (int) (
                    $flow['scoreAwarded']
                    ?? 0
                ),

            'maxScore' =>
                (int) (
                    $flow['maxScore']
                    ?? 0
                ),

            'minimumScore' =>
                (int) (
                    $flow['minimumScore']
                    ?? 0
                ),
        ];
    }
}