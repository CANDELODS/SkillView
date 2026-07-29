<?php

declare(strict_types=1);

namespace Tests\Unit;

use Classes\PorcentajeActividadService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PorcentajeActividadServiceTest extends TestCase
{
    /**
     * Comprueba diferentes relaciones entre actividades
     * completadas y actividades disponibles.
     */
    #[DataProvider('casosCalculoPorcentaje')]
    public function test_calcula_correctamente_el_porcentaje(
        int $actividadesCompletadas,
        int $totalActividades,
        int $porcentajeEsperado
    ): void {
        $porcentajeObtenido =
            PorcentajeActividadService::calcular(
                $actividadesCompletadas,
                $totalActividades
            );

        self::assertSame(
            $porcentajeEsperado,
            $porcentajeObtenido
        );
    }

    public static function casosCalculoPorcentaje(): array
    {
        return [
            'ninguna de cuatro actividades' => [
                0,
                4,
                0
            ],

            'una de cuatro actividades' => [
                1,
                4,
                25
            ],

            'dos de cuatro actividades' => [
                2,
                4,
                50
            ],

            'tres de cuatro actividades' => [
                3,
                4,
                75
            ],

            'cuatro de cuatro actividades' => [
                4,
                4,
                100
            ],

            'una de tres actividades redondea hacia abajo' => [
                1,
                3,
                33
            ],

            'dos de tres actividades redondea hacia arriba' => [
                2,
                3,
                67
            ],

            'una única actividad completada' => [
                1,
                1,
                100
            ],

            'ninguna actividad disponible' => [
                0,
                0,
                0
            ],

            'completadas superiores al total' => [
                5,
                4,
                100
            ]
        ];
    }

    /**
     * Las actividades completadas no pueden ser negativas.
     */
    public function test_rechaza_actividades_completadas_negativas(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'La cantidad de actividades completadas '
            . 'no puede ser negativa.'
        );

        PorcentajeActividadService::calcular(-1, 4);
    }

    /**
     * El total de actividades no puede ser negativo.
     */
    public function test_rechaza_un_total_de_actividades_negativo(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'El total de actividades no puede ser negativo.'
        );

        PorcentajeActividadService::calcular(0, -1);
    }

    /**
     * No es coherente registrar actividades completadas
     * cuando no existen actividades disponibles.
     */
    public function test_rechaza_actividades_completadas_con_total_cero(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'No puede haber actividades completadas '
            . 'cuando el total es cero.'
        );

        PorcentajeActividadService::calcular(1, 0);
    }
}