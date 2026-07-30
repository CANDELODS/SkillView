<?php

declare(strict_types=1);

namespace Tests\Integration;

use Classes\UsuarioEdicionAdminService;
use Model\Usuario;

require_once __DIR__
    . '/DatabaseIntegrationTestCase.php';

final class UsuarioEdicionAdminIntegrationTest extends
    DatabaseIntegrationTestCase
{
    private const PASSWORD_ORIGINAL =
        'Clave1!';

    private int $idAdministrador;
    private int $idUsuario;
    private string $correoAdministrador;
    private string $correoUsuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->correoAdministrador =
            'admin_edicion_'
            . bin2hex(random_bytes(5))
            . '@skillview.test';

        $this->correoUsuario =
            'usuario_edicion_'
            . bin2hex(random_bytes(5))
            . '@skillview.test';

        $this->idAdministrador =
            $this->crearUsuario(
                $this->correoAdministrador,
                1
            );

        $this->idUsuario =
            $this->crearUsuario(
                $this->correoUsuario,
                0
            );
    }

    public function test_actualiza_los_datos_personales_y_academicos(): void
    {
        $datos = $this->datosEdicion(
            $this->idUsuario,
            [
                'nombres' =>
                    '  José Andrés  ',

                'apellidos' =>
                    '  Pérez Gómez  ',

                'edad' =>
                    28,

                'sexo' =>
                    1,

                'correo' =>
                    '  NUEVO_'
                    . bin2hex(random_bytes(4))
                    . '@SKILLVIEW.TEST  ',

                'universidad' =>
                    'Universidad del Cauca',

                'carrera' =>
                    'Ingeniería de Sistemas'
            ]
        );

        $correoEsperado =
            mb_strtolower(
                trim($datos['correo']),
                'UTF-8'
            );

        $resultado =
            UsuarioEdicionAdminService::editar(
                $this->idUsuario,
                $this->idAdministrador,
                $datos
            );

        self::assertTrue(
            $resultado['ok']
        );

        self::assertSame(
            UsuarioEdicionAdminService::
                USUARIO_ACTUALIZADO,
            $resultado['estado']
        );

        $usuario =
            Usuario::find(
                $this->idUsuario
            );

        self::assertSame(
            'José Andrés',
            $usuario->nombres
        );

        self::assertSame(
            'Pérez Gómez',
            $usuario->apellidos
        );

        self::assertSame(
            28,
            (int) $usuario->edad
        );

        self::assertSame(
            1,
            (int) $usuario->sexo
        );

        self::assertSame(
            $correoEsperado,
            $usuario->correo
        );

        self::assertSame(
            'Universidad del Cauca',
            $usuario->universidad
        );

        self::assertSame(
            'Ingeniería de Sistemas',
            $usuario->carrera
        );
    }

    public function test_conserva_el_hash_si_no_se_cambia_la_password(): void
    {
        $usuarioAntes =
            Usuario::find(
                $this->idUsuario
            );

        $hashOriginal =
            $usuarioAntes->password;

        $resultado =
            UsuarioEdicionAdminService::editar(
                $this->idUsuario,
                $this->idAdministrador,
                $this->datosEdicion(
                    $this->idUsuario
                )
            );

        self::assertTrue(
            $resultado['ok']
        );

        self::assertFalse(
            $resultado['passwordCambiada']
        );

        $usuarioDespues =
            Usuario::find(
                $this->idUsuario
            );

        self::assertSame(
            $hashOriginal,
            $usuarioDespues->password
        );

        self::assertSame(
            0,
            (int) $usuarioDespues
                ->debe_cambiar_password
        );

        self::assertTrue(
            password_verify(
                self::PASSWORD_ORIGINAL,
                $usuarioDespues->password
            )
        );
    }

    public function test_genera_un_nuevo_hash_y_obliga_el_cambio_de_password(): void
    {
        $usuarioAntes =
            Usuario::find(
                $this->idUsuario
            );

        $hashOriginal =
            $usuarioAntes->password;

        $passwordNueva =
            'NuevaClave2!';

        $resultado =
            UsuarioEdicionAdminService::editar(
                $this->idUsuario,
                $this->idAdministrador,
                $this->datosEdicion(
                    $this->idUsuario,
                    [
                        'password' =>
                            $passwordNueva,

                        'password2' =>
                            $passwordNueva
                    ]
                )
            );

        self::assertTrue(
            $resultado['ok']
        );

        self::assertTrue(
            $resultado['passwordCambiada']
        );

        $usuario =
            Usuario::find(
                $this->idUsuario
            );

        self::assertNotSame(
            $hashOriginal,
            $usuario->password
        );

        self::assertTrue(
            password_verify(
                $passwordNueva,
                $usuario->password
            )
        );

        self::assertFalse(
            password_verify(
                self::PASSWORD_ORIGINAL,
                $usuario->password
            )
        );

        self::assertSame(
            1,
            (int) $usuario
                ->debe_cambiar_password
        );
    }

    public function test_deshabilita_y_rehabilita_una_cuenta(): void
    {
        $resultadoDeshabilitado =
            UsuarioEdicionAdminService::editar(
                $this->idUsuario,
                $this->idAdministrador,
                $this->datosEdicion(
                    $this->idUsuario,
                    [
                        'habilitado' => 0
                    ]
                )
            );

        self::assertTrue(
            $resultadoDeshabilitado['ok']
        );

        self::assertSame(
            0,
            (int) Usuario::find(
                $this->idUsuario
            )->habilitado
        );

        $resultadoHabilitado =
            UsuarioEdicionAdminService::editar(
                $this->idUsuario,
                $this->idAdministrador,
                $this->datosEdicion(
                    $this->idUsuario,
                    [
                        'habilitado' => 1
                    ]
                )
            );

        self::assertTrue(
            $resultadoHabilitado['ok']
        );

        self::assertSame(
            1,
            (int) Usuario::find(
                $this->idUsuario
            )->habilitado
        );
    }

    public function test_impide_que_el_administrador_se_deshabilite_a_si_mismo(): void
    {
        $resultado =
            UsuarioEdicionAdminService::editar(
                $this->idAdministrador,
                $this->idAdministrador,
                $this->datosEdicion(
                    $this->idAdministrador,
                    [
                        'habilitado' => 0
                    ]
                )
            );

        self::assertFalse(
            $resultado['ok']
        );

        self::assertSame(
            UsuarioEdicionAdminService::
                AUTO_DESHABILITACION_NO_PERMITIDA,
            $resultado['estado']
        );

        self::assertArrayHasKey(
            'error',
            $resultado['alertas']
        );

        self::assertSame(
            1,
            (int) Usuario::find(
                $this->idAdministrador
            )->habilitado
        );
    }

    public function test_rechaza_datos_invalidos_sin_modificar_la_base(): void
    {
        $usuarioAntes =
            $this->obtenerFilaUsuario(
                $this->idUsuario
            );

        $resultado =
            UsuarioEdicionAdminService::editar(
                $this->idUsuario,
                $this->idAdministrador,
                $this->datosEdicion(
                    $this->idUsuario,
                    [
                        'nombres' =>
                            'Usuario 123',

                        'edad' =>
                            17
                    ]
                )
            );

        self::assertFalse(
            $resultado['ok']
        );

        self::assertSame(
            UsuarioEdicionAdminService::
                DATOS_INVALIDOS,
            $resultado['estado']
        );

        self::assertArrayHasKey(
            'error',
            $resultado['alertas']
        );

        $usuarioDespues =
            $this->obtenerFilaUsuario(
                $this->idUsuario
            );

        self::assertSame(
            $usuarioAntes,
            $usuarioDespues
        );
    }

    public function test_rechaza_un_correo_asignado_a_otro_usuario(): void
    {
        $idOtroUsuario =
            $this->crearUsuario(
                'otro_'
                . bin2hex(random_bytes(5))
                . '@skillview.test',
                0
            );

        $correoOtroUsuario =
            Usuario::find(
                $idOtroUsuario
            )->correo;

        $resultado =
            UsuarioEdicionAdminService::editar(
                $this->idUsuario,
                $this->idAdministrador,
                $this->datosEdicion(
                    $this->idUsuario,
                    [
                        'correo' =>
                            $correoOtroUsuario
                    ]
                )
            );

        self::assertFalse(
            $resultado['ok']
        );

        self::assertSame(
            UsuarioEdicionAdminService::
                CORREO_DUPLICADO,
            $resultado['estado']
        );

        self::assertSame(
            $this->correoUsuario,
            Usuario::find(
                $this->idUsuario
            )->correo
        );

        self::assertSame(
            1,
            $this->contarUsuariosPorCorreo(
                $correoOtroUsuario
            )
        );
    }

    public function test_ignora_los_campos_no_permitidos(): void
    {
        $resultado =
            UsuarioEdicionAdminService::editar(
                $this->idUsuario,
                $this->idAdministrador,
                array_merge(
                    $this->datosEdicion(
                        $this->idUsuario
                    ),
                    [
                        'admin' => 1,
                        'debe_cambiar_password' => 1,
                        'autoriza_tratamiento_datos' => 0,
                        'token_recuperacion' =>
                            str_repeat('a', 64),

                        'token_expiracion' =>
                            time() + 3600
                    ]
                )
            );

        self::assertTrue(
            $resultado['ok']
        );

        $usuario =
            Usuario::find(
                $this->idUsuario
            );

        self::assertSame(
            0,
            (int) $usuario->admin
        );

        self::assertSame(
            0,
            (int) $usuario
                ->debe_cambiar_password
        );

        self::assertSame(
            1,
            (int) $usuario
                ->autoriza_tratamiento_datos
        );

        self::assertSame(
            '',
            $usuario->token_recuperacion
        );

        self::assertSame(
            0,
            (int) $usuario->token_expiracion
        );
    }

    public function test_conserva_los_registros_de_progreso_del_usuario(): void
    {
        $idHabilidad =
            $this->crearHabilidad();

        $this->crearProgreso(
            $this->idUsuario,
            $idHabilidad
        );

        $progresoAntes =
            $this->obtenerProgreso(
                $this->idUsuario,
                $idHabilidad
            );

        $resultado =
            UsuarioEdicionAdminService::editar(
                $this->idUsuario,
                $this->idAdministrador,
                $this->datosEdicion(
                    $this->idUsuario,
                    [
                        'nombres' =>
                            'Nombre Actualizado'
                    ]
                )
            );

        self::assertTrue(
            $resultado['ok']
        );

        $progresoDespues =
            $this->obtenerProgreso(
                $this->idUsuario,
                $idHabilidad
            );

        self::assertSame(
            $progresoAntes,
            $progresoDespues
        );

        self::assertSame(
            50.0,
            (float) $progresoDespues['progreso']
        );

        self::assertSame(
            2,
            (int) $progresoDespues['nivel']
        );
    }

    private function crearUsuario(
        string $correo,
        int $admin
    ): int {
        $password = password_hash(
            self::PASSWORD_ORIGINAL,
            PASSWORD_BCRYPT
        );

        $nombres =
            $admin === 1
                ? 'Administrador Prueba'
                : 'Usuario Prueba';

        $apellidos =
            'Integración Skillview';

        $edad = 24;
        $sexo = 3;

        $universidad =
            'Universidad del Cauca';

        $carrera =
            'Ingeniería de Sistemas';

        $debeCambiarPassword = 0;
        $habilitado = 1;
        $token = '';
        $tokenExpiracion = 0;
        $autorizaDatos = 1;

        $consulta = self::$db->prepare(
            'INSERT INTO usuarios (
                nombres,
                apellidos,
                edad,
                sexo,
                correo,
                password,
                universidad,
                carrera,
                admin,
                debe_cambiar_password,
                habilitado,
                token_recuperacion,
                token_expiracion,
                autoriza_tratamiento_datos
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $consulta->bind_param(
            'ssiissssiiisii',
            $nombres,
            $apellidos,
            $edad,
            $sexo,
            $correo,
            $password,
            $universidad,
            $carrera,
            $admin,
            $debeCambiarPassword,
            $habilitado,
            $token,
            $tokenExpiracion,
            $autorizaDatos
        );

        $consulta->execute();

        $idUsuario =
            (int) self::$db->insert_id;

        $consulta->close();

        return $idUsuario;
    }

    private function datosEdicion(
        int $idUsuario,
        array $cambios = []
    ): array {
        $usuario =
            Usuario::find(
                $idUsuario
            );

        return array_replace(
            [
                'nombres' =>
                    $usuario->nombres,

                'apellidos' =>
                    $usuario->apellidos,

                'edad' =>
                    $usuario->edad,

                'sexo' =>
                    $usuario->sexo,

                'correo' =>
                    $usuario->correo,

                'universidad' =>
                    $usuario->universidad,

                'carrera' =>
                    $usuario->carrera,

                'habilitado' =>
                    $usuario->habilitado,

                'password' =>
                    '',

                'password2' =>
                    ''
            ],
            $cambios
        );
    }

    private function obtenerFilaUsuario(
        int $idUsuario
    ): array {
        $consulta = self::$db->prepare(
            'SELECT *
             FROM usuarios
             WHERE id = ?
             LIMIT 1'
        );

        $consulta->bind_param(
            'i',
            $idUsuario
        );

        $consulta->execute();

        $fila = $consulta
            ->get_result()
            ->fetch_assoc();

        $consulta->close();

        return is_array($fila)
            ? $fila
            : [];
    }

    private function contarUsuariosPorCorreo(
        string $correo
    ): int {
        $consulta = self::$db->prepare(
            'SELECT COUNT(*) AS total
             FROM usuarios
             WHERE correo = ?'
        );

        $consulta->bind_param(
            's',
            $correo
        );

        $consulta->execute();

        $fila = $consulta
            ->get_result()
            ->fetch_assoc();

        $consulta->close();

        return (int) (
            $fila['total']
            ?? 0
        );
    }

    private function crearHabilidad(): int
    {
        $nombre =
            'Edición Usuario '
            . bin2hex(random_bytes(3));

        $descripcion =
            'Habilidad temporal para comprobar '
            . 'la conservación del progreso.';

        $tag =
            'Edición, Integración';

        $habilitado = 1;

        $consulta = self::$db->prepare(
            'INSERT INTO habilidades_blandas (
                nombre,
                descripcion,
                tag,
                habilitado
             ) VALUES (?, ?, ?, ?)'
        );

        $consulta->bind_param(
            'sssi',
            $nombre,
            $descripcion,
            $tag,
            $habilitado
        );

        $consulta->execute();

        $idHabilidad =
            (int) self::$db->insert_id;

        $consulta->close();

        return $idHabilidad;
    }

    private function crearProgreso(
        int $idUsuario,
        int $idHabilidad
    ): void {
        $fecha = date('Y-m-d');

        $consulta = self::$db->prepare(
            'INSERT INTO usuarios_habilidades (
                id_usuarios,
                id_habilidades,
                nivel,
                progreso,
                ultima_actualizacion
             ) VALUES (?, ?, 2, 50.00, ?)'
        );

        $consulta->bind_param(
            'iis',
            $idUsuario,
            $idHabilidad,
            $fecha
        );

        $consulta->execute();
        $consulta->close();
    }

    private function obtenerProgreso(
        int $idUsuario,
        int $idHabilidad
    ): array {
        $consulta = self::$db->prepare(
            'SELECT nivel,
                    progreso,
                    ultima_actualizacion
             FROM usuarios_habilidades
             WHERE id_usuarios = ?
               AND id_habilidades = ?
             LIMIT 1'
        );

        $consulta->bind_param(
            'ii',
            $idUsuario,
            $idHabilidad
        );

        $consulta->execute();

        $fila = $consulta
            ->get_result()
            ->fetch_assoc();

        $consulta->close();

        return is_array($fila)
            ? $fila
            : [];
    }
}