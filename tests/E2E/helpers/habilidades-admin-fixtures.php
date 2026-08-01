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

/*
 * Comprobar que las pruebas utilicen exclusivamente
 * la copia de la base de datos.
 */
$resultadoBase = $db->query(
    'SELECT DATABASE() AS database_name'
);

$filaBase = $resultadoBase->fetch_assoc();
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
 * Datos controlados para este escenario.
 */
const CORREO_ADMIN =
    'e2e_habilidades_admin@skillview.test';

const PASSWORD_ADMIN =
    'AdminCaja1!';

const MARCADOR_E2E =
    'E2E-Control-Habilidades';

const NOMBRE_HABILIDAD_EDITABLE =
    'Habilidad Editable E2E';

const NOMBRE_HABILIDAD_ELIMINABLE =
    'Habilidad Eliminable E2E';

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
        "Usa: php habilidades-admin-fixtures.php "
        . "reset|cleanup\n"
    );

    exit(1);
}

/**
 * Elimina únicamente las habilidades y la cuenta
 * temporal utilizadas durante el escenario.
 *
 * Esta limpieza no corresponde al caso funcional
 * de eliminación evaluado por Playwright.
 */
function limpiarDatosHabilidadesE2E(
    mysqli $db
): void {
    $patronTag =
        '%' . MARCADOR_E2E . '%';

    $consultaHabilidades = $db->prepare(
        'DELETE FROM habilidades_blandas
         WHERE tag LIKE ?'
    );

    $consultaHabilidades->bind_param(
        's',
        $patronTag
    );

    $consultaHabilidades->execute();
    $consultaHabilidades->close();

    $correo =
        CORREO_ADMIN;

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
}

/**
 * Crea el administrador utilizado para ingresar
 * al panel de SkillView.
 */
function crearAdministradorHabilidadesE2E(
    mysqli $db
): int {
    $nombres =
        'Administrador Habilidades';

    $apellidos =
        'Prueba E Dos E';

    $edad = 24;
    $sexo = 3;

    $correo =
        CORREO_ADMIN;

    $password = password_hash(
        PASSWORD_ADMIN,
        PASSWORD_BCRYPT
    );

    $universidad =
        'Universidad del Cauca';

    $carrera =
        'Ingeniería de Sistemas';

    $admin = 1;
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

    $idAdministrador =
        (int) $db->insert_id;

    $consulta->close();

    return $idAdministrador;
}

/**
 * Crea una habilidad controlada y devuelve
 * su identificador.
 */
function crearHabilidadE2E(
    mysqli $db,
    string $nombre,
    string $descripcion,
    string $etiquetaAdicional
): int {
    $tag =
        MARCADOR_E2E
        . ', '
        . $etiquetaAdicional;

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

try {
    $db->begin_transaction();

    /*
     * El reset también elimina posibles registros
     * dejados por una ejecución interrumpida.
     */
    limpiarDatosHabilidadesE2E(
        $db
    );

    $ids = [];

    if ($comando === 'reset') {
        $ids['admin'] =
            crearAdministradorHabilidadesE2E(
                $db
            );

        $ids['editable'] =
            crearHabilidadE2E(
                $db,
                NOMBRE_HABILIDAD_EDITABLE,
                'Habilidad temporal utilizada para '
                . 'comprobar la administración de '
                . 'habilidades blandas.',
                'Administración, Prueba'
            );

        $ids['deletable'] =
            crearHabilidadE2E(
                $db,
                NOMBRE_HABILIDAD_ELIMINABLE,
                'Habilidad temporal destinada '
                . 'exclusivamente a comprobar '
                . 'el proceso de eliminación.',
                'Eliminación, Prueba'
            );
    }

    $db->commit();

    if ($comando === 'reset') {
        echo json_encode(
            $ids,
            JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
        );
    } else {
        echo "Datos E2E de habilidades eliminados.\n";
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