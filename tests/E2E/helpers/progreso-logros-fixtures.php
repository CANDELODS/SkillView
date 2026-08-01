<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require_once dirname(__DIR__, 3)
    . '/vendor/autoload.php';

mysqli_report(
    MYSQLI_REPORT_ERROR
        | MYSQLI_REPORT_STRICT
);

$rutaProyecto = dirname(__DIR__, 3);

Dotenv::createImmutable(
    $rutaProyecto . '/includes'
)->safeLoad();

require $rutaProyecto
    . '/includes/database.php';

if (
    !isset($db)
    || !$db instanceof mysqli
) {
    fwrite(
        STDERR,
        "No fue posible conectar con MySQL.\n"
    );

    exit(1);
}

/**
 * Recupera los primeros logros habilitados que
 * realmente son presentados en el perfil.
 */
function obtenerLogrosDestacadosPerfilE2E(
    mysqli $db,
    int $limite = 6
): array {
    $limite = max(
        1,
        (int) $limite
    );

    $resultado = $db->query(
        'SELECT id, nombre
         FROM logros
         WHERE habilitado = 1
         ORDER BY id ASC
         LIMIT ' . $limite
    );

    $logros = [];

    while (
        $fila =
        $resultado->fetch_assoc()
    ) {
        $logros[] = [
            'id' =>
            (int) $fila['id'],

            'name' =>
            (string) $fila['nombre']
        ];
    }

    $resultado->free();

    /*
     * Se requieren al menos tres:
     * dos desbloqueados y uno bloqueado.
     */
    if (count($logros) < 3) {
        throw new RuntimeException(
            'Se requieren al menos tres logros '
                . 'habilitados para probar el perfil.'
        );
    }

    return $logros;
}

/*
|--------------------------------------------------------------------------
| Validación de la base de datos
|--------------------------------------------------------------------------
*/

$resultadoBase = $db->query(
    'SELECT DATABASE() AS database_name'
);

$filaBase =
    $resultadoBase->fetch_assoc();

$resultadoBase->free();

$baseActual =
    (string) (
        $filaBase['database_name']
        ?? ''
    );

if ($baseActual !== 'skillview_test') {
    fwrite(
        STDERR,
        "Ejecución cancelada. Base detectada: "
            . $baseActual
            . ". Se requiere skillview_test.\n"
    );

    exit(1);
}

/*
|--------------------------------------------------------------------------
| Datos controlados
|--------------------------------------------------------------------------
*/

const CORREO_USUARIO =
'e2e_progreso_logros@skillview.test';

const PASSWORD_USUARIO =
'ProgresoCaja1!';

const MARCADOR_HABILIDAD =
'E2E-Progreso-Logros';

const PREFIJO_HABILIDAD =
'E2E Progreso';

const PREFIJO_RETO =
'E2E Reto Progreso';

const PREFIJO_LOGRO =
'E2E Progreso';

const FECHA_ACTUALIZACION =
'2026-07-15';

const FECHA_LOGRO =
'2026-07-15';

$comando = strtolower(
    trim(
        (string) (
            $argv[1]
            ?? ''
        )
    )
);

if (
    !in_array(
        $comando,
        [
            'reset',
            'cleanup'
        ],
        true
    )
) {
    fwrite(
        STDERR,
        "Usa: php progreso-logros-fixtures.php "
            . "reset|cleanup\n"
    );

    exit(1);
}

/*
|--------------------------------------------------------------------------
| Utilidades SQL
|--------------------------------------------------------------------------
*/

/**
 * Comprueba si una tabla contiene una columna.
 */
function tablaContieneColumna(
    mysqli $db,
    string $tabla,
    string $columna
): bool {
    $tablaSegura =
        $db->real_escape_string(
            $tabla
        );

    $columnaSegura =
        $db->real_escape_string(
            $columna
        );

    $resultado = $db->query(
        "SHOW COLUMNS
         FROM `{$tablaSegura}`
         LIKE '{$columnaSegura}'"
    );

    $existe =
        $resultado->num_rows > 0;

    $resultado->free();

    return $existe;
}

