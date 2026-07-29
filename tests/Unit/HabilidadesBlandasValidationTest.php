<?php

declare(strict_types=1);

namespace Tests\Unit;

use Model\HabilidadesBlandas;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HabilidadesBlandasValidationTest extends TestCase
{
    /**
     * Construye una habilidad que cumple todas las reglas
     * y permite reemplazar únicamente el dato evaluado.
     */
    private function crearHabilidadValida(
        array $cambios = []
    ): HabilidadesBlandas {
        $datosValidos = [
            'nombre' => 'Comunicación efectiva',
            'descripcion' =>
                'Capacidad para expresar ideas de manera clara',
            'tag' =>
                'Comunicación, Escucha activa, Expresión oral',
            'habilitado' => '1'
        ];

        return new HabilidadesBlandas(
            array_replace($datosValidos, $cambios)
        );
    }

    /**
     * Comprueba que los datos inválidos generen
     * la alerta correspondiente.
     */
    #[DataProvider('casosDatosInvalidos')]
    public function test_rechaza_datos_invalidos(
        array $cambios,
        string $mensajeEsperado
    ): void {
        $habilidad =
            $this->crearHabilidadValida($cambios);

        $alertas = $habilidad->validar();

        self::assertContains(
            $mensajeEsperado,
            $alertas['error'] ?? []
        );
    }

    public static function casosDatosInvalidos(): array
    {
        return [
            'nombre vacío' => [
                [
                    'nombre' => ''
                ],
                'El nombre es obligatorio'
            ],

            'nombre compuesto solamente por espacios' => [
                [
                    'nombre' => '   '
                ],
                'El nombre es obligatorio'
            ],

            'descripción vacía' => [
                [
                    'descripcion' => ''
                ],
                'La descripción es obligatoria'
            ],

            'descripción compuesta solamente por espacios' => [
                [
                    'descripcion' => '   '
                ],
                'La descripción es obligatoria'
            ],

            'tags vacíos' => [
                [
                    'tag' => ''
                ],
                'Los tags son obligatorios'
            ],

            'tags compuestos solamente por espacios' => [
                [
                    'tag' => '   '
                ],
                'Los tags son obligatorios'
            ],

            'tags compuestos solamente por comas' => [
                [
                    'tag' => ', , ,'
                ],
                'Los tags son obligatorios'
            ],

            'estado vacío' => [
                [
                    'habilitado' => ''
                ],
                'El estado seleccionado no es válido'
            ],

            'estado no permitido' => [
                [
                    'habilitado' => '2'
                ],
                'El estado seleccionado no es válido'
            ]
        ];
    }

    /**
     * Comprueba las opciones permitidas para
     * el estado de la habilidad.
     */
    #[DataProvider('casosEstadosValidos')]
    public function test_acepta_estados_validos(
        int|string $estado
    ): void {
        $habilidad =
            $this->crearHabilidadValida([
                'habilitado' => $estado
            ]);

        $alertas = $habilidad->validar();

        self::assertSame([], $alertas);
    }

    public static function casosEstadosValidos(): array
    {
        return [
            'habilitada como cadena' => [
                '1'
            ],

            'deshabilitada como cadena' => [
                '0'
            ],

            'habilitada como entero' => [
                1
            ],

            'deshabilitada como entero' => [
                0
            ]
        ];
    }

    /**
     * Comprueba una habilidad completamente válida.
     */
    public function test_acepta_una_habilidad_valida(): void
    {
        $habilidad =
            $this->crearHabilidadValida();

        $alertas = $habilidad->validar();

        self::assertSame([], $alertas);
    }

    /**
     * Comprueba que los espacios externos sean eliminados
     * y que las etiquetas sean normalizadas.
     */
    public function test_normaliza_los_datos_de_la_habilidad(): void
    {
        $habilidad =
            $this->crearHabilidadValida([
                'nombre' =>
                    '  Comunicación efectiva  ',

                'descripcion' =>
                    '  Capacidad para comunicar ideas  ',

                'tag' =>
                    ' Liderazgo,  Comunicación , , Empatía '
            ]);

        $alertas = $habilidad->validar();

        self::assertSame([], $alertas);

        self::assertSame(
            'Comunicación efectiva',
            $habilidad->nombre
        );

        self::assertSame(
            'Capacidad para comunicar ideas',
            $habilidad->descripcion
        );

        self::assertSame(
            'Liderazgo, Comunicación, Empatía',
            $habilidad->tag
        );
    }

    /**
     * Comprueba que el modelo pueda reportar varios
     * campos inválidos durante una misma validación.
     */
    public function test_detecta_varios_errores_simultaneamente(): void
    {
        $habilidad =
            $this->crearHabilidadValida([
                'nombre' => '',
                'descripcion' => '',
                'tag' => ', ,',
                'habilitado' => '3'
            ]);

        $errores =
            $habilidad->validar()['error'] ?? [];

        self::assertCount(4, $errores);

        self::assertContains(
            'El nombre es obligatorio',
            $errores
        );

        self::assertContains(
            'La descripción es obligatoria',
            $errores
        );

        self::assertContains(
            'Los tags son obligatorios',
            $errores
        );

        self::assertContains(
            'El estado seleccionado no es válido',
            $errores
        );
    }

    /**
     * Comprueba que las alertas estáticas no se acumulen
     * entre validaciones consecutivas.
     */
    public function test_limpia_las_alertas_entre_validaciones(): void
    {
        $habilidad =
            $this->crearHabilidadValida([
                'nombre' => ''
            ]);

        $primeraValidacion =
            $habilidad->validar();

        self::assertNotEmpty(
            $primeraValidacion
        );

        $habilidad->nombre =
            'Trabajo en equipo';

        $segundaValidacion =
            $habilidad->validar();

        self::assertSame(
            [],
            $segundaValidacion
        );
    }
}