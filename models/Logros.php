<?php

namespace Model;

class Logros extends ActiveRecord
{
    protected static $tabla = 'logros';

    protected static $columnasDB = [
        'id',
        'nombre',
        'descripcion',
        'icono',
        'tipo',
        'valor_objetivo',
        'habilitado'
    ];

    public $id;
    public $nombre;
    public $descripcion;
    public $icono;
    public $tipo;
    public $valor_objetivo;
    public $habilitado;

    public function __construct($args = [])
    {
        $this->id =
            $args['id']
            ?? null;

        $this->nombre =
            $args['nombre']
            ?? '';

        $this->descripcion =
            $args['descripcion']
            ?? '';

        $this->icono =
            $args['icono']
            ?? '';

        $this->tipo =
            $args['tipo']
            ?? 1;

        $this->valor_objetivo =
            $args['valor_objetivo']
            ?? 0;

        $this->habilitado =
            $args['habilitado']
            ?? 1;
    }

    //----------------------------RETOS----------------------------//

    /**
     * Obtiene los primeros logros habilitados que se muestran
     * como medallas destacadas en la página de retos.
     */
    public static function destacados(
        int $limite = 6
    ): array {
        $limite =
            max(
                0,
                (int) $limite
            );

        if ($limite === 0) {
            return [];
        }

        $query = "
            SELECT *
            FROM " . static::$tabla . "
            WHERE habilitado = 1
            ORDER BY id ASC
            LIMIT {$limite}
        ";

        return self::consultarSQL(
            $query
        );
    }

    //----------------------------FIN RETOS----------------------------//

    //----------------------------LOGROS----------------------------//

    /**
     * Obtiene todos los logros habilitados.
     */
    public static function habilitados(): array
    {
        $query = "
            SELECT *
            FROM " . static::$tabla . "
            WHERE habilitado = 1
            ORDER BY id ASC
        ";

        return self::consultarSQL(
            $query
        );
    }

    /**
     * Obtiene todos los logros de tipo 1.
     *
     * La comprobación de habilitado se conserva dentro de la lógica
     * de asignación para que un logro deshabilitado nunca pueda
     * registrarse aunque sea recuperado por esta consulta.
     */
    public static function obtenerLogrosTipoHabilidad(): array
    {
        $query = "
            SELECT *
            FROM " . static::$tabla . "
            WHERE tipo = 1
            ORDER BY id ASC
        ";

        return self::consultarSQL(
            $query
        );
    }

    /**
     * Obtiene todos los logros de tipo 4.
     *
     * Los logros de este tipo representan reconocimientos por
     * desempeño en retos.
     */
    public static function obtenerLogrosTipoDesempeno(): array
    {
        $query = "
            SELECT *
            FROM " . static::$tabla . "
            WHERE tipo = 4
            ORDER BY id ASC
        ";

        return self::consultarSQL(
            $query
        );
    }

    /**
     * Convierte el nombre de una habilidad al identificador utilizado
     * por los iconos de los logros.
     */
    public static function slugHabilidad(
        string $nombreHabilidad
    ): ?string {
        $mapa = [
            'Autoconfianza' =>
                'autoconfianza',
            'Manejo del Estrés' =>
                'estres',
            'Inteligencia Emocional' =>
                'inteligencia-emocional',
            'Comunicación Asertiva' =>
                'comunicacion-asertiva',
            'Comunicación No Verbal' =>
                'comunicacion-no-verbal',
            'Empatía y Escucha Activa' =>
                'empatia-y-escucha-activa',
            'Trabajo en Equipo' =>
                'trabajo-en-equipo',
            'Responsabilidad' =>
                'responsabilidad',
            'Adaptabilidad' =>
                'adaptabilidad',
            'Actitud Positiva' =>
                'actitud-positiva',
            'Liderazgo' =>
                'liderazgo'
        ];

        $nombreHabilidad =
            trim(
                $nombreHabilidad
            );

        return $mapa[
            $nombreHabilidad
        ]
        ?? null;
    }

