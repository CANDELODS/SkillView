<?php

declare(strict_types=1);

namespace Tests\Unit;

use Classes\Paginacion;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaginacionTest extends TestCase
{
    #[DataProvider('casosOffset')]
    public function test_calcula_correctamente_el_offset(
        int $paginaActual,
        int $registrosPorPagina,
        int $offsetEsperado
    ): void {
        $paginacion = new Paginacion(
            $paginaActual,
            $registrosPorPagina,
            100
        );

        self::assertSame(
            $offsetEsperado,
            $paginacion->offset()
        );
    }

    public static function casosOffset(): array
    {
        return [
            'primera página' => [
                1,
                10,
                0
            ],

            'segunda página' => [
                2,
                10,
                10
            ],

            'quinta página con cinco registros' => [
                5,
                5,
                20
            ]
        ];
    }

    #[DataProvider('casosTotalPaginas')]
    public function test_calcula_correctamente_el_total_de_paginas(
        int $totalRegistros,
        int $registrosPorPagina,
        int $totalEsperado
    ): void {
        $paginacion = new Paginacion(
            1,
            $registrosPorPagina,
            $totalRegistros
        );

        self::assertSame(
            $totalEsperado,
            $paginacion->totalPaginas()
        );
    }

    public static function casosTotalPaginas(): array
    {
        return [
            'sin registros' => [
                0,
                10,
                0
            ],

            'un registro' => [
                1,
                10,
                1
            ],

            'división exacta en una página' => [
                10,
                10,
                1
            ],

            'un registro adicional crea otra página' => [
                11,
                10,
                2
            ],

            'veinticinco registros en grupos de diez' => [
                25,
                10,
                3
            ],

            'cincuenta registros en grupos de cinco' => [
                50,
                5,
                10
            ]
        ];
    }

    #[DataProvider('casosPaginaAnterior')]
    public function test_determina_correctamente_la_pagina_anterior(
        int $paginaActual,
        int|false $resultadoEsperado
    ): void {
        $paginacion = new Paginacion(
            $paginaActual,
            10,
            100
        );

        self::assertSame(
            $resultadoEsperado,
            $paginacion->paginaAnterior()
        );
    }

    public static function casosPaginaAnterior(): array
    {
        return [
            'primera página no tiene anterior' => [
                1,
                false
            ],

            'segunda página vuelve a la primera' => [
                2,
                1
            ],

            'quinta página vuelve a la cuarta' => [
                5,
                4
            ]
        ];
    }

    #[DataProvider('casosPaginaSiguiente')]
    public function test_determina_correctamente_la_pagina_siguiente(
        int $paginaActual,
        int $totalRegistros,
        int|false $resultadoEsperado
    ): void {
        $paginacion = new Paginacion(
            $paginaActual,
            10,
            $totalRegistros
        );

        self::assertSame(
            $resultadoEsperado,
            $paginacion->paginaSiguiente()
        );
    }

    public static function casosPaginaSiguiente(): array
    {
        return [
            'primera página avanza a la segunda' => [
                1,
                25,
                2
            ],

            'segunda página avanza a la tercera' => [
                2,
                25,
                3
            ],

            'última página no tiene siguiente' => [
                3,
                25,
                false
            ],

            'sin registros no tiene siguiente' => [
                1,
                0,
                false
            ]
        ];
    }

    #[DataProvider('casosExtraQuery')]
    public function test_normaliza_los_parametros_adicionales(
        string $entrada,
        string $resultadoEsperado
    ): void {
        $paginacion = new Paginacion(
            1,
            10,
            20,
            $entrada
        );

        self::assertSame(
            $resultadoEsperado,
            $paginacion->extraQuery
        );
    }

    public static function casosExtraQuery(): array
    {
        return [
            'sin parámetro adicional' => [
                '',
                ''
            ],

            'parámetro sin prefijo' => [
                'busqueda=ana',
                '&busqueda=ana'
            ],

            'parámetro iniciado con interrogación' => [
                '?busqueda=ana',
                '&busqueda=ana'
            ],

            'parámetro iniciado con ampersand' => [
                '&busqueda=ana',
                '&busqueda=ana'
            ]
        ];
    }

    public function test_no_genera_enlace_anterior_en_la_primera_pagina(): void
    {
        $paginacion = new Paginacion(
            1,
            10,
            30
        );

        self::assertSame(
            '',
            $paginacion->enlaceAnterior()
        );
    }

    public function test_genera_el_enlace_anterior_y_conserva_la_busqueda(): void
    {
        $paginacion = new Paginacion(
            2,
            10,
            30,
            'busqueda=ana'
        );

        $html = $paginacion->enlaceAnterior();

        self::assertStringContainsString(
            'href="?page=1&busqueda=ana"',
            $html
        );

        self::assertStringContainsString(
            'paginacion__enlace--texto',
            $html
        );

        self::assertStringContainsString(
            'Anterior',
            $html
        );
    }

    public function test_no_genera_enlace_siguiente_en_la_ultima_pagina(): void
    {
        $paginacion = new Paginacion(
            3,
            10,
            25
        );

        self::assertSame(
            '',
            $paginacion->enlaceSiguiente()
        );
    }

    public function test_genera_el_enlace_siguiente_y_conserva_la_busqueda(): void
    {
        $paginacion = new Paginacion(
            1,
            10,
            25,
            'busqueda=ana'
        );

        $html = $paginacion->enlaceSiguiente();

        self::assertStringContainsString(
            'href="?page=2&busqueda=ana"',
            $html
        );

        self::assertStringContainsString(
            'paginacion__enlace--texto',
            $html
        );

        self::assertStringContainsString(
            'Siguiente',
            $html
        );
    }

    public function test_identifica_visualmente_la_pagina_actual(): void
    {
        $paginacion = new Paginacion(
            2,
            10,
            30
        );

        $html = $paginacion->numerosPagina();

        self::assertStringContainsString(
            '<span class="paginacion__enlace '
            . 'paginacion__enlace--actual">2</span>',
            $html
        );

        self::assertStringContainsString(
            'href="?page=1"',
            $html
        );

        self::assertStringContainsString(
            'href="?page=3"',
            $html
        );

        self::assertSame(
            1,
            substr_count(
                $html,
                'paginacion__enlace--actual'
            )
        );
    }

    public function test_conserva_la_busqueda_en_los_numeros_de_pagina(): void
    {
        $paginacion = new Paginacion(
            2,
            10,
            30,
            'busqueda=ana'
        );

        $html = $paginacion->numerosPagina();

        self::assertStringContainsString(
            'href="?page=1&busqueda=ana"',
            $html
        );

        self::assertStringContainsString(
            'href="?page=3&busqueda=ana"',
            $html
        );
    }

    public function test_no_muestra_paginacion_sin_registros(): void
    {
        $paginacion = new Paginacion(
            1,
            10,
            0
        );

        self::assertSame(
            '',
            $paginacion->paginacion()
        );
    }

    public function test_no_muestra_paginacion_cuando_existe_una_sola_pagina(): void
    {
        $paginacion = new Paginacion(
            1,
            10,
            8
        );

        self::assertSame(
            '',
            $paginacion->paginacion()
        );
    }

    public function test_genera_el_componente_cuando_existen_varias_paginas(): void
    {
        $paginacion = new Paginacion(
            2,
            10,
            25
        );

        $html = $paginacion->paginacion();

        self::assertStringContainsString(
            '<div class="paginacion">',
            $html
        );

        self::assertStringContainsString(
            'Anterior',
            $html
        );

        self::assertStringContainsString(
            'paginacion__enlace--actual">2',
            $html
        );

        self::assertStringContainsString(
            'Siguiente',
            $html
        );
    }

    public function test_rechaza_una_pagina_actual_inferior_a_uno(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'La página actual debe ser mayor '
            . 'o igual a uno.'
        );

        new Paginacion(
            0,
            10,
            20
        );
    }

    public function test_rechaza_cero_registros_por_pagina(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'La cantidad de registros por página '
            . 'debe ser mayor que cero.'
        );

        new Paginacion(
            1,
            0,
            20
        );
    }

    public function test_rechaza_un_total_de_registros_negativo(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'El total de registros no puede ser negativo.'
        );

        new Paginacion(
            1,
            10,
            -1
        );
    }
}