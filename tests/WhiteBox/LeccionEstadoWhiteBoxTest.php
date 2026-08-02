<?php

declare(strict_types=1);

namespace Tests\WhiteBox;

use Classes\LeccionEstadoService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(LeccionEstadoService::class)]
final class LeccionEstadoWhiteBoxTest extends TestCase
{
    /**
     * Contenido pedagógico controlado.
     *
     * @return array<string, mixed>
     */
    private function contenido(): array
    {
        return [
            'objective' =>
                'Fortalecer la comunicación.',

            'keyConcepts' => [
                'Claridad',
                'Escucha activa',
            ],

            'commonMistakes' => [
                'Responder sin ejemplos',
            ],

            'microPractice' =>
                'Explica una fortaleza personal.',

            'miniEvaluation' =>
                'Describe cómo aplicarías lo aprendido.',

            'expectedSummary' =>
                'El usuario estructuró sus respuestas.',

            'scenario' =>
                '',

            'evaluationCriteria' =>
                [],

            'resultFormat' =>
                '',
        ];
    }

    /**
     * Crea un flujo nuevo para cada caso.
     *
     * @return array<string, mixed>
     */
    private function crearFlujo(): array
    {
        return LeccionEstadoService
            ::crearFlujoInicial(
                15,
                4,
                $this->contenido(),
                'standard'
            );
    }

