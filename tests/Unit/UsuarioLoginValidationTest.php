<?php

declare(strict_types=1);

namespace Tests\Unit;

use Model\Usuario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UsuarioLoginValidationTest extends TestCase
{
    /**
     * Construye credenciales válidas y permite reemplazar
     * únicamente el dato que requiere cada caso.
     */
    private function crearCredenciales(
        array $cambios = []
    ): Usuario {
        $datosValidos = [
            'correo' => 'usuario@correo.com',
            'password' => 'Clave1!'
        ];

        return new Usuario(
            array_replace($datosValidos, $cambios)
        );
    }

    /**
     * Comprueba que las credenciales inválidas generen
     * la alerta correspondiente.
     */
    #[DataProvider('casosCredencialesInvalidas')]
    public function test_rechaza_credenciales_invalidas(
        array $cambios,
        string $mensajeEsperado
    ): void {
        $usuario = $this->crearCredenciales($cambios);

        $alertas = $usuario->validarLogin();

        self::assertContains(
            $mensajeEsperado,
            $alertas['error'] ?? []
        );
    }

    public static function casosCredencialesInvalidas(): array
    {
        return [
            'correo vacío' => [
                [
                    'correo' => ''
                ],
                'El correo es obligatorio'
            ],

            'correo compuesto solamente por espacios' => [
                [
                    'correo' => '   '
                ],
                'El correo es obligatorio'
            ],

            'correo con formato inválido' => [
                [
                    'correo' => 'correo-invalido'
                ],
                'Correo no válido'
            ],

            'contraseña vacía' => [
                [
                    'password' => ''
                ],
                'La contraseña no puede ir vacía'
            ]
        ];
    }

    /**
     * Comprueba diferentes formatos válidos de correo.
     */
    #[DataProvider('casosCorreosValidos')]
    public function test_acepta_formatos_validos_de_correo(
        string $correo
    ): void {
        $usuario = $this->crearCredenciales([
            'correo' => $correo
        ]);

        $alertas = $usuario->validarLogin();

        self::assertSame([], $alertas);
    }

    public static function casosCorreosValidos(): array
    {
        return [
            'correo convencional' => [
                'usuario@correo.com'
            ],

            'correo con subdominio' => [
                'usuario@estudiantes.universidad.edu.co'
            ],

            'correo con símbolo más' => [
                'usuario+pruebas@correo.com'
            ]
        ];
    }

    /**
     * Comprueba que dos campos vacíos produzcan
     * exactamente las dos alertas correspondientes.
     */
    public function test_detecta_correo_y_password_vacios(): void
    {
        $usuario = $this->crearCredenciales([
            'correo' => '',
            'password' => ''
        ]);

        $errores = $usuario->validarLogin()['error'] ?? [];

        self::assertCount(2, $errores);

        self::assertContains(
            'El correo es obligatorio',
            $errores
        );

        self::assertContains(
            'La contraseña no puede ir vacía',
            $errores
        );
    }

    /**
     * Comprueba que el correo se normalice eliminando
     * espacios externos y convirtiéndolo a minúsculas.
     */
    public function test_normaliza_el_correo_antes_de_validarlo(): void
    {
        $usuario = $this->crearCredenciales([
            'correo' => '  Usuario@Correo.COM  '
        ]);

        $alertas = $usuario->validarLogin();

        self::assertSame([], $alertas);

        self::assertSame(
            'usuario@correo.com',
            $usuario->correo
        );
    }

    /**
     * Comprueba que unas credenciales completamente
     * válidas no generen alertas.
     */
    public function test_acepta_credenciales_validas(): void
    {
        $usuario = $this->crearCredenciales();

        $alertas = $usuario->validarLogin();

        self::assertSame([], $alertas);
    }

    /**
     * Comprueba que una alerta generada anteriormente
     * no se conserve después de corregir los datos.
     */
    public function test_limpia_las_alertas_entre_validaciones(): void
    {
        $usuario = $this->crearCredenciales([
            'correo' => ''
        ]);

        $primeraValidacion = $usuario->validarLogin();

        self::assertNotEmpty($primeraValidacion);

        $usuario->correo = 'usuario@correo.com';

        $segundaValidacion = $usuario->validarLogin();

        self::assertSame([], $segundaValidacion);
    }
}