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
 * Comprobar que la prueba se ejecute
 * exclusivamente sobre skillview_test.
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

const CORREO_PERFIL =
    'e2e_perfil_usuario@skillview.test';

const PASSWORD_PERFIL =
    'PerfilCaja1!';

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
        "Usa: php perfil-fixtures.php "
        . "reset|cleanup\n"
    );

    exit(1);
}

/**
 * Elimina exclusivamente la cuenta temporal.
 *
 * Las relaciones de progreso se eliminan mediante
 * las llaves foráneas configuradas con CASCADE.
 */
function limpiarDatosPerfilE2E(
    mysqli $db
): void {
    $correo =
        CORREO_PERFIL;

    $consulta = $db->prepare(
        'DELETE FROM usuarios
         WHERE correo = ?'
    );

    $consulta->bind_param(
        's',
        $correo
    );

    $consulta->execute();
    $consulta->close();
}

/**
 * Crea la cuenta general utilizada en la prueba.
 */
function crearUsuarioPerfilE2E(
    mysqli $db
): int {
    $nombres =
        'Usuario Perfil';

    $apellidos =
        'Prueba E Dos E';

    $edad = 24;
    $sexo = 3;

    $correo =
        CORREO_PERFIL;

    $password = password_hash(
        PASSWORD_PERFIL,
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
 * Inicializa una fila de progreso por cada
 * habilidad habilitada.
 */
function inicializarProgresoPerfilE2E(
    mysqli $db,
    int $idUsuario
): void {
    $fecha =
        date('Y-m-d');

    $consulta = $db->prepare(
        'INSERT INTO usuarios_habilidades (
            id_usuarios,
            id_habilidades,
            nivel,
            progreso,
            ultima_actualizacion
         )
         SELECT ?,
                hb.id,
                1,
                0.00,
                ?
         FROM habilidades_blandas hb
         WHERE hb.habilitado = 1'
    );

    $consulta->bind_param(
        'is',
        $idUsuario,
        $fecha
    );

    $consulta->execute();
    $consulta->close();
}

try {
    $db->begin_transaction();

    /*
     * El reset elimina cualquier registro dejado
     * por una ejecución interrumpida.
     */
    limpiarDatosPerfilE2E(
        $db
    );

    $datos = [];

    if ($comando === 'reset') {
        $datos['usuario'] =
            crearUsuarioPerfilE2E(
                $db
            );

        inicializarProgresoPerfilE2E(
            $db,
            $datos['usuario']
        );
    }

    $db->commit();

    if ($comando === 'reset') {
        echo json_encode(
            $datos,
            JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
        );
    } else {
        echo "Datos E2E del perfil eliminados.\n";
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