<?php

declare(strict_types=1);

namespace Tests\WhiteBox;

use Classes\RetoEstadoService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(RetoEstadoService::class)]
final class RetoEstadoWhiteBoxTest extends TestCase
{
    /**
     * Contenido controlado del reto.
     *
     * @return array<string, mixed>
     */
    private function contenido(
        int $maxPoints = 30
    ): array {
        return [
            'title' =>
                'Reto de comunicación',

            'skillName' =>
                'Comunicación asertiva',

            'description' =>
                'Resuelve una situación laboral.',

            'difficultyLabel' =>
                'Intermedio',

            'difficultyValue' =>
                2,

            'timeMin' =>
                10,

            'timeMax' =>
                15,

            'maxPoints' =>
                $maxPoints,

            'objective' =>
                'Aplicar comunicación asertiva.',

            'expectedAction' =>
                'Responder con claridad.',
        ];
    }

    /**
     * Crea un flujo nuevo para cada escenario.
     *
     * @return array<string, mixed>
     */
    private function crearFlujo(
        int $maxPoints = 30
    ): array {
        return RetoEstadoService
            ::crearFlujoInicial(
                12,
                4,
                8,
                $this->contenido(
                    $maxPoints
                ),
                '2026-08-02 10:00:00'
            );
    }

    /**
     * Evaluación rechazada controlada.
     *
     * @return array<string, mixed>
     */
    private function evaluacionRechazada(
        string $reason = 'TOO_GENERIC'
    ): array {
        return [
            'accepted' =>
                false,

            'needsRetry' =>
                true,

            'retryReason' =>
                $reason,

            'detectedIssues' =>
                [
                    'Falta una acción concreta.',
                ],

            'scoreRatio' =>
                0,

            'performanceLevel' =>
                'INSUFFICIENT',

            'feedbackSummary' =>
                'La respuesta necesita más desarrollo.',
        ];
    }

    /**
     * Evaluación exitosa controlada.
     *
     * @return array<string, mixed>
     */
    private function evaluacionExitosa(): array
    {
        return [
            'accepted' =>
                true,

            'needsRetry' =>
                false,

            'retryReason' =>
                null,

            'detectedIssues' =>
                [],

            'scoreRatio' =>
                0.8,

            'performanceLevel' =>
                'GOOD',

            'feedbackSummary' =>
                'La respuesta fue clara y concreta.',
        ];
    }

