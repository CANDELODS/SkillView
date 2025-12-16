<?php

namespace Controllers;

use Model\Usuario;
use MVC\Router;

class PrincipalController {
    public static function index(Router $router) {
        // Verificamos si el usuario está autenticado
        if (!isAuth()) {
            header('Location: /');
            exit;
        }
        $login = false;

        $datosUsuario = obtenerDatosUsuarioHeader($_SESSION['id']);
        // Render a la vista 
        $router->render('paginas/principal', [
            'titulo' => 'SkillView',
            'login' => $login,
            'nombreUsuario'    => $datosUsuario['nombreUsuario'],
            'inicialesUsuario' => $datosUsuario['inicialesUsuario']
        ]);
    }

    public static function notFound(Router $router) {
        $login = true;
        // Render a la vista 
        $router->render('paginas/404', [
            'titulo' => 'Página No Encontrada',
            'login' => $login
        ]);
    }
}