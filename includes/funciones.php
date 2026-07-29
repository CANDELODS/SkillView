<?php

use Classes\FuncionesAuxiliaresService;
use Model\Usuario;

/**
 * Muestra una variable con formato legible
 * y detiene la ejecución.
 */
function debuguear($variable): void
{
    echo '<pre>';
    var_dump($variable);
    echo '</pre>';
    exit;
}

/**
 * Escapa contenido antes de mostrarlo en HTML.
 */
function s($html): string
{
    return FuncionesAuxiliaresService::sanitizarHtml(
        $html
    );
}

/**
 * Comprueba que exista una sesión normal válida
 * y que el usuario continúe habilitado.
 */
function isAuth(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $sesion = $_SESSION ?? [];

    /*
     * Si faltan los datos básicos, no existe
     * una sesión normal autenticada.
     */
    if (
        empty($sesion['id'])
        || trim(
            (string)($sesion['correo'] ?? '')
        ) === ''
    ) {
        return false;
    }

    /*
     * Valida localmente el identificador y el correo
     * antes de realizar una consulta en MySQL.
     */
    if (
        !FuncionesAuxiliaresService::
            sesionTieneCredencialesValidas(
                $sesion
            )
    ) {
        $_SESSION = [];

        if (
            session_status()
            === PHP_SESSION_ACTIVE
        ) {
            session_destroy();
        }

        return false;
    }

    $usuarioId = (int) $sesion['id'];

    /*
     * Se consulta nuevamente el usuario para confirmar
     * que todavía exista y continúe habilitado.
     */
    $usuario = Usuario::find($usuarioId);

    if (
        !$usuario
        || (int) $usuario->habilitado !== 1
    ) {
        $_SESSION = [];

        if (
            session_status()
            === PHP_SESSION_ACTIVE
        ) {
            session_destroy();
        }

        return false;
    }

    return true;
}

/**
 * Indica si la ruta recibida corresponde
 * a la página actual o a una subruta.
 */
function pagina_actual($path): bool
{
    return FuncionesAuxiliaresService::esPaginaActual(
        $_SERVER['PATH_INFO'] ?? null,
        (string) $path
    );
}

/**
 * Obtiene el nombre corto y las iniciales
 * que se muestran en el encabezado.
 */
function obtenerDatosUsuarioHeader(
    int $usuarioId
): array {
    $datosPorDefecto =
        FuncionesAuxiliaresService::
            construirDatosHeader(
                null,
                null
            );

    if ($usuarioId <= 0) {
        return $datosPorDefecto;
    }

    $usuario = Usuario::find($usuarioId);

    if (!$usuario) {
        return $datosPorDefecto;
    }

    return FuncionesAuxiliaresService::
        construirDatosHeader(
            $usuario->nombres ?? null,
            $usuario->apellidos ?? null
        );
}