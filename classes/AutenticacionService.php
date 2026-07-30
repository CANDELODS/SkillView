<?php

declare(strict_types=1);

namespace Classes;

use Model\Usuario;

/**
 * Centraliza la consulta y las decisiones relacionadas
 * con la autenticación de usuarios en SkillView.
 */
final class AutenticacionService
{
    public const AUTENTICADO =
        'AUTENTICADO';

    public const USUARIO_NO_EXISTE =
        'USUARIO_NO_EXISTE';

    public const PASSWORD_INCORRECTA =
        'PASSWORD_INCORRECTA';

    public const CUENTA_DESHABILITADA =
        'CUENTA_DESHABILITADA';

    public const CAMBIO_PASSWORD_REQUERIDO =
        'CAMBIO_PASSWORD_REQUERIDO';

    /**
     * Consulta el usuario, verifica la contraseña
     * y determina el tipo de acceso permitido.
     *
     * @return array{
     *     estado: string,
     *     mensaje: ?string,
     *     redireccion: ?string,
     *     sesion: array<string, mixed>,
     *     usuario: ?Usuario
     * }
     */
    public static function autenticar(
        string $correo,
        string $password
    ): array {
        $correoNormalizado = mb_strtolower(
            trim($correo),
            'UTF-8'
        );

        $usuario = Usuario::where(
            'correo',
            $correoNormalizado
        );

        if (!$usuario instanceof Usuario) {
            return self::respuestaRechazada(
                self::USUARIO_NO_EXISTE,
                'El usuario no existe.'
            );
        }

        /*
         * Se verifica la contraseña antes de informar
         * cualquier condición interna de la cuenta.
         */
        if (
            !password_verify(
                $password,
                (string) $usuario->password
            )
        ) {
            return self::respuestaRechazada(
                self::PASSWORD_INCORRECTA,
                'La contraseña es incorrecta.'
            );
        }

        /*
         * Una cuenta deshabilitada permanece en la base
         * de datos, pero no puede iniciar sesión.
         */
        if ((int) $usuario->habilitado !== 1) {
            return self::respuestaRechazada(
                self::CUENTA_DESHABILITADA,
                'Tu cuenta se encuentra deshabilitada. '
                . 'Comunícate con un administrador.'
            );
        }

        /*
         * Cuando debe cambiar la contraseña, únicamente
         * se prepara una sesión temporal.
         */
        if (
            (int) $usuario->debe_cambiar_password === 1
        ) {
            return [
                'estado' =>
                    self::CAMBIO_PASSWORD_REQUERIDO,

                'mensaje' =>
                    null,

                'redireccion' =>
                    '/cambiar-password',

                'sesion' => [
                    'cambio_password_usuario_id' =>
                        (int) $usuario->id
                ],

                'usuario' =>
                    $usuario
            ];
        }

        $redireccion =
            (int) $usuario->admin === 1
                ? '/admin/dashboard'
                : '/principal';

        return [
            'estado' =>
                self::AUTENTICADO,

            'mensaje' =>
                null,

            'redireccion' =>
                $redireccion,

            'sesion' =>
                self::construirSesionNormal(
                    $usuario
                ),

            'usuario' =>
                $usuario
        ];
    }

    /**
     * Construye los datos utilizados en una sesión
     * autenticada normal.
     */
    private static function construirSesionNormal(
        Usuario $usuario
    ): array {
        return [
            'id' =>
                (int) $usuario->id,

            'nombres' =>
                (string) $usuario->nombres,

            'apellidos' =>
                (string) $usuario->apellidos,

            'edad' =>
                (int) $usuario->edad,

            'sexo' =>
                (int) $usuario->sexo,

            'correo' =>
                (string) $usuario->correo,

            'universidad' =>
                (string) $usuario->universidad,

            'carrera' =>
                (string) $usuario->carrera,

            'admin' =>
                (int) $usuario->admin
        ];
    }

    /**
     * Genera una respuesta sin sesión ni redirección.
     */
    private static function respuestaRechazada(
        string $estado,
        string $mensaje
    ): array {
        return [
            'estado' =>
                $estado,

            'mensaje' =>
                $mensaje,

            'redireccion' =>
                null,

            'sesion' =>
                [],

            'usuario' =>
                null
        ];
    }
}