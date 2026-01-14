<?php

namespace Controllers;

use MVC\Router;

class PerfilController{
        public static function index(Router $router) {
        // Verificamos si el usuario está autenticado
        if (!isAuth()) {
            header('Location: /');
            exit;
        }
        $login = false;

        $datosUsuario = obtenerDatosUsuarioHeader($_SESSION['id']);
        // Render a la vista 
        $router->render('paginas/perfil/perfil', [
            'titulo' => 'Perfil',
            'login' => $login,
            'nombreUsuario'    => $datosUsuario['nombreUsuario'],
            'inicialesUsuario' => $datosUsuario['inicialesUsuario']
        ]);
    }
}