    #[TestDox(
        'Crea el flujo inicial con el puntaje mínimo y los intentos definidos'
    )]
    public function test_crea_flujo_inicial(): void
    {
        $flow =
            $this->crearFlujo();

        self::assertSame(
            12,
            $flow['challengeId']
        );

        self::assertSame(
            4,
            $flow['skillId']
        );

        self::assertSame(
            8,
            $flow['userId']
        );

        self::assertSame(
            RetoEstadoService::ETAPA_INTRO,
            $flow['currentStage']
        );

        self::assertSame(
            RetoEstadoService::ACCION_AVANZAR,
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
            $flow['passed']
        );

        self::assertFalse(
            $flow['failed']
        );

        self::assertSame(
            0,
            $flow['attempts']
                ['challengeAnswer']
        );

        self::assertSame(
            3,
            $flow['limits']
                ['challengeAnswer']
        );

        self::assertSame(
            30,
            $flow['maxScore']
        );

        self::assertSame(
            21,
            $flow['minimumScore']
        );

        self::assertSame(
            '2026-08-02 10:00:00',
            $flow['startedAt']
        );

        self::assertSame(
            '2026-08-02 10:00:00',
            $flow['lastInteractionAt']
        );

        $payload =
            RetoEstadoService
                ::sessionPayload(
                    $flow
                );

        self::assertSame(
            21,
            $payload['minimumScore']
        );

        self::assertArrayNotHasKey(
            'content',
            $payload
        );

        self::assertArrayNotHasKey(
            'evaluation',
            $payload
        );

        self::assertArrayNotHasKey(
            'userId',
            $payload
        );
    }

    #[TestDox(
        'Valida las acciones permitidas los estados cerrados y una etapa desconocida'
    )]
    public function test_valida_acciones_y_estados(): void
    {
        $flow =
            $this->crearFlujo();

        $validIntro =
            RetoEstadoService
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

        $invalidIntro =
            RetoEstadoService
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

        $answerFlow =
            RetoEstadoService
                ::abrirRespuesta(
                    $flow
                );

        $validReply =
            RetoEstadoService
                ::validarAccion(
                    $answerFlow,
                    'reply'
                );

        self::assertTrue(
            $validReply['valid']
        );

        $completed =
            RetoEstadoService
                ::completar(
                    $answerFlow,
                    'Respuesta aprobada.',
                    $this->evaluacionExitosa(),
                    24
                );

        $closedComplete =
            RetoEstadoService
                ::validarAccion(
                    $completed,
                    'reply'
                );

        self::assertFalse(
            $closedComplete['valid']
        );

        self::assertSame(
            'STATE_CLOSED',
            $closedComplete['code']
        );

        $failed =
            RetoEstadoService
                ::fallar(
                    $answerFlow,
                    0
                );

        $closedFailed =
            RetoEstadoService
                ::validarAccion(
                    $failed,
                    'reply'
                );

        self::assertSame(
            'STATE_CLOSED',
            $closedFailed['code']
        );

        $unknown =
            $this->crearFlujo();

        $unknown['currentStage'] =
            'unknown_stage';

        $invalidStage =
            RetoEstadoService
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
        'Avanza desde la introducción hacia la respuesta del reto'
    )]
    public function test_abre_respuesta(): void
    {
        $flow =
            RetoEstadoService
                ::abrirRespuesta(
                    $this->crearFlujo()
                );

        self::assertSame(
            RetoEstadoService
                ::ETAPA_RESPUESTA,
            $flow['currentStage']
        );

        self::assertSame(
            RetoEstadoService
                ::ACCION_RESPONDER,
            $flow['nextExpectedAction']
        );

        self::assertTrue(
            $flow['inputEnabled']
        );

        self::assertTrue(
            $flow['requiresUserResponse']
        );
    }

    #[TestDox(
        'Calcula el mínimo y el puntaje respetando los límites definidos'
    )]
    public function test_calcula_puntajes_y_limites(): void
    {
        self::assertSame(
            0,
            RetoEstadoService
                ::calcularPuntajeMinimo(
                    0
                )
        );

        self::assertSame(
            0,
            RetoEstadoService
                ::calcularPuntajeMinimo(
                    -10
                )
        );

        self::assertSame(
            1,
            RetoEstadoService
                ::calcularPuntajeMinimo(
                    1
                )
        );

        self::assertSame(
            21,
            RetoEstadoService
                ::calcularPuntajeMinimo(
                    30
                )
        );

        self::assertSame(
            0,
            RetoEstadoService
                ::calcularPuntaje(
                    0.8,
                    30,
                    false
                )
        );

        self::assertSame(
            0,
            RetoEstadoService
                ::calcularPuntaje(
                    0.8,
                    0,
                    true
                )
        );

        self::assertSame(
            1,
            RetoEstadoService
                ::calcularPuntaje(
                    -0.5,
                    30,
                    true
                )
        );

        self::assertSame(
            18,
            RetoEstadoService
                ::calcularPuntaje(
                    0.6,
                    30,
                    true
                )
        );

        self::assertSame(
            30,
            RetoEstadoService
                ::calcularPuntaje(
                    1.5,
                    30,
                    true
                )
        );

        self::assertFalse(
            RetoEstadoService
                ::cumplePuntajeMinimo(
                    30,
                    0
                )
        );

        self::assertFalse(
            RetoEstadoService
                ::cumplePuntajeMinimo(
                    20,
                    21
                )
        );

        self::assertTrue(
            RetoEstadoService
                ::cumplePuntajeMinimo(
                    21,
                    21
                )
        );

        self::assertTrue(
            RetoEstadoService
                ::cumplePuntajeMinimo(
                    24,
                    21
                )
        );
    }

    #[TestDox(
        'Controla los reintentos rechazados y finaliza al agotar las oportunidades'
    )]
    public function test_reintentos_rechazados(): void
    {
        $flow =
            RetoEstadoService
                ::abrirRespuesta(
                    $this->crearFlujo()
                );

        $first =
            RetoEstadoService
                ::registrarReintento(
                    $flow,
                    $this->evaluacionRechazada(),
                    0,
                    '  Respuesta demasiado general.  '
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
                ['challengeAnswer']
        );

        self::assertSame(
            'Respuesta demasiado general.',
            $first['flow']['answers']
                ['challengeAnswer']
        );

        self::assertSame(
            RetoEstadoService
                ::ETAPA_REINTENTO,
            $first['flow']['currentStage']
        );

        self::assertTrue(
            $first['flow']['inputEnabled']
        );

        $second =
            RetoEstadoService
                ::registrarReintento(
                    $first['flow'],
                    $this->evaluacionRechazada(),
                    0
                );

        self::assertFalse(
            $second['exhausted']
        );

        self::assertSame(
            1,
            $second['remainingAttempts']
        );

        $third =
            RetoEstadoService
                ::registrarReintento(
                    $second['flow'],
                    $this->evaluacionRechazada(),
                    0
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
                ['challengeAnswer']
        );

        self::assertSame(
            RetoEstadoService
                ::ETAPA_FALLIDA,
            $third['flow']['currentStage']
        );

        self::assertTrue(
            $third['flow']['completed']
        );

        self::assertFalse(
            $third['flow']['passed']
        );

        self::assertTrue(
            $third['flow']['failed']
        );

        self::assertFalse(
            $third['flow']['inputEnabled']
        );

        self::assertSame(
            0,
            RetoEstadoService
                ::intentosRestantes(
                    $third['flow']
                )
        );
    }

    #[TestDox(
        'Conserva el puntaje real cuando la respuesta queda por debajo del mínimo'
    )]
    public function test_reintento_por_puntaje_insuficiente(): void
    {
        $flow =
            RetoEstadoService
                ::abrirRespuesta(
                    $this->crearFlujo()
                );

        $evaluation =
            $this->evaluacionRechazada(
                'BELOW_MINIMUM_SCORE'
            );

        $evaluation['scoreRatio'] =
            0.6;

        $evaluation['performanceLevel'] =
            'ACCEPTABLE';

        $retry =
            RetoEstadoService
                ::registrarReintento(
                    $flow,
                    $evaluation,
                    18,
                    '  Escucharía a ambas personas y propondría un acuerdo.  '
                );

        self::assertFalse(
            $retry['exhausted']
        );

        self::assertSame(
            18,
            $retry['flow']['scoreAwarded']
        );

        self::assertSame(
            'BELOW_MINIMUM_SCORE',
            $retry['flow']['evaluation']
                ['retryReason']
        );

        self::assertSame(
            'Escucharía a ambas personas y propondría un acuerdo.',
            $retry['flow']['answers']
                ['challengeAnswer']
        );

        self::assertSame(
            2,
            $retry['remainingAttempts']
        );

        self::assertFalse(
            RetoEstadoService
                ::cumplePuntajeMinimo(
                    $retry['flow']
                        ['scoreAwarded'],
                    $retry['flow']
                        ['minimumScore']
                )
        );
    }

    #[TestDox(
        'Completa el reto cuando el puntaje alcanza el mínimo'
    )]
    public function test_completa_reto_aprobado(): void
    {
        $flow =
            RetoEstadoService
                ::abrirRespuesta(
                    $this->crearFlujo()
                );

        $completed =
            RetoEstadoService
                ::completar(
                    $flow,
                    '  Escucharía, resumiría las ideas y propondría un acuerdo equilibrado.  ',
                    $this->evaluacionExitosa(),
                    24
                );

        self::assertSame(
            RetoEstadoService
                ::ETAPA_COMPLETA,
            $completed['currentStage']
        );

        self::assertSame(
            'Escucharía, resumiría las ideas y propondría un acuerdo equilibrado.',
            $completed['answers']
                ['challengeAnswer']
        );

        self::assertSame(
            24,
            $completed['scoreAwarded']
        );

        self::assertNull(
            $completed['nextExpectedAction']
        );

        self::assertFalse(
            $completed['inputEnabled']
        );

        self::assertFalse(
            $completed[
                'requiresUserResponse'
            ]
        );

        self::assertTrue(
            $completed['completed']
        );

        self::assertTrue(
            $completed['passed']
        );

        self::assertFalse(
            $completed['failed']
        );

        /*
         * También se comprueba que el puntaje
         * nunca supere el máximo del reto.
         */
        $clamped =
            RetoEstadoService
                ::completar(
                    $flow,
                    'Respuesta válida.',
                    $this->evaluacionExitosa(),
                    40
                );

        self::assertSame(
            30,
            $clamped['scoreAwarded']
        );
    }

    #[TestDox(
        'Marca el reto como fallido conservando el puntaje informado'
    )]
    public function test_marca_reto_fallido(): void
    {
        $flow =
            RetoEstadoService
                ::abrirRespuesta(
                    $this->crearFlujo()
                );

        $flow['scoreAwarded'] =
            12;

        $failedWithoutOverride =
            RetoEstadoService
                ::fallar(
                    $flow
                );

        self::assertSame(
            12,
            $failedWithoutOverride[
                'scoreAwarded'
            ]
        );

        $failedWithOverride =
            RetoEstadoService
                ::fallar(
                    $flow,
                    18
                );

        self::assertSame(
            18,
            $failedWithOverride[
                'scoreAwarded'
            ]
        );

        self::assertSame(
            RetoEstadoService
                ::ETAPA_FALLIDA,
            $failedWithOverride[
                'currentStage'
            ]
        );

        self::assertTrue(
            $failedWithOverride[
                'completed'
            ]
        );

        self::assertFalse(
            $failedWithOverride[
                'passed'
            ]
        );

        self::assertTrue(
            $failedWithOverride[
                'failed'
            ]
        );
    }

    #[TestDox(
        'Pausa el reto sin alterar etapa intentos respuesta puntaje ni resultado'
    )]
    public function test_pausa_por_indisponibilidad_ia(): void
    {
        $flow =
            RetoEstadoService
                ::abrirRespuesta(
                    $this->crearFlujo()
                );

        $retry =
            RetoEstadoService
                ::registrarReintento(
                    $flow,
                    $this->evaluacionRechazada(
                        'BELOW_MINIMUM_SCORE'
                    ),
                    18,
                    'Respuesta conservada.'
                );

        $before =
            $retry['flow'];

        $paused =
            RetoEstadoService
                ::pausarPorIA(
                    $before
                );

        self::assertSame(
            $before['currentStage'],
            $paused['currentStage']
        );

        self::assertSame(
            $before['attempts'],
            $paused['attempts']
        );

        self::assertSame(
            $before['answers'],
            $paused['answers']
        );

        self::assertSame(
            $before['scoreAwarded'],
            $paused['scoreAwarded']
        );

        self::assertSame(
            $before['completed'],
            $paused['completed']
        );

        self::assertSame(
            $before['passed'],
            $paused['passed']
        );

        self::assertSame(
            $before['failed'],
            $paused['failed']
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
    }
}