    /**
     * Evalúa y asigna nuevos logros de tipo 1.
     *
     * Un logro de Habilidad se obtiene únicamente cuando:
     *
     * - el logro está habilitado;
     * - la habilidad tiene al menos una lección habilitada;
     * - el usuario completó todas sus lecciones habilitadas;
     * - el icono del logro corresponde con la habilidad;
     * - el usuario todavía no posee ese logro.
     *
     * Los retos completados y el porcentaje consolidado de
     * usuarios_habilidades no intervienen en esta condición.
     *
     * @return array<int, object>
     */
    public static function evaluarYAsignarNuevosPorLeccion(
        int $idUsuario
    ): array {
        $idUsuario =
            (int) $idUsuario;

        if ($idUsuario <= 0) {
            return [];
        }

        // 1) Recuperar los logros configurados para habilidades.
        $logrosTipoHabilidad =
            self::obtenerLogrosTipoHabilidad();

        if (empty($logrosTipoHabilidad)) {
            return [];
        }

        // 2) Recuperar únicamente habilidades actualmente habilitadas.
        $sqlHabilidades = "
            SELECT id, nombre
            FROM habilidades_blandas
            WHERE habilitado = 1
            ORDER BY id ASC
        ";

        $resultadoHabilidades =
            self::$db->query(
                $sqlHabilidades
            );

        if (!$resultadoHabilidades) {
            return [];
        }

        $habilidades = [];

        while (
            $row =
                $resultadoHabilidades->fetch_assoc()
        ) {
            $habilidades[] = $row;
        }

        $resultadoHabilidades->free();

        if (empty($habilidades)) {
            return [];
        }

        $nuevosLogrosIds = [];

        foreach ($habilidades as $habilidad) {
            $idHabilidad =
                (int) (
                    $habilidad['id']
                    ?? 0
                );

            if ($idHabilidad <= 0) {
                continue;
            }

            $nombreHabilidad =
                (string) (
                    $habilidad['nombre']
                    ?? ''
                );

            $slug =
                self::slugHabilidad(
                    $nombreHabilidad
                );

            if ($slug === null) {
                continue;
            }

            // 3) Contar las lecciones habilitadas de la habilidad.
            $sqlTotalLecciones = "
                SELECT COUNT(*) AS total
                FROM lecciones
                WHERE id_habilidades = {$idHabilidad}
                  AND habilitado = 1
            ";

            $resultadoTotal =
                self::$db->query(
                    $sqlTotalLecciones
                );

            if (!$resultadoTotal) {
                continue;
            }

            $rowTotal =
                $resultadoTotal->fetch_assoc();

            $resultadoTotal->free();

            $totalLecciones =
                max(
                    0,
                    (int) (
                        $rowTotal['total']
                        ?? 0
                    )
                );

            /*
             * Una habilidad sin lecciones habilitadas no cumple
             * la condición de logro.
             */
            if ($totalLecciones <= 0) {
                continue;
            }

            // 4) Contar las lecciones completadas por el usuario.
            $sqlCompletadas = "
                SELECT COUNT(*) AS completadas
                FROM usuarios_lecciones ul
                INNER JOIN lecciones l
                    ON l.id = ul.id_lecciones
                WHERE ul.id_usuarios = {$idUsuario}
                  AND ul.completado = 1
                  AND l.id_habilidades = {$idHabilidad}
                  AND l.habilitado = 1
            ";

            $resultadoCompletadas =
                self::$db->query(
                    $sqlCompletadas
                );

            if (!$resultadoCompletadas) {
                continue;
            }

            $rowCompletadas =
                $resultadoCompletadas->fetch_assoc();

            $resultadoCompletadas->free();

            $leccionesCompletadas =
                max(
                    0,
                    (int) (
                        $rowCompletadas['completadas']
                        ?? 0
                    )
                );

            $iconoEsperado =
                'logros/habilidad_'
                . $slug;

            /*
             * 5) Evaluar los logros relacionados con esta habilidad.
             * El registro solo se ejecuta cuando todas las condiciones
             * se cumplen y no existe una asignación previa.
             */
            foreach (
                $logrosTipoHabilidad
                as $logro
            ) {
                if (
                    !self::cumpleLogroHabilidad(
                        $logro,
                        $totalLecciones,
                        $leccionesCompletadas,
                        $iconoEsperado
                    )
                ) {
                    continue;
                }

                if (
                    self::registrarLogroNuevo(
                        $idUsuario,
                        $logro
                    )
                ) {
                    $nuevosLogrosIds[] =
                        (int) $logro->id;
                }
            }
        }

        return self::obtenerNuevosLogrosPorIds(
            $nuevosLogrosIds
        );
    }

