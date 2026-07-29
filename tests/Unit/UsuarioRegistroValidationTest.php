<?php

declare(strict_types=1);

namespace Tests\Unit;

use Model\Usuario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UsuarioRegistroValidationTest extends TestCase
{
    /**
     * Construye un usuario que cumple todas las reglas del registro.
     *
     * Cada prueba puede reemplazar solamente el dato que desea evaluar,
     * evitando que otros campos inválidos interfieran en el resultado.
     */
    private function crearUsuarioValido(array $cambios = []): Usuario
    {
        $datosValidos = [
            'nombres' => 'José María',
            'apellidos' => 'Muñoz Peña',
            'edad' => 25,
            'sexo' => '0',
            'correo' => 'usuario@correo.com',
            'password' => 'Clave1!',
            'password2' => 'Clave1!',
            'universidad' => 'Corporación Universitaria Comfacauca',
            'carrera' => 'Ingeniería de Sistemas',
            'autoriza_tratamiento_datos' => '1'
        ];

        return new Usuario(
            array_replace($datosValidos, $cambios)
        );
    }

    /**
     * Comprueba que cada dato inválido genere
     * la alerta correspondiente.
     */
    #[DataProvider('casosDatosInvalidos')]
    public function test_rechaza_datos_invalidos_del_registro(
        array $cambios,
        string $mensajeEsperado
    ): void {
        $usuario = $this->crearUsuarioValido($cambios);

        $alertas = $usuario->validar_cuenta();

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
                ['nombres' => 'Juan123'],
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
                ['apellidos' => 'Pérez123'],
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

            'correo con formato inválido' => [
                ['correo' => 'correo-invalido'],
                'Correo no válido'
            ],

            'sin autorización para el tratamiento de datos' => [
                ['autoriza_tratamiento_datos' => '0'],
                'Debes autorizar el tratamiento de tus datos personales para crear una cuenta'
            ],

            'contraseña vacía' => [
                [
                    'password' => '',
                    'password2' => ''
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
                    'password' => str_repeat('A', 15) . '1!',
                    'password2' => str_repeat('A', 15) . '1!'
                ],
                'La contraseña debe tener entre 6 y 16 caracteres'
            ],

            'contraseña sin mayúscula' => [
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
     * en los límites permitidos.
     */
    #[DataProvider('casosValoresPermitidos')]
    public function test_acepta_valores_limite_y_opciones_permitidas(
        array $cambios
    ): void {
        $usuario = $this->crearUsuarioValido($cambios);

        $alertas = $usuario->validar_cuenta();

        self::assertSame([], $alertas);
    }

    public static function casosValoresPermitidos(): array
    {
        $passwordMinimo = 'Aa1!aa'; // 6 caracteres
        $passwordMaximo = str_repeat('A', 13) . 'a1!'; // 16 caracteres

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

            'contraseña con seis caracteres' => [
                [
                    'password' => $passwordMinimo,
                    'password2' => $passwordMinimo
                ]
            ],

            'contraseña con dieciséis caracteres' => [
                [
                    'password' => $passwordMaximo,
                    'password2' => $passwordMaximo
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
     * Comprueba un registro completamente válido,
     * incluyendo letras con tilde y la letra ñ.
     */
    public function test_acepta_un_registro_completamente_valido(): void
    {
        $usuario = $this->crearUsuarioValido();

        $alertas = $usuario->validar_cuenta();

        self::assertSame([], $alertas);
    }

    /**
     * Comprueba que un correo vacío sea identificado
     * como un campo obligatorio.
     */
    public function test_rechaza_un_correo_vacio(): void
    {
        $usuario = $this->crearUsuarioValido([
            'correo' => ''
        ]);

        $alertas = $usuario->validar_cuenta();

        self::assertContains(
            'El correo es obligatorio',
            $alertas['error'] ?? []
        );
    }

    /**
     * Comprueba que el modelo pueda informar varios
     * errores durante una misma validación.
     */
    public function test_detecta_varios_datos_invalidos_simultaneamente(): void
    {
        $usuario = $this->crearUsuarioValido([
            'nombres' => '',
            'edad' => 17,
            'sexo' => '2',
            'correo' => 'correo-invalido',
            'autoriza_tratamiento_datos' => '0',
            'password' => 'clave',
            'password2' => 'diferente'
        ]);

        $errores = $usuario->validar_cuenta()['error'] ?? [];

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
            'Debes autorizar el tratamiento de tus datos personales para crear una cuenta',
            $errores
        );

        self::assertContains(
            'Las contraseñas no coinciden',
            $errores
        );
    }

    /**
     * Comprueba que las alertas estáticas no se acumulen
     * entre validaciones consecutivas.
     */
    public function test_limpia_las_alertas_entre_validaciones(): void
    {
        $usuario = $this->crearUsuarioValido([
            'nombres' => ''
        ]);

        $primeraValidacion = $usuario->validar_cuenta();

        self::assertNotEmpty($primeraValidacion);

        $usuario->nombres = 'Ana María';

        $segundaValidacion = $usuario->validar_cuenta();

        self::assertSame([], $segundaValidacion);
    }

    /**
     * Comprueba que los espacios externos sean eliminados
     * antes de validar y conservar los datos.
     */
    public function test_elimina_espacios_al_inicio_y_al_final(): void
    {
        $usuario = $this->crearUsuarioValido([
            'nombres' => '  José María  ',
            'apellidos' => '  Muñoz Peña  ',
            'universidad' => '  Universidad del Cauca  ',
            'carrera' => '  Ingeniería de Sistemas  ',
            'correo' => '  usuario@correo.com  '
        ]);

        $alertas = $usuario->validar_cuenta();

        self::assertSame([], $alertas);
        self::assertSame('José María', $usuario->nombres);
        self::assertSame('Muñoz Peña', $usuario->apellidos);
        self::assertSame('Universidad del Cauca', $usuario->universidad);
        self::assertSame('Ingeniería de Sistemas', $usuario->carrera);
        self::assertSame('usuario@correo.com', $usuario->correo);
    }
}