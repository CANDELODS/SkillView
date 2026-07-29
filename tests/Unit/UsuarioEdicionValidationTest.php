<?php

declare(strict_types=1);

namespace Tests\Unit;

use Model\Usuario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UsuarioEdicionValidationTest extends TestCase
{
    /**
     * Construye un usuario con datos válidos para edición.
     *
     * La contraseña queda vacía por defecto porque su cambio
     * es opcional dentro del formulario administrativo.
     */
    private function crearUsuarioValido(
        array $cambios = []
    ): Usuario {
        $datosValidos = [
            'id' => 10,
            'nombres' => 'Ana María',
            'apellidos' => 'Muñoz Peña',
            'edad' => 25,
            'sexo' => '0',
            'correo' => 'ana@correo.com',
            'password' => '',
            'password2' => '',
            'universidad' => 'Universidad del Cauca',
            'carrera' => 'Ingeniería de Sistemas',
            'habilitado' => '1'
        ];

        return new Usuario(
            array_replace($datosValidos, $cambios)
        );
    }

    /**
     * Comprueba que cada modificación inválida
     * genere la alerta correspondiente.
     */
    #[DataProvider('casosDatosInvalidos')]
    public function test_rechaza_datos_invalidos_en_la_edicion(
        array $cambios,
        string $mensajeEsperado
    ): void {
        $usuario = $this->crearUsuarioValido($cambios);

        $alertas = $usuario->validar_edicion();

        self::assertContains(
            $mensajeEsperado,
            $alertas['error'] ?? []
        );
    }

    public static function casosDatosInvalidos(): array
    {
        return [
            'nombre vacío' => [
                ['nombres' => ''],
                'El nombre es obligatorio y no puede estar vacío'
            ],

            'nombre compuesto solamente por espacios' => [
                ['nombres' => '   '],
                'El nombre es obligatorio y no puede estar vacío'
            ],

            'nombre con números' => [
                ['nombres' => 'Ana123'],
                'El nombre solo puede contener letras y espacios'
            ],

            'nombre superior a veinticinco caracteres' => [
                ['nombres' => str_repeat('A', 26)],
                'El nombre no puede superar 25 caracteres'
            ],

            'apellido vacío' => [
                ['apellidos' => ''],
                'El apellido es obligatorio y no puede estar vacío'
            ],

            'apellido con caracteres no permitidos' => [
                ['apellidos' => 'Muñoz123'],
                'El apellido solo puede contener letras y espacios'
            ],

            'apellido superior a veinticinco caracteres' => [
                ['apellidos' => str_repeat('A', 26)],
                'El apellido no puede superar 25 caracteres'
            ],

            'edad inferior al mínimo' => [
                ['edad' => 17],
                'La edad debe estar entre 18 y 35 años'
            ],

            'edad superior al máximo' => [
                ['edad' => 36],
                'La edad debe estar entre 18 y 35 años'
            ],

            'edad no numérica' => [
                ['edad' => 'veinte'],
                'La edad debe estar entre 18 y 35 años'
            ],

            'sexo sin seleccionar' => [
                ['sexo' => ''],
                'El sexo es obligatorio'
            ],

            'sexo con valor no permitido' => [
                ['sexo' => '2'],
                'El sexo es obligatorio'
            ],

            'universidad vacía' => [
                ['universidad' => ''],
                'La universidad es obligatorio y no puede estar vacío'
            ],

            'universidad con números' => [
                ['universidad' => 'Universidad 123'],
                'La universidad solo puede contener letras y espacios'
            ],

            'universidad superior a cuarenta y cinco caracteres' => [
                ['universidad' => str_repeat('A', 46)],
                'La universidad no puede superar 45 caracteres'
            ],

            'carrera vacía' => [
                ['carrera' => ''],
                'La carrera es obligatorio y no puede estar vacío'
            ],

            'carrera con números' => [
                ['carrera' => 'Ingeniería 123'],
                'La carrera solo puede contener letras y espacios'
            ],

            'carrera superior a cuarenta y cinco caracteres' => [
                ['carrera' => str_repeat('A', 46)],
                'La carrera no puede superar 45 caracteres'
            ],

            'correo vacío' => [
                ['correo' => ''],
                'El correo es obligatorio'
            ],

            'correo con formato inválido' => [
                ['correo' => 'correo-invalido'],
                'Correo no válido'
            ],

            'estado vacío' => [
                ['habilitado' => ''],
                'El estado seleccionado no es válido'
            ],

            'estado con valor no permitido' => [
                ['habilitado' => '2'],
                'El estado seleccionado no es válido'
            ],

            'confirmación escrita sin nueva contraseña' => [
                [
                    'password' => '',
                    'password2' => 'Clave1!'
                ],
                'La contraseña no puede ir vacía'
            ],

            'contraseña menor de seis caracteres' => [
                [
                    'password' => 'A1!',
                    'password2' => 'A1!'
                ],
                'La contraseña debe tener entre 6 y 16 caracteres'
            ],

            'contraseña superior a dieciséis caracteres' => [
                [
                    'password' => str_repeat('A', 14) . 'a1!',
                    'password2' => str_repeat('A', 14) . 'a1!'
                ],
                'La contraseña debe tener entre 6 y 16 caracteres'
            ],

            'contraseña sin letra mayúscula' => [
                [
                    'password' => 'clave1!',
                    'password2' => 'clave1!'
                ],
                'La contraseña debe contener al menos una letra mayúscula'
            ],

            'contraseña sin número' => [
                [
                    'password' => 'Clave!!',
                    'password2' => 'Clave!!'
                ],
                'La contraseña debe contener al menos un número'
            ],

            'contraseña sin carácter especial' => [
                [
                    'password' => 'Clave12',
                    'password2' => 'Clave12'
                ],
                'La contraseña debe contener al menos un carácter especial'
            ],

            'contraseñas diferentes' => [
                [
                    'password' => 'Clave1!',
                    'password2' => 'Clave2!'
                ],
                'Las contraseñas no coinciden'
            ]
        ];
    }

    /**
     * Comprueba los valores situados exactamente
     * en los límites y opciones permitidas.
     */
    #[DataProvider('casosValoresPermitidos')]
    public function test_acepta_valores_limite_y_opciones_permitidas(
        array $cambios
    ): void {
        $usuario = $this->crearUsuarioValido($cambios);

        $alertas = $usuario->validar_edicion();

        self::assertSame([], $alertas);
    }

    public static function casosValoresPermitidos(): array
    {
        $passwordMinima = 'Aa1!aa';

        $passwordMaxima =
            str_repeat('A', 13) . 'a1!';

        return [
            'edad mínima de dieciocho años' => [
                ['edad' => 18]
            ],

            'edad máxima de treinta y cinco años' => [
                ['edad' => 35]
            ],

            'sexo masculino' => [
                ['sexo' => '0']
            ],

            'sexo femenino' => [
                ['sexo' => '1']
            ],

            'prefiere no indicar el sexo' => [
                ['sexo' => '3']
            ],

            'usuario deshabilitado' => [
                ['habilitado' => '0']
            ],

            'usuario habilitado' => [
                ['habilitado' => '1']
            ],

            'contraseña con seis caracteres' => [
                [
                    'password' => $passwordMinima,
                    'password2' => $passwordMinima
                ]
            ],

            'contraseña con dieciséis caracteres' => [
                [
                    'password' => $passwordMaxima,
                    'password2' => $passwordMaxima
                ]
            ],

            'contraseña válida convencional' => [
                [
                    'password' => 'NuevaClave1!',
                    'password2' => 'NuevaClave1!'
                ]
            ],

            'nombre con veinticinco caracteres' => [
                ['nombres' => str_repeat('A', 25)]
            ],

            'apellido con veinticinco caracteres' => [
                ['apellidos' => str_repeat('A', 25)]
            ],

            'universidad con cuarenta y cinco caracteres' => [
                ['universidad' => str_repeat('A', 45)]
            ],

            'carrera con cuarenta y cinco caracteres' => [
                ['carrera' => str_repeat('A', 45)]
            ]
        ];
    }

    /**
     * Comprueba que los campos de contraseña puedan
     * permanecer vacíos durante la edición.
     */
    public function test_acepta_una_edicion_sin_cambiar_la_password(): void
    {
        $usuario = $this->crearUsuarioValido([
            'password' => '',
            'password2' => ''
        ]);

        $alertas = $usuario->validar_edicion();

        self::assertSame([], $alertas);
    }

    /**
     * Comprueba que el modelo informe varios errores
     * detectados durante una misma edición.
     */
    public function test_detecta_varios_datos_invalidos_simultaneamente(): void
    {
        $usuario = $this->crearUsuarioValido([
            'nombres' => '',
            'edad' => 17,
            'sexo' => '2',
            'correo' => 'correo-invalido',
            'habilitado' => '3',
            'password' => 'clave',
            'password2' => 'diferente'
        ]);

        $errores =
            $usuario->validar_edicion()['error'] ?? [];

        self::assertContains(
            'El nombre es obligatorio y no puede estar vacío',
            $errores
        );

        self::assertContains(
            'La edad debe estar entre 18 y 35 años',
            $errores
        );

        self::assertContains(
            'El sexo es obligatorio',
            $errores
        );

        self::assertContains(
            'Correo no válido',
            $errores
        );

        self::assertContains(
            'El estado seleccionado no es válido',
            $errores
        );

        self::assertContains(
            'Las contraseñas no coinciden',
            $errores
        );
    }

    /**
     * Comprueba que las alertas estáticas no se
     * acumulen entre validaciones consecutivas.
     */
    public function test_limpia_las_alertas_entre_validaciones(): void
    {
        $usuario = $this->crearUsuarioValido([
            'nombres' => ''
        ]);

        $primeraValidacion =
            $usuario->validar_edicion();

        self::assertNotEmpty(
            $primeraValidacion
        );

        $usuario->nombres = 'Ana María';

        $segundaValidacion =
            $usuario->validar_edicion();

        self::assertSame(
            [],
            $segundaValidacion
        );
    }

    /**
     * Comprueba que los espacios externos sean eliminados
     * antes de validar y conservar los datos editados.
     */
    public function test_elimina_espacios_al_inicio_y_al_final(): void
    {
        $usuario = $this->crearUsuarioValido([
            'nombres' => '  Ana María  ',
            'apellidos' => '  Muñoz Peña  ',
            'universidad' => '  Universidad del Cauca  ',
            'carrera' => '  Ingeniería de Sistemas  ',
            'correo' => '  ana@correo.com  '
        ]);

        $alertas = $usuario->validar_edicion();

        self::assertSame([], $alertas);
        self::assertSame('Ana María', $usuario->nombres);
        self::assertSame('Muñoz Peña', $usuario->apellidos);
        self::assertSame(
            'Universidad del Cauca',
            $usuario->universidad
        );
        self::assertSame(
            'Ingeniería de Sistemas',
            $usuario->carrera
        );
        self::assertSame(
            'ana@correo.com',
            $usuario->correo
        );
    }
}