    /**
     * Evalúa y asigna nuevos logros de tipo 4.
     *
     * Un logro de Desempeño se obtiene únicamente cuando:
     *
     * - el logro está habilitado;
     * - el reto existe y está habilitado;
     * - el usuario completó el reto;
     * - el logro corresponde con la habilidad del reto;
     * - el puntaje obtenido es igual o superior al valor objetivo;
     * - el logro todavía no fue asignado al usuario.
     *
     * @return array<int, object>
     */
    public static function evaluarYAsignarNuevosPorReto(
        int $idUsuario,
        int $idReto
    ): array {
        $idUsuario =
            (int) $idUsuario;

        $idReto =
            (int) $idReto;

        if (
            $idUsuario <= 0
            || $idReto <= 0
        ) {
            return [];
        }

        /*
         * 1) Obtener el reto junto con su habilidad.
         *
         * No se filtra por habilitado en la consulta porque esa
         * condición se valida explícitamente antes de asignar.
         */
        $sqlReto = "
            SELECT
                r.id,
                r.id_habilidades,
                r.habilitado AS reto_habilitado,
                hb.nombre AS nombre_habilidad
            FROM retos r
            INNER JOIN habilidades_blandas hb
                ON hb.id = r.id_habilidades
            WHERE r.id = {$idReto}
            LIMIT 1
        ";

        $resultadoReto =
            self::$db->query(
                $sqlReto
            );

        if (
            !$resultadoReto
            || $resultadoReto->num_rows === 0
        ) {
            return [];
        }

        $reto =
            $resultadoReto->fetch_assoc();

        $resultadoReto->free();

        $nombreHabilidad =
            (string) (
                $reto['nombre_habilidad']
                ?? ''
            );

        $slug =
            self::slugHabilidad(
                $nombreHabilidad
            );

        if ($slug === null) {
            return [];
        }

        // 2) Obtener el resultado persistido del usuario en ese reto.
        $sqlUsuarioReto = "
            SELECT
                completado,
                puntaje_obtenido
            FROM usuarios_retos
            WHERE id_usuarios = {$idUsuario}
              AND id_retos = {$idReto}
            LIMIT 1
        ";

        $resultadoUsuarioReto =
            self::$db->query(
                $sqlUsuarioReto
            );

        if (
            !$resultadoUsuarioReto
            || $resultadoUsuarioReto->num_rows === 0
        ) {
            return [];
        }

        $usuarioReto =
            $resultadoUsuarioReto->fetch_assoc();

        $resultadoUsuarioReto->free();

        $retoHabilitado =
            (int) (
                $reto['reto_habilitado']
                ?? 0
            ) === 1;

        $retoCompletado =
            (int) (
                $usuarioReto['completado']
                ?? 0
            ) === 1;

        $puntajeObtenido =
            max(
                0.0,
                (float) (
                    $usuarioReto['puntaje_obtenido']
                    ?? 0
                )
            );

        // 3) Recuperar los logros configurados para desempeño.
        $logrosTipoDesempeno =
            self::obtenerLogrosTipoDesempeno();

        if (empty($logrosTipoDesempeno)) {
            return [];
        }

        $nuevosLogrosIds = [];

        $iconoEsperado =
            'logros/desempeno_'
            . $slug;

        foreach (
            $logrosTipoDesempeno
            as $logro
        ) {
            if (
                !self::cumpleLogroDesempeno(
                    $logro,
                    $retoHabilitado,
                    $retoCompletado,
                    $puntajeObtenido,
                    $iconoEsperado
                )
            ) {
                continue;
            }

            if (
                self::registrarLogroNuevo(
                    $idUsuario,
                    $logro
                )
            ) {
                $nuevosLogrosIds[] =
                    (int) $logro->id;
            }
        }

        return self::obtenerNuevosLogrosPorIds(
            $nuevosLogrosIds
        );
    }

