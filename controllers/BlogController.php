<?php

namespace Controllers;

use MVC\Router;

class BlogController
{
    public static function index(Router $router)
    {
        // Verificamos si el usuario está autenticado
        if (!isAuth()) {
            header('Location: /');
            exit;
        }
        $login = false;

        $datosUsuario = obtenerDatosUsuarioHeader($_SESSION['id']);
        // Render a la vista 
        $router->render('paginas/blog/blog', [
            'titulo' => 'Explora, aprende y crece con SkillView',
            'login' => $login,
            'nombreUsuario'    => $datosUsuario['nombreUsuario'],
            'inicialesUsuario' => $datosUsuario['inicialesUsuario']
        ]);
    }
}