/**
 * Convierte un valor controlado en un literal SQL.
 */
function literalSql(
    mysqli $db,
    mixed $valor
): string {
    if ($valor === null) {
        return 'NULL';
    }

    if (
        is_int($valor)
        || is_float($valor)
    ) {
        return (string) $valor;
    }

    if (is_bool($valor)) {
        return $valor
            ? '1'
            : '0';
    }

    return "'"
        . $db->real_escape_string(
            (string) $valor
        )
        . "'";
}

/**
 * Inserta información utilizando únicamente
 * las columnas proporcionadas.
 */
function insertarDinamico(
    mysqli $db,
    string $tabla,
    array $datos
): int {
    $columnas = [];
    $valores = [];

    foreach (
        $datos as $columna => $valor
    ) {
        if (
            !tablaContieneColumna(
                $db,
                $tabla,
                $columna
            )
        ) {
            continue;
        }

        $columnas[] =
            "`{$columna}`";

        $valores[] =
            literalSql(
                $db,
                $valor
            );
    }

    if (empty($columnas)) {
        throw new RuntimeException(
            "No se encontraron columnas válidas "
                . "para insertar en {$tabla}."
        );
    }

    $sql =
        "INSERT INTO `{$tabla}` ("
        . implode(
            ', ',
            $columnas
        )
        . ') VALUES ('
        . implode(
            ', ',
            $valores
        )
        . ')';

    $db->query($sql);

    return (int) $db->insert_id;
}

/*
|--------------------------------------------------------------------------
| Limpieza
|--------------------------------------------------------------------------
*/

/**
 * Elimina exclusivamente los registros creados
 * para este escenario de caja negra.
 */
function limpiarProgresoLogrosE2E(
    mysqli $db
): void {
    /*
     * El usuario se elimina primero para retirar
     * relaciones con habilidades, retos y logros.
     */
    $correo =
        CORREO_USUARIO;

    $consultaUsuario = $db->prepare(
        'DELETE FROM usuarios
         WHERE correo = ?'
    );

    $consultaUsuario->bind_param(
        's',
        $correo
    );

    $consultaUsuario->execute();
    $consultaUsuario->close();

    /*
     * Eliminar retos controlados.
     */
    $patronRetos =
        PREFIJO_RETO . '%';

    $consultaRetos = $db->prepare(
        'DELETE FROM retos
         WHERE nombre LIKE ?'
    );

    $consultaRetos->bind_param(
        's',
        $patronRetos
    );

    $consultaRetos->execute();
    $consultaRetos->close();

    /*
     * Eliminar habilidades controladas.
     */
    $patronHabilidades =
        PREFIJO_HABILIDAD . '%';

    $consultaHabilidades = $db->prepare(
        'DELETE FROM habilidades_blandas
         WHERE nombre LIKE ?'
    );

    $consultaHabilidades->bind_param(
        's',
        $patronHabilidades
    );

    $consultaHabilidades->execute();
    $consultaHabilidades->close();

    /*
     * Eliminar logros controlados.
     */
    $patronLogros =
        PREFIJO_LOGRO . '%';

    $consultaLogros = $db->prepare(
        'DELETE FROM logros
         WHERE nombre LIKE ?'
    );

    $consultaLogros->bind_param(
        's',
        $patronLogros
    );

    $consultaLogros->execute();
    $consultaLogros->close();
}

/*
|--------------------------------------------------------------------------
| Creación de datos
|--------------------------------------------------------------------------
*/

/**
 * Crea el usuario general.
 */
