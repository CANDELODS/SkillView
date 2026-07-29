<?php

declare(strict_types=1);

namespace Tests\Unit;

use Model\Usuario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UsuarioPasswordTest extends TestCase
{
    /**
     * Crea un usuario con una nueva contraseña
     * y su correspondiente confirmación.
     */
    private function crearUsuarioParaCambio(
        string $password = 'Clave1!',
        string $password2 = 'Clave1!'
    ): Usuario {
        return new Usuario([
            'password' => $password,
            'password2' => $password2
        ]);
    }

    /**
     * Comprueba que la contraseña en texto plano
     * sea reemplazada por un hash.
     */
    public function test_reemplaza_la_password_plana_por_un_hash(): void
    {
        $passwordPlano = 'Clave1!';

        $usuario = new Usuario([
            'password' => $passwordPlano
        ]);

        $usuario->hashPassword();

        self::assertNotSame(
            $passwordPlano,
            $usuario->password
        );

        self::assertTrue(
            password_verify(
                $passwordPlano,
                $usuario->password
            )
        );
    }

    /**
     * Comprueba que el algoritmo utilizado sea bcrypt.
     */
    public function test_genera_el_hash_con_bcrypt(): void
    {
        $usuario = new Usuario([
            'password' => 'Clave1!'
        ]);

        $usuario->hashPassword();

        $informacionHash = password_get_info(
            $usuario->password
        );

        self::assertSame(
            'bcrypt',
            $informacionHash['algoName']
        );

        self::assertStringStartsWith(
            '$2y$',
            $usuario->password
        );
    }

    /**
     * bcrypt utiliza una sal aleatoria, por lo que dos hashes
     * de la misma contraseña no deben ser idénticos.
     */
    public function test_genera_hashes_diferentes_para_la_misma_password(): void
    {
        $passwordPlano = 'Clave1!';

        $primerUsuario = new Usuario([
            'password' => $passwordPlano
        ]);

        $segundoUsuario = new Usuario([
            'password' => $passwordPlano
        ]);

        $primerUsuario->hashPassword();
        $segundoUsuario->hashPassword();

        self::assertNotSame(
            $primerUsuario->password,
            $segundoUsuario->password
        );

        self::assertTrue(
            password_verify(
                $passwordPlano,
                $primerUsuario->password
            )
        );

        self::assertTrue(
            password_verify(
                $passwordPlano,
                $segundoUsuario->password
            )
        );
    }

    /**
     * Comprueba que el método del modelo acepte
     * la contraseña correcta.
     */
    public function test_comprueba_una_password_correcta(): void
    {
        $passwordPlano = 'Clave1!';
        $hash = password_hash(
            $passwordPlano,
            PASSWORD_BCRYPT
        );

        $usuario = new Usuario([
            'password' => $hash
        ]);

        $usuario->password_actual = $passwordPlano;

        self::assertTrue(
            $usuario->comprobar_password()
        );
    }

    /**
     * Comprueba que el método rechace
     * una contraseña incorrecta.
     */
    public function test_rechaza_una_password_incorrecta(): void
    {
        $hash = password_hash(
            'Clave1!',
            PASSWORD_BCRYPT
        );

        $usuario = new Usuario([
            'password' => $hash
        ]);

        $usuario->password_actual = 'OtraClave1!';

        self::assertFalse(
            $usuario->comprobar_password()
        );
    }

    /**
     * Comprueba las diferentes reglas que pueden
     * invalidar una nueva contraseña.
     */
    #[DataProvider('casosPasswordInvalida')]
    public function test_rechaza_passwords_invalidas(
        string $password,
        string $password2,
        string $mensajeEsperado
    ): void {
        $usuario = $this->crearUsuarioParaCambio(
            $password,
            $password2
        );

        $alertas = $usuario->validarCambioPassword();

        self::assertContains(
            $mensajeEsperado,
            $alertas['error'] ?? []
        );
    }

    public static function casosPasswordInvalida(): array
    {
        return [
            'password vacía' => [
                '',
                '',
                'La contraseña no puede ir vacía'
            ],

            'confirmación vacía' => [
                'Clave1!',
                '',
                'Debes confirmar la nueva contraseña'
            ],

            'menos de seis caracteres' => [
                'A1!',
                'A1!',
                'La contraseña debe tener entre 6 y 16 caracteres'
            ],

            'más de dieciséis caracteres' => [
                str_repeat('A', 14) . 'a1!',
                str_repeat('A', 14) . 'a1!',
                'La contraseña debe tener entre 6 y 16 caracteres'
            ],

            'sin letra mayúscula' => [
                'clave1!',
                'clave1!',
                'La contraseña debe contener al menos una letra mayúscula'
            ],

            'sin número' => [
                'Clave!!',
                'Clave!!',
                'La contraseña debe contener al menos un número'
            ],

            'sin carácter especial' => [
                'Clave12',
                'Clave12',
                'La contraseña debe contener al menos un carácter especial'
            ],

            'passwords diferentes' => [
                'Clave1!',
                'Clave2!',
                'Las contraseñas no coinciden'
            ]
        ];
    }

    /**
     * Comprueba contraseñas situadas exactamente
     * en los límites permitidos.
     */
    #[DataProvider('casosPasswordValida')]
    public function test_acepta_passwords_validas(
        string $password
    ): void {
        $usuario = $this->crearUsuarioParaCambio(
            $password,
            $password
        );

        $alertas = $usuario->validarCambioPassword();

        self::assertSame([], $alertas);
    }

    public static function casosPasswordValida(): array
    {
        return [
            'exactamente seis caracteres' => [
                'Aa1!aa'
            ],

            'exactamente dieciséis caracteres' => [
                str_repeat('A', 13) . 'a1!'
            ],

            'password válida convencional' => [
                'NuevaClave1!'
            ]
        ];
    }

    /**
     * Comprueba que el modelo pueda informar varias
     * reglas incumplidas durante una misma validación.
     */
    public function test_detecta_varios_errores_de_fortaleza(): void
    {
        $usuario = $this->crearUsuarioParaCambio(
            'abcdef',
            'abcdef'
        );

        $errores = $usuario
            ->validarCambioPassword()['error'] ?? [];

        self::assertCount(3, $errores);

        self::assertContains(
            'La contraseña debe contener al menos una letra mayúscula',
            $errores
        );

        self::assertContains(
            'La contraseña debe contener al menos un número',
            $errores
        );

        self::assertContains(
            'La contraseña debe contener al menos un carácter especial',
            $errores
        );
    }

    /**
     * Comprueba que las alertas no se acumulen
     * entre validaciones consecutivas.
     */
    public function test_limpia_las_alertas_entre_validaciones(): void
    {
        $usuario = $this->crearUsuarioParaCambio(
            'clave',
            'diferente'
        );

        $primeraValidacion =
            $usuario->validarCambioPassword();

        self::assertNotEmpty(
            $primeraValidacion
        );

        $usuario->password = 'NuevaClave1!';
        $usuario->password2 = 'NuevaClave1!';

        $segundaValidacion =
            $usuario->validarCambioPassword();

        self::assertSame(
            [],
            $segundaValidacion
        );
    }
}