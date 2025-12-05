<?php

namespace Controllers;

use MVC\Router;

class RetosController {

    public static function index (Router $router){
    // Verificamos si el usuario está autenticado
        if (!isAuth()) {
            header('Location: /');
            exit;
        }

        $login = false;

                // Render a la vista 
        $router->render('paginas/retos/retos', [
            'titulo' => 'Pon a prueba tus habilidades',
            'login' => $login,
        ]);
    }
}