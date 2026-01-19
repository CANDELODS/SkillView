<?php

namespace Controllers;

use Model\Logros;
use Model\Usuario;
use Model\usuarios_habilidades;
use Model\usuarios_logros;
use Model\usuarios_retos;
use MVC\Router;

class PerfilController
{
    public static function index(Router $router)
    {
        // Verificamos si el usuario está autenticado
        if (!isAuth()) {
            header('Location: /');
            exit;
        }
        $login = false;
        $idUsuario = (int)($_SESSION['id'] ?? 0);

        $datosUsuario = obtenerDatosUsuarioHeader($_SESSION['id']);

        // 1) Datos del usuario (para la tarjeta del perfil)
        $usuario = Usuario::find($idUsuario);
        
        // 2) Progreso general (promedio del progreso en usuarios_habilidades)
        $progresoGeneral = usuarios_habilidades::progresoGeneral($idUsuario);
        
        // 3) Puntos totales (sumatoria de puntaje_obtenido en retos completados)
        $puntosTotales = usuarios_retos::puntosTotalesUsuario($idUsuario);
        
        // 4) Progreso por habilidad (tabla)
        $progresoPorHabilidad = usuarios_habilidades::progresoPorHabilidad($idUsuario);
        
        // 5) Logros / medallas (destacados)
        $medallas = Logros::destacados(6);
        
        // Lookup de logros del usuario: [id_logro => fecha]
        $logrosUsuarioLookup = usuarios_logros::lookupLogrosUsuario($idUsuario);
        
        foreach ($medallas as $medalla) {
            $idLogro = (int)$medalla->id;
            $medalla->desbloqueado = isset($logrosUsuarioLookup[$idLogro]);
            $medalla->fecha_obtenido = $logrosUsuarioLookup[$idLogro] ?? null;
        }
        // Render a la vista 
        $router->render('paginas/perfil/perfil', [
            'titulo' => 'Perfil',
            'login'  => $login,

            // Header
            'nombreUsuario'    => $datosUsuario['nombreUsuario'],
            'inicialesUsuario' => $datosUsuario['inicialesUsuario'],

            // Perfil
            'usuario' => $usuario,
            'progresoGeneral' => $progresoGeneral,
            'puntosTotales' => $puntosTotales,
            'progresoPorHabilidad' => $progresoPorHabilidad,
            'medallas' => $medallas
        ]);
    }
}