    /**
     * Comprueba las condiciones internas de un logro de Habilidad.
     *
     * Este método reemplaza la decisión que durante las pruebas se
     * aisló temporalmente en un servicio, pero mantiene la lógica
     * dentro del modelo existente para no cambiar la arquitectura
     * definitiva del proyecto.
     */
    private static function cumpleLogroHabilidad(
        object $logro,
        int $totalLecciones,
        int $leccionesCompletadas,
        string $iconoEsperado
    ): bool {
        // Un logro deshabilitado nunca puede asignarse.
        if (
            (int) (
                $logro->habilitado
                ?? 0
            ) !== 1
        ) {
            return false;
        }

        // La habilidad debe tener al menos una lección disponible.
        if ($totalLecciones <= 0) {
            return false;
        }

        // Deben estar completadas todas las lecciones habilitadas.
        if (
            $leccionesCompletadas
            < $totalLecciones
        ) {
            return false;
        }

        /*
         * El icono funciona como vínculo lógico entre el logro
         * y la habilidad correspondiente.
         */
        return trim(
            (string) (
                $logro->icono
                ?? ''
            )
        ) === trim(
            $iconoEsperado
        );
    }

    /**
     * Comprueba las condiciones internas de un logro de Desempeño.
     */
    private static function cumpleLogroDesempeno(
        object $logro,
        bool $retoHabilitado,
        bool $retoCompletado,
        float $puntajeObtenido,
        string $iconoEsperado
    ): bool {
        // El logro debe estar habilitado.
        if (
            (int) (
                $logro->habilitado
                ?? 0
            ) !== 1
        ) {
            return false;
        }

        // El reto debe continuar disponible.
        if (!$retoHabilitado) {
            return false;
        }

        // El usuario debe haber completado el reto.
        if (!$retoCompletado) {
            return false;
        }

        // El logro debe corresponder con la habilidad del reto.
        if (
            trim(
                (string) (
                    $logro->icono
                    ?? ''
                )
            ) !== trim(
                $iconoEsperado
            )
        ) {
            return false;
        }

        /*
         * La comparación es mayor o igual.
         * Alcanzar exactamente el valor objetivo sí concede el logro.
         */
        $valorObjetivo =
            max(
                0.0,
                (float) (
                    $logro->valor_objetivo
                    ?? 0
                )
            );

        return $puntajeObtenido
            >= $valorObjetivo;
    }

    /**
     * Registra un logro únicamente cuando todavía no existe
     * la relación usuario-logro.
     *
     * La comprobación previa evita asignaciones duplicadas y el
     * resultado del modelo se conserva: si registrarLogro() falla,
     * este método también devuelve false.
     */
    private static function registrarLogroNuevo(
        int $idUsuario,
        object $logro
    ): bool {
        $idLogro =
            (int) (
                $logro->id
                ?? 0
            );

        if (
            $idUsuario <= 0
            || $idLogro <= 0
        ) {
            return false;
        }

        $yaExiste =
            usuarios_logros::
                existeLogroUsuario(
                    $idUsuario,
                    $idLogro
                );

        if ($yaExiste) {
            return false;
        }

        return (bool)
            usuarios_logros::
                registrarLogro(
                    $idUsuario,
                    $idLogro
                );
    }

    /**
     * Recupera la información completa de los logros asignados
     * durante la evaluación actual.
     *
     * @param array<int, int> $ids
     * @return array<int, object>
     */
    private static function obtenerNuevosLogrosPorIds(
        array $ids
    ): array {
        if (empty($ids)) {
            return [];
        }

        /*
         * Aunque registrarLogroNuevo() ya previene duplicados,
         * array_unique() actúa como una protección adicional antes
         * de consultar la información completa.
         */
        $ids =
            array_values(
                array_unique(
                    array_map(
                        'intval',
                        $ids
                    )
                )
            );

        if (empty($ids)) {
            return [];
        }

        return usuarios_logros::
            obtenerPorIds(
                $ids
            );
    }

    //----------------------------FIN LOGROS----------------------------//
}