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
 * Impide que los datos de la prueba sean creados
 * en una base diferente de skillview_test.
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

const CORREO_USUARIO =
    'e2e_leccion_usuario@skillview.test';

const PASSWORD_USUARIO =
    'LeccionCaja1!';

const NOMBRE_HABILIDAD =
    'Comunicación Visual E2E';

const TITULO_LECCION =
    'Comunicación clara en una entrevista E2E';

const MARCADOR_E2E =
    'E2E-Flujo-Leccion';

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
        "Usa: php leccion-flujo-fixtures.php "
        . "reset|cleanup\n"
    );

    exit(1);
}

/**
 * Elimina únicamente los datos temporales
 * utilizados por este escenario.
 */
function limpiarDatosLeccionE2E(
    mysqli $db
): void {
    /*
     * La lección debe eliminarse antes que
     * la habilidad a la que pertenece.
     */
    $titulo =
        TITULO_LECCION;

    $consultaLeccion = $db->prepare(
        'DELETE FROM lecciones
         WHERE titulo = ?'
    );

    $consultaLeccion->bind_param(
        's',
        $titulo
    );

    $consultaLeccion->execute();
    $consultaLeccion->close();

    $patronTag =
        '%' . MARCADOR_E2E . '%';

    $consultaHabilidad = $db->prepare(
        'DELETE FROM habilidades_blandas
         WHERE tag LIKE ?'
    );

    $consultaHabilidad->bind_param(
        's',
        $patronTag
    );

    $consultaHabilidad->execute();
    $consultaHabilidad->close();

    /*
     * Las relaciones del usuario se eliminan
     * mediante las llaves foráneas con CASCADE.
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
}

/**
 * Crea la cuenta general utilizada por Playwright.
 */
function crearUsuarioLeccionE2E(
    mysqli $db
): int {
    $nombres =
        'Usuario Lección';

    $apellidos =
        'Prueba E Dos E';

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
 * Crea la habilidad asociada con la lección.
 */
function crearHabilidadLeccionE2E(
    mysqli $db
): int {
    $nombre =
        NOMBRE_HABILIDAD;

    $descripcion =
        'Habilidad temporal utilizada para '
        . 'comprobar el flujo visual de una lección.';

    $tag =
        MARCADOR_E2E
        . ', Comunicación, Entrevista';

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
 * Crea la lección utilizada para cargar
 * la interfaz conversacional.
 */
function crearLeccionE2E(
    mysqli $db,
    int $idHabilidad
): int {
    $titulo =
        TITULO_LECCION;

    $descripcion =
        "Objetivo:\n"
        . "Expresar ideas de manera clara durante "
        . "una entrevista laboral.\n\n"
        . "Conceptos clave:\n"
        . "- Organización de ideas\n"
        . "- Claridad verbal\n"
        . "- Ejemplos concretos\n\n"
        . "Errores comunes:\n"
        . "- Responder sin estructura\n"
        . "- Utilizar respuestas demasiado generales\n\n"
        . "Micro-práctica:\n"
        . "Explica cómo presentarías una fortaleza "
        . "personal durante una entrevista.\n\n"
        . "Mini-evaluación:\n"
        . "Describe una situación en la que hayas "
        . "comunicado una idea con claridad.\n\n"
        . "Resumen esperado:\n"
        . "El usuario debe organizar su respuesta, "
        . "presentar un ejemplo y explicar el resultado.";

    $orden = 1;
    $habilitado = 1;

    $consulta = $db->prepare(
        'INSERT INTO lecciones (
            id_habilidades,
            titulo,
            descripcion,
            orden,
            habilitado
         ) VALUES (?, ?, ?, ?, ?)'
    );

    $consulta->bind_param(
        'issii',
        $idHabilidad,
        $titulo,
        $descripcion,
        $orden,
        $habilitado
    );

    $consulta->execute();

    $idLeccion =
        (int) $db->insert_id;

    $consulta->close();

    return $idLeccion;
}

/**
 * Inicializa el progreso de la habilidad
 * para el usuario controlado.
 */
function inicializarProgresoLeccionE2E(
    mysqli $db,
    int $idUsuario,
    int $idHabilidad
): void {
    $nivel = 1;
    $progreso = 0.00;
    $fecha = date('Y-m-d');

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
    $consulta->close();
}

try {
    $db->begin_transaction();

    limpiarDatosLeccionE2E(
        $db
    );

    $ids = [];

    if ($comando === 'reset') {
        $ids['user'] =
            crearUsuarioLeccionE2E(
                $db
            );

        $ids['skill'] =
            crearHabilidadLeccionE2E(
                $db
            );

        $ids['lesson'] =
            crearLeccionE2E(
                $db,
                $ids['skill']
            );

        inicializarProgresoLeccionE2E(
            $db,
            $ids['user'],
            $ids['skill']
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
        echo "Datos E2E de la lección eliminados.\n";
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