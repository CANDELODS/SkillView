<?php

namespace Controllers;

use Model\Logros;
use Model\usuarios_logros;
use MVC\Router;

class LogrosController
{

    private static function formatearFechaLogro(?string $fecha): string
    {
        if (empty($fecha)) {
            return '';
        }

        $timestamp = strtotime($fecha);
        if (!$timestamp) {
            return '';
        }

        $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $dia  = (int) date('d', $timestamp);
        $mes  = $meses[(int)date('m', $timestamp) - 1] ?? date('m', $timestamp);
        $anio = date('Y', $timestamp);

        return "{$dia} {$mes} {$anio}";
    }

    public static function index(Router $router)
    {
        // Verificamos si el usuario está autenticado
        if (!isAuth()) {
            header('Location: /');
            exit;
        }

        $login = false;
        $datosUsuario = obtenerDatosUsuarioHeader($_SESSION['id']);
        $idUsuario = (int) ($_SESSION['id'] ?? 0);

        // 1) Todos los logros disponibles (habilitados)
        $logros = Logros::habilitados();

        // 2) Lookup [id_logro => fecha]
        $logrosLookup = usuarios_logros::lookupLogrosUsuario($idUsuario);

        // 3) Separar por estado + contar
        $logrosDesbloqueados = [];
        $logrosBloqueados = [];

        // 4) Definir etiquetas para tipos de logros
        $tags = [
            1 => 'Reto',
            2 => 'Nivel',
            3 => 'Puntaje'
        ];

        foreach ($logros as $logro) {
            $idLogro = (int) $logro->id;
            //Validamos el tipo de logro y asignamos la etiqueta correspondiente
            $tipo = (int) ($logro->tipo ?? 0);
            $logro->tag_texto = $tags[$tipo] ?? 'General';

            if (isset($logrosLookup[$idLogro])) {
                // Logro desbloqueado
                //Creamos la propiedad dinamica 'debloqueado' en el objeto logro
                $logro->debloqueado = true;
                //Creamos la propiedad dinamica 'fecha_obtenido' en el objeto logro
                $logro->fecha_obtenido = $logrosLookup[$idLogro];
                $logro->fecha_formateada = self::formatearFechaLogro($logro->fecha_obtenido);
                //Agregamos el logro al array de logros desbloqueados
                $logrosDesbloqueados[] = $logro;
            } else {
                // Logro bloqueado
                $logro->debloqueado = false;
                $logrosBloqueados[] = $logro;
            }
        }

        $totalDesbloqueados = count($logrosDesbloqueados);
        $totalBloqueados = count($logrosBloqueados);
        $totalLogros = count($logros);

        $router->render('paginas/logros/logros', [
            'login' => $login,
            'titulo' => 'Logros y Medallas',
            // Header
            'nombreUsuario'    => $datosUsuario['nombreUsuario'],
            'inicialesUsuario' => $datosUsuario['inicialesUsuario'],
            // Datos para vista (secciones + contadores)
            'logrosDesbloqueados' => $logrosDesbloqueados,
            'logrosBloqueados'    => $logrosBloqueados,
            'totalDesbloqueados'  => $totalDesbloqueados,
            'totalBloqueados'     => $totalBloqueados,
            'totalLogros'         => $totalLogros
        ]);
    }
}
