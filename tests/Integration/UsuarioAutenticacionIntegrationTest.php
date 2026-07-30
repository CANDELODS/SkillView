<?php

declare(strict_types=1);

namespace Tests\Integration;

use Classes\AutenticacionService;
use Model\Usuario;

require_once __DIR__
    . '/DatabaseIntegrationTestCase.php';

final class UsuarioAutenticacionIntegrationTest extends
    DatabaseIntegrationTestCase
{
    private const PASSWORD_VALIDA =
        'Clave1!';

    private string $correoPrueba;

    protected function setUp(): void
    {
        parent::setUp();

        $this->correoPrueba =
            'auth_'
            . bin2hex(random_bytes(6))
            . '@skillview.test';
    }

    public function test_autentica_un_usuario_general_habilitado(): void
    {
        $idUsuario = $this->crearUsuario([
            'admin' => 0,
            'habilitado' => 1,
            'debe_cambiar_password' => 0
        ]);

        $resultado =
            AutenticacionService::autenticar(
                $this->correoPrueba,
                self::PASSWORD_VALIDA
            );

        self::assertSame(
            AutenticacionService::AUTENTICADO,
            $resultado['estado']
        );

        self::assertSame(
            '/principal',
            $resultado['redireccion']
        );

        self::assertInstanceOf(
            Usuario::class,
            $resultado['usuario']
        );

        self::assertSame(
            $idUsuario,
            $resultado['sesion']['id']
        );

        self::assertSame(
            $this->correoPrueba,
            $resultado['sesion']['correo']
        );

        self::assertSame(
            0,
            $resultado['sesion']['admin']
        );

        self::assertArrayNotHasKey(
            'password',
            $resultado['sesion']
        );

        self::assertArrayNotHasKey(
            'cambio_password_usuario_id',
            $resultado['sesion']
        );
    }

    public function test_autentica_un_administrador_y_dirige_al_dashboard(): void
    {
        $idUsuario = $this->crearUsuario([
            'admin' => 1,
            'habilitado' => 1,
            'debe_cambiar_password' => 0
        ]);

        $resultado =
            AutenticacionService::autenticar(
                $this->correoPrueba,
                self::PASSWORD_VALIDA
            );

        self::assertSame(
            AutenticacionService::AUTENTICADO,
            $resultado['estado']
        );

        self::assertSame(
            '/admin/dashboard',
            $resultado['redireccion']
        );

        self::assertSame(
            $idUsuario,
            $resultado['sesion']['id']
        );

        self::assertSame(
            1,
            $resultado['sesion']['admin']
        );
    }

    public function test_normaliza_el_correo_antes_de_buscar_el_usuario(): void
    {
        $this->crearUsuario();

        $correoConCambios =
            '  '
            . mb_strtoupper(
                $this->correoPrueba,
                'UTF-8'
            )
            . '  ';

        $resultado =
            AutenticacionService::autenticar(
                $correoConCambios,
                self::PASSWORD_VALIDA
            );

        self::assertSame(
            AutenticacionService::AUTENTICADO,
            $resultado['estado']
        );

        self::assertSame(
            $this->correoPrueba,
            $resultado['sesion']['correo']
        );
    }

    public function test_rechaza_una_password_incorrecta(): void
    {
        $this->crearUsuario();

        $resultado =
            AutenticacionService::autenticar(
                $this->correoPrueba,
                'PasswordIncorrecta1!'
            );

        self::assertSame(
            AutenticacionService::PASSWORD_INCORRECTA,
            $resultado['estado']
        );

        self::assertNull(
            $resultado['redireccion']
        );

        self::assertSame(
            [],
            $resultado['sesion']
        );

        self::assertNull(
            $resultado['usuario']
        );
    }

    public function test_rechaza_un_correo_no_registrado(): void
    {
        $resultado =
            AutenticacionService::autenticar(
                'no_existe_'
                    . bin2hex(random_bytes(5))
                    . '@skillview.test',

                self::PASSWORD_VALIDA
            );

        self::assertSame(
            AutenticacionService::USUARIO_NO_EXISTE,
            $resultado['estado']
        );

        self::assertNull(
            $resultado['redireccion']
        );

        self::assertSame(
            [],
            $resultado['sesion']
        );
    }

    public function test_bloquea_una_cuenta_deshabilitada(): void
    {
        $this->crearUsuario([
            'habilitado' => 0
        ]);

        $resultado =
            AutenticacionService::autenticar(
                $this->correoPrueba,
                self::PASSWORD_VALIDA
            );

        self::assertSame(
            AutenticacionService::CUENTA_DESHABILITADA,
            $resultado['estado']
        );

        self::assertNull(
            $resultado['redireccion']
        );

        self::assertSame(
            [],
            $resultado['sesion']
        );

        self::assertNull(
            $resultado['usuario']
        );

        /*
         * La cuenta continúa almacenada aunque
         * no tenga acceso.
         */
        self::assertSame(
            1,
            $this->contarUsuariosPorCorreo(
                $this->correoPrueba
            )
        );
    }

    public function test_el_bloqueo_tiene_prioridad_sobre_el_cambio_de_password(): void
    {
        $this->crearUsuario([
            'habilitado' => 0,
            'debe_cambiar_password' => 1
        ]);

        $resultado =
            AutenticacionService::autenticar(
                $this->correoPrueba,
                self::PASSWORD_VALIDA
            );

        self::assertSame(
            AutenticacionService::CUENTA_DESHABILITADA,
            $resultado['estado']
        );

        self::assertNotSame(
            AutenticacionService::CAMBIO_PASSWORD_REQUERIDO,
            $resultado['estado']
        );

        self::assertSame(
            [],
            $resultado['sesion']
        );
    }

    public function test_detecta_el_cambio_obligatorio_y_crea_sesion_temporal(): void
    {
        $idUsuario = $this->crearUsuario([
            'habilitado' => 1,
            'debe_cambiar_password' => 1
        ]);

        $resultado =
            AutenticacionService::autenticar(
                $this->correoPrueba,
                self::PASSWORD_VALIDA
            );

        self::assertSame(
            AutenticacionService::CAMBIO_PASSWORD_REQUERIDO,
            $resultado['estado']
        );

        self::assertSame(
            '/cambiar-password',
            $resultado['redireccion']
        );

        self::assertSame(
            [
                'cambio_password_usuario_id' =>
                    $idUsuario
            ],
            $resultado['sesion']
        );

        self::assertArrayNotHasKey(
            'id',
            $resultado['sesion']
        );

        self::assertArrayNotHasKey(
            'correo',
            $resultado['sesion']
        );
    }

    public function test_una_cuenta_rehabilitada_recupera_el_acceso(): void
    {
        $idUsuario = $this->crearUsuario([
            'habilitado' => 0
        ]);

        $resultadoBloqueado =
            AutenticacionService::autenticar(
                $this->correoPrueba,
                self::PASSWORD_VALIDA
            );

        self::assertSame(
            AutenticacionService::CUENTA_DESHABILITADA,
            $resultadoBloqueado['estado']
        );

        $this->actualizarEstadoCuenta(
            $idUsuario,
            1
        );

        $resultadoHabilitado =
            AutenticacionService::autenticar(
                $this->correoPrueba,
                self::PASSWORD_VALIDA
            );

        self::assertSame(
            AutenticacionService::AUTENTICADO,
            $resultadoHabilitado['estado']
        );

        self::assertSame(
            '/principal',
            $resultadoHabilitado['redireccion']
        );

        self::assertSame(
            $idUsuario,
            $resultadoHabilitado['sesion']['id']
        );
    }

    /**
     * Inserta un usuario de prueba con un hash bcrypt.
     */
    private function crearUsuario(
        array $cambios = []
    ): int {
        $datos = array_replace(
            [
                'nombres' =>
                    'Usuario Autenticación',

                'apellidos' =>
                    'Prueba Integración',

                'edad' =>
                    24,

                'sexo' =>
                    3,

                'correo' =>
                    $this->correoPrueba,

                'password' =>
                    password_hash(
                        self::PASSWORD_VALIDA,
                        PASSWORD_BCRYPT
                    ),

                'universidad' =>
                    'Universidad del Cauca',

                'carrera' =>
                    'Ingeniería de Sistemas',

                'admin' =>
                    0,

                'debe_cambiar_password' =>
                    0,

                'habilitado' =>
                    1,

                'token_recuperacion' =>
                    '',

                'token_expiracion' =>
                    0,

                'autoriza_tratamiento_datos' =>
                    1
            ],
            $cambios
        );

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
            $datos['nombres'],
            $datos['apellidos'],
            $datos['edad'],
            $datos['sexo'],
            $datos['correo'],
            $datos['password'],
            $datos['universidad'],
            $datos['carrera'],
            $datos['admin'],
            $datos['debe_cambiar_password'],
            $datos['habilitado'],
            $datos['token_recuperacion'],
            $datos['token_expiracion'],
            $datos['autoriza_tratamiento_datos']
        );

        $consulta->execute();

        $idUsuario =
            (int) self::$db->insert_id;

        $consulta->close();

        return $idUsuario;
    }

    private function actualizarEstadoCuenta(
        int $idUsuario,
        int $habilitado
    ): void {
        $consulta = self::$db->prepare(
            'UPDATE usuarios
             SET habilitado = ?
             WHERE id = ?
             LIMIT 1'
        );

        $consulta->bind_param(
            'ii',
            $habilitado,
            $idUsuario
        );

        $consulta->execute();
        $consulta->close();
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
}