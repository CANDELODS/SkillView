<?php

declare(strict_types=1);

namespace Tests\Unit;

use Classes\LogroService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LogroServiceTest extends TestCase
{
    #[DataProvider('casosSlugHabilidad')]
    public function test_obtiene_el_slug_correcto(
        string $habilidad,
        ?string $slugEsperado
    ): void {
        self::assertSame(
            $slugEsperado,
            LogroService::slugHabilidad($habilidad)
        );
    }

    public static function casosSlugHabilidad(): array
    {
        return [
            'autoconfianza' => [
                'Autoconfianza',
                'autoconfianza'
            ],

            'manejo del estrés' => [
                'Manejo del Estrés',
                'estres'
            ],

            'inteligencia emocional' => [
                'Inteligencia Emocional',
                'inteligencia-emocional'
            ],

            'comunicación asertiva' => [
                'Comunicación Asertiva',
                'comunicacion-asertiva'
            ],

            'comunicación no verbal' => [
                'Comunicación No Verbal',
                'comunicacion-no-verbal'
            ],

            'empatía y escucha activa' => [
                'Empatía y Escucha Activa',
                'empatia-y-escucha-activa'
            ],

            'trabajo en equipo' => [
                'Trabajo en Equipo',
                'trabajo-en-equipo'
            ],

            'responsabilidad' => [
                'Responsabilidad',
                'responsabilidad'
            ],

            'adaptabilidad' => [
                'Adaptabilidad',
                'adaptabilidad'
            ],

            'actitud positiva' => [
                'Actitud Positiva',
                'actitud-positiva'
            ],

            'liderazgo' => [
                'Liderazgo',
                'liderazgo'
            ],

            'nombre con espacios externos' => [
                '  Liderazgo  ',
                'liderazgo'
            ],

            'habilidad desconocida' => [
                'Creatividad',
                null
            ]
        ];
    }

    #[DataProvider('casosIconos')]
    public function test_construye_los_iconos_correctamente(
        string $tipo,
        string $habilidad,
        ?string $iconoEsperado
    ): void {
        $iconoObtenido = match ($tipo) {
            'habilidad' =>
                LogroService::iconoHabilidad(
                    $habilidad
                ),

            'desempeno' =>
                LogroService::iconoDesempeno(
                    $habilidad
                )
        };

        self::assertSame(
            $iconoEsperado,
            $iconoObtenido
        );
    }

    public static function casosIconos(): array
    {
        return [
            'icono de habilidad' => [
                'habilidad',
                'Autoconfianza',
                'logros/habilidad_autoconfianza'
            ],

            'icono de desempeño' => [
                'desempeno',
                'Manejo del Estrés',
                'logros/desempeno_estres'
            ],

            'icono de habilidad desconocida' => [
                'habilidad',
                'Creatividad',
                null
            ],

            'icono de desempeño desconocido' => [
                'desempeno',
                'Creatividad',
                null
            ]
        ];
    }

    #[DataProvider('casosHabilidadCompletada')]
    public function test_determina_si_la_habilidad_fue_completada(
        int $total,
        int $completadas,
        bool $resultadoEsperado
    ): void {
        self::assertSame(
            $resultadoEsperado,
            LogroService::habilidadCompletada(
                $total,
                $completadas
            )
        );
    }

    public static function casosHabilidadCompletada(): array
    {
        return [
            'habilidad sin lecciones' => [
                0,
                0,
                false
            ],

            'ninguna lección completada' => [
                3,
                0,
                false
            ],

            'habilidad pendiente' => [
                3,
                2,
                false
            ],

            'habilidad exactamente completada' => [
                3,
                3,
                true
            ],

            'completadas superiores al total' => [
                3,
                4,
                true
            ]
        ];
    }

    #[DataProvider('casosDesempeno')]
    public function test_determina_si_el_reto_cumple_el_objetivo(
        int $completado,
        float $puntaje,
        float $objetivo,
        bool $resultadoEsperado
    ): void {
        self::assertSame(
            $resultadoEsperado,
            LogroService::retoCumpleDesempeno(
                $completado,
                $puntaje,
                $objetivo
            )
        );
    }

    public static function casosDesempeno(): array
    {
        return [
            'reto no completado' => [
                0,
                100,
                70,
                false
            ],

            'puntaje inferior al objetivo' => [
                1,
                69,
                70,
                false
            ],

            'puntaje igual al objetivo' => [
                1,
                70,
                70,
                true
            ],

            'puntaje superior al objetivo' => [
                1,
                90,
                70,
                true
            ]
        ];
    }

    #[DataProvider('casosEtiquetaTipo')]
    public function test_asigna_la_etiqueta_del_tipo(
        int $tipo,
        string $etiquetaEsperada
    ): void {
        self::assertSame(
            $etiquetaEsperada,
            LogroService::etiquetaTipo($tipo)
        );
    }

    public static function casosEtiquetaTipo(): array
    {
        return [
            'tipo habilidad' => [
                1,
                'Habilidad'
            ],

            'tipo puntaje' => [
                2,
                'Puntaje'
            ],

            'tipo retos' => [
                3,
                'Retos'
            ],

            'tipo desempeño' => [
                4,
                'Desempeño'
            ],

            'tipo desconocido' => [
                99,
                'General'
            ]
        ];
    }

    #[DataProvider('casosFecha')]
    public function test_formatea_la_fecha_del_logro(
        ?string $fecha,
        string $resultadoEsperado
    ): void {
        self::assertSame(
            $resultadoEsperado,
            LogroService::formatearFecha($fecha)
        );
    }

    public static function casosFecha(): array
    {
        return [
            'fecha válida' => [
                '2026-07-25',
                '25 jul 2026'
            ],

            'fecha con hora' => [
                '2026-01-05 14:30:00',
                '5 ene 2026'
            ],

            'fecha nula' => [
                null,
                ''
            ],

            'fecha inválida' => [
                'fecha-invalida',
                ''
            ]
        ];
    }

    public function test_rechaza_cantidades_de_lecciones_negativas(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Las cantidades de lecciones '
            . 'no pueden ser negativas.'
        );

        LogroService::habilidadCompletada(
            -1,
            0
        );
    }

    public function test_rechaza_lecciones_completadas_negativas(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Las cantidades de lecciones '
            . 'no pueden ser negativas.'
        );

        LogroService::habilidadCompletada(
            3,
            -1
        );
    }

    public function test_rechaza_un_estado_de_reto_invalido(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'El estado de finalización del reto '
            . 'no es válido.'
        );

        LogroService::retoCumpleDesempeno(
            2,
            70,
            70
        );
    }

    public function test_rechaza_un_puntaje_negativo(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'El puntaje obtenido no puede ser negativo.'
        );

        LogroService::retoCumpleDesempeno(
            1,
            -1,
            70
        );
    }

    public function test_rechaza_un_objetivo_negativo(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'El valor objetivo no puede ser negativo.'
        );

        LogroService::retoCumpleDesempeno(
            1,
            70,
            -1
        );
    }
}