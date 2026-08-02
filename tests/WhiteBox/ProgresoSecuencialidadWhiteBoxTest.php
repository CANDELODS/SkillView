<?php

declare(strict_types=1);

namespace Tests\WhiteBox;

use Classes\ProgresoService;
use Classes\RutaAprendizajeService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProgresoService::class)]
#[CoversClass(RutaAprendizajeService::class)]
final class ProgresoSecuencialidadWhiteBoxTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | R1. Sin actividades
    |--------------------------------------------------------------------------
    */

    #[TestDox(
        'Mantiene el progreso en cero cuando no existen actividades'
    )]
    public function test_progreso_sin_actividades(): void
    {
        $resultado =
            ProgresoService
                ::calcularDetalleDesdeCantidades(
                    0,
                    0,
                    0,
                    0
                );

        self::assertSame(
            0,
            $resultado[
                'porcentajeLecciones'
            ]
        );

        self::assertSame(
            0,
            $resultado[
                'porcentajeRetos'
            ]
        );

        self::assertSame(
            0,
            $resultado['progreso']
        );

        self::assertSame(
            'Básico',
            $resultado['nivelTexto']
        );

        self::assertSame(
            1,
            $resultado['nivelNumerico']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | R2. Únicamente lecciones
    |--------------------------------------------------------------------------
    */

    #[TestDox(
        'Calcula el progreso cuando únicamente existen lecciones'
    )]
    public function test_progreso_solo_con_lecciones(): void
    {
        $resultado =
            ProgresoService
                ::calcularDetalleDesdeCantidades(
                    2,
                    2,
                    0,
                    0
                );

        self::assertSame(
            100,
            $resultado[
                'porcentajeLecciones'
            ]
        );

        self::assertSame(
            0,
            $resultado[
                'porcentajeRetos'
            ]
        );

        /*
         * Las lecciones representan la mitad
         * del progreso consolidado.
         */
        self::assertSame(
            50,
            $resultado['progreso']
        );

        self::assertSame(
            'Intermedio',
            $resultado['nivelTexto']
        );

        self::assertSame(
            2,
            $resultado['nivelNumerico']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | R3. Únicamente retos
    |--------------------------------------------------------------------------
    */

    #[TestDox(
        'Calcula el progreso cuando únicamente existen retos'
    )]
    public function test_progreso_solo_con_retos(): void
    {
        $resultado =
            ProgresoService
                ::calcularDetalleDesdeCantidades(
                    0,
                    0,
                    3,
                    3
                );

        self::assertSame(
            0,
            $resultado[
                'porcentajeLecciones'
            ]
        );

        self::assertSame(
            100,
            $resultado[
                'porcentajeRetos'
            ]
        );

        /*
         * Los retos representan la mitad
         * del progreso consolidado.
         */
        self::assertSame(
            50,
            $resultado['progreso']
        );

        self::assertSame(
            'Intermedio',
            $resultado['nivelTexto']
        );

        self::assertSame(
            2,
            $resultado['nivelNumerico']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | R4. Participación de ambos componentes
    |--------------------------------------------------------------------------
    */

    #[TestDox(
        'Combina lecciones y retos aplicando la ponderación del cincuenta por ciento'
    )]
    public function test_combina_lecciones_y_retos(): void
    {
        /*
         * Lecciones:
         * 1 de 2 = 50 %
         *
         * Retos:
         * 3 de 4 = 75 %
         *
         * Consolidado:
         * 50 × 0.5 + 75 × 0.5 = 62.5
         * round(62.5) = 63
         */
        $resultado =
            ProgresoService
                ::calcularDetalleDesdeCantidades(
                    1,
                    2,
                    3,
                    4
                );

        self::assertSame(
            50,
            $resultado[
                'porcentajeLecciones'
            ]
        );

        self::assertSame(
            75,
            $resultado[
                'porcentajeRetos'
            ]
        );

        self::assertSame(
            63,
            $resultado['progreso']
        );

        self::assertSame(
            'Intermedio',
            $resultado['nivelTexto']
        );

        self::assertSame(
            2,
            $resultado['nivelNumerico']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | R5. Datos superiores al total
    |--------------------------------------------------------------------------
    */

    #[TestDox(
        'Limita al cien por ciento las cantidades completadas superiores al total'
    )]
    public function test_limita_porcentajes_al_cien(): void
    {
        /*
         * Los datos inconsistentes no deben
         * producir porcentajes superiores a 100.
         */
        $resultado =
            ProgresoService
                ::calcularDetalleDesdeCantidades(
                    5,
                    2,
                    7,
                    3
                );

        self::assertSame(
            100,
            $resultado[
                'porcentajeLecciones'
            ]
        );

        self::assertSame(
            100,
            $resultado[
                'porcentajeRetos'
            ]
        );

        self::assertSame(
            100,
            $resultado['progreso']
        );

        self::assertSame(
            'Avanzado',
            $resultado['nivelTexto']
        );

        self::assertSame(
            3,
            $resultado['nivelNumerico']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | R6. Primera habilidad pendiente
    |--------------------------------------------------------------------------
    */

    #[TestDox(
        'Mantiene completadas las habilidades anteriores y marca la primera pendiente como actual'
    )]
    public function test_determina_primera_habilidad_pendiente(): void
    {
        $habilidades = [
            [
                'total_lecciones' =>
                    3,

                'lecciones_completadas' =>
                    3
            ],
            [
                'total_lecciones' =>
                    3,

                'lecciones_completadas' =>
                    1
            ],
            [
                'total_lecciones' =>
                    2,

                'lecciones_completadas' =>
                    0
            ]
        ];

        $estados =
            RutaAprendizajeService
                ::determinarEstados(
                    $habilidades
                );

        self::assertSame(
            [
                RutaAprendizajeService
                    ::ESTADO_COMPLETADO,

                RutaAprendizajeService
                    ::ESTADO_ACTUAL,

                RutaAprendizajeService
                    ::ESTADO_BLOQUEADO
            ],
            $estados
        );

        self::assertSame(
            1,
            count(
                array_filter(
                    $estados,
                    static fn (
                        string $estado
                    ): bool =>
                        $estado ===
                        RutaAprendizajeService
                            ::ESTADO_ACTUAL
                )
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | R7. Habilidad sin lecciones
    |--------------------------------------------------------------------------
    */

    #[TestDox(
        'Bloquea las habilidades posteriores y las habilidades sin lecciones'
    )]
    public function test_bloquea_habilidades_no_disponibles(): void
    {
        $habilidades = [
            [
                'total_lecciones' =>
                    0,

                'lecciones_completadas' =>
                    0
            ],
            [
                'total_lecciones' =>
                    2,

                'lecciones_completadas' =>
                    1
            ],
            [
                'total_lecciones' =>
                    2,

                'lecciones_completadas' =>
                    0
            ]
        ];

        $estados =
            RutaAprendizajeService
                ::determinarEstados(
                    $habilidades
                );

        self::assertSame(
            RutaAprendizajeService
                ::ESTADO_BLOQUEADO,
            $estados[0]
        );

        self::assertSame(
            RutaAprendizajeService
                ::ESTADO_ACTUAL,
            $estados[1]
        );

        self::assertSame(
            RutaAprendizajeService
                ::ESTADO_BLOQUEADO,
            $estados[2]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | R8. Ruta completamente terminada
    |--------------------------------------------------------------------------
    */

    #[TestDox(
        'Elimina la habilidad actual cuando todo el recorrido fue completado'
    )]
    public function test_ruta_totalmente_completada(): void
    {
        /*
         * También se recorre la salida anticipada
         * correspondiente a una lista vacía.
         */
        self::assertSame(
            [],
            RutaAprendizajeService
                ::determinarEstados([])
        );

        $habilidades = [
            [
                'total_lecciones' =>
                    2,

                'lecciones_completadas' =>
                    2
            ],
            [
                'total_lecciones' =>
                    0,

                'lecciones_completadas' =>
                    0
            ],
            [
                'total_lecciones' =>
                    3,

                'lecciones_completadas' =>
                    3
            ]
        ];

        $estados =
            RutaAprendizajeService
                ::determinarEstados(
                    $habilidades
                );

        self::assertSame(
            [
                RutaAprendizajeService
                    ::ESTADO_COMPLETADO,

                RutaAprendizajeService
                    ::ESTADO_BLOQUEADO,

                RutaAprendizajeService
                    ::ESTADO_COMPLETADO
            ],
            $estados
        );

        self::assertNotContains(
            RutaAprendizajeService
                ::ESTADO_ACTUAL,
            $estados
        );
    }
}