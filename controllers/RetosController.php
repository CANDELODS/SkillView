<?php

namespace Controllers;

use Model\Retos;
use MVC\Router;

class RetosController {

    public static function index (Router $router){
    // Verificamos si el usuario está autenticado
        if (!isAuth()) {
            header('Location: /');
            exit;
        }

        $login = false;
        $datosUsuario = obtenerDatosUsuarioHeader($_SESSION['id']);
        // Id del usuario logueado
        $idUsuario = $_SESSION['id'] ?? null;

        // 1) Traer los retos habilitados
        $retos = Retos::habilitadas(); 

        if(!empty($retos)){
            foreach($retos as $reto){
                if($reto->dificultad === '1'){
                    $reto->dificultad = 'Básico';
                }
                elseif($reto->dificultad === '2'){
                    $reto->dificultad = 'Intermedio';
                }
                elseif($reto->dificultad === '3'){
                    $reto->dificultad = 'Avanzado';
                }
            }
        }
        // Render a la vista 
        $router->render('paginas/retos/retos', [
            'titulo' => 'Pon a prueba tus habilidades',
            'login' => $login,
            'retos' => $retos,
            'nombreUsuario'    => $datosUsuario['nombreUsuario'],
            'inicialesUsuario' => $datosUsuario['inicialesUsuario']
        ]);
    }
}