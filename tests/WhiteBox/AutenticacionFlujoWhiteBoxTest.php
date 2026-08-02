<?php

declare(strict_types=1);

namespace Tests\WhiteBox;

use Classes\AutenticacionService;
use mysqli;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Recorre las decisiones internas principales
 * de AutenticacionService::autenticar().
 *
 * La base de datos solo se utiliza para colocar
 * el servicio en cada estado requerido.
 */
#[CoversClass(AutenticacionService::class)]
final class AutenticacionFlujoWhiteBoxTest extends TestCase
{
    private static mysqli $db;

    /**
     * Contraseña válida utilizada por todas
     * las cuentas controladas.
     */
    private const PASSWORD = 'ClaveSegura1!';

    private const EMAIL_GENERAL =
    'whitebox.auth.general@skillview.test';

    private const EMAIL_ADMIN =
    'whitebox.auth.admin@skillview.test';

    private const EMAIL_CAMBIO =
    'whitebox.auth.cambio@skillview.test';

    private const EMAIL_DESHABILITADO =
    'whitebox.auth.deshabilitado@skillview.test';

    /**
     * Identificadores creados durante
     * la preparación del escenario.
     *
     * @var array<string, int>
     */
    private static array $usuariosIds = [];

    /*
    |--------------------------------------------------------------------------
    | Preparación y limpieza
    |--------------------------------------------------------------------------
    */

    public static function setUpBeforeClass(): void
    {
        /*
     * La conexión ya fue creada por
     * tests/bootstrap.php y configurada
     * dentro de ActiveRecord.
     */
        $conexion =
            $GLOBALS['db']
            ?? null;

        if (!$conexion instanceof mysqli) {
            throw new \RuntimeException(
                'tests/bootstrap.php no proporcionó '
                    . 'una conexión mysqli para las pruebas.'
            );
        }

        self::$db = $conexion;

        self::verificarBaseDeDatosPruebas();
        self::limpiarUsuariosControlados();

        /*
     * Usuario general habilitado.
     */
        self::$usuariosIds['general'] =
            self::crearUsuario(
                correo: self::EMAIL_GENERAL,
                admin: 0,
                habilitado: 1,
                debeCambiarPassword: 0
            );

        /*
     * Administrador habilitado.
     */
        self::$usuariosIds['admin'] =
            self::crearUsuario(
                correo: self::EMAIL_ADMIN,
                admin: 1,
                habilitado: 1,
                debeCambiarPassword: 0
            );

        /*
     * Usuario habilitado que debe cambiar
     * la contraseña temporal.
     */
        self::$usuariosIds['cambio'] =
            self::crearUsuario(
                correo: self::EMAIL_CAMBIO,
                admin: 0,
                habilitado: 1,
                debeCambiarPassword: 1
            );

        /*
     * Cuenta deshabilitada que también tiene
     * activo el cambio obligatorio.
     */
        self::$usuariosIds['deshabilitado'] =
            self::crearUsuario(
                correo: self::EMAIL_DESHABILITADO,
                admin: 0,
                habilitado: 0,
                debeCambiarPassword: 1
            );
    }

    public static function tearDownAfterClass(): void
    {
        self::limpiarUsuariosControlados();
    }

    /**
     * Evita ejecutar el escenario sobre
     * una base diferente de skillview_test.
     */
    private static function verificarBaseDeDatosPruebas(): void
    {
        $resultado = self::$db->query(
            'SELECT DATABASE() AS nombre_base'
        );

        $fila = $resultado->fetch_assoc();
        $resultado->free();

        $nombreBase =
            (string) (
                $fila['nombre_base']
                ?? ''
            );

        if ($nombreBase !== 'skillview_test') {
            throw new \RuntimeException(
                'La prueba de caja blanca solo puede '
                    . 'ejecutarse sobre skillview_test. '
                    . 'Base detectada: '
                    . $nombreBase
            );
        }
    }