function crearUsuarioProgresoE2E(
    mysqli $db
): int {
    $nombres =
        'Usuario Progreso';

    $apellidos =
        'Logros E Dos E';

    $edad = 24;
    $sexo = 3;

    $correo =
        CORREO_USUARIO;

    $password = password_hash(
        PASSWORD_USUARIO,
        PASSWORD_BCRYPT
    );

    $universidad =
        'Universidad del Cauca';

    $carrera =
        'Ingeniería de Sistemas';

    $admin = 0;
    $debeCambiarPassword = 0;
    $habilitado = 1;
    $token = '';
    $tokenExpiracion = 0;
    $autorizaDatos = 1;

    $consulta = $db->prepare(
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
        (int) $db->insert_id;

    $consulta->close();

    return $idUsuario;
}

/**
 * Crea una habilidad habilitada.
 */
function crearHabilidadProgresoE2E(
    mysqli $db,
    string $nombre
): int {
    $descripcion =
        'Habilidad temporal utilizada para '
        . 'comprobar la visualización del progreso.';

    $tag =
        MARCADOR_HABILIDAD;

    $habilitado = 1;

    $consulta = $db->prepare(
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
        (int) $db->insert_id;

    $consulta->close();

    return $idHabilidad;
}

/**
 * Registra el progreso consolidado
 * de una habilidad.
 */
function crearProgresoHabilidadE2E(
    mysqli $db,
    int $idUsuario,
    int $idHabilidad,
    int $nivel,
    float $progreso
): int {
    $fecha =
        FECHA_ACTUALIZACION;

    $consulta = $db->prepare(
        'INSERT INTO usuarios_habilidades (
            id_usuarios,
            id_habilidades,
            nivel,
            progreso,
            ultima_actualizacion
         ) VALUES (?, ?, ?, ?, ?)'
    );

    $consulta->bind_param(
        'iiids',
        $idUsuario,
        $idHabilidad,
        $nivel,
        $progreso,
        $fecha
    );

    $consulta->execute();

    $idProgreso =
        (int) $db->insert_id;

    $consulta->close();

    return $idProgreso;
}

/**
 * Crea un reto habilitado.
 */
function crearRetoProgresoE2E(
    mysqli $db,
    int $idHabilidad,
    string $nombre,
    int $puntos
): int {
    $descripcion =
        'Reto temporal utilizado para comprobar '
        . 'el puntaje acumulado del perfil.';

    $tag =
        MARCADOR_HABILIDAD
        . ', progreso';

    $tiempoMin = 5;
    $tiempoMax = 10;
    $dificultad = 1;
    $habilitado = 1;

    $consulta = $db->prepare(
        'INSERT INTO retos (
            id_habilidades,
            nombre,
            descripcion,
            tag,
            tiempo_min,
            tiempo_max,
            puntos,
            dificultad,
            habilitado
         ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?
         )'
    );

    $consulta->bind_param(
        'isssiiiii',
        $idHabilidad,
        $nombre,
        $descripcion,
        $tag,
        $tiempoMin,
        $tiempoMax,
        $puntos,
        $dificultad,
        $habilitado
    );

    $consulta->execute();

    $idReto =
        (int) $db->insert_id;

    $consulta->close();

    return $idReto;
}

/**
 * Registra un reto completado por el usuario.
 *
 * La fecha se agrega únicamente cuando la columna
 * correspondiente existe en la tabla actual.
 */
function completarRetoE2E(
    mysqli $db,
    int $idUsuario,
    int $idReto,
    int $puntaje
): int {
    $datos = [
        'id_usuarios' =>
        $idUsuario,

        'id_retos' =>
        $idReto,

        'completado' =>
        1,

        'puntaje_obtenido' =>
        $puntaje
    ];

    if (
        tablaContieneColumna(
            $db,
            'usuarios_retos',
            'fecha_completado'
        )
    ) {
        $datos['fecha_completado'] =
            FECHA_ACTUALIZACION;
    } elseif (
        tablaContieneColumna(
            $db,
            'usuarios_retos',
            'fecha'
        )
    ) {
        $datos['fecha'] =
            FECHA_ACTUALIZACION;
    }

    return insertarDinamico(
        $db,
        'usuarios_retos',
        $datos
    );
}

