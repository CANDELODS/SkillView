<?php

use Model\Usuario;

/**
 * Muestra el contenido de una variable y detiene la ejecución.
 *
 * Debe utilizarse únicamente durante la depuración local.
 */
function debuguear($variable): void
{
    echo '<pre>';
    var_dump($variable);
    echo '</pre>';

    exit;
}

/**
 * Sanitiza contenido antes de imprimirlo dentro de una vista HTML.
 *
 * Se codifican comillas simples y dobles, y se establece UTF-8
 * explícitamente para conservar correctamente tildes y la letra ñ.
 */
function s($html): string
{
    return htmlspecialchars(
        (string) $html,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Comprueba que exista una sesión autenticada.
 *
 * session_status() evita intentar iniciar una sesión que ya se
 * encuentra activa.
 */
function isAuth(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    /*
     * El correo se crea durante el inicio de sesión correcto.
     * También comprobamos que la sesión no esté vacía.
     */
    return isset($_SESSION['correo'])
        && $_SESSION['correo'] !== ''
        && !empty($_SESSION);
}

/**
 * Comprueba que la sesión pertenezca a una cuenta administrativa.
 */
function isAdmin(): bool
{
    /*
     * isAuth() también se encarga de iniciar la sesión
     * cuando todavía no está activa.
     */
    if (!isAuth()) {
        return false;
    }

    return isset($_SESSION['admin'])
        && (int) $_SESSION['admin'] === 1;
}

/**
 * Determina si la ruta recibida forma parte de la ruta actual.
 *
 * Se conserva el comportamiento utilizado por el menú de navegación,
 * pero se evita acceder a PATH_INFO cuando la clave no existe.
 */
function pagina_actual($path): bool
{
    $pathInfo = $_SERVER['PATH_INFO'] ?? '';

    return str_contains(
        $pathInfo,
        (string) $path
    );
}

/**
 * Obtiene el nombre corto y las iniciales que se muestran
 * en el encabezado de las páginas autenticadas.
 *
 * @return array{
 *     nombreUsuario: string,
 *     inicialesUsuario: string
 * }
 */
function obtenerDatosUsuarioHeader(
    int $usuarioId
): array {
    /*
     * Valores predeterminados utilizados cuando el usuario
     * no existe o no puede recuperarse desde la base de datos.
     */
    $datos = [
        'nombreUsuario' => 'Juan Candelo',
        'inicialesUsuario' => 'JC'
    ];

    if ($usuarioId <= 0) {
        return $datos;
    }

    $usuario = Usuario::find($usuarioId);

    if (!$usuario) {
        return $datos;
    }

    /*
     * Se eliminan espacios externos antes de separar los nombres.
     */
    $nombres = trim(
        (string) ($usuario->nombres ?? '')
    );

    $apellidos = trim(
        (string) ($usuario->apellidos ?? '')
    );

    /*
     * preg_split() admite espacios normales, tabulaciones
     * y saltos de línea como separadores.
     *
     * Ejemplo:
     * "Juan Sebastián" se convierte en ["Juan", "Sebastián"].
     */
    $partesNombres = preg_split(
        '/\s+/u',
        $nombres,
        -1,
        PREG_SPLIT_NO_EMPTY
    );

    $partesApellidos = preg_split(
        '/\s+/u',
        $apellidos,
        -1,
        PREG_SPLIT_NO_EMPTY
    );

    $primerNombre = $partesNombres[0] ?? '';
    $primerApellido = $partesApellidos[0] ?? '';

    /*
     * El encabezado utiliza únicamente el primer nombre
     * y el primer apellido.
     */
    $nombreCorto = trim(
        $primerNombre . ' ' . $primerApellido
    );

    if ($nombreCorto !== '') {
        $datos['nombreUsuario'] = $nombreCorto;
    }

    /*
     * mb_substr() permite obtener correctamente la primera letra
     * aunque el nombre o apellido comience con un carácter acentuado.
     */
    $inicialNombre = mb_substr(
        $primerNombre,
        0,
        1,
        'UTF-8'
    );

    $inicialApellido = mb_substr(
        $primerApellido,
        0,
        1,
        'UTF-8'
    );

    $iniciales = mb_strtoupper(
        $inicialNombre . $inicialApellido,
        'UTF-8'
    );

    /*
     * Solo reemplazamos las iniciales predeterminadas cuando
     * realmente se obtuvo al menos una letra.
     */
    if ($iniciales !== '') {
        $datos['inicialesUsuario'] = $iniciales;
    }

    return $datos;
}