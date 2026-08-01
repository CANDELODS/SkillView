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

const PASSWORD_ADMIN =
    'AdminCaja1!';

const PASSWORD_USUARIO =
    'UsuarioCaja1!';

const CORREO_ADMIN =
    'e2e_gestion_admin@skillview.test';

const CORREO_EDITABLE =
    'e2e_gestion_editable@skillview.test';

const CORREO_EXISTENTE =
    'e2e_gestion_existente@skillview.test';

/*
 * Cuentas controladas utilizadas exclusivamente
 * durante este escenario de caja negra.
 */
$correosControlados = [
    CORREO_ADMIN,
    CORREO_EDITABLE,
    CORREO_EXISTENTE
];

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
        "Usa: php usuarios-admin-fixtures.php "
        . "reset|cleanup\n"
    );

    exit(1);
}

/**
 * Elimina exclusivamente las cuentas temporales
 * creadas para las pruebas automatizadas.
 *
 * Esta limpieza no corresponde al caso funcional
 * de eliminación administrativa de usuarios.
 */
function eliminarUsuariosAdministrativosE2E(
    mysqli $db,
    array $correos
): void {
    $consulta = $db->prepare(
        'DELETE FROM usuarios
         WHERE correo = ?'
    );

    foreach ($correos as $correo) {
        $consulta->bind_param(
            's',
            $correo
        );

        $consulta->execute();
    }

    $consulta->close();
}

/**
 * Crea una cuenta controlada y devuelve
 * su identificador.
 */
function crearUsuarioAdministrativoE2E(
    mysqli $db,
    string $nombres,
    string $apellidos,
    string $correo,
    string $passwordPlano,
    int $admin
): int {
    $edad = 24;
    $sexo = 3;

    $password = password_hash(
        $passwordPlano,
        PASSWORD_BCRYPT
    );

    $universidad =
        'Universidad del Cauca';

    $carrera =
        'Ingeniería de Sistemas';

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

try {
    $db->begin_transaction();

    /*
     * El reset limpia posibles registros
     * dejados por una ejecución interrumpida.
     */
    eliminarUsuariosAdministrativosE2E(
        $db,
        $correosControlados
    );

    $ids = [];

    if ($comando === 'reset') {
        $ids['admin'] =
            crearUsuarioAdministrativoE2E(
                $db,
                'Administrador Gestión',
                'Prueba E Dos E',
                CORREO_ADMIN,
                PASSWORD_ADMIN,
                1
            );

        $ids['editable'] =
            crearUsuarioAdministrativoE2E(
                $db,
                'Usuario Editable',
                'Prueba E Dos E',
                CORREO_EDITABLE,
                PASSWORD_USUARIO,
                0
            );

        $ids['existing'] =
            crearUsuarioAdministrativoE2E(
                $db,
                'Usuario Existente',
                'Prueba E Dos E',
                CORREO_EXISTENTE,
                PASSWORD_USUARIO,
                0
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
        echo "Datos E2E administrativos eliminados.\n";
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