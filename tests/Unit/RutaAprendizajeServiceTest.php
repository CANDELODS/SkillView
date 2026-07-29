<?php

declare(strict_types=1);

namespace Tests\Unit;

use Classes\RutaAprendizajeService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RutaAprendizajeServiceTest extends TestCase
{
    #[DataProvider('casosSecuencialidad')]
    public function test_asigna_correctamente_los_estados(
        array $habilidades,
        array $estadosEsperados
    ): void {
        $estadosObtenidos =
            RutaAprendizajeService::determinarEstados(
                $habilidades
            );

        self::assertSame(
            $estadosEsperados,
            $estadosObtenidos
        );
    }

    public static function casosSecuencialidad(): array
    {
        return [
            'primera habilidad pendiente' => [
                [
                    [
                        'total_lecciones' => 3,
                        'lecciones_completadas' => 0
                    ],
                    [
                        'total_lecciones' => 2,
                        'lecciones_completadas' => 0
                    ],
                    [
                        'total_lecciones' => 4,
                        'lecciones_completadas' => 0
                    ]
                ],
                [
                    'current',
                    'locked',
                    'locked'
                ]
            ],

            'primera completada y segunda pendiente' => [
                [
                    [
                        'total_lecciones' => 3,
                        'lecciones_completadas' => 3
                    ],
                    [
                        'total_lecciones' => 2,
                        'lecciones_completadas' => 1
                    ],
                    [
                        'total_lecciones' => 4,
                        'lecciones_completadas' => 0
                    ]
                ],
                [
                    'completed',
                    'current',
                    'locked'
                ]
            ],

            'varias completadas antes de la actual' => [
                [
                    [
                        'total_lecciones' => 2,
                        'lecciones_completadas' => 2
                    ],
                    [
                        'total_lecciones' => 3,
                        'lecciones_completadas' => 3
                    ],
                    [
                        'total_lecciones' => 4,
                        'lecciones_completadas' => 2
                    ],
                    [
                        'total_lecciones' => 2,
                        'lecciones_completadas' => 0
                    ]
                ],
                [
                    'completed',
                    'completed',
                    'current',
                    'locked'
                ]
            ],

            'habilidad posterior completa permanece bloqueada' => [
                [
                    [
                        'total_lecciones' => 3,
                        'lecciones_completadas' => 1
                    ],
                    [
                        'total_lecciones' => 2,
                        'lecciones_completadas' => 2
                    ]
                ],
                [
                    'current',
                    'locked'
                ]
            ],

            'habilidad sin lecciones queda bloqueada' => [
                [
                    [
                        'total_lecciones' => 0,
                        'lecciones_completadas' => 0
                    ],
                    [
                        'total_lecciones' => 2,
                        'lecciones_completadas' => 0
                    ]
                ],
                [
                    'locked',
                    'current'
                ]
            ],

            'todas las habilidades completadas' => [
                [
                    [
                        'total_lecciones' => 2,
                        'lecciones_completadas' => 2
                    ],
                    [
                        'total_lecciones' => 3,
                        'lecciones_completadas' => 3
                    ]
                ],
                [
                    'completed',
                    'completed'
                ]
            ],

            'una sola habilidad pendiente' => [
                [
                    [
                        'total_lecciones' => 2,
                        'lecciones_completadas' => 1
                    ]
                ],
                [
                    'current'
                ]
            ],

            'una sola habilidad completada' => [
                [
                    [
                        'total_lecciones' => 2,
                        'lecciones_completadas' => 2
                    ]
                ],
                [
                    'completed'
                ]
            ],

            'completadas superiores al total' => [
                [
                    [
                        'total_lecciones' => 2,
                        'lecciones_completadas' => 3
                    ],
                    [
                        'total_lecciones' => 2,
                        'lecciones_completadas' => 0
                    ]
                ],
                [
                    'completed',
                    'current'
                ]
            ],

            'arreglo vacío' => [
                [],
                []
            ]
        ];
    }

    public function test_solo_asigna_una_habilidad_actual(): void
    {
        $estados =
            RutaAprendizajeService::determinarEstados([
                [
                    'total_lecciones' => 3,
                    'lecciones_completadas' => 3
                ],
                [
                    'total_lecciones' => 2,
                    'lecciones_completadas' => 1
                ],
                [
                    'total_lecciones' => 4,
                    'lecciones_completadas' => 0
                ]
            ]);

        $cantidadActuales = count(
            array_filter(
                $estados,
                static fn(string $estado): bool =>
                    $estado === 'current'
            )
        );

        self::assertSame(1, $cantidadActuales);
    }

    public function test_conserva_el_orden_de_las_habilidades(): void
    {
        $habilidades = [
            [
                'total_lecciones' => 1,
                'lecciones_completadas' => 1
            ],
            [
                'total_lecciones' => 2,
                'lecciones_completadas' => 0
            ],
            [
                'total_lecciones' => 3,
                'lecciones_completadas' => 0
            ]
        ];

        $estados =
            RutaAprendizajeService::determinarEstados(
                $habilidades
            );

        self::assertSame(
            [0, 1, 2],
            array_keys($estados)
        );

        self::assertSame(
            ['completed', 'current', 'locked'],
            $estados
        );
    }

    public function test_rechaza_cantidades_negativas(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Las cantidades de lecciones no pueden ser negativas.'
        );

        RutaAprendizajeService::determinarEstados([
            [
                'total_lecciones' => 3,
                'lecciones_completadas' => -1
            ]
        ]);
    }

    public function test_rechaza_una_estructura_incompleta(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Cada habilidad debe incluir el total '
            . 'de lecciones y las lecciones completadas.'
        );

        RutaAprendizajeService::determinarEstados([
            [
                'total_lecciones' => 3
            ]
        ]);
    }

    public function test_rechaza_cantidades_no_enteras(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        RutaAprendizajeService::determinarEstados([
            [
                'total_lecciones' => 3,
                'lecciones_completadas' => 1.5
            ]
        ]);
    }
}