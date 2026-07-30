<?php

declare(strict_types=1);

namespace Classes;

use Model\Usuario;

/**
 * Coordina la validación y persistencia de las
 * ediciones administrativas de usuarios.
 */
final class UsuarioEdicionAdminService
{
    public const USUARIO_ACTUALIZADO =
        'USUARIO_ACTUALIZADO';

    public const USUARIO_NO_EXISTE =
        'USUARIO_NO_EXISTE';

    public const DATOS_INVALIDOS =
        'DATOS_INVALIDOS';

    public const CORREO_DUPLICADO =
        'CORREO_DUPLICADO';

    public const AUTO_DESHABILITACION_NO_PERMITIDA =
        'AUTO_DESHABILITACION_NO_PERMITIDA';

    public const ERROR_PERSISTENCIA =
        'ERROR_PERSISTENCIA';

    /**
     * Campos que el formulario administrativo
     * tiene autorización para modificar.
     */
    private const CAMPOS_PERMITIDOS = [
        'nombres',
        'apellidos',
        'edad',
        'sexo',
        'correo',
        'universidad',
        'carrera',
        'habilitado',
        'password',
        'password2'
    ];

    /**
     * Valida y almacena la edición administrativa.
     *
     * @return array{
     *     ok: bool,
     *     estado: string,
     *     alertas: array,
     *     usuario: ?Usuario,
     *     passwordCambiada: bool
     * }
     */
    public static function editar(
        int $idUsuario,
        int $idAdministrador,
        array $datos
    ): array {
        if ($idUsuario <= 0) {
            return self::respuestaRechazada(
                self::USUARIO_NO_EXISTE
            );
        }

        $usuario = Usuario::find(
            $idUsuario
        );

        if (!$usuario instanceof Usuario) {
            return self::respuestaRechazada(
                self::USUARIO_NO_EXISTE
            );
        }

        /*
         * Se conservan los valores que deben mantenerse
         * cuando no se proporciona una contraseña nueva.
         */
        $passwordOriginal =
            (string) $usuario->password;

        $debeCambiarOriginal =
            (int) $usuario->debe_cambiar_password;

        /*
         * Los campos de contraseña deben iniciar vacíos.
         * De lo contrario, el hash recuperado desde MySQL
         * sería interpretado como una contraseña nueva.
         */
        $usuario->password = '';
        $usuario->password2 = '';

        $datosPermitidos =
            self::filtrarDatosPermitidos(
                $datos
            );

        $usuario->sincronizar(
            $datosPermitidos
        );

        $usuario->correo =
            mb_strtolower(
                trim(
                    (string) $usuario->correo
                ),
                'UTF-8'
            );

        $alertas =
            $usuario->validar_edicion();

        /*
         * La cuenta utilizada por el administrador
         * no puede deshabilitarse a sí misma.
         */
        if (
            $idUsuario === $idAdministrador
            && (int) $usuario->habilitado === 0
        ) {
            $usuario->habilitado = 1;

            $alertas['error'][] =
                'No puedes deshabilitar tu propia cuenta '
                . 'mientras tienes la sesión iniciada.';

            return self::respuestaRechazada(
                self::AUTO_DESHABILITACION_NO_PERMITIDA,
                $alertas,
                $usuario
            );
        }

        /*
         * El correo puede pertenecer al mismo usuario,
         * pero no a una cuenta diferente.
         */
        if (
            !isset($alertas['error'])
            && Usuario::correoEnUsoPorOtroUsuario(
                $usuario->correo,
                $idUsuario
            )
        ) {
            $alertas['error'][] =
                'El correo ya pertenece a otro usuario.';

            return self::respuestaRechazada(
                self::CORREO_DUPLICADO,
                $alertas,
                $usuario
            );
        }

        if (!empty($alertas)) {
            return self::respuestaRechazada(
                self::DATOS_INVALIDOS,
                $alertas,
                $usuario
            );
        }

        $passwordCambiada = false;

        if ($usuario->password !== '') {
            $usuario->hashPassword();

            /*
             * La contraseña establecida por el administrador
             * es temporal y debe cambiarse durante el próximo
             * inicio de sesión.
             */
            $usuario->debe_cambiar_password = 1;
            $passwordCambiada = true;
        } else {
            $usuario->password =
                $passwordOriginal;

            $usuario->debe_cambiar_password =
                $debeCambiarOriginal;
        }

        $resultado =
            $usuario->guardar();

        if (!$resultado) {
            return self::respuestaRechazada(
                self::ERROR_PERSISTENCIA,
                [
                    'error' => [
                        'No fue posible actualizar el usuario.'
                    ]
                ],
                $usuario
            );
        }

        $usuarioPersistido =
            Usuario::find(
                $idUsuario
            );

        return [
            'ok' =>
                true,

            'estado' =>
                self::USUARIO_ACTUALIZADO,

            'alertas' =>
                [],

            'usuario' =>
                $usuarioPersistido,

            'passwordCambiada' =>
                $passwordCambiada
        ];
    }

    /**
     * Elimina cualquier dato que no forme parte
     * de la edición administrativa permitida.
     */
    private static function filtrarDatosPermitidos(
        array $datos
    ): array {
        $permitidos = [];

        foreach (
            self::CAMPOS_PERMITIDOS
            as $campo
        ) {
            if (array_key_exists($campo, $datos)) {
                $permitidos[$campo] =
                    $datos[$campo];
            }
        }

        return $permitidos;
    }

    private static function respuestaRechazada(
        string $estado,
        array $alertas = [],
        ?Usuario $usuario = null
    ): array {
        return [
            'ok' =>
                false,

            'estado' =>
                $estado,

            'alertas' =>
                $alertas,

            'usuario' =>
                $usuario,

            'passwordCambiada' =>
                false
        ];
    }
}