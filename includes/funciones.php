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
 * Invalida una sesión autenticada sin realizar redirecciones.
 *
 * Se utiliza cuando el usuario de la sesión:
 * - ya no existe en la base de datos;
 * - fue deshabilitado mientras tenía una sesión abierta.
 *
 * No redirigir desde aquí permite que cada controlador conserve
 * su comportamiento actual:
 * - las páginas normales redirigen al login;
 * - los endpoints de API responden JSON con código 401.
 */
function invalidarSesionAutenticada(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Eliminar todas las variables de la sesión actual.
    $_SESSION = [];

    /*
     * Eliminar también la cookie que identifica la sesión,
     * siempre que PHP esté utilizando cookies para sesiones.
     */
    if (ini_get('session.use_cookies')) {
        $parametros = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $parametros['path'],
            $parametros['domain'],
            $parametros['secure'],
            $parametros['httponly']
        );
    }

    // Invalidar el identificador almacenado en el servidor.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/**
 * Comprueba que la sesión siga perteneciendo a una cuenta válida.
 *
 * No basta con comprobar los datos guardados en $_SESSION, porque
 * el administrador puede deshabilitar una cuenta mientras el usuario
 * todavía tiene el navegador abierto.
 *
 * Por eso, en cada petición protegida:
 * 1. se comprueba la sesión;
 * 2. se consulta nuevamente el usuario en la base de datos;
 * 3. se verifica que continúe habilitado.
 *
 * Si la cuenta fue deshabilitada, la sesión se invalida inmediatamente.
 */
function isAuth(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    /*
     * Una sesión temporal de cambio obligatorio de contraseña
     * no contiene id/correo y no debe considerarse autenticada.
     */
    $idUsuario = filter_var(
        $_SESSION['id'] ?? null,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1
            ]
        ]
    );

    $correoSesion = trim(
        (string) (
            $_SESSION['correo']
            ?? ''
        )
    );

    if (
        $idUsuario === false
        || $correoSesion === ''
    ) {
        return false;
    }

    try {
        /*
         * La base de datos es la fuente real del estado de la cuenta.
         * Esto impide que una sesión antigua continúe autorizada
         * después de cambiar habilitado de 1 a 0.
         */
        $usuario = Usuario::find(
            (int) $idUsuario
        );
    } catch (\Throwable $e) {
        /*
         * Ante un fallo al comprobar la cuenta se aplica un criterio
         * seguro: no se concede acceso con un estado que no pudo
         * verificarse.
         */
        error_log(
            'No fue posible validar la sesión del usuario '
            . (int) $idUsuario
            . ': '
            . $e->getMessage()
        );

        return false;
    }

    /*
     * Si la cuenta fue eliminada externamente o está deshabilitada,
     * se destruye la sesión que permanecía abierta en el navegador.
     */
    if (
        !$usuario
        || (int) (
            $usuario->habilitado
            ?? 0
        ) !== 1
    ) {
        invalidarSesionAutenticada();

        return false;
    }

    /*
     * Sincronizamos los datos de sesión que pueden cambiar desde
     * administración. Así la sesión no conserva un rol o correo
     * diferente al registrado actualmente en la base de datos.
     */
    $_SESSION['correo'] =
        (string) $usuario->correo;

    $_SESSION['admin'] =
        (int) (
            $usuario->admin
            ?? 0
        );

    return true;
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