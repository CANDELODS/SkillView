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
        "No fue posible crear la conexión con MySQL.\n"
    );

    exit(1);
}

/*
 * Impide que la preparación se ejecute sobre
 * una base diferente de skillview_test.
 */
$resultadoBase = $db->query(
    'SELECT DATABASE() AS database_name'
);

$filaBase = $resultadoBase->fetch_assoc();
$resultadoBase->free();

$baseActual =
    (string) ($filaBase['database_name'] ?? '');

if ($baseActual !== 'skillview_test') {
    fwrite(
        STDERR,
        "Ejecución cancelada. Base detectada: "
        . $baseActual
        . ". Se requiere skillview_test.\n"
    );

    exit(1);
}

const CORREO_NUEVO =
    'e2e_registro_nuevo@skillview.test';

const CORREO_DUPLICADO =
    'e2e_registro_duplicado@skillview.test';

const PASSWORD_DUPLICADO =
    'CajaNegra1!';

$correosControlados = [
    CORREO_NUEVO,
    CORREO_DUPLICADO
];

$comando = strtolower(
    trim((string) ($argv[1] ?? ''))
);

if (
    !in_array(
        $comando,
        ['reset', 'cleanup'],
        true
    )
) {
    fwrite(
        STDERR,
        "Usa: php registro-fixtures.php reset|cleanup\n"
    );

    exit(1);
}

/**
 * Elimina las cuentas controladas y sus relaciones.
 * Las llaves foráneas con CASCADE eliminan los
 * registros dependientes.
 */
function eliminarUsuariosRegistro(
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

try {
    $db->begin_transaction();

    eliminarUsuariosRegistro(
        $db,
        $correosControlados
    );

    /*
     * Para el reset se crea únicamente la cuenta
     * utilizada en el caso del correo duplicado.
     */
    if ($comando === 'reset') {
        $nombres =
            'Usuario Duplicado';

        $apellidos =
            'Prueba E2E';

        $edad = 24;
        $sexo = 3;

        $correo =
            CORREO_DUPLICADO;

        $password = password_hash(
            PASSWORD_DUPLICADO,
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
        $consulta->close();
    }

    $db->commit();

    echo $comando === 'reset'
        ? "Datos E2E de registro preparados correctamente.\n"
        : "Datos E2E de registro eliminados correctamente.\n";
} catch (Throwable $error) {
    $db->rollback();

    fwrite(
        STDERR,
        $error->getMessage() . PHP_EOL
    );

    exit(1);
} finally {
    $db->close();
}