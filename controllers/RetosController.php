<?php

namespace Controllers;

use MVC\Router;
use Model\Retos;
use Model\Logros;
use Model\usuarios_retos;
use Model\HabilidadesBlandas;
use Model\usuarios_logros;

class RetosController
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
        // Id del usuario logueado
        $idUsuario = $_SESSION['id'] ?? null;

        // ---- Progreso general ----
        $totalRetos = Retos::totalHabilitados();
        $completadosTotal = usuarios_retos::totalCompletadosUsuario($idUsuario);

        // ---- Progreso por habilidad ----
        // Traemos TODAS las habilidades habilitadas (para mostrarlas aunque estén en 0)
        $habilidades = HabilidadesBlandas::conRetosHabilitados();
        // completados por habilidad (solo las que el usuario ha completado algo)
        $completadosHab = usuarios_retos::completadosPorHabilidad($idUsuario);

        // Convertimos a lookup por id_habilidad para acceso rápido
        $lookupCompletados = [];
        foreach ($completadosHab as $row) {
            $lookupCompletados[$row['id_habilidad']] = $row['completados'];
        }

        $progresoPorHabilidad = [];
        $totalesHab = Retos::totalesPorHabilidad();
        foreach ($habilidades as $hab) {
            $idHab = (int)$hab->id;

            $totalHab = $totalesHab[$idHab] ?? 0;
            $compHab  = $lookupCompletados[$idHab] ?? 0;

            $porcentaje = ($totalHab > 0) ? (int) round(($compHab / $totalHab) * 100) : 0;

            // Niveles sugeridos (ajústalos a tu gusto)
            $nivel = 'Básico';
            if ($porcentaje >= 70) $nivel = 'Intermedio';
            if ($porcentaje >= 90) $nivel = 'Avanzado';

            $progresoPorHabilidad[] = [
                'nombre' => $hab->nombre,
                'porcentaje' => $porcentaje,
                'nivel' => $nivel,
                'completados' => $compHab,
                'total' => $totalHab
            ];
        }

        // ---- Medallas ----
        $medallas = Logros::destacados(6);
        $idsLogrosUsuario = usuarios_logros::idsLogrosUsuario($idUsuario);
        $logrosLookup = array_flip($idsLogrosUsuario);

        foreach ($medallas as $medalla) {
            $medalla->desbloqueado = isset($logrosLookup[(int)$medalla->id]);
        }

        $idCompletados = usuarios_retos::idsRetosCompletados((int)$idUsuario);

        // Para búsqueda rápida O(1)
        //array flip Convierte [1,3,7] en ['1'=>0,'3'=>1,'7'=>2] y así isset(...) es rapidísimo.
        $completadosLookup = array_flip($idCompletados);
        //Traer los retos habilitados
        $retos = Retos::habilitadas();


        // Leer filtros desde GET (cuando el user cambie selects)
        //¿Existe la clave habilidad dentro de $_GET? y “Existe habilidad y además no está vacío”
        //Si el usuario selecciona "Todas las habilidades" entonces isset($_GET['habilidad']) es true (porque existe), pero $_GET['habilidad'] !== '' es false (porque está vacío)
        $idHabilidad = isset($_GET['habilidad']) && $_GET['habilidad'] !== '' ? (int)$_GET['habilidad'] : null;
        $dificultad  = isset($_GET['dificultad']) && $_GET['dificultad'] !== '' ? (int)$_GET['dificultad'] : null;

        //Habilidades para el select (solo las que tienen retos habilitados)
        $habilidadesFiltro = HabilidadesBlandas::conRetosHabilitados();

        $iconosHabilidades = [
            'Comunicación Asertiva'      => 'fa-regular fa-message',
            'Gestión del Tiempo'         => 'fa-regular fa-clock',
            'Inteligencia Emocional'     => 'fa-regular fa-heart',
            'Liderazgo'                  => 'fa-regular fa-star',
            'Resolución de Problemas'    => 'fa-solid fa-puzzle-piece',
            'Trabajo en Equipo'          => 'fa-solid fa-people-group',
        ];


        //Retos filtrados (combinable)
        $retos = Retos::filtrar($idHabilidad, $dificultad);

        if (!empty($retos)) {
            foreach ($retos as $reto) {
                //Creamos la llave tags y la llenamos con el array con los tag separados
                //Explode nos permite convertir un string en array separándolo por un carácter específico, en este caso (,)
                //Array_map recorre un array y aplica una función a cada elemento, devolviendo un array nuevo, en este caso
                //se usó trim para limpiar espacios al inicio y al final, es una forma de evitar confictos.
                //Array_filter elimina elementos vacíos o falsos del array, ya que si en la BD hay "comunicación,confianza,"
                //Esto produciría esto: ["comunicación", "confianza", ""], por lo cual se crearía un <p> vacío en la vista.
                $reto->tags = array_filter(array_map('trim', explode(',', (string)$reto->tag)));
                //Traer nombres de habilidades
                $reto->habilidad_nombre = HabilidadesBlandas::find($reto->id_habilidades)->nombre;
                // Icono por defecto
                $reto->icono = 'fa-regular fa-message';

                if (isset($iconosHabilidades[$reto->habilidad_nombre])) {
                    $reto->icono = $iconosHabilidades[$reto->habilidad_nombre];
                }

                // Retos completados por el usuario true/false
                $reto->completado = isset($completadosLookup[(int)$reto->id]);
                //Cambiamos los valores de la llave dificultad dependiendo de su valor para poder mostrarlo en la vista
                if ($reto->dificultad === '1') {
                    $reto->dificultad = 'Básico';
                } elseif ($reto->dificultad === '2') {
                    $reto->dificultad = 'Intermedio';
                } elseif ($reto->dificultad === '3') {
                    $reto->dificultad = 'Avanzado';
                }
            }
        }
        // Render a la vista 
        $router->render('paginas/retos/retos', [
            'titulo' => 'Pon a prueba tus habilidades',
            'login' => $login,
            'retos' => $retos,
            //Header
            'nombreUsuario'    => $datosUsuario['nombreUsuario'],
            'inicialesUsuario' => $datosUsuario['inicialesUsuario'],
            //Filtros
            'habilidadesFiltro' => $habilidadesFiltro,
            'filtroHabilidad'   => $idHabilidad,
            'filtroDificultad'  => $dificultad,
            //Progreso
            'totalRetos' => $totalRetos,
            'completadosTotal' => $completadosTotal,
            'progresoPorHabilidad' => $progresoPorHabilidad,
            'medallas' => $medallas
        ]);
    }
}
