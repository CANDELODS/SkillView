<?php

declare(strict_types=1);

namespace Classes;

/**
 * Centraliza las validaciones locales y las respuestas
 * utilizadas cuando el servicio de IA no está disponible.
 */
final class SoporteIAService
{
    /**
     * Valida una respuesta antes de enviarla
     * al servicio de inteligencia artificial del reto.
     */
    public static function validarRespuestaReto(
        string $message
    ): array {
        $message = trim($message);

        if ($message === '') {
            return [
                'valid' => false,
                'reason' => 'EMPTY_RESPONSE',
                'message' =>
                    'Tu respuesta está vacía. '
                    . 'Intenta escribir una idea completa.'
            ];
        }

        if (mb_strlen($message) < 12) {
            return [
                'valid' => false,
                'reason' => 'TOO_SHORT',
                'message' =>
                    'Tu respuesta es demasiado corta. '
                    . 'Intenta desarrollar mejor tu idea.'
            ];
        }

        $words = preg_split(
            '/\s+/u',
            $message,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if (!$words || count($words) < 3) {
            return [
                'valid' => false,
                'reason' => 'INSUFFICIENT_DEVELOPMENT',
                'message' =>
                    'Tu respuesta necesita un poco más de desarrollo.'
            ];
        }

        if (!preg_match('/[a-záéíóúñ0-9]/iu', $message)) {
            return [
                'valid' => false,
                'reason' => 'INVALID_CONTENT',
                'message' =>
                    'Tu respuesta no contiene contenido válido. '
                    . 'Intenta escribir una idea clara.'
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

        if (
            in_array(
                mb_strtolower($message),
                $generic,
                true
            )
        ) {
            return [
                'valid' => false,
                'reason' => 'TOO_GENERIC',
                'message' =>
                    'Tu respuesta es demasiado general. '
                    . 'Intenta ser más específico.'
            ];
        }

        return [
            'valid' => true,
            'reason' => null,
            'message' => null
        ];
    }

    /**
     * Valida una respuesta antes de enviarla
     * a la IA de la micropráctica.
     */
    public static function validarRespuestaMicroPractica(
        string $message
    ): array {
        $message = trim(
            mb_strtolower($message)
        );

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

        if (
            in_array(
                $message,
                $invalidShortAnswers,
                true
            )
        ) {
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

        $words = preg_split(
            '/\s+/u',
            $message,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if (!$words || count($words) < 4) {
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

    /**
     * Construye el fallback utilizado por los retos.
     */
    public static function construirRespuestaRetoNoDisponible(
        array &$flow,
        string $returnUrl,
        ?string $userMessage = null
    ): array {
        self::pausarFlujo($flow);

        return [
            'ok' => true,
            'error' => null,
            'serviceError' => [
                'code' => 'AI_UNAVAILABLE',
                'message' =>
                    'El servicio de inteligencia artificial '
                    . 'no está disponible.'
            ],
            'session' => self::sessionReto($flow),
            'messages' => self::mensajesNoDisponible(
                $userMessage,
                'msg_ai_connection_'
            ),
            'redirectTo' => $returnUrl,
            'ui' => self::interfazNoDisponible()
        ];
    }

    /**
     * Construye el fallback utilizado por las lecciones.
     */
    public static function construirRespuestaLeccionNoDisponible(
        array &$flow,
        string $returnUrl,
        ?string $userMessage = null
    ): array {
        self::pausarFlujo($flow);

        return [
            'ok' => true,
            'error' => null,
            'serviceError' => [
                'code' => 'AI_UNAVAILABLE',
                'message' =>
                    'El servicio de inteligencia artificial '
                    . 'no está disponible.'
            ],
            'session' => self::sessionLeccion($flow),
            'messages' => self::mensajesNoDisponible(
                $userMessage,
                'msg_a_connection_'
            ),
            'redirectTo' => $returnUrl,
            'ui' => self::interfazNoDisponible()
        ];
    }

    /**
     * Mensaje de intentos utilizado en los retos.
     */
    public static function mensajeIntentosReto(
        int $remainingAttempts
    ): string {
        if ($remainingAttempts <= 0) {
            return 'No te quedan más intentos en este reto.';
        }

        if ($remainingAttempts === 1) {
            return 'Te queda 1 intento.';
        }

        return "Te quedan {$remainingAttempts} intentos.";
    }

    /**
     * Mensaje de intentos utilizado en las lecciones.
     */
    public static function mensajeIntentosLeccion(
        int $remainingAttempts
    ): string {
        if ($remainingAttempts <= 0) {
            return
                'Has agotado los intentos disponibles '
                . 'para esta fase de la lección.';
        }

        if ($remainingAttempts === 1) {
            return
                'Te queda 1 intento más para responder '
                . 'correctamente esta parte.';
        }

        return
            "Te quedan {$remainingAttempts} intentos más "
            . 'para responder correctamente esta parte.';
    }

    /**
     * Pausa la interacción sin cambiar la etapa,
     * los intentos ni el progreso.
     */
    private static function pausarFlujo(
        array &$flow
    ): void {
        $flow['nextExpectedAction'] = null;
        $flow['inputEnabled'] = false;
        $flow['requiresUserResponse'] = false;
    }

    /**
     * Genera el mensaje mostrado dentro del chat.
     */
    private static function mensajesNoDisponible(
        ?string $userMessage,
        string $assistantPrefix
    ): array {
        $messages = [];

        if (
            $userMessage !== null
            && trim($userMessage) !== ''
        ) {
            $messages[] = [
                'id' => 'msg_u_' . uniqid(),
                'role' => 'user',
                'type' => 'text',
                'text' => trim($userMessage)
            ];
        }

        $messages[] = [
            'id' => $assistantPrefix . uniqid(),
            'role' => 'assistant',
            'type' => 'text',
            'text' =>
                'En este momento no fue posible conectarse '
                . 'con el servicio de inteligencia artificial. '
                . 'Verifica tu conexión a internet e inténtalo '
                . 'nuevamente más tarde. Tu progreso y tus '
                . 'intentos no se verán afectados.'
        ];

        return $messages;
    }

    private static function interfazNoDisponible(): array
    {
        return [
            'showTyping' => true,
            'showAvatarSpeaking' => false,
            'composerPlaceholder' =>
                'Actividad pausada por falta de conexión',
            'focusInput' => false,
            'showReturnButton' => true
        ];
    }

    private static function sessionReto(
        array $flow
    ): array {
        return [
            'challengeId' =>
                (int)($flow['challengeId'] ?? 0),

            'skillId' =>
                (int)($flow['skillId'] ?? 0),

            'currentStage' =>
                (string)($flow['currentStage'] ?? 'intro'),

            'nextExpectedAction' =>
                $flow['nextExpectedAction'] ?? null,

            'inputEnabled' =>
                (bool)($flow['inputEnabled'] ?? false),

            'requiresUserResponse' =>
                (bool)($flow['requiresUserResponse'] ?? false),

            'completed' =>
                (bool)($flow['completed'] ?? false),

            'passed' =>
                (bool)($flow['passed'] ?? false),

            'failed' =>
                (bool)($flow['failed'] ?? false),

            'attempts' => [
                'challengeAnswer' =>
                    (int)(
                        $flow['attempts']['challengeAnswer']
                        ?? 0
                    )
            ],

            'limits' => [
                'challengeAnswer' =>
                    (int)(
                        $flow['limits']['challengeAnswer']
                        ?? 3
                    )
            ],

            'scoreAwarded' =>
                (int)($flow['scoreAwarded'] ?? 0),

            'maxScore' =>
                (int)($flow['maxScore'] ?? 0),

            'minimumScore' =>
                (int)($flow['minimumScore'] ?? 0)
        ];
    }

    private static function sessionLeccion(
        array $flow
    ): array {
        return [
            'lessonId' =>
                (int)($flow['lessonId'] ?? 0),

            'skillId' =>
                (int)($flow['skillId'] ?? 0),

            'currentStage' =>
                $flow['currentStage'] ?? null,

            'nextExpectedAction' =>
                $flow['nextExpectedAction'] ?? null,

            'inputEnabled' =>
                (bool)($flow['inputEnabled'] ?? false),

            'requiresUserResponse' =>
                (bool)($flow['requiresUserResponse'] ?? false),

            'completed' =>
                (bool)($flow['completed'] ?? false),

            'answers' =>
                $flow['answers'] ?? [],

            'attempts' =>
                $flow['attempts'] ?? []
        ];
    }
}