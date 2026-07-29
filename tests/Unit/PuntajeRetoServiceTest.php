<?php

declare(strict_types=1);

namespace Tests\Unit;

use Classes\PuntajeRetoService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PuntajeRetoServiceTest extends TestCase
{
    /**
     * Comprueba el cálculo del 70 % y el redondeo
     * hacia arriba cuando el resultado tiene decimales.
     */
    #[DataProvider('casosPuntajeMinimo')]
    public function test_calcula_correctamente_el_puntaje_minimo(
        int $puntajeTotal,
        int $minimoEsperado
    ): void {
        $resultado = PuntajeRetoService::calcularPuntajeMinimo(
            $puntajeTotal
        );

        self::assertSame(
            $minimoEsperado,
            $resultado
        );
    }

    /**
     * Casos utilizados para calcular el puntaje mínimo.
     */
    public static function casosPuntajeMinimo(): array
    {
        return [
            'reto de cien puntos' => [
                100,
                70
            ],

            'reto de cincuenta puntos' => [
                50,
                35
            ],

            'reto de veinticinco puntos con redondeo' => [
                25,
                18
            ],

            'reto de diez puntos' => [
                10,
                7
            ],

            'reto de un punto' => [
                1,
                1
            ]
        ];
    }

    /**
     * Comprueba la aprobación y reprobación
     * según el puntaje obtenido.
     */
    #[DataProvider('casosAprobacion')]
    public function test_determina_correctamente_si_el_reto_fue_aprobado(
        int $puntajeObtenido,
        int $puntajeTotal,
        bool $resultadoEsperado
    ): void {
        $resultado = PuntajeRetoService::aprobo(
            $puntajeObtenido,
            $puntajeTotal
        );

        self::assertSame(
            $resultadoEsperado,
            $resultado
        );
    }

    /**
     * Casos ubicados por debajo, exactamente en el límite
     * y por encima del puntaje mínimo.
     */
    public static function casosAprobacion(): array
    {
        return [
            'un punto por debajo del mínimo' => [
                69,
                100,
                false
            ],

            'exactamente en el mínimo' => [
                70,
                100,
                true
            ],

            'puntaje superior al mínimo' => [
                90,
                100,
                true
            ],

            'puntaje máximo del reto' => [
                100,
                100,
                true
            ],

            'debajo del mínimo con redondeo' => [
                17,
                25,
                false
            ],

            'mínimo aprobado después del redondeo' => [
                18,
                25,
                true
            ]
        ];
    }

    /**
     * Un reto no puede tener un puntaje total igual a cero.
     */
    public function test_rechaza_un_puntaje_total_igual_a_cero(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'El puntaje total debe ser mayor que cero.'
        );

        PuntajeRetoService::calcularPuntajeMinimo(0);
    }

    /**
     * Un reto no puede tener un puntaje total negativo.
     */
    public function test_rechaza_un_puntaje_total_negativo(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'El puntaje total debe ser mayor que cero.'
        );

        PuntajeRetoService::calcularPuntajeMinimo(-10);
    }

    /**
     * El usuario no puede obtener un puntaje negativo.
     */
    public function test_rechaza_un_puntaje_obtenido_negativo(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'El puntaje obtenido no puede ser negativo.'
        );

        PuntajeRetoService::aprobo(-1, 100);
    }

    /**
     * El usuario no puede obtener más puntos
     * que los disponibles en el reto.
     */
    public function test_rechaza_un_puntaje_superior_al_total(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'El puntaje obtenido no puede superar el puntaje total.'
        );

        PuntajeRetoService::aprobo(101, 100);
    }
}