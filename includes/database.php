<?php

/*
 * SkillView trabaja con la hora local de Colombia.
 *
 * El servidor/PHP puede utilizar UTC por defecto. En Colombia, después
 * de las 7:00 p. m., UTC ya corresponde al día siguiente, lo que puede
 * provocar que date('Y-m-d') guarde una fecha adelantada.
 *
 * Colombia utiliza UTC-5 durante todo el año y no aplica horario
 * de verano.
 */
date_default_timezone_set('America/Bogota');

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

/*
 * Mantiene también la sesión de MySQL en UTC-5.
 *
 * Esto cubre consultas o valores predeterminados que utilicen
 * funciones de MySQL como NOW(), CURDATE() o CURRENT_DATE.
 *
 * Se usa el desplazamiento -05:00 en lugar de un nombre de zona
 * para no depender de que MySQL tenga cargadas las tablas de zonas
 * horarias del sistema.
 */
if (!$db->query("SET SESSION time_zone = '-05:00'")) {
    echo "Error: No fue posible establecer la zona horaria de MySQL.";
    echo "Detalle: " . $db->error;
    exit;
}
