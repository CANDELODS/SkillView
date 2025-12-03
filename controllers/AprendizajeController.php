<?php

namespace Controllers;

use Model\HabilidadesBlandas;
use Model\Lecciones;
use Model\Usuario;
use Model\usuarios_lecciones;
use MVC\Router;

class AprendizajeController {

    public static function index(Router $router) {
        // Verificamos si el usuario está autenticado
        if (!isAuth()) {
            header('Location: /');
            exit;
        }

        $login = false;

        // Id del usuario logueado (ajusta según cómo lo guardes en sesión)
        $idUsuario = $_SESSION['id'] ?? null;
        
        // 1) Traer habilidades habilitadas
        $habilidades = HabilidadesBlandas::habilitadas();

        // 2) Para cada habilidad, calcular total lecciones y completadas para este usuario
        foreach ($habilidades as $habilidad) {

            // Total de lecciones de esa habilidad
            $totalLecciones = Lecciones::totalPorHabilidad($habilidad->id);

            // Lecciones completadas por el usuario en esa habilidad
            $leccionesCompletadas = usuarios_lecciones::totalCompletadasPorHabilidad($idUsuario, $habilidad->id);
            
            // Guardamos estos datos directamente en el objeto para que la vista los use
            $habilidad->total_lecciones = $totalLecciones;
            $habilidad->lecciones_completadas = $leccionesCompletadas;
            $habilidad->porcentaje_progreso = $totalLecciones > 0
            ? ($leccionesCompletadas / $totalLecciones) * 100 : 0;
        }

        // 3) Calcular estado de cada habilidad (completed, current, upcoming, locked)
        //    Regla: 
        //      - completed: completadas == total y total > 0
        //      - current: primera habilidad (por orden) donde completadas < total
        //      - upcoming: las siguientes
        //      - locked: si total == 0 (por ahora, casi no usarás esto)

        $indiceActual = null;
        //El indiceActual será la posición del array $habilidades
        //$index => 0, 1, 2, 3... (Posición)
        //$habilidad → objeto HabilidadesBlandas con los campos + los que le añadimos.
        //BUSCAMOS LA PRIMERA HABILIDAD QUE NO ESTÁ COMPLETA (SERÁ LA ACTUAL)
        foreach ($habilidades as $index => $habilidad) {
            //Si la habilidad tiene lecciones y el usuario no las ha completado todas entonces...
            if ($habilidad->total_lecciones > 0 && 
                $habilidad->lecciones_completadas < $habilidad->total_lecciones) {
                //Guardamos la posición en $indiceActual
                $indiceActual = $index;
                //Salimos del ForEach dado que ya tenemos la primera pendiente (En otras palabras la actual)
                break;
            }
        }
        //Asignamos el estado a cada habilidad
        foreach ($habilidades as $index => $habilidad) {

            if ($habilidad->total_lecciones === 0) {
                $habilidad->estado = 'locked';
                //Salta al siguiente ciclo del foreach y no evalúa el resto de condiciones.
                continue;
            }
            //Si el usuario a completado todas las lecciones entonces...
            if ($habilidad->lecciones_completadas >= $habilidad->total_lecciones) {
                $habilidad->estado = 'completed';
            }
            //Nos aseguramos que haya una habilidad actual (Si el usuario ya terminó todas, $indiceActual queda en null).
            //Validamos si la habilidad que estamos recorriendo es justamente la que marcamos como actual en el primer foreach.
            elseif ($indiceActual !== null && $index === $indiceActual) {
                $habilidad->estado = 'current';
            }
            //Todas las que vengan depués de la actual = bloqueadas
            else {
                $habilidad->estado = 'locked';
            }
        }

        // 4) Datos para el resumen general de progreso
        $totalLeccionesSistema = Lecciones::total(); // todas las lecciones habilitadas en el sistema
        $leccionesCompletadasUsuario = usuarios_lecciones::totalCompletadasUsuario($idUsuario);

        // 5) Asignar lección actual para el modal
        foreach ($habilidades as $habilidad) {

        // Solo tiene sentido buscar lección actual si la habilidad NO está completada
        if ($habilidad->estado !== 'completed' && $habilidad->total_lecciones > 0) {
        $habilidad->leccion_actual = Lecciones::leccionActualPorUsuarioYHabilidad($idUsuario, $habilidad->id);
        } else {
        $habilidad->leccion_actual = null;
        }
    }
        // Evitamos división por cero
        $porcentajeProgreso = 0;
        if ($totalLeccionesSistema > 0) {
            $porcentajeProgreso = ($leccionesCompletadasUsuario / $totalLeccionesSistema) * 100;
        }

        // Render a la vista 
        $router->render('paginas/aprendizaje/aprendizaje', [
            'titulo' => 'Desarrolla tus habilidades paso a paso',
            'login' => $login,
            'habilidades' => $habilidades, // aquí viene todo lo de cada card
            'totalLeccionesSistema' => $totalLeccionesSistema,
            'leccionesCompletadasUsuario' => $leccionesCompletadasUsuario,
            'porcentajeProgreso' => $porcentajeProgreso
        ]);
    }
}