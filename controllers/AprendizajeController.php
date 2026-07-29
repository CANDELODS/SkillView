<?php

namespace Controllers;

use Classes\RutaAprendizajeService;
use Classes\PorcentajeActividadService;
use Model\HabilidadesBlandas;
use Model\Lecciones;
use Model\usuarios_lecciones;
use MVC\Router;

class AprendizajeController
{
    public static function index(Router $router)
    {
        // Verificamos si el usuario está autenticado
        if (!isAuth()) {
            header('Location: /');
            exit;
        }

        $login = false;

        $datosUsuario = obtenerDatosUsuarioHeader(
            $_SESSION['id']
        );

        // ID del usuario autenticado
        $idUsuario = $_SESSION['id'] ?? null;

        // --------------------------------------------------
        // 1. OBTENER HABILIDADES HABILITADAS
        // --------------------------------------------------

        $habilidades = HabilidadesBlandas::habilitadas();

        // --------------------------------------------------
        // 2. CALCULAR PROGRESO DE LECCIONES POR HABILIDAD
        // --------------------------------------------------

        foreach ($habilidades as $habilidad) {

            // Total de lecciones habilitadas de la habilidad
            $totalLecciones = Lecciones::totalPorHabilidad(
                $habilidad->id
            );

            // Lecciones completadas por el usuario
            $leccionesCompletadas =
                usuarios_lecciones::totalCompletadasPorHabilidad(
                    $idUsuario,
                    $habilidad->id
                );

            // Agregamos la información al objeto
            $habilidad->total_lecciones =
                (int) $totalLecciones;

            $habilidad->lecciones_completadas =
                (int) $leccionesCompletadas;

            // Evitar división por cero
            $habilidad->porcentaje_progreso =
                PorcentajeActividadService::calcular(
                    (int) $leccionesCompletadas,
                    (int) $totalLecciones
                );
        }

        // --------------------------------------------------
        // 3. DETERMINAR LA SECUENCIALIDAD DEL APRENDIZAJE
        // --------------------------------------------------

        /*
         * Preparamos únicamente los datos que necesita
         * RutaAprendizajeService para determinar los estados.
         */
        $datosRuta = array_map(
            static function ($habilidad): array {
                return [
                    'total_lecciones' =>
                    (int) $habilidad->total_lecciones,

                    'lecciones_completadas' =>
                    (int) $habilidad->lecciones_completadas
                ];
            },
            $habilidades
        );

        /*
         * El servicio devuelve un estado por cada habilidad:
         *
         * completed = habilidad completada
         * current   = primera habilidad pendiente
         * locked    = habilidad posterior o sin lecciones
         */
        $estados =
            RutaAprendizajeService::determinarEstados(
                $datosRuta
            );

        // Asignar cada estado al objeto correspondiente
        foreach ($habilidades as $indice => $habilidad) {
            $habilidad->estado =
                $estados[$indice]
                ?? RutaAprendizajeService::ESTADO_BLOQUEADO;
        }

        // --------------------------------------------------
        // 4. DATOS DEL RESUMEN GENERAL DE PROGRESO
        // --------------------------------------------------

        // Total de lecciones habilitadas del sistema
        $totalLeccionesSistema = Lecciones::total();

        // Total de lecciones completadas por el usuario
        $leccionesCompletadasUsuario =
            usuarios_lecciones::totalCompletadasUsuario(
                $idUsuario
            );

        // --------------------------------------------------
        // 5. ASIGNAR LA LECCIÓN ACTUAL PARA EL MODAL
        // --------------------------------------------------

        foreach ($habilidades as $habilidad) {

            /*
             * Solo buscamos una lección cuando la habilidad
             * tiene contenido y todavía no está completada.
             */
            if (
                $habilidad->estado !==
                RutaAprendizajeService::ESTADO_COMPLETADO
                &&
                $habilidad->total_lecciones > 0
            ) {
                $habilidad->leccion_actual =
                    Lecciones::leccionActualPorUsuarioYHabilidad(
                        $idUsuario,
                        $habilidad->id
                    );
            } else {
                $habilidad->leccion_actual = null;
            }
        }

        // --------------------------------------------------
        // 6. PORCENTAJE GENERAL DE LECCIONES
        // --------------------------------------------------

        $porcentajeProgreso =
            PorcentajeActividadService::calcular(
                (int) $leccionesCompletadasUsuario,
                (int) $totalLeccionesSistema
            );

        // --------------------------------------------------
        // 7. LOGROS RECIENTES
        // --------------------------------------------------

        $logrosRecientes =
            $_SESSION['logros_recientes'] ?? [];

        unset($_SESSION['logros_recientes']);

        // --------------------------------------------------
        // 8. RENDERIZAR LA VISTA
        // --------------------------------------------------

        $router->render(
            'paginas/aprendizaje/aprendizaje',
            [
                'titulo' =>
                'Desarrolla tus habilidades paso a paso',

                'login' =>
                $login,

                'habilidades' =>
                $habilidades,

                'totalLeccionesSistema' =>
                $totalLeccionesSistema,

                'leccionesCompletadasUsuario' =>
                $leccionesCompletadasUsuario,

                'porcentajeProgreso' =>
                $porcentajeProgreso,

                'nombreUsuario' =>
                $datosUsuario['nombreUsuario'],

                'inicialesUsuario' =>
                $datosUsuario['inicialesUsuario'],

                'logrosRecientes' =>
                $logrosRecientes
            ]
        );
    }
}
