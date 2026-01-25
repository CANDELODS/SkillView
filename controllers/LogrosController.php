<?php

namespace Controllers;

use Model\Logros;
use Model\usuarios_logros;
use MVC\Router;

class LogrosController{
    public static function index(Router $router) {
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

        foreach ($logros as $logro){
            $idLogro = (int) $logro->id;

            if(isset($logrosLookup[$idLogro])){
                // Logro desbloqueado
                //Creamos la propiedad dinamica 'debloqueado' en el objeto logro
                $logro->debloqueado = true;
                //Creamos la propiedad dinamica 'fecha_obtenido' en el objeto logro
                $logro->fecha_obtenido = $logrosLookup[$idLogro];
                //Agregamos el logro al array de logros desbloqueados
                $logrosDesbloqueados[] = $logro;
            }else{
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