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
    /*
     * Rangos utilizados para mostrar el nivel alcanzado en los retos
     * de cada habilidad.
     *
     * Estos límites coinciden con la regla general de SkillView:
     * - 0 a 33: Básico.
     * - 34 a 66: Intermedio.
     * - 67 a 100: Avanzado.
     */
    private const INICIO_NIVEL_INTERMEDIO = 34.0;
    private const INICIO_NIVEL_AVANZADO = 67.0;

    // El porcentaje siempre debe permanecer entre 0 y 100.
    private const PORCENTAJE_MINIMO = 0.0;
    private const PORCENTAJE_MAXIMO = 100.0;

    // Icono utilizado cuando una habilidad no tiene uno específico.
    private const ICONO_PREDETERMINADO =
        'fa-regular fa-message';

    /*
     * Relación entre nombres de habilidades e iconos mostrados
     * en las tarjetas de retos.
     */
    private const ICONOS_HABILIDADES = [
        'Comunicación Asertiva' =>
            'fa-regular fa-message',
        'Gestión del Tiempo' =>
            'fa-regular fa-clock',
        'Inteligencia Emocional' =>
            'fa-regular fa-heart',
        'Liderazgo' =>
            'fa-regular fa-star',
        'Resolución de Problemas' =>
            'fa-solid fa-puzzle-piece',
        'Trabajo en Equipo' =>
            'fa-solid fa-people-group'
    ];

    /**
     * Prepara la información de progreso, filtros, medallas y tarjetas
     * que se muestra en la página principal de retos.
     */
    public static function index(
        Router $router
    ): void {
        // Verificamos si el usuario está autenticado.
        if (!isAuth()) {
            header('Location: /');
            exit;
        }

        /*
         * Todas las consultas de progreso dependen de un identificador
         * válido. Si la sesión está incompleta, se redirige al login.
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

        // -------------------------
        // PROGRESO GENERAL
        // -------------------------
        $totalRetos =
            (int) Retos::totalHabilitados();

        $completadosTotal =
            (int) usuarios_retos::
                totalCompletadosUsuario(
                    $idUsuario
                );

        /*
         * Con estas dos variables la vista puede mostrar,
         * por ejemplo, "3 de 6 retos completados".
         */

        // -------------------------
        // HABILIDADES DISPONIBLES
        // -------------------------
        /*
         * Se realiza una sola consulta y el mismo resultado se utiliza
         * para el progreso, los filtros y el nombre de las habilidades.
         */
        $habilidades =
            HabilidadesBlandas::
                conRetosHabilitados();

        $progresoPorHabilidad =
            self::prepararProgresoPorHabilidad(
                $habilidades,
                $idUsuario
            );

        // -------------------------
        // MEDALLAS
        // -------------------------
        $medallas =
            self::prepararMedallas(
                $idUsuario
            );

        // -------------------------
        // FILTROS
        // -------------------------
        $idHabilidad =
            self::obtenerFiltroHabilidad();

        $dificultad =
            self::obtenerFiltroDificultad();

        /*
         * Las habilidades consultadas anteriormente también alimentan
         * el selector de filtros, evitando repetir la misma consulta.
         */
        $habilidadesFiltro =
            $habilidades;

        // -------------------------
        // RETOS Y TARJETAS
        // -------------------------
        $idsRetosCompletados =
            usuarios_retos::
                idsRetosCompletados(
                    $idUsuario
                );

        /*
         * array_flip convierte:
         * [1, 3, 7]
         * en:
         * [1 => 0, 3 => 1, 7 => 2]
         *
         * Esto permite comprobar cada reto con isset() en tiempo O(1).
         */
        $completadosLookup =
            array_flip(
                $idsRetosCompletados
            );

        $habilidadesLookup =
            self::crearLookupHabilidades(
                $habilidades
            );

        // Los filtros de habilidad y dificultad pueden combinarse.
        $retos =
            Retos::filtrar(
                $idHabilidad,
                $dificultad
            );

        self::prepararTarjetasRetos(
            $retos,
            $completadosLookup,
            $habilidadesLookup
        );

        // Render a la vista.
        $router->render(
            'paginas/retos/retos',
            [
                'titulo' =>
                    'Pon a prueba tus habilidades',
                'login' =>
                    $login,
                'retos' =>
                    $retos,

                // Header.
                'nombreUsuario' =>
                    $datosUsuario['nombreUsuario'],
                'inicialesUsuario' =>
                    $datosUsuario['inicialesUsuario'],

                // Filtros.
                'habilidadesFiltro' =>
                    $habilidadesFiltro,
                'filtroHabilidad' =>
                    $idHabilidad,
                'filtroDificultad' =>
                    $dificultad,

                // Progreso.
                'totalRetos' =>
                    $totalRetos,
                'completadosTotal' =>
                    $completadosTotal,
                'progresoPorHabilidad' =>
                    $progresoPorHabilidad,
                'medallas' =>
                    $medallas
            ]
        );
    }

    /**
     * Construye el progreso de retos para cada habilidad habilitada.
     *
     * Se utilizan lookups para evitar búsquedas repetidas dentro
     * del recorrido de habilidades.
     *
     * @param array<int, object> $habilidades
     * @return array<int, array{
     *     nombre: string,
     *     porcentaje: float,
     *     nivel: string,
     *     completados: int,
     *     total: int
     * }>
     */
    private static function prepararProgresoPorHabilidad(
        array $habilidades,
        int $idUsuario
    ): array {
        /*
         * Solo se reciben filas para habilidades en las que el usuario
         * completó al menos un reto.
         */
        $completadosPorHabilidad =
            usuarios_retos::
                completadosPorHabilidad(
                    $idUsuario
                );

        $lookupCompletados =
            self::crearLookupCompletados(
                $completadosPorHabilidad
            );

        /*
         * Retos::totalesPorHabilidad() obtiene todos los totales en
         * una sola consulta y evita el problema de consultas N+1.
         */
        $totalesPorHabilidad =
            Retos::totalesPorHabilidad();

        $progreso = [];

        foreach ($habilidades as $habilidad) {
            $idHabilidad =
                (int) $habilidad->id;

            $total =
                (int) (
                    $totalesPorHabilidad[
                        $idHabilidad
                    ]
                    ?? 0
                );

            $completados =
                (int) (
                    $lookupCompletados[
                        $idHabilidad
                    ]
                    ?? 0
                );

            $porcentaje =
                self::calcularPorcentajeActividad(
                    $completados,
                    $total
                );

            $progreso[] = [
                'nombre' =>
                    (string) $habilidad->nombre,
                'porcentaje' =>
                    $porcentaje,
                'nivel' =>
                    self::determinarNivel(
                        $porcentaje
                    ),
                'completados' =>
                    $completados,
                'total' =>
                    $total
            ];
        }

        return $progreso;
    }

    /**
     * Convierte las filas de retos completados en un arreglo indexado
     * por el identificador de la habilidad.
     *
     * @param array<int, array<string, mixed>> $filas
     * @return array<int, int>
     */
    private static function crearLookupCompletados(
        array $filas
    ): array {
        $lookup = [];

        foreach ($filas as $fila) {
            $idHabilidad =
                (int) (
                    $fila['id_habilidad']
                    ?? 0
                );

            if ($idHabilidad <= 0) {
                continue;
            }

            $lookup[$idHabilidad] =
                max(
                    0,
                    (int) (
                        $fila['completados']
                        ?? 0
                    )
                );
        }

        return $lookup;
    }

    /**
     * Calcula el porcentaje completado de un conjunto de retos.
     *
     * - Evita divisiones por cero.
     * - No permite cantidades negativas.
     * - Limita el resultado al 100 % ante registros duplicados.
     */
    private static function calcularPorcentajeActividad(
        int $completados,
        int $total
    ): float {
        $completados =
            max(
                0,
                $completados
            );

        $total =
            max(
                0,
                $total
            );

        if ($total === 0) {
            return self::PORCENTAJE_MINIMO;
        }

        $porcentaje =
            ($completados / $total)
            * 100;

        return round(
            min(
                self::PORCENTAJE_MAXIMO,
                $porcentaje
            ),
            2
        );
    }

    /**
     * Asigna el nivel textual de acuerdo con los rangos establecidos
     * para las habilidades blandas en SkillView.
     */
    private static function determinarNivel(
        float $porcentaje
    ): string {
        if (
            $porcentaje >=
            self::INICIO_NIVEL_AVANZADO
        ) {
            return 'Avanzado';
        }

        if (
            $porcentaje >=
            self::INICIO_NIVEL_INTERMEDIO
        ) {
            return 'Intermedio';
        }

        return 'Básico';
    }

    /**
     * Obtiene las medallas destacadas y marca cuáles pertenecen
     * al usuario autenticado.
     *
     * @return array<int, object>
     */
    private static function prepararMedallas(
        int $idUsuario
    ): array {
        $medallas =
            Logros::destacados(6);

        $idsLogrosUsuario =
            usuarios_logros::
                idsLogrosUsuario(
                    $idUsuario
                );

        /*
         * El lookup permite verificar cada logro mediante isset(),
         * evitando recorrer el arreglo completo en cada comparación.
         */
        $logrosLookup =
            array_flip(
                $idsLogrosUsuario
            );

        foreach ($medallas as $medalla) {
            $medalla->desbloqueado =
                isset(
                    $logrosLookup[
                        (int) $medalla->id
                    ]
                );
        }

        return $medallas;
    }

    /**
     * Lee y valida el filtro de habilidad.
     */
    private static function obtenerFiltroHabilidad(): ?int
    {
        if (
            !isset($_GET['habilidad'])
            || $_GET['habilidad'] === ''
        ) {
            return null;
        }

        $idHabilidad =
            (int) $_GET['habilidad'];

        return $idHabilidad > 0
            ? $idHabilidad
            : null;
    }

    /**
     * Lee el filtro de dificultad y acepta únicamente:
     * 1 = Básico, 2 = Intermedio y 3 = Avanzado.
     */
    private static function obtenerFiltroDificultad(): ?int
    {
        if (
            !isset($_GET['dificultad'])
            || $_GET['dificultad'] === ''
        ) {
            return null;
        }

        $dificultad =
            (int) $_GET['dificultad'];

        return in_array(
            $dificultad,
            [1, 2, 3],
            true
        )
            ? $dificultad
            : null;
    }

    /**
     * Construye un lookup con el nombre de cada habilidad.
     *
     * Esto evita ejecutar HabilidadesBlandas::find() para cada reto,
     * eliminando una consulta repetida de tipo N+1.
     *
     * @param array<int, object> $habilidades
     * @return array<int, string>
     */
    private static function crearLookupHabilidades(
        array $habilidades
    ): array {
        $lookup = [];

        foreach ($habilidades as $habilidad) {
            $idHabilidad =
                (int) $habilidad->id;

            if ($idHabilidad <= 0) {
                continue;
            }

            $lookup[$idHabilidad] =
                (string) $habilidad->nombre;
        }

        return $lookup;
    }

    /**
     * Agrega a cada reto los datos requeridos por su tarjeta:
     * etiquetas, habilidad, icono, estado y dificultad.
     *
     * @param array<int, object> $retos
     * @param array<int, int> $completadosLookup
     * @param array<int, string> $habilidadesLookup
     */
    private static function prepararTarjetasRetos(
        array $retos,
        array $completadosLookup,
        array $habilidadesLookup
    ): void {
        foreach ($retos as $reto) {
            /*
             * Las etiquetas se almacenan como una cadena separada
             * por comas. Se limpian espacios y elementos vacíos.
             *
             * Ejemplo:
             * "comunicación, confianza,"
             * se convierte en:
             * ["comunicación", "confianza"].
             */
            $reto->tags =
                self::normalizarTags(
                    (string) $reto->tag
                );

            $idHabilidad =
                (int) $reto->id_habilidades;

            $reto->habilidad_nombre =
                $habilidadesLookup[
                    $idHabilidad
                ]
                ?? '';

            $reto->icono =
                self::obtenerIconoHabilidad(
                    $reto->habilidad_nombre
                );

            // Indica si el usuario ya completó el reto.
            $reto->completado =
                isset(
                    $completadosLookup[
                        (int) $reto->id
                    ]
                );

            // Convierte el valor numérico a la etiqueta visible.
            $reto->dificultad =
                self::obtenerNombreDificultad(
                    $reto->dificultad
                );
        }
    }

    /**
     * Convierte una cadena de etiquetas en un arreglo limpio.
     *
     * @return array<int, string>
     */
    private static function normalizarTags(
        string $tags
    ): array {
        $lista =
            array_map(
                'trim',
                explode(',', $tags)
            );

        return array_values(
            array_filter(
                $lista,
                static fn(string $tag): bool =>
                    $tag !== ''
            )
        );
    }

    /**
     * Devuelve el icono configurado para la habilidad o el icono
     * predeterminado cuando no existe una relación específica.
     */
    private static function obtenerIconoHabilidad(
        string $nombreHabilidad
    ): string {
        return self::ICONOS_HABILIDADES[
            $nombreHabilidad
        ]
        ?? self::ICONO_PREDETERMINADO;
    }

    /**
     * Convierte la dificultad almacenada en su nombre visible.
     */
    private static function obtenerNombreDificultad(
        mixed $dificultad
    ): string {
        return match ((int) $dificultad) {
            1 => 'Básico',
            2 => 'Intermedio',
            3 => 'Avanzado',
            default => 'No definida'
        };
    }
}