<?php

declare(strict_types=1);

namespace Tests\Integration;

use Model\Usuario;
use Model\usuarios_habilidades;
use RuntimeException;

require_once __DIR__
    . '/DatabaseIntegrationTestCase.php';

final class UsuarioRegistroIntegrationTest extends
DatabaseIntegrationTestCase
{
    private const PASSWORD_PRINCIPAL =
    'Clave1!';

    private string $correoPrueba;

    /**
     * Genera un correo diferente para cada caso
     * y después inicia la transacción definida
     * en la clase base.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->correoPrueba =
            'integracion_'
            . bin2hex(random_bytes(6))
            . '@skillview.test';
    }

    /**
     * Comprueba expresamente que la conexión activa
     * corresponde a la base de datos de pruebas.
     */
    public function test_utiliza_exclusivamente_skillview_test(): void
    {
        self::assertSame(
            self::TEST_DATABASE,
            self::nombreBaseDatosActual()
        );
    }

    /**
     * Comprueba el flujo principal:
     *
     * validación → hash → INSERT → recuperación.
     */
    public function test_registra_y_recupera_un_usuario_con_password_hasheada(): void
    {
        self::assertSame(
            'utf8mb4',
            self::$db->character_set_name(),
            'La conexión con MySQL no está utilizando utf8mb4.'
        );

        self::assertTrue(
            mb_check_encoding(
                'Prueba Integración',
                'UTF-8'
            ),
            'UsuarioRegistroIntegrationTest.php no está guardado en UTF-8.'
        );
        
        $passwordPlano =
            self::PASSWORD_PRINCIPAL;

        $usuario = $this->crearUsuarioValido();

        // Validar todos los datos del registro.
        $alertas = $usuario->validar_cuenta();

        self::assertSame(
            [],
            $alertas
        );

        // Antes de registrar, el correo no debe existir.
        self::assertNull(
            Usuario::where(
                'correo',
                $usuario->correo
            )
        );

        // Generar el hash.
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

        $informacionHash = password_get_info(
            $usuario->password
        );

        self::assertSame(
            'bcrypt',
            $informacionHash['algoName']
        );

        // Guardar mediante ActiveRecord.
        $resultado = $usuario->guardar();

        self::assertIsArray($resultado);

        self::assertTrue(
            (bool)(
                $resultado['resultado']
                ?? false
            )
        );

        $idUsuario = (int)(
            $resultado['id']
            ?? 0
        );

        self::assertGreaterThan(
            0,
            $idUsuario
        );

        // Inicializar el progreso por habilidad.
        self::assertTrue(
            usuarios_habilidades::inicializarHabilidadesUsuario(
                    $idUsuario
                )
        );

        // Recuperar el usuario desde MySQL.
        $usuarioPersistido =
            Usuario::find($idUsuario);

        self::assertInstanceOf(
            Usuario::class,
            $usuarioPersistido
        );

        self::assertSame(
            $this->correoPrueba,
            $usuarioPersistido->correo
        );

        self::assertSame(
            'Prueba Integración',
            $usuarioPersistido->nombres
        );

        self::assertSame(
            0,
            (int) $usuarioPersistido->admin
        );

        self::assertSame(
            1,
            (int) $usuarioPersistido->habilitado
        );

        self::assertSame(
            1,
            (int) $usuarioPersistido
                ->autoriza_tratamiento_datos
        );

        self::assertTrue(
            password_verify(
                $passwordPlano,
                $usuarioPersistido->password
            )
        );
    }

    /**
     * Comprueba que cada habilidad habilitada tenga
     * exactamente una fila inicial para el usuario.
     */
    public function test_inicializa_el_progreso_de_todas_las_habilidades_habilitadas(): void
    {
        $fechaPrueba = date('Y-m-d');

        $registro =
            $this->registrarUsuarioCompleto(
                $this->crearUsuarioValido(),
                $fechaPrueba
            );

        $idUsuario =
            $registro['id'];

        $idsHabilidades =
            $this->obtenerIdsHabilidadesHabilitadas();

        self::assertNotEmpty(
            $idsHabilidades,
            'skillview_test debe contener al menos '
                . 'una habilidad habilitada.'
        );

        $idsProgreso =
            $this->obtenerIdsHabilidadesUsuario(
                $idUsuario
            );

        /*
         * Los identificadores deben coincidir:
         * ni deben faltar habilidades habilitadas,
         * ni deben agregarse habilidades deshabilitadas.
         */
        self::assertSame(
            $idsHabilidades,
            $idsProgreso
        );

        $resumen =
            $this->obtenerResumenProgresoInicial(
                $idUsuario,
                $fechaPrueba
            );

        $totalHabilidades =
            count($idsHabilidades);

        self::assertSame(
            $totalHabilidades,
            $resumen['total']
        );

        self::assertSame(
            $totalHabilidades,
            $resumen['niveles_basicos']
        );

        self::assertSame(
            $totalHabilidades,
            $resumen['progresos_cero']
        );

        self::assertSame(
            $totalHabilidades,
            $resumen['fechas_correctas']
        );
    }

    /**
     * Comprueba que ejecutar la inicialización varias
     * veces no produzca filas duplicadas.
     */
    public function test_la_inicializacion_de_habilidades_es_idempotente(): void
    {
        $registro =
            $this->registrarUsuarioCompleto(
                $this->crearUsuarioValido()
            );

        $idUsuario =
            $registro['id'];

        $cantidadInicial =
            $this->contarProgresosUsuario(
                $idUsuario
            );

        self::assertTrue(
            usuarios_habilidades::inicializarHabilidadesUsuario(
                    $idUsuario
                )
        );

        self::assertTrue(
            usuarios_habilidades::inicializarHabilidadesUsuario(
                    $idUsuario
                )
        );

        $cantidadFinal =
            $this->contarProgresosUsuario(
                $idUsuario
            );

        self::assertSame(
            $cantidadInicial,
            $cantidadFinal
        );

        self::assertSame(
            0,
            $this->contarHabilidadesDuplicadas(
                $idUsuario
            )
        );
    }

    /**
     * Comprueba que una habilidad deshabilitada
     * no sea asignada al usuario nuevo.
     */
    public function test_no_inicializa_habilidades_deshabilitadas(): void
    {
        $idHabilidadDeshabilitada =
            $this->crearHabilidadDeshabilitada();

        self::assertGreaterThan(
            0,
            $idHabilidadDeshabilitada
        );

        $registro =
            $this->registrarUsuarioCompleto(
                $this->crearUsuarioValido()
            );

        $idUsuario =
            $registro['id'];

        $consulta = self::$db->prepare(
            'SELECT COUNT(*) AS total
             FROM usuarios_habilidades
             WHERE id_usuarios = ?
               AND id_habilidades = ?'
        );

        $consulta->bind_param(
            'ii',
            $idUsuario,
            $idHabilidadDeshabilitada
        );

        $consulta->execute();

        $fila = $consulta
            ->get_result()
            ->fetch_assoc();

        $consulta->close();

        self::assertSame(
            0,
            (int)($fila['total'] ?? 0)
        );

        self::assertSame(
            count(
                $this->obtenerIdsHabilidadesHabilitadas()
            ),
            $this->contarProgresosUsuario(
                $idUsuario
            )
        );
    }

    /**
     * Comprueba el control aplicado por el flujo de
     * registro cuando el correo ya se encuentra guardado.
     */
    public function test_detecta_un_correo_duplicado_y_evita_la_segunda_insercion(): void
    {
        $primerRegistro =
            $this->registrarUsuarioCompleto(
                $this->crearUsuarioValido()
            );

        self::assertTrue(
            $primerRegistro['insertado']
        );

        $segundoUsuario =
            $this->crearUsuarioValido([
                'nombres' => 'Segundo Usuario',
                'password' => 'OtraClave1!',
                'password2' => 'OtraClave1!'
            ]);

        $segundoRegistro =
            $this->registrarUsuarioCompleto(
                $segundoUsuario
            );

        self::assertFalse(
            $segundoRegistro['insertado']
        );

        /*
         * El flujo devuelve el ID de la cuenta
         * encontrada, no uno nuevo.
         */
        self::assertSame(
            $primerRegistro['id'],
            $segundoRegistro['id']
        );

        self::assertSame(
            1,
            $this->contarUsuariosPorCorreo(
                $this->correoPrueba
            )
        );

        self::assertSame(
            count(
                $this->obtenerIdsHabilidadesHabilitadas()
            ),
            $this->contarProgresosUsuario(
                $primerRegistro['id']
            )
        );

        $usuarioPersistido =
            Usuario::find(
                $primerRegistro['id']
            );

        self::assertInstanceOf(
            Usuario::class,
            $usuarioPersistido
        );

        /*
         * La contraseña almacenada debe seguir siendo
         * la del primer registro.
         */
        self::assertTrue(
            password_verify(
                self::PASSWORD_PRINCIPAL,
                $usuarioPersistido->password
            )
        );

        self::assertFalse(
            password_verify(
                'OtraClave1!',
                $usuarioPersistido->password
            )
        );
    }

    /**
     * Crea un objeto Usuario completamente válido.
     */
    private function crearUsuarioValido(
        array $cambios = []
    ): Usuario {
        $datos = [
            'nombres' =>
            'Prueba Integración',

            'apellidos' =>
            'Usuario Skillview',

            'edad' =>
            24,

            'sexo' =>
            3,

            'correo' =>
            $this->correoPrueba,

            'password' =>
            self::PASSWORD_PRINCIPAL,

            'password2' =>
            self::PASSWORD_PRINCIPAL,

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
        ];

        return new Usuario(
            array_replace(
                $datos,
                $cambios
            )
        );
    }

    /**
     * Ejecuta el mismo orden general utilizado
     * por el proceso real de registro.
     *
     * @return array{
     *     insertado: bool,
     *     id: int
     * }
     */
    private function registrarUsuarioCompleto(
        Usuario $usuario,
        ?string $fecha = null
    ): array {
        $alertas =
            $usuario->validar_cuenta();

        if ($alertas !== []) {
            throw new RuntimeException(
                'Los datos preparados para la prueba '
                    . 'no superaron la validación: '
                    . json_encode(
                        $alertas,
                        JSON_UNESCAPED_UNICODE
                    )
            );
        }

        /*
         * El controlador consulta el correo antes
         * de ejecutar guardar().
         */
        $usuarioExistente =
            Usuario::where(
                'correo',
                $usuario->correo
            );

        if ($usuarioExistente instanceof Usuario) {
            return [
                'insertado' => false,
                'id' => (int) $usuarioExistente->id
            ];
        }

        $usuario->hashPassword();

        $resultado =
            $usuario->guardar();

        if (
            !is_array($resultado)
            || !($resultado['resultado'] ?? false)
        ) {
            throw new RuntimeException(
                'No fue posible insertar el usuario '
                    . 'durante la prueba de integración.'
            );
        }

        $idUsuario = (int)(
            $resultado['id']
            ?? 0
        );

        if ($idUsuario <= 0) {
            throw new RuntimeException(
                'MySQL no devolvió un identificador '
                    . 'válido para el usuario registrado.'
            );
        }

        $inicializado =
            usuarios_habilidades::inicializarHabilidadesUsuario(
                $idUsuario,
                $fecha
            );

        if (!$inicializado) {
            throw new RuntimeException(
                'No fue posible inicializar el '
                    . 'progreso del usuario.'
            );
        }

        return [
            'insertado' => true,
            'id' => $idUsuario
        ];
    }

    /**
     * Obtiene los identificadores de todas
     * las habilidades habilitadas.
     *
     * @return array<int, int>
     */
    private function obtenerIdsHabilidadesHabilitadas(): array
    {
        $resultado = self::$db->query(
            'SELECT id
             FROM habilidades_blandas
             WHERE habilitado = 1
             ORDER BY id ASC'
        );

        $ids = [];

        while (
            $fila = $resultado->fetch_assoc()
        ) {
            $ids[] = (int) $fila['id'];
        }

        $resultado->free();

        return $ids;
    }

    /**
     * Obtiene los identificadores de las habilidades
     * inicializadas para un usuario.
     *
     * @return array<int, int>
     */
    private function obtenerIdsHabilidadesUsuario(
        int $idUsuario
    ): array {
        $consulta = self::$db->prepare(
            'SELECT id_habilidades
             FROM usuarios_habilidades
             WHERE id_usuarios = ?
             ORDER BY id_habilidades ASC'
        );

        $consulta->bind_param(
            'i',
            $idUsuario
        );

        $consulta->execute();

        $resultado =
            $consulta->get_result();

        $ids = [];

        while (
            $fila = $resultado->fetch_assoc()
        ) {
            $ids[] =
                (int) $fila['id_habilidades'];
        }

        $consulta->close();

        return $ids;
    }

    /**
     * Resume los valores iniciales guardados
     * en usuarios_habilidades.
     *
     * @return array{
     *     total: int,
     *     niveles_basicos: int,
     *     progresos_cero: int,
     *     fechas_correctas: int
     * }
     */
    private function obtenerResumenProgresoInicial(
        int $idUsuario,
        string $fecha
    ): array {
        $consulta = self::$db->prepare(
            'SELECT
                COUNT(*) AS total,
                SUM(nivel = 1) AS niveles_basicos,
                SUM(progreso = 0.00) AS progresos_cero,
                SUM(
                    ultima_actualizacion = ?
                ) AS fechas_correctas
             FROM usuarios_habilidades
             WHERE id_usuarios = ?'
        );

        $consulta->bind_param(
            'si',
            $fecha,
            $idUsuario
        );

        $consulta->execute();

        $fila = $consulta
            ->get_result()
            ->fetch_assoc();

        $consulta->close();

        return [
            'total' =>
            (int)($fila['total'] ?? 0),

            'niveles_basicos' =>
            (int)(
                $fila['niveles_basicos']
                ?? 0
            ),

            'progresos_cero' =>
            (int)(
                $fila['progresos_cero']
                ?? 0
            ),

            'fechas_correctas' =>
            (int)(
                $fila['fechas_correctas']
                ?? 0
            )
        ];
    }

    /**
     * Cuenta las filas de progreso del usuario.
     */
    private function contarProgresosUsuario(
        int $idUsuario
    ): int {
        $consulta = self::$db->prepare(
            'SELECT COUNT(*) AS total
             FROM usuarios_habilidades
             WHERE id_usuarios = ?'
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

        return (int)(
            $fila['total']
            ?? 0
        );
    }

    /**
     * Cuenta las habilidades repetidas para el usuario.
     */
    private function contarHabilidadesDuplicadas(
        int $idUsuario
    ): int {
        $consulta = self::$db->prepare(
            'SELECT COUNT(*) AS total
             FROM (
                 SELECT id_habilidades
                 FROM usuarios_habilidades
                 WHERE id_usuarios = ?
                 GROUP BY id_habilidades
                 HAVING COUNT(*) > 1
             ) AS duplicadas'
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

        return (int)(
            $fila['total']
            ?? 0
        );
    }

    /**
     * Cuenta usuarios con un correo determinado.
     */
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

        return (int)(
            $fila['total']
            ?? 0
        );
    }

    /**
     * Crea una habilidad temporal deshabilitada.
     * El rollback la eliminará automáticamente.
     */
    private function crearHabilidadDeshabilitada(): int
    {
        $nombre =
            'Integración '
            . bin2hex(random_bytes(4));

        $descripcion =
            'Habilidad temporal utilizada '
            . 'en una prueba de integración';

        $tags =
            'Integración, Prueba';

        $consulta = self::$db->prepare(
            'INSERT INTO habilidades_blandas
                (
                    nombre,
                    descripcion,
                    tag,
                    habilitado
                )
             VALUES (?, ?, ?, 0)'
        );

        $consulta->bind_param(
            'sss',
            $nombre,
            $descripcion,
            $tags
        );

        $consulta->execute();

        $idHabilidad =
            (int) self::$db->insert_id;

        $consulta->close();

        return $idHabilidad;
    }
}
