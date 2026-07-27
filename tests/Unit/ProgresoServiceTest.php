<?php

declare(strict_types=1);

namespace Tests\Unit;

use Classes\ProgresoService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProgresoServiceTest extends TestCase
{
    /**
     * Verifica el cálculo consolidado:
     * 50 % lecciones + 50 % retos.
     */
    #[DataProvider('casosCalculoProgreso')]
    public function test_calcula_correctamente_el_progreso(
        float $progresoLecciones,
        float $progresoRetos,
        int $resultadoEsperado
    ): void {
        $resultadoObtenido = ProgresoService::calcularProgreso(
            $progresoLecciones,
            $progresoRetos
        );

        self::assertSame(
            $resultadoEsperado,
            $resultadoObtenido
        );
    }

    /**
     * Datos utilizados para probar diferentes combinaciones
     * de progreso en lecciones y retos.
     */
    public static function casosCalculoProgreso(): array
    {
        return [
            'sin actividades completadas' => [
                0,
                0,
                0
            ],

            'solo lecciones completadas' => [
                100,
                0,
                50
            ],

            'solo retos completados' => [
                0,
                100,
                50
            ],

            'progreso diferente en cada componente' => [
                80,
                60,
                70
            ],

            'todas las actividades completadas' => [
                100,
                100,
                100
            ]
        ];
    }

    /**
     * Verifica especialmente los valores límite
     * entre Básico, Intermedio y Avanzado.
     */
    #[DataProvider('casosAsignacionNivel')]
    public function test_asigna_el_nivel_correcto(
        float $progreso,
        string $nivelEsperado
    ): void {
        $nivelObtenido = ProgresoService::determinarNivel(
            $progreso
        );

        self::assertSame(
            $nivelEsperado,
            $nivelObtenido
        );
    }

    /**
     * Valores límite establecidos en los requerimientos
     * funcionales de SkillView.
     */
    public static function casosAsignacionNivel(): array
    {
        return [
            'inicio del nivel básico' => [
                0,
                'Básico'
            ],

            'límite superior de básico' => [
                33,
                'Básico'
            ],

            'inicio de intermedio' => [
                34,
                'Intermedio'
            ],

            'límite superior de intermedio' => [
                66,
                'Intermedio'
            ],

            'inicio de avanzado' => [
                67,
                'Avanzado'
            ],

            'progreso completo' => [
                100,
                'Avanzado'
            ]
        ];
    }

    /**
     * Verifica que no se acepten porcentajes negativos.
     */
    public function test_rechaza_un_porcentaje_negativo(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'El progreso de las lecciones debe estar entre 0 y 100.'
        );

        ProgresoService::calcularProgreso(-1, 50);
    }

    /**
     * Verifica que no se acepten porcentajes superiores a 100.
     */
    public function test_rechaza_un_porcentaje_superior_a_cien(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'El progreso consolidado debe estar entre 0 y 100.'
        );

        ProgresoService::determinarNivel(101);
    }
}