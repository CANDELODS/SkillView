<?php

namespace Controllers;

use Model\HabilidadesBlandas;
use Model\Lecciones;
use Model\usuarios_lecciones;
use MVC\Router;

class AprendizajeController
{
    /*
     * El progreso mostrado por el sistema siempre debe mantenerse
     * dentro de este rango.
     */
    private const PROGRESO_MINIMO = 0.0;
    private const PROGRESO_MAXIMO = 100.0;

    /**
     * Prepara la ruta de aprendizaje y renderiza la página principal
     * del módulo.
     */
    public static function index(Router $router): void
    {
        // Verificamos si el usuario está autenticado.
        if (!isAuth()) {
            header('Location: /');
            exit;
        }

        /*
         * Aunque isAuth() confirma la sesión, también verificamos
         * explícitamente el identificador porque todas las consultas
         * de progreso dependen de este valor.
         */
        $idUsuario = (int) (
            $_SESSION['id']
            ?? 0
        );

        if ($idUsuario <= 0) {
            header('Location: /');
            exit;
        }

        $login = false;

        $datosUsuario =
            obtenerDatosUsuarioHeader(
                $idUsuario
            );

        // 1) Traer las habilidades habilitadas en el orden del sistema.
        $habilidades =
            HabilidadesBlandas::habilitadas();

        /*
         * 2) Agregar a cada habilidad los datos necesarios para
         * construir sus tarjetas en la vista.
         */
        self::prepararProgresoHabilidades(
            $habilidades,
            $idUsuario
        );

        /*
         * 3) Organizar la ruta secuencial:
         *
         * - completed: habilidad anterior ya terminada;
         * - current: primera habilidad con lecciones pendientes;
         * - locked: habilidad posterior o sin lecciones disponibles.
         *
         * Solo puede existir una habilidad current. Si todas las
         * habilidades fueron completadas, ninguna queda activa.
         */
        self::asignarEstadosSecuenciales(
            $habilidades
        );

        /*
         * 4) La lección actual solo se consulta para la habilidad
         * habilitada como current. Las habilidades completadas o
         * bloqueadas no deben exponer una lección para iniciar.
         */
        self::asignarLeccionActual(
            $habilidades,
            $idUsuario
        );

        // 5) Datos utilizados por el resumen general de aprendizaje.
        $totalLeccionesSistema =
            (int) Lecciones::total();

        $leccionesCompletadasUsuario =
            (int) usuarios_lecciones::
                totalCompletadasUsuario(
                    $idUsuario
                );

        $porcentajeProgreso =
            self::calcularPorcentajeActividad(
                $leccionesCompletadasUsuario,
                $totalLeccionesSistema
            );

        /*
         * Los logros asignados al completar una lección se guardan
         * temporalmente en sesión para mostrarlos una sola vez.
         */
        $logrosRecientes =
            $_SESSION['logros_recientes']
            ?? [];

        unset(
            $_SESSION['logros_recientes']
        );

        // Render a la vista.
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

    /**
     * Calcula el total, las actividades completadas y el porcentaje
     * de cada habilidad.
     *
     * Los resultados se agregan al objeto porque la vista ya utiliza
     * estas propiedades para construir las tarjetas.
     *
     * @param array<int, object> $habilidades
     */
    private static function prepararProgresoHabilidades(
        array $habilidades,
        int $idUsuario
    ): void {
        foreach ($habilidades as $habilidad) {
            $idHabilidad =
                (int) $habilidad->id;

            // Total de lecciones habilitadas de la habilidad.
            $totalLecciones =
                (int) Lecciones::
                    totalPorHabilidad(
                        $idHabilidad
                    );

            /*
             * Cantidad de lecciones que el usuario ya completó
             * dentro de la misma habilidad.
             */
            $leccionesCompletadas =
                (int) usuarios_lecciones::
                    totalCompletadasPorHabilidad(
                        $idUsuario,
                        $idHabilidad
                    );

            $habilidad->total_lecciones =
                $totalLecciones;

            $habilidad->lecciones_completadas =
                $leccionesCompletadas;

            $habilidad->porcentaje_progreso =
                self::calcularPorcentajeActividad(
                    $leccionesCompletadas,
                    $totalLecciones
                );
        }
    }

    /**
     * Asigna los estados de la ruta respetando el orden recibido.
     *
     * Una habilidad posterior permanece bloqueada incluso si existen
     * registros inconsistentes que la muestran como completada antes
     * de finalizar la habilidad actual.
     *
     * @param array<int, object> $habilidades
     */
    private static function asignarEstadosSecuenciales(
        array $habilidades
    ): void {
        $indiceActual = null;

        /*
         * La primera habilidad con lecciones pendientes será la única
         * habilidad activa de la ruta.
         */
        foreach (
            $habilidades
            as $indice => $habilidad
        ) {
            $totalLecciones =
                (int) $habilidad->total_lecciones;

            $leccionesCompletadas =
                (int) $habilidad->
                    lecciones_completadas;

            if (
                $totalLecciones > 0
                && $leccionesCompletadas
                    < $totalLecciones
            ) {
                $indiceActual = $indice;
                break;
            }
        }

        foreach (
            $habilidades
            as $indice => $habilidad
        ) {
            $totalLecciones =
                (int) $habilidad->total_lecciones;

            /*
             * Una habilidad sin lecciones habilitadas no puede
             * iniciarse y permanece bloqueada.
             */
            if ($totalLecciones <= 0) {
                $habilidad->estado = 'locked';
                continue;
            }

            /*
             * Cuando no hay una habilidad pendiente significa que
             * el usuario completó todas las habilidades disponibles.
             */
            if ($indiceActual === null) {
                $habilidad->estado = 'completed';
                continue;
            }

            // Las habilidades anteriores a la actual están completas.
            if ($indice < $indiceActual) {
                $habilidad->estado = 'completed';
                continue;
            }

            // Solo la primera habilidad pendiente queda habilitada.
            if ($indice === $indiceActual) {
                $habilidad->estado = 'current';
                continue;
            }

            // Todas las habilidades posteriores permanecen bloqueadas.
            $habilidad->estado = 'locked';
        }
    }

    /**
     * Obtiene la siguiente lección únicamente para la habilidad actual.
     *
     * @param array<int, object> $habilidades
     */
    private static function asignarLeccionActual(
        array $habilidades,
        int $idUsuario
    ): void {
        foreach ($habilidades as $habilidad) {
            if ($habilidad->estado !== 'current') {
                $habilidad->leccion_actual = null;
                continue;
            }

            $habilidad->leccion_actual =
                Lecciones::
                    leccionActualPorUsuarioYHabilidad(
                        $idUsuario,
                        (int) $habilidad->id
                    );
        }
    }

    /**
     * Calcula el porcentaje completado de un grupo de actividades.
     *
     * - Evita divisiones por cero.
     * - No permite cantidades negativas.
     * - Limita el resultado al 100 % ante registros duplicados.
     */
    private static function calcularPorcentajeActividad(
        int $completadas,
        int $total
    ): float {
        $completadas =
            max(
                0,
                $completadas
            );

        $total =
            max(
                0,
                $total
            );

        if ($total === 0) {
            return self::PROGRESO_MINIMO;
        }

        $porcentaje =
            ($completadas / $total)
            * 100;

        return round(
            min(
                self::PROGRESO_MAXIMO,
                $porcentaje
            ),
            2
        );
    }
}