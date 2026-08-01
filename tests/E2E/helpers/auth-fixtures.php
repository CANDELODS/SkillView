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
 * Protección para impedir que los datos de caja negra
 * se creen en la base principal del proyecto.
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

const PASSWORD_GENERAL =
    'CajaNegra1!';

const PASSWORD_TEMPORAL =
    'Temporal1!';

/*
 * Cuentas controladas utilizadas únicamente
 * en el escenario de autenticación.
 */
$usuarios = [
    [
        'nombres' =>
            'Usuario General',

        'apellidos' =>
            'Prueba E2E',

        'correo' =>
            'e2e_auth_general@skillview.test',

        'password' =>
            PASSWORD_GENERAL,

        'admin' =>
            0,

        'debe_cambiar_password' =>
            0,

        'habilitado' =>
            1
    ],
    [
        'nombres' =>
            'Usuario Administrador',

        'apellidos' =>
            'Prueba E2E',

        'correo' =>
            'e2e_auth_admin@skillview.test',

        'password' =>
            PASSWORD_GENERAL,

        'admin' =>
            1,

        'debe_cambiar_password' =>
            0,

        'habilitado' =>
            1
    ],
    [
        'nombres' =>
            'Usuario Deshabilitado',

        'apellidos' =>
            'Prueba E2E',

        'correo' =>
            'e2e_auth_deshabilitado@skillview.test',

        'password' =>
            PASSWORD_GENERAL,

        'admin' =>
            0,

        'debe_cambiar_password' =>
            0,

        'habilitado' =>
            0
    ],
    [
        'nombres' =>
            'Usuario Temporal',

        'apellidos' =>
            'Prueba E2E',

        'correo' =>
            'e2e_auth_temporal@skillview.test',

        'password' =>
            PASSWORD_TEMPORAL,

        'admin' =>
            0,

        'debe_cambiar_password' =>
            1,

        'habilitado' =>
            1
    ],
    [
        'nombres' =>
            'Usuario Bloqueado',

        'apellidos' =>
            'Prueba E2E',

        'correo' =>
            'e2e_auth_bloqueado@skillview.test',

        'password' =>
            PASSWORD_TEMPORAL,

        'admin' =>
            0,

        'debe_cambiar_password' =>
            1,

        'habilitado' =>
            0
    ]
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
        "Usa: php auth-fixtures.php reset|cleanup\n"
    );

    exit(1);
}

/**
 * Elimina todas las cuentas controladas.
 *
 * Las relaciones dependientes también se eliminan
 * mediante las llaves foráneas configuradas con CASCADE.
 */
function eliminarUsuariosE2E(
    mysqli $db,
    array $usuarios
): void {
    $consulta = $db->prepare(
        'DELETE FROM usuarios
         WHERE correo = ?'
    );

    foreach ($usuarios as $usuario) {
        $correo = $usuario['correo'];

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

    /*
     * El reset también limpia posibles registros
     * residuales de una ejecución interrumpida.
     */
    eliminarUsuariosE2E(
        $db,
        $usuarios
    );

    if ($comando === 'reset') {
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
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
             )'
        );

        foreach ($usuarios as $usuario) {
            $nombres =
                $usuario['nombres'];

            $apellidos =
                $usuario['apellidos'];

            $edad = 24;
            $sexo = 3;

            $correo =
                $usuario['correo'];

            $password = password_hash(
                $usuario['password'],
                PASSWORD_BCRYPT
            );

            $universidad =
                'Universidad del Cauca';

            $carrera =
                'Ingeniería de Sistemas';

            $admin =
                $usuario['admin'];

            $debeCambiar =
                $usuario['debe_cambiar_password'];

            $habilitado =
                $usuario['habilitado'];

            $token = '';
            $tokenExpiracion = 0;
            $autorizaDatos = 1;

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
                $debeCambiar,
                $habilitado,
                $token,
                $tokenExpiracion,
                $autorizaDatos
            );

            $consulta->execute();
        }

        $consulta->close();
    }

    $db->commit();

    echo $comando === 'reset'
        ? "Usuarios E2E preparados correctamente.\n"
        : "Usuarios E2E eliminados correctamente.\n";
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