    /**
     * Inserta una cuenta con el estado requerido.
     */
    private static function crearUsuario(
        string $correo,
        int $admin,
        int $habilitado,
        int $debeCambiarPassword
    ): int {
        $nombres = 'Prueba';
        $apellidos = 'Caja Blanca';
        $edad = 24;
        $sexo = 0;

        $passwordHash = password_hash(
            self::PASSWORD,
            PASSWORD_BCRYPT
        );

        $universidad =
            'Universidad SkillView';

        $carrera =
            'Ingeniería de Sistemas';

        $tokenRecuperacion = '';
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
             ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?
             )'
        );

        if (!$consulta) {
            throw new \RuntimeException(
                'No fue posible preparar la inserción '
                    . 'del usuario controlado.'
            );
        }

        $consulta->bind_param(
            'ssiissssiiisii',
            $nombres,
            $apellidos,
            $edad,
            $sexo,
            $correo,
            $passwordHash,
            $universidad,
            $carrera,
            $admin,
            $debeCambiarPassword,
            $habilitado,
            $tokenRecuperacion,
            $tokenExpiracion,
            $autorizaDatos
        );

        $consulta->execute();

        $idUsuario =
            (int) self::$db->insert_id;

        $consulta->close();

        if ($idUsuario <= 0) {
            throw new \RuntimeException(
                'No fue posible crear el usuario '
                    . 'controlado para la prueba.'
            );
        }

        return $idUsuario;
    }

    /**
     * Elimina exclusivamente las cuentas creadas
     * por este escenario.
     */
    private static function limpiarUsuariosControlados(): void
    {
        $patron =
            'whitebox.auth.%@skillview.test';

        $consulta = self::$db->prepare(
            'DELETE FROM usuarios
             WHERE correo LIKE ?'
        );

        if (!$consulta) {
            throw new \RuntimeException(
                'No fue posible preparar la limpieza '
                    . 'de los usuarios controlados.'
            );
        }

        $consulta->bind_param(
            's',
            $patron
        );

        $consulta->execute();
        $consulta->close();
    }

    /*
    |--------------------------------------------------------------------------
    | Aserciones auxiliares
    |--------------------------------------------------------------------------
    */

    /**
     * Verifica la estructura común devuelta
     * por AutenticacionService.
     */
    private function comprobarEstructuraResultado(
        array $resultado
    ): void {
        self::assertArrayHasKey(
            'estado',
            $resultado
        );

        self::assertArrayHasKey(
            'mensaje',
            $resultado
        );

        self::assertArrayHasKey(
            'redireccion',
            $resultado
        );

        self::assertArrayHasKey(
            'sesion',
            $resultado
        );

        self::assertArrayHasKey(
            'usuario',
            $resultado
        );
    }

    /**
     * Comprueba que una rama de rechazo no
     * produzca sesión ni ruta de acceso.
     */
    private function comprobarRechazo(
        array $resultado,
        string $estadoEsperado
    ): void {
        $this->comprobarEstructuraResultado(
            $resultado
        );

        self::assertSame(
            $estadoEsperado,
            $resultado['estado']
        );

        self::assertNull(
            $resultado['redireccion']
        );

        self::assertSame(
            [],
            $resultado['sesion']
        );

        self::assertIsString(
            $resultado['mensaje']
        );

        self::assertNotSame(
            '',
            trim($resultado['mensaje'])
        );
    }

    /**
     * Verifica los datos esenciales de una
     * sesión autenticada normal.
     */
    private function comprobarSesionNormal(
        array $sesion,
        int $usuarioId,
        string $correo,
        int $admin
    ): void {
        self::assertArrayHasKey(
            'id',
            $sesion
        );

        self::assertArrayHasKey(
            'nombres',
            $sesion
        );

        self::assertArrayHasKey(
            'apellidos',
            $sesion
        );

        self::assertArrayHasKey(
            'edad',
            $sesion
        );

        self::assertArrayHasKey(
            'sexo',
            $sesion
        );

        self::assertArrayHasKey(
            'correo',
            $sesion
        );

        self::assertArrayHasKey(
            'universidad',
            $sesion
        );

        self::assertArrayHasKey(
            'carrera',
            $sesion
        );

        self::assertArrayHasKey(
            'admin',
            $sesion
        );

        self::assertSame(
            $usuarioId,
            (int) $sesion['id']
        );

        self::assertSame(
            $correo,
            $sesion['correo']
        );

        self::assertSame(
            $admin,
            (int) $sesion['admin']
        );

        self::assertArrayNotHasKey(
            'cambio_password_usuario_id',
            $sesion
        );
    }

    /*
    |--------------------------------------------------------------------------
    | R1. Usuario inexistente
    |--------------------------------------------------------------------------
    */

    #[TestDox(
        'Recorre la ruta interna de usuario inexistente'
    )]
    public function test_usuario_inexistente(): void
    {
        $resultado =
            AutenticacionService::autenticar(
                'whitebox.auth.inexistente@skillview.test',
                self::PASSWORD
            );

        $this->comprobarRechazo(
            $resultado,
            'USUARIO_NO_EXISTE'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | R2. Contraseña incorrecta
    |--------------------------------------------------------------------------
    */

    #[TestDox(
        'Recorre la ruta interna de contraseña incorrecta'
    )]
    public function test_password_incorrecta(): void
    {
        $resultado =
            AutenticacionService::autenticar(
                self::EMAIL_GENERAL,
                'PasswordEquivocada1!'
            );

        $this->comprobarRechazo(
            $resultado,
            'PASSWORD_INCORRECTA'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | R3. Cuenta deshabilitada
    |--------------------------------------------------------------------------
    */

    #[TestDox(
        'Prioriza el bloqueo sobre el cambio obligatorio'
    )]
    public function test_cuenta_deshabilitada_tiene_prioridad(): void
    {
        $resultado =
            AutenticacionService::autenticar(
                self::EMAIL_DESHABILITADO,
                self::PASSWORD
            );

        /*
         * La cuenta también tiene activo
         * debe_cambiar_password, pero debe
         * ingresar por la rama de bloqueo.
         */
        $this->comprobarRechazo(
            $resultado,
            'CUENTA_DESHABILITADA'
        );

        self::assertNotSame(
            'CAMBIO_PASSWORD_REQUERIDO',
            $resultado['estado']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | R4. Cambio obligatorio
    |--------------------------------------------------------------------------
    */

    #[TestDox(
        'Crea únicamente la sesión temporal para cambiar la contraseña'
    )]
    public function test_cambio_password_requerido(): void
    {
        $resultado =
            AutenticacionService::autenticar(
                self::EMAIL_CAMBIO,
                self::PASSWORD
            );

        $this->comprobarEstructuraResultado(
            $resultado
        );

        self::assertSame(
            'CAMBIO_PASSWORD_REQUERIDO',
            $resultado['estado']
        );

        self::assertSame(
            '/cambiar-password',
            $resultado['redireccion']
        );

        self::assertSame(
            [
                'cambio_password_usuario_id' =>
                self::$usuariosIds['cambio']
            ],
            $resultado['sesion']
        );

        /*
         * Una sesión temporal no debe contener
         * las credenciales de una sesión normal.
         */
        self::assertArrayNotHasKey(
            'id',
            $resultado['sesion']
        );

        self::assertArrayNotHasKey(
            'admin',
            $resultado['sesion']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | R5. Usuario general
    |--------------------------------------------------------------------------
    */

    #[TestDox(
        'Autentica al usuario general y selecciona la ruta principal'
    )]
    public function test_autentica_usuario_general(): void
    {
        /*
         * Se agregan espacios y mayúsculas para
         * recorrer también la normalización del correo.
         */
        $resultado =
            AutenticacionService::autenticar(
                '  WHITEBOX.AUTH.GENERAL@SKILLVIEW.TEST  ',
                self::PASSWORD
            );

        $this->comprobarEstructuraResultado(
            $resultado
        );

        self::assertSame(
            'AUTENTICADO',
            $resultado['estado']
        );

        self::assertSame(
            '/principal',
            $resultado['redireccion']
        );

        $this->comprobarSesionNormal(
            $resultado['sesion'],
            self::$usuariosIds['general'],
            self::EMAIL_GENERAL,
            0
        );
    }

    /*
    |--------------------------------------------------------------------------
    | R6. Administrador
    |--------------------------------------------------------------------------
    */

    #[TestDox(
        'Autentica al administrador y selecciona el dashboard'
    )]
    public function test_autentica_administrador(): void
    {
        $resultado =
            AutenticacionService::autenticar(
                self::EMAIL_ADMIN,
                self::PASSWORD
            );

        $this->comprobarEstructuraResultado(
            $resultado
        );

        self::assertSame(
            'AUTENTICADO',
            $resultado['estado']
        );

        self::assertSame(
            '/admin/dashboard',
            $resultado['redireccion']
        );

        $this->comprobarSesionNormal(
            $resultado['sesion'],
            self::$usuariosIds['admin'],
            self::EMAIL_ADMIN,
            1
        );
    }
}
