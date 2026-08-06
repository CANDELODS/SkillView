<?php

$db = mysqli_connect(
    $_ENV['DB_HOST'] ?? '',
    $_ENV['DB_USER'] ?? '',
    $_ENV['DB_PASS'] ?? '',
    $_ENV['DB_NAME'] ?? ''
);

if (!$db) {
    echo "Error: No se pudo conectar a MySQL.";
    echo "errno de depuración: " . mysqli_connect_errno();
    echo "error de depuración: " . mysqli_connect_error();
    exit;
}

/*
 * Establece UTF-8 completo para la comunicación entre PHP y MySQL.
 *
 * utf8mb4 permite almacenar y recuperar correctamente caracteres
 * como á, é, í, ó, ú, ñ y símbolos Unicode.
 */
if (!$db->set_charset('utf8mb4')) {
    echo "Error: No fue posible establecer la codificación UTF-8.";
    echo "Detalle: " . $db->error;
    exit;
}