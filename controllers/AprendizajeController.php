<?php

namespace Controllers;

use Model\Usuario;
use MVC\Router;

class AprendizajeController {

        public static function index(Router $router) {
        $login = false;
        // Render a la vista 
        $router->render('paginas/aprendizaje/aprendizaje', [
            'titulo' => 'Desarrolla tus habilidades paso a paso',
            'login' => $login
        ]);
    }

}