/**
 * Crea un logro del catálogo.
 */
function crearLogroE2E(
    mysqli $db,
    string $nombre,
    string $descripcion,
    string $icono,
    int $tipo,
    int $valorObjetivo,
    int $habilitado
): int {
    $consulta = $db->prepare(
        'INSERT INTO logros (
            nombre,
            descripcion,
            icono,
            tipo,
            valor_objetivo,
            habilitado
         ) VALUES (?, ?, ?, ?, ?, ?)'
    );

    $consulta->bind_param(
        'sssiii',
        $nombre,
        $descripcion,
        $icono,
        $tipo,
        $valorObjetivo,
        $habilitado
    );

    $consulta->execute();

    $idLogro =
        (int) $db->insert_id;

    $consulta->close();

    return $idLogro;
}

/**
 * Asigna un logro al usuario.
 */
function asignarLogroE2E(
    mysqli $db,
    int $idUsuario,
    int $idLogro
): int {
    $datos = [
        'id_usuarios' =>
        $idUsuario,

        'id_logros' =>
        $idLogro
    ];

    if (
        tablaContieneColumna(
            $db,
            'usuarios_logros',
            'fecha_obtenido'
        )
    ) {
        $datos['fecha_obtenido'] =
            FECHA_LOGRO;
    } elseif (
        tablaContieneColumna(
            $db,
            'usuarios_logros',
            'fecha'
        )
    ) {
        $datos['fecha'] =
            FECHA_LOGRO;
    }

    return insertarDinamico(
        $db,
        'usuarios_logros',
        $datos
    );
}

/*
|--------------------------------------------------------------------------
| Ejecución
|--------------------------------------------------------------------------
*/

