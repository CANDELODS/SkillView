<?php

namespace Controllers;

use Model\HabilidadesBlandas;
use Model\Retos;
use MVC\Router;

class RetoController
{
    public static function reto(Router $router)
    {
        if (!isAuth()) {
            header('Location: /');
            exit;
        }

        $login = false;
        $datosUsuario = obtenerDatosUsuarioHeader($_SESSION['id']);

        $idReto = $_GET['id'] ?? null;
        if (!$idReto) {
            header('Location: /retos');
            exit;
        }

        $reto = Retos::find($idReto);
        if (!$reto) {
            header('Location: /retos');
            exit;
        }

        $habilidad = HabilidadesBlandas::find($reto->id_habilidades);
        $reto->nombreHabilidad = $habilidad ? $habilidad->nombre : 'Habilidad';

        $router->render('paginas/retos/reto', [
            'titulo' => $reto->nombre,
            'login' => $login,
            'nombreUsuario'    => $datosUsuario['nombreUsuario'],
            'inicialesUsuario' => $datosUsuario['inicialesUsuario'],
            'reto' => $reto
        ]);
    }

    public static function startChallenge() {}

    public static function turnChallenge() {}
}
