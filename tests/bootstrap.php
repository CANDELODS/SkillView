<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Model\ActiveRecord;

$rutaProyecto =
    dirname(__DIR__);

require_once $rutaProyecto
    . '/vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Variables de entorno
|--------------------------------------------------------------------------
*/

$dotenv = Dotenv::createImmutable(
    $rutaProyecto . '/includes'
);

$dotenv->safeLoad();

/*
|--------------------------------------------------------------------------
| Conexión exclusiva para pruebas
|--------------------------------------------------------------------------
*/

$db = new mysqli(
    $_ENV['DB_HOST'] ?? '',
    $_ENV['DB_USER'] ?? '',
    $_ENV['DB_PASS'] ?? '',
    $_ENV['DB_NAME'] ?? ''
);

if ($db->connect_errno) {
    throw new RuntimeException(
        'No fue posible conectar con la base '
        . 'de datos de pruebas: '
        . $db->connect_error
    );
}

$db->set_charset('utf8mb4');

/*
|--------------------------------------------------------------------------
| Protección de la base real
|--------------------------------------------------------------------------
*/

$resultadoBase = $db->query(
    'SELECT DATABASE() AS nombre_base'
);

$filaBase =
    $resultadoBase->fetch_assoc();

$resultadoBase->free();

$nombreBase =
    (string) (
        $filaBase['nombre_base']
        ?? ''
    );

if ($nombreBase !== 'skillview_test') {
    $db->close();

    throw new RuntimeException(
        'Las pruebas solo pueden ejecutarse sobre '
        . 'skillview_test. Base detectada: '
        . $nombreBase
    );
}

/*
|--------------------------------------------------------------------------
| Compartir la conexión
|--------------------------------------------------------------------------
|
| ActiveRecord la utilizará para los modelos.
| $GLOBALS permitirá que las pruebas que realizan
| consultas directas utilicen la misma conexión.
|
*/

ActiveRecord::setDB($db);

$GLOBALS['db'] =
    $db;