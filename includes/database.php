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
 * Establece UTF-8 para la comunicación entre PHP y MySQL.
 *
 * Esto evita que caracteres como á, é, í, ó, ú y ñ
 * sean almacenados o recuperados como signos de interrogación.
 */
if (!$db->set_charset('utf8mb4')) {
    echo 'Error: No fue posible establecer la codificación UTF-8.';
    echo 'Detalle: ' . $db->error;
    exit;
}