try {
    $db->begin_transaction();

    limpiarProgresoLogrosE2E(
        $db
    );

    $ids = [];

    if ($comando === 'reset') {
        /*
        |--------------------------------------------------------------------------
        | Usuario
        |--------------------------------------------------------------------------
        */

        $ids['user'] =
            crearUsuarioProgresoE2E(
                $db
            );

        /*
|--------------------------------------------------------------------------
| Logros destacados utilizados por el perfil
|--------------------------------------------------------------------------
|
| El perfil muestra los primeros seis logros
| habilitados del catálogo, no los últimos
| logros insertados por el fixture.
|
*/
        $logrosPerfil =
            obtenerLogrosDestacadosPerfilE2E(
                $db,
                6
            );

        /*
 * Los dos primeros se asignan al usuario.
 * El tercero permanece bloqueado.
 */
        asignarLogroE2E(
            $db,
            $ids['user'],
            $logrosPerfil[0]['id']
        );

        asignarLogroE2E(
            $db,
            $ids['user'],
            $logrosPerfil[1]['id']
        );

        $ids['profileAchievements'] = [
            'firstUnlocked' => [
                'id' =>
                $logrosPerfil[0]['id'],

                'name' =>
                $logrosPerfil[0]['name']
            ],

            'secondUnlocked' => [
                'id' =>
                $logrosPerfil[1]['id'],

                'name' =>
                $logrosPerfil[1]['name']
            ],

            'locked' => [
                'id' =>
                $logrosPerfil[2]['id'],

                'name' =>
                $logrosPerfil[2]['name']
            ],

            'allFeatured' =>
            array_map(
                static function (
                    array $logro
                ): array {
                    return [
                        'id' =>
                        $logro['id'],

                        'name' =>
                        $logro['name']
                    ];
                },
                $logrosPerfil
            )
        ];
        /*
        |--------------------------------------------------------------------------
        | Habilidades y progreso
        |--------------------------------------------------------------------------
        */

        $ids['skills'] = [];

        $ids['skills']['basic'] =
            crearHabilidadProgresoE2E(
                $db,
                'E2E Progreso Básico'
            );

        $ids['skills']['intermediate'] =
            crearHabilidadProgresoE2E(
                $db,
                'E2E Progreso Intermedio'
            );

        $ids['skills']['advanced'] =
            crearHabilidadProgresoE2E(
                $db,
                'E2E Progreso Avanzado'
            );

        /*
         * 33 % → Básico.
         */
        crearProgresoHabilidadE2E(
            $db,
            $ids['user'],
            $ids['skills']['basic'],
            1,
            33.00
        );

        /*
         * 50 % → Intermedio.
         */
        crearProgresoHabilidadE2E(
            $db,
            $ids['user'],
            $ids['skills']['intermediate'],
            2,
            50.00
        );

        /*
         * 100 % → Avanzado.
         */
        crearProgresoHabilidadE2E(
            $db,
            $ids['user'],
            $ids['skills']['advanced'],
            3,
            100.00
        );

        /*
        |--------------------------------------------------------------------------
        | Retos y puntaje
        |--------------------------------------------------------------------------
        */

        $ids['challenges'] = [];

        $ids['challenges']['first'] =
            crearRetoProgresoE2E(
                $db,
                $ids['skills']['basic'],
                'E2E Reto Progreso Uno',
                30
            );

        $ids['challenges']['second'] =
            crearRetoProgresoE2E(
                $db,
                $ids['skills']['intermediate'],
                'E2E Reto Progreso Dos',
                30
            );

        completarRetoE2E(
            $db,
            $ids['user'],
            $ids['challenges']['first'],
            30
        );

        completarRetoE2E(
            $db,
            $ids['user'],
            $ids['challenges']['second'],
            24
        );

        /*
        |--------------------------------------------------------------------------
        | Logros
        |--------------------------------------------------------------------------
        */

        $ids['achievements'] = [];

        $ids['achievements']['skillUnlocked'] =
            crearLogroE2E(
                $db,
                'E2E Progreso Habilidad Desbloqueada',
                'Logro obtenido por completar '
                    . 'el objetivo de una habilidad.',
                'logros/habilidad_autoconfianza',
                1,
                100,
                1
            );

        $ids['achievements']['performanceUnlocked'] =
            crearLogroE2E(
                $db,
                'E2E Progreso Desempeño Desbloqueado',
                'Logro obtenido por alcanzar '
                    . 'un desempeño destacado.',
                'logros/desempeno_autoconfianza',
                4,
                21,
                1
            );

        $ids['achievements']['scoreLocked'] =
            crearLogroE2E(
                $db,
                'E2E Progreso Puntaje Bloqueado',
                'Logro que todavía no fue '
                    . 'alcanzado por el usuario.',
                'logros/desempeno_autoconfianza',
                2,
                100,
                1
            );

        $ids['achievements']['disabled'] =
            crearLogroE2E(
                $db,
                'E2E Progreso Oculto',
                'Este logro se encuentra deshabilitado '
                    . 'y no debe aparecer en las vistas.',
                'logros/desempeno_autoconfianza',
                3,
                1,
                0
            );

        asignarLogroE2E(
            $db,
            $ids['user'],
            $ids['achievements']['skillUnlocked']
        );

        asignarLogroE2E(
            $db,
            $ids['user'],
            $ids['achievements']['performanceUnlocked']
        );

        /*
        |--------------------------------------------------------------------------
        | Resultados esperados
        |--------------------------------------------------------------------------
        */

        $ids['expected'] = [
            /*
             * round((33 + 50 + 100) / 3) = 61
             */
            'generalProgress' =>
            61,

            'totalPoints' =>
            54,

            'achievementDate' =>
            '15 jul 2026'
        ];
    }

    $db->commit();

    if ($comando === 'reset') {
        echo json_encode(
            $ids,
            JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
        );
    } else {
        echo "Datos E2E de progreso y logros eliminados.\n";
    }
} catch (Throwable $error) {
    $db->rollback();

    fwrite(
        STDERR,
        $error->getMessage()
            . PHP_EOL
    );

    exit(1);
} finally {
    $db->close();
}
