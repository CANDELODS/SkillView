<?php

namespace Controllers;

use MVC\Router;

class PrincipalController {
    public static function index(Router $router) {
        $login = false;
        // Render a la vista 
        $router->render('paginas/principal', [
            'titulo' => 'SkillView',
            'login' => $login
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