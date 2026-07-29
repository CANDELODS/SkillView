<?php

declare(strict_types=1);

namespace Tests\Unit;

use Classes\SoporteIAService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SoporteIAServiceTest extends TestCase
{
    #[DataProvider('casosRespuestaReto')]
    public function test_valida_respuestas_del_reto(
        string $respuesta,
        bool $valida,
        ?string $razon
    ): void {
        $resultado =
            SoporteIAService::validarRespuestaReto(
                $respuesta
            );

        self::assertSame(
            $valida,
            $resultado['valid']
        );

        self::assertSame(
            $razon,
            $resultado['reason']
        );
    }

    public static function casosRespuestaReto(): array
    {
        return [
            'respuesta vacía' => [
                '',
                false,
                'EMPTY_RESPONSE'
            ],

            'respuesta con espacios' => [
                '   ',
                false,
                'EMPTY_RESPONSE'
            ],

            'respuesta demasiado corta' => [
                'Hola',
                false,
                'TOO_SHORT'
            ],

            'respuesta sin desarrollo suficiente' => [
                'Respondería mejor',
                false,
                'INSUFFICIENT_DEVELOPMENT'
            ],

            'respuesta sin contenido alfanumérico' => [
                '!!! --- *** ???',
                false,
                'INVALID_CONTENT'
            ],

            'respuesta demasiado genérica' => [
                'Lo haría bien',
                false,
                'TOO_GENERIC'
            ],

            'respuesta válida' => [
                'Explicaría mi idea con calma y daría un ejemplo concreto.',
                true,
                null
            ],

            'respuesta válida con tildes' => [
                'Respondería con empatía y propondría una solución clara.',
                true,
                null
            ]
        ];
    }

    #[DataProvider('casosRespuestaMicroPractica')]
    public function test_valida_respuestas_de_micropractica(
        string $respuesta,
        bool $valida,
        ?string $razon
    ): void {
        $resultado =
            SoporteIAService::
                validarRespuestaMicroPractica(
                    $respuesta
                );

        self::assertSame(
            $valida,
            $resultado['valid']
        );

        self::assertSame(
            $razon,
            $resultado['reason']
        );
    }

    public static function casosRespuestaMicroPractica(): array
    {
        return [
            'respuesta vacía' => [
                '',
                false,
                'EMPTY'
            ],

            'respuesta con espacios' => [
                '   ',
                false,
                'EMPTY'
            ],

            'respuesta genérica sí' => [
                '  SÍ  ',
                false,
                'TOO_GENERIC'
            ],

            'respuesta genérica asdf' => [
                'asdf',
                false,
                'TOO_GENERIC'
            ],

            'respuesta demasiado corta' => [
                'Hola mundo',
                false,
                'TOO_SHORT'
            ],

            'respuesta con menos de cuatro palabras' => [
                'Respondería con calma',
                false,
                'TOO_SHORT'
            ],

            'respuesta válida' => [
                'Respondería con calma y explicaría mi decisión.',
                true,
                null
            ]
        ];
    }

    public function test_pausa_el_reto_si_la_ia_no_esta_disponible(): void
    {
        $flow = $this->crearFlujoReto();

        $respuesta =
            SoporteIAService::
                construirRespuestaRetoNoDisponible(
                    $flow,
                    '/retos'
                );

        self::assertTrue($respuesta['ok']);
        self::assertNull($respuesta['error']);

        self::assertSame(
            'AI_UNAVAILABLE',
            $respuesta['serviceError']['code']
        );

        self::assertNull(
            $flow['nextExpectedAction']
        );

        self::assertFalse(
            $flow['inputEnabled']
        );

        self::assertFalse(
            $flow['requiresUserResponse']
        );

        self::assertFalse(
            $respuesta['ui']['showAvatarSpeaking']
        );

        self::assertTrue(
            $respuesta['ui']['showReturnButton']
        );

        self::assertSame(
            '/retos',
            $respuesta['redirectTo']
        );
    }

    public function test_incluye_el_mensaje_del_usuario_en_el_fallback_del_reto(): void
    {
        $flow = $this->crearFlujoReto();

        $respuesta =
            SoporteIAService::
                construirRespuestaRetoNoDisponible(
                    $flow,
                    '/retos',
                    '  Mi respuesta al reto  '
                );

        self::assertCount(
            2,
            $respuesta['messages']
        );

        self::assertSame(
            'user',
            $respuesta['messages'][0]['role']
        );

        self::assertSame(
            'Mi respuesta al reto',
            $respuesta['messages'][0]['text']
        );
    }

    public function test_omite_un_mensaje_vacio_en_el_fallback_del_reto(): void
    {
        $flow = $this->crearFlujoReto();

        $respuesta =
            SoporteIAService::
                construirRespuestaRetoNoDisponible(
                    $flow,
                    '/retos',
                    '   '
                );

        self::assertCount(
            1,
            $respuesta['messages']
        );

        self::assertSame(
            'assistant',
            $respuesta['messages'][0]['role']
        );
    }

    public function test_el_fallback_del_reto_no_consume_intentos_ni_puntaje(): void
    {
        $flow = $this->crearFlujoReto();

        SoporteIAService::
            construirRespuestaRetoNoDisponible(
                $flow,
                '/retos'
            );

        self::assertSame(
            'challenge_answer',
            $flow['currentStage']
        );

        self::assertSame(
            1,
            $flow['attempts']['challengeAnswer']
        );

        self::assertSame(
            18,
            $flow['scoreAwarded']
        );

        self::assertFalse(
            $flow['completed']
        );
    }

    public function test_pausa_la_leccion_si_la_ia_no_esta_disponible(): void
    {
        $flow = $this->crearFlujoLeccion();

        $respuesta =
            SoporteIAService::
                construirRespuestaLeccionNoDisponible(
                    $flow,
                    '/aprendizaje'
                );

        self::assertTrue($respuesta['ok']);

        self::assertSame(
            'AI_UNAVAILABLE',
            $respuesta['serviceError']['code']
        );

        self::assertNull(
            $flow['nextExpectedAction']
        );

        self::assertFalse(
            $flow['inputEnabled']
        );

        self::assertFalse(
            $flow['requiresUserResponse']
        );

        self::assertSame(
            '/aprendizaje',
            $respuesta['redirectTo']
        );

        self::assertTrue(
            $respuesta['ui']['showReturnButton']
        );
    }

    public function test_incluye_el_mensaje_del_usuario_en_el_fallback_de_la_leccion(): void
    {
        $flow = $this->crearFlujoLeccion();

        $respuesta =
            SoporteIAService::
                construirRespuestaLeccionNoDisponible(
                    $flow,
                    '/aprendizaje',
                    '  Mi respuesta de la lección  '
                );

        self::assertCount(
            2,
            $respuesta['messages']
        );

        self::assertSame(
            'user',
            $respuesta['messages'][0]['role']
        );

        self::assertSame(
            'Mi respuesta de la lección',
            $respuesta['messages'][0]['text']
        );
    }

    public function test_omite_un_mensaje_vacio_en_el_fallback_de_la_leccion(): void
    {
        $flow = $this->crearFlujoLeccion();

        $respuesta =
            SoporteIAService::
                construirRespuestaLeccionNoDisponible(
                    $flow,
                    '/aprendizaje',
                    ''
                );

        self::assertCount(
            1,
            $respuesta['messages']
        );

        self::assertSame(
            'assistant',
            $respuesta['messages'][0]['role']
        );
    }

    public function test_el_fallback_de_la_leccion_no_consume_intentos_ni_completa(): void
    {
        $flow = $this->crearFlujoLeccion();

        SoporteIAService::
            construirRespuestaLeccionNoDisponible(
                $flow,
                '/aprendizaje'
            );

        self::assertSame(
            'micro_practice_answer',
            $flow['currentStage']
        );

        self::assertSame(
            1,
            $flow['attempts']['microPractice']
        );

        self::assertSame(
            0,
            $flow['attempts']['miniEvaluation']
        );

        self::assertFalse(
            $flow['completed']
        );
    }

    #[DataProvider('casosMensajeIntentosReto')]
    public function test_genera_el_mensaje_de_intentos_del_reto(
        int $intentos,
        string $mensajeEsperado
    ): void {
        self::assertSame(
            $mensajeEsperado,
            SoporteIAService::mensajeIntentosReto(
                $intentos
            )
        );
    }

    public static function casosMensajeIntentosReto(): array
    {
        return [
            'sin intentos' => [
                0,
                'No te quedan más intentos en este reto.'
            ],

            'un intento' => [
                1,
                'Te queda 1 intento.'
            ],

            'varios intentos' => [
                2,
                'Te quedan 2 intentos.'
            ]
        ];
    }

    #[DataProvider('casosMensajeIntentosLeccion')]
    public function test_genera_el_mensaje_de_intentos_de_la_leccion(
        int $intentos,
        string $mensajeEsperado
    ): void {
        self::assertSame(
            $mensajeEsperado,
            SoporteIAService::mensajeIntentosLeccion(
                $intentos
            )
        );
    }

    public static function casosMensajeIntentosLeccion(): array
    {
        return [
            'sin intentos' => [
                0,
                'Has agotado los intentos disponibles '
                . 'para esta fase de la lección.'
            ],

            'un intento' => [
                1,
                'Te queda 1 intento más para responder '
                . 'correctamente esta parte.'
            ],

            'varios intentos' => [
                2,
                'Te quedan 2 intentos más para responder '
                . 'correctamente esta parte.'
            ]
        ];
    }

    private function crearFlujoReto(): array
    {
        return [
            'challengeId' => 5,
            'skillId' => 2,
            'currentStage' => 'challenge_answer',
            'nextExpectedAction' => 'reply',
            'inputEnabled' => true,
            'requiresUserResponse' => true,
            'completed' => false,
            'passed' => false,
            'failed' => false,
            'attempts' => [
                'challengeAnswer' => 1
            ],
            'limits' => [
                'challengeAnswer' => 3
            ],
            'scoreAwarded' => 18,
            'maxScore' => 30,
            'minimumScore' => 21
        ];
    }

    private function crearFlujoLeccion(): array
    {
        return [
            'lessonId' => 4,
            'skillId' => 2,
            'currentStage' => 'micro_practice_answer',
            'nextExpectedAction' => 'reply',
            'inputEnabled' => true,
            'requiresUserResponse' => true,
            'completed' => false,
            'answers' => [
                'microPractice' => null,
                'miniEvaluation' => null
            ],
            'attempts' => [
                'microPractice' => 1,
                'miniEvaluation' => 0
            ]
        ];
    }
}