    #[TestDox(
        'Crea el flujo inicial en la etapa de introducción'
    )]
    public function test_crea_flujo_inicial(): void
    {
        $flow =
            $this->crearFlujo();

        self::assertSame(
            15,
            $flow['lessonId']
        );

        self::assertSame(
            4,
            $flow['skillId']
        );

        self::assertSame(
            LeccionEstadoService::ETAPA_INTRO,
            $flow['currentStage']
        );

        self::assertSame(
            LeccionEstadoService::ACCION_AVANZAR,
            $flow['nextExpectedAction']
        );

        self::assertFalse(
            $flow['inputEnabled']
        );

        self::assertFalse(
            $flow['requiresUserResponse']
        );

        self::assertFalse(
            $flow['completed']
        );

        self::assertFalse(
            $flow['failed']
        );

        self::assertSame(
            0,
            $flow['attempts']
                ['microPractice']
        );

        self::assertSame(
            0,
            $flow['attempts']
                ['miniEvaluation']
        );

        self::assertSame(
            3,
            $flow['limits']
                ['microPractice']
        );

        self::assertSame(
            3,
            $flow['limits']
                ['miniEvaluation']
        );

        $payload =
            LeccionEstadoService
                ::sessionPayload(
                    $flow
                );

        self::assertSame(
            $flow['currentStage'],
            $payload['currentStage']
        );

        self::assertArrayNotHasKey(
            'content',
            $payload
        );

        self::assertArrayNotHasKey(
            'lessonType',
            $payload
        );
    }

    #[TestDox(
        'Valida las acciones permitidas y los estados cerrados'
    )]
    public function test_valida_acciones_y_estados(): void
    {
        $flow =
            $this->crearFlujo();

        /*
         * intro acepta advance.
         */
        $validIntro =
            LeccionEstadoService
                ::validarAccion(
                    $flow,
                    'advance'
                );

        self::assertTrue(
            $validIntro['valid']
        );

        self::assertNull(
            $validIntro['code']
        );

        /*
         * intro rechaza reply.
         */
        $invalidIntro =
            LeccionEstadoService
                ::validarAccion(
                    $flow,
                    'reply'
                );

        self::assertFalse(
            $invalidIntro['valid']
        );

        self::assertSame(
            'INVALID_ACTION',
            $invalidIntro['code']
        );

        self::assertSame(
            'advance',
            $invalidIntro[
                'expectedAction'
            ]
        );

        /*
         * micropráctica acepta reply.
         */
        $flow =
            LeccionEstadoService
                ::abrirMicroPractica(
                    $flow
                );

        $validReply =
            LeccionEstadoService
                ::validarAccion(
                    $flow,
                    'reply'
                );

        self::assertTrue(
            $validReply['valid']
        );

        /*
         * complete es un estado cerrado.
         */
        $completed =
            LeccionEstadoService
                ::completar(
                    $flow,
                    'Respuesta final suficientemente desarrollada.'
                );

        $closed =
            LeccionEstadoService
                ::validarAccion(
                    $completed,
                    'reply'
                );

        self::assertFalse(
            $closed['valid']
        );

        self::assertSame(
            'STATE_CLOSED',
            $closed['code']
        );

        /*
         * Una etapa desconocida se rechaza.
         */
        $unknown =
            $this->crearFlujo();

        $unknown['currentStage'] =
            'estado_desconocido';

        $invalidStage =
            LeccionEstadoService
                ::validarAccion(
                    $unknown,
                    'advance'
                );

        self::assertFalse(
            $invalidStage['valid']
        );

        self::assertSame(
            'INVALID_STAGE',
            $invalidStage['code']
        );
    }

    #[TestDox(
        'Avanza de la introducción hacia la micropráctica'
    )]
    public function test_abre_micropractica(): void
    {
        $flow =
            LeccionEstadoService
                ::abrirMicroPractica(
                    $this->crearFlujo()
                );

        self::assertSame(
            LeccionEstadoService
                ::ETAPA_MICRO_PRACTICA,
            $flow['currentStage']
        );

        self::assertSame(
            LeccionEstadoService
                ::ACCION_RESPONDER,
            $flow['nextExpectedAction']
        );

        self::assertTrue(
            $flow['inputEnabled']
        );

        self::assertTrue(
            $flow['requiresUserResponse']
        );

        self::assertFalse(
            $flow['completed']
        );
    }

    #[TestDox(
        'Controla los reintentos y el agotamiento de la micropráctica'
    )]
    public function test_reintentos_micropractica(): void
    {
        $flow =
            LeccionEstadoService
                ::abrirMicroPractica(
                    $this->crearFlujo()
                );

        /*
         * Primer intento fallido.
         */
        $first =
            LeccionEstadoService
                ::registrarReintentoMicroPractica(
                    $flow
                );

        self::assertFalse(
            $first['exhausted']
        );

        self::assertSame(
            2,
            $first['remainingAttempts']
        );

        self::assertSame(
            1,
            $first['flow']['attempts']
                ['microPractice']
        );

        self::assertSame(
            LeccionEstadoService
                ::ETAPA_REINTENTO_MICRO,
            $first['flow']['currentStage']
        );

        self::assertTrue(
            $first['flow']['inputEnabled']
        );

        /*
         * Segundo intento fallido.
         */
        $second =
            LeccionEstadoService
                ::registrarReintentoMicroPractica(
                    $first['flow']
                );

        self::assertFalse(
            $second['exhausted']
        );

        self::assertSame(
            1,
            $second['remainingAttempts']
        );

        /*
         * Tercer intento: agotamiento.
         */
        $third =
            LeccionEstadoService
                ::registrarReintentoMicroPractica(
                    $second['flow']
                );

        self::assertTrue(
            $third['exhausted']
        );

        self::assertSame(
            0,
            $third['remainingAttempts']
        );

        self::assertSame(
            3,
            $third['flow']['attempts']
                ['microPractice']
        );

        self::assertSame(
            LeccionEstadoService
                ::ETAPA_FALLIDA,
            $third['flow']['currentStage']
        );

        self::assertFalse(
            $third['flow']['inputEnabled']
        );

        self::assertFalse(
            $third['flow']
                ['requiresUserResponse']
        );

        self::assertFalse(
            $third['flow']['completed']
        );

        self::assertTrue(
            $third['flow']['failed']
        );
    }

    #[TestDox(
        'Guarda la micropráctica y abre la minievaluación'
    )]
    public function test_avanza_a_mini_evaluacion(): void
    {
        $flow =
            LeccionEstadoService
                ::abrirMicroPractica(
                    $this->crearFlujo()
                );

        $answer =
            '  Presentaría una fortaleza con '
            . 'un ejemplo concreto.  ';

        $flow =
            LeccionEstadoService
                ::avanzarAMiniEvaluacion(
                    $flow,
                    $answer
                );

        self::assertSame(
            'Presentaría una fortaleza con '
            . 'un ejemplo concreto.',
            $flow['answers']
                ['microPractice']
        );

        self::assertSame(
            LeccionEstadoService
                ::ETAPA_MINI_EVALUACION,
            $flow['currentStage']
        );

        self::assertSame(
            LeccionEstadoService
                ::ACCION_RESPONDER,
            $flow['nextExpectedAction']
        );

        self::assertTrue(
            $flow['inputEnabled']
        );

        self::assertTrue(
            $flow['requiresUserResponse']
        );

        self::assertSame(
            0,
            $flow['attempts']
                ['miniEvaluation']
        );
    }

    #[TestDox(
        'Controla los reintentos y el agotamiento de la minievaluación'
    )]
    public function test_reintentos_mini_evaluacion(): void
    {
        $flow =
            LeccionEstadoService
                ::abrirMicroPractica(
                    $this->crearFlujo()
                );

        $flow =
            LeccionEstadoService
                ::avanzarAMiniEvaluacion(
                    $flow,
                    'Utilizaría un ejemplo concreto '
                    . 'durante mi respuesta.'
                );

        $first =
            LeccionEstadoService
                ::registrarReintentoMiniEvaluacion(
                    $flow
                );

        self::assertFalse(
            $first['exhausted']
        );

        self::assertSame(
            2,
            $first['remainingAttempts']
        );

        self::assertSame(
            1,
            $first['flow']['attempts']
                ['miniEvaluation']
        );

        self::assertSame(
            LeccionEstadoService
                ::ETAPA_MINI_EVALUACION,
            $first['flow']['currentStage']
        );

        self::assertSame(
            0,
            $first['flow']['attempts']
                ['microPractice']
        );

        $second =
            LeccionEstadoService
                ::registrarReintentoMiniEvaluacion(
                    $first['flow']
                );

        self::assertFalse(
            $second['exhausted']
        );

        self::assertSame(
            1,
            $second['remainingAttempts']
        );

        $third =
            LeccionEstadoService
                ::registrarReintentoMiniEvaluacion(
                    $second['flow']
                );

        self::assertTrue(
            $third['exhausted']
        );

        self::assertSame(
            0,
            $third['remainingAttempts']
        );

        self::assertSame(
            3,
            $third['flow']['attempts']
                ['miniEvaluation']
        );

        self::assertSame(
            LeccionEstadoService
                ::ETAPA_FALLIDA,
            $third['flow']['currentStage']
        );

        self::assertTrue(
            $third['flow']['failed']
        );

        self::assertFalse(
            $third['flow']['completed']
        );
    }

    #[TestDox(
        'Completa la lección y bloquea nuevas respuestas'
    )]
    public function test_completa_leccion(): void
    {
        $flow =
            LeccionEstadoService
                ::abrirMicroPractica(
                    $this->crearFlujo()
                );

        $flow =
            LeccionEstadoService
                ::avanzarAMiniEvaluacion(
                    $flow,
                    'Expondría la fortaleza mediante '
                    . 'un ejemplo concreto.'
                );

        $flow =
            LeccionEstadoService
                ::completar(
                    $flow,
                    '  Reflexionaría sobre el ejemplo '
                    . 'y explicaría el resultado.  '
                );

        self::assertSame(
            LeccionEstadoService
                ::ETAPA_COMPLETA,
            $flow['currentStage']
        );

        self::assertSame(
            'Reflexionaría sobre el ejemplo '
            . 'y explicaría el resultado.',
            $flow['answers']
                ['miniEvaluation']
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

        self::assertTrue(
            $flow['completed']
        );

        self::assertFalse(
            $flow['failed']
        );
    }

    #[TestDox(
        'Pausa la lección sin alterar etapa respuestas ni intentos'
    )]
    public function test_pausa_por_indisponibilidad_ia(): void
    {
        $flow =
            LeccionEstadoService
                ::abrirMicroPractica(
                    $this->crearFlujo()
                );

        $flow =
            LeccionEstadoService
                ::avanzarAMiniEvaluacion(
                    $flow,
                    'Presentaría una experiencia '
                    . 'personal como ejemplo.'
                );

        $retry =
            LeccionEstadoService
                ::registrarReintentoMiniEvaluacion(
                    $flow
                );

        $flowAntes =
            $retry['flow'];

        $paused =
            LeccionEstadoService
                ::pausarPorIA(
                    $flowAntes
                );

        /*
         * La etapa no cambia.
         */
        self::assertSame(
            $flowAntes['currentStage'],
            $paused['currentStage']
        );

        /*
         * Los intentos no se consumen.
         */
        self::assertSame(
            $flowAntes['attempts'],
            $paused['attempts']
        );

        /*
         * Las respuestas permanecen intactas.
         */
        self::assertSame(
            $flowAntes['answers'],
            $paused['answers']
        );

        self::assertNull(
            $paused['nextExpectedAction']
        );

        self::assertFalse(
            $paused['inputEnabled']
        );

        self::assertFalse(
            $paused[
                'requiresUserResponse'
            ]
        );

        self::assertFalse(
            $paused['completed']
        );

        self::assertFalse(
            $paused['failed']
        );
    }
}