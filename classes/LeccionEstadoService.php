<?php

declare(strict_types=1);

namespace Classes;

/**
 * Administra las transiciones internas del flujo
 * pedagógico de una lección.
 *
 * Esta clase no realiza consultas, no consume IA
 * y no genera respuestas HTTP. Solo transforma
 * el estado almacenado en lesson_flow.
 */
final class LeccionEstadoService
{
    public const ETAPA_INTRO =
        'intro';

    public const ETAPA_MICRO_PRACTICA =
        'micro_practice_answer';

    public const ETAPA_REINTENTO_MICRO =
        'micro_practice_answer_retry';

    public const ETAPA_MINI_EVALUACION =
        'mini_eval_answer';

    public const ETAPA_COMPLETA =
        'complete';

    public const ETAPA_FALLIDA =
        'failed';

    public const ACCION_AVANZAR =
        'advance';

    public const ACCION_RESPONDER =
        'reply';

    /**
     * Acciones permitidas para las etapas
     * que todavía admiten interacción.
     */
    private const ACCIONES_ESPERADAS = [
        self::ETAPA_INTRO =>
            self::ACCION_AVANZAR,

        self::ETAPA_MICRO_PRACTICA =>
            self::ACCION_RESPONDER,

        self::ETAPA_REINTENTO_MICRO =>
            self::ACCION_RESPONDER,

        self::ETAPA_MINI_EVALUACION =>
            self::ACCION_RESPONDER,
    ];

    /**
     * Construye el estado con el que inicia
     * una nueva lección.
     */
    public static function crearFlujoInicial(
        int $lessonId,
        int $skillId,
        array $content,
        string $lessonType
    ): array {
        return [
            'lessonId' =>
                $lessonId,

            'skillId' =>
                $skillId,

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

            'answers' => [
                'microPractice' =>
                    null,

                'miniEvaluation' =>
                    null,
            ],

            'attempts' => [
                'microPractice' =>
                    0,

                'miniEvaluation' =>
                    0,
            ],

            'limits' => [
                'microPractice' =>
                    3,

                'miniEvaluation' =>
                    3,
            ],

            'failed' =>
                false,

            'content' =>
                $content,

            'lessonType' =>
                $lessonType,
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
            (string) $flow['currentStage'];

        /*
         * Las etapas complete y failed son estados
         * cerrados y no admiten nuevas acciones.
         */
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
     * Abre la micropráctica después de terminar
     * la introducción.
     */
    public static function abrirMicroPractica(
        array $flow
    ): array {
        $flow['currentStage'] =
            self::ETAPA_MICRO_PRACTICA;

        $flow['nextExpectedAction'] =
            self::ACCION_RESPONDER;

        $flow['inputEnabled'] =
            true;

        $flow['requiresUserResponse'] =
            true;

        return $flow;
    }

    /**
     * Registra un intento fallido en la
     * micropráctica.
     */
    public static function registrarReintentoMicroPractica(
        array $flow
    ): array {
        $attempts =
            (int) $flow['attempts']
                ['microPractice']
            + 1;

        $limit =
            (int) $flow['limits']
                ['microPractice'];

        $remainingAttempts =
            max(
                0,
                $limit - $attempts
            );

        $flow['attempts']
            ['microPractice'] =
                $attempts;

        $exhausted =
            $remainingAttempts === 0;

        if ($exhausted) {
            $flow =
                self::fallar(
                    $flow
                );
        } else {
            $flow['currentStage'] =
                self::ETAPA_REINTENTO_MICRO;

            $flow['nextExpectedAction'] =
                self::ACCION_RESPONDER;

            $flow['inputEnabled'] =
                true;

            $flow['requiresUserResponse'] =
                true;
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
     * Registra la respuesta aceptada de la
     * micropráctica y abre la minievaluación.
     */
    public static function avanzarAMiniEvaluacion(
        array $flow,
        string $answer
    ): array {
        $flow['answers']
            ['microPractice'] =
                trim($answer);

        $flow['currentStage'] =
            self::ETAPA_MINI_EVALUACION;

        $flow['nextExpectedAction'] =
            self::ACCION_RESPONDER;

        $flow['inputEnabled'] =
            true;

        $flow['requiresUserResponse'] =
            true;

        return $flow;
    }

    /**
     * Registra un intento fallido en la
     * minievaluación.
     */
    public static function registrarReintentoMiniEvaluacion(
        array $flow
    ): array {
        $attempts =
            (int) $flow['attempts']
                ['miniEvaluation']
            + 1;

        $limit =
            (int) $flow['limits']
                ['miniEvaluation'];

        $remainingAttempts =
            max(
                0,
                $limit - $attempts
            );

        $flow['attempts']
            ['miniEvaluation'] =
                $attempts;

        $exhausted =
            $remainingAttempts === 0;

        if ($exhausted) {
            $flow =
                self::fallar(
                    $flow
                );
        } else {
            $flow['currentStage'] =
                self::ETAPA_MINI_EVALUACION;

            $flow['nextExpectedAction'] =
                self::ACCION_RESPONDER;

            $flow['inputEnabled'] =
                true;

            $flow['requiresUserResponse'] =
                true;
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
     * Cierra satisfactoriamente la lección.
     */
    public static function completar(
        array $flow,
        string $miniEvaluationAnswer
    ): array {
        $flow['answers']
            ['miniEvaluation'] =
                trim(
                    $miniEvaluationAnswer
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

        $flow['failed'] =
            false;

        return $flow;
    }

    /**
     * Cierra la actividad como no completada.
     */
    public static function fallar(
        array $flow
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
            false;

        $flow['failed'] =
            true;

        return $flow;
    }

    /**
     * Pausa la interacción por un fallo técnico
     * sin modificar la etapa, las respuestas,
     * los intentos o el resultado de la lección.
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
     * Construye el estado público enviado
     * al frontend.
     */
    public static function sessionPayload(
        array $flow
    ): array {
        return [
            'lessonId' =>
                (int) $flow['lessonId'],

            'skillId' =>
                (int) $flow['skillId'],

            'currentStage' =>
                $flow['currentStage'],

            'nextExpectedAction' =>
                $flow['nextExpectedAction'],

            'inputEnabled' =>
                (bool) $flow['inputEnabled'],

            'requiresUserResponse' =>
                (bool) $flow[
                    'requiresUserResponse'
                ],

            'completed' =>
                (bool) $flow['completed'],

            'answers' =>
                $flow['answers'],

            'attempts' =>
                $flow['attempts'],

            'limits' =>
                $flow['limits'],

            'failed' =>
                (bool) $flow['failed'],
        ];
    }
}