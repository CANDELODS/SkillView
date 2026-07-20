<?php

use Model\Usuario;

function debuguear($variable): string
{
    echo "<pre>";
    var_dump($variable);
    echo "</pre>";
    exit;
}
function s($html): string
{
    $s = htmlspecialchars($html);
    return $s;
}

function isAuth(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // No existe una sesión normal autenticada
    if (
        empty($_SESSION['id']) ||
        empty($_SESSION['correo'])
    ) {
        return false;
    }

    $usuarioId = filter_var(
        $_SESSION['id'],
        FILTER_VALIDATE_INT
    );

    // El ID de la sesión no es válido
    if (!$usuarioId) {
        $_SESSION = [];
        session_destroy();

        return false;
    }

    // Consultar nuevamente el usuario en la base de datos
    $usuario = Usuario::find((int) $usuarioId);

    /*
     * Se invalida la sesión si:
     * - El usuario ya no existe.
     * - Su cuenta está deshabilitada.
     */
    if (
        !$usuario ||
        (int) $usuario->habilitado !== 1
    ) {
        $_SESSION = [];
        session_destroy();

        return false;
    }

    return true;
}

function pagina_actual($path): bool
{
    return str_contains($_SERVER['PATH_INFO'], $path) ? true : false;
}

function obtenerDatosUsuarioHeader(int $usuarioId): array
{
    // Valores por defecto
    $datos = [
        'nombreUsuario'    => 'Juan Candelo',
        'inicialesUsuario' => 'JC'
    ];

    $usuario = Usuario::find($usuarioId);

    if (!$usuario) {
        return $datos;
    }

    // Tomamos nombres y apellidos desde la BD
    $nombres   = trim($usuario->nombres ?? '');
    $apellidos = trim($usuario->apellidos ?? '');

    // preg_split divide un string en partes por medio de una expresión regular como separador
    //Soporta tabulaciones, saltos de linea y más. /\s+/ = Cualquier espacio en blanco (Espacio normal, tab, salto de línea, etc)
    //+ = Uno o más. "Juan Sebastian" -> ["Juan", "Sebastian"]
    $nParts = preg_split('/\s+/', $nombres);
    $aParts = preg_split('/\s+/', $apellidos);
    //Obtenemos la primer parte del arreglo
    $primerNombre   = $nParts[0] ?? '';
    $primerApellido = $aParts[0] ?? '';

    // Nombre corto: "PrimerNombre PrimerApellido"
    $nombreCorto = trim($primerNombre . ' ' . $primerApellido);
    if ($nombreCorto !== '') {
        $datos['nombreUsuario'] = $nombreCorto;
    }

    // Iniciales usando mb_substr por si hay acentos
    //Además nos devuelve el tecto en MAYUSCULA
    $datos['inicialesUsuario'] = mb_strtoupper(
        //mb_substr: Toma la primera letra del texto sin importar si tiene acentos o no.
        mb_substr($primerNombre, 0, 1, 'UTF-8') .
            mb_substr($primerApellido, 0, 1, 'UTF-8'),
        'UTF-8'
    );

    return $datos;
}
