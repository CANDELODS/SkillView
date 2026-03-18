<?php

namespace Model;

class Logros extends ActiveRecord
{
    protected static $tabla = 'logros';
    protected static $columnasDB = ['id', 'nombre', 'descripcion', 'icono', 'tipo', 'valor_objetivo', 'habilitado'];

    public $id, $nombre, $descripcion, $icono, $tipo, $valor_objetivo, $habilitado;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->nombre = $args['nombre'] ?? '';
        $this->descripcion = $args['descripcion'] ?? '';
        $this->icono = $args['icono'] ?? '';
        $this->tipo = $args['tipo'] ?? 1;
        $this->valor_objetivo = $args['valor_objetivo'] ?? 0;
        $this->habilitado = $args['habilitado'] ?? 1;
    }

    //----------------------------RETOS----------------------------//
    //Obtenemos los retos destacados (los primeros N retos habilitados)
    public static function destacados(int $limite = 6): array
    {
        $limite = (int)$limite;
        $query = "
        SELECT *
        FROM " . static::$tabla . "
        WHERE habilitado = 1
        ORDER BY id ASC
        LIMIT {$limite}
    ";
        return self::consultarSQL($query);
    }

    //----------------------------FIN RETOS----------------------------//

    //----------------------------LOGROS----------------------------//
    //Obtener todos los logros habilitados
    public static function habilitados(): array
    {
        $query = "
        SELECT *
        FROM " . static::$tabla . "
        WHERE habilitado = 1
        ORDER BY id ASC
    ";
        return self::consultarSQL($query);
    }

    /**
     * Obtiene logros habilitados de tipo 1 (habilidad completada)
     */
    public static function obtenerLogrosTipoHabilidad(): array
    {
        $sql = "SELECT * 
                FROM " . static::$tabla . " 
                WHERE habilitado = 1
                  AND tipo = 1
                ORDER BY id ASC";

        return self::consultarSQL($sql);
    }

    /**
     * Mapa fijo entre nombre de habilidad y slug usado en iconos/logros
     */
    public static function slugHabilidad(string $nombreHabilidad): ?string
    {
        $mapa = [
            'Autoconfianza' => 'autoconfianza',
            'Manejo del Estrés' => 'estres',
            'Inteligencia Emocional' => 'inteligencia-emocional',
            'Comunicación Asertiva' => 'comunicacion-asertiva',
            'Comunicación No Verbal' => 'comunicacion-no-verbal',
            'Empatía y Escucha Activa' => 'empatia-y-escucha-activa',
            'Trabajo en Equipo' => 'trabajo-en-equipo',
            'Responsabilidad' => 'responsabilidad',
            'Adaptabilidad' => 'adaptabilidad',
            'Actitud Positiva' => 'actitud-positiva',
            'Liderazgo' => 'liderazgo'
        ];

        return $mapa[$nombreHabilidad] ?? null;
    }

    /**
     * Evalúa logros nuevos tipo 1 (habilidad completada) para un usuario
     * y los registra en usuarios_logros si aún no existen.
     *
     * Devuelve un arreglo con los logros recién obtenidos.
     */
    public static function evaluarYAsignarNuevosPorLeccion(int $idUsuario): array
    {
        $idUsuario = (int)$idUsuario;

        if ($idUsuario <= 0) {
            return [];
        }

        // 1. Traer logros habilitados de tipo 1 (Habilidad)
        $logrosTipoHabilidad = self::obtenerLogrosTipoHabilidad();

        if (empty($logrosTipoHabilidad)) {
            return [];
        }

        // 2. Traer todas las habilidades habilitadas
        $sqlHabilidades = "SELECT id, nombre
                       FROM habilidades_blandas
                       WHERE habilitado = 1";

        $resultadoHabilidades = self::$db->query($sqlHabilidades);

        if (!$resultadoHabilidades) {
            return [];
        }

        $habilidades = [];
        while ($row = $resultadoHabilidades->fetch_assoc()) {
            $habilidades[] = $row;
        }
        $resultadoHabilidades->free();

        if (empty($habilidades)) {
            return [];
        }

        $nuevosLogrosIds = [];

        foreach ($habilidades as $habilidad) {
            $idHabilidad = (int)$habilidad['id'];
            $nombreHabilidad = $habilidad['nombre'] ?? '';

            $slug = self::slugHabilidad($nombreHabilidad);

            if (!$slug) {
                continue;
            }

            // 3. Contar total de lecciones habilitadas de esa habilidad
            $sqlTotalLecciones = "SELECT COUNT(*) AS total
                              FROM lecciones
                              WHERE id_habilidades = {$idHabilidad}
                                AND habilitado = 1";

            $resultadoTotal = self::$db->query($sqlTotalLecciones);

            if (!$resultadoTotal) {
                continue;
            }

            $rowTotal = $resultadoTotal->fetch_assoc();
            $resultadoTotal->free();

            $totalLecciones = (int)($rowTotal['total'] ?? 0);

            if ($totalLecciones <= 0) {
                continue;
            }

            // 4. Contar cuántas lecciones completó el usuario en esa habilidad
            $sqlCompletadas = "SELECT COUNT(*) AS completadas
                           FROM usuarios_lecciones ul
                           INNER JOIN lecciones l ON l.id = ul.id_lecciones
                           WHERE ul.id_usuarios = {$idUsuario}
                             AND ul.completado = 1
                             AND l.id_habilidades = {$idHabilidad}
                             AND l.habilitado = 1";

            $resultadoCompletadas = self::$db->query($sqlCompletadas);

            if (!$resultadoCompletadas) {
                continue;
            }

            $rowCompletadas = $resultadoCompletadas->fetch_assoc();
            $resultadoCompletadas->free();

            $leccionesCompletadas = (int)($rowCompletadas['completadas'] ?? 0);

            // 5. Si completó todas las lecciones de la habilidad, revisar el logro correspondiente
            if ($leccionesCompletadas >= $totalLecciones) {
                $iconoEsperado = 'logros/habilidad_' . $slug;

                foreach ($logrosTipoHabilidad as $logro) {
                    if ($logro->icono === $iconoEsperado) {
                        $yaExiste = usuarios_logros::existeLogroUsuario($idUsuario, (int)$logro->id);

                        if (!$yaExiste) {
                            $registrado = usuarios_logros::registrarLogro($idUsuario, (int)$logro->id);

                            if ($registrado) {
                                $nuevosLogrosIds[] = (int)$logro->id;
                            }
                        }
                    }
                }
            }
        }

        // 6. Devolver la información completa de los logros recién asignados
        if (empty($nuevosLogrosIds)) {
            return [];
        }

        return usuarios_logros::obtenerPorIds($nuevosLogrosIds);
    }

    /**
     * Evalúa logros nuevos tipo 4 (habilidad completada) para un usuario
     * y los registra en usuarios_logros si aún no existen.
     *
     * Devuelve un arreglo con los logros recién obtenidos.
     */
    public static function evaluarYAsignarNuevosPorReto(int $idUsuario, int $idReto): array
    {
        $idUsuario = (int)$idUsuario;
        $idReto = (int)$idReto;

        if ($idUsuario <= 0 || $idReto <= 0) {
            return [];
        }

        // 1. Obtener información del reto y su habilidad
        $sqlReto = "SELECT r.id, r.id_habilidades, hb.nombre AS nombre_habilidad
                FROM retos r
                INNER JOIN habilidades_blandas hb ON hb.id = r.id_habilidades
                WHERE r.id = {$idReto}
                  AND r.habilitado = 1
                LIMIT 1";

        $resultadoReto = self::$db->query($sqlReto);

        if (!$resultadoReto || $resultadoReto->num_rows === 0) {
            return [];
        }

        $reto = $resultadoReto->fetch_assoc();
        $resultadoReto->free();

        $nombreHabilidad = $reto['nombre_habilidad'] ?? '';
        $slug = self::slugHabilidad($nombreHabilidad);

        if (!$slug) {
            return [];
        }

        // 2. Obtener resultado del usuario en ese reto
        $sqlUsuarioReto = "SELECT completado, puntaje_obtenido
                       FROM usuarios_retos
                       WHERE id_usuarios = {$idUsuario}
                         AND id_retos = {$idReto}
                       LIMIT 1";

        $resultadoUsuarioReto = self::$db->query($sqlUsuarioReto);

        if (!$resultadoUsuarioReto || $resultadoUsuarioReto->num_rows === 0) {
            return [];
        }

        $usuarioReto = $resultadoUsuarioReto->fetch_assoc();
        $resultadoUsuarioReto->free();

        $completado = (int)($usuarioReto['completado'] ?? 0);
        $puntajeObtenido = (float)($usuarioReto['puntaje_obtenido'] ?? 0);

        if ($completado !== 1) {
            return [];
        }

        // 3. Buscar logros tipo 4 (Desempeño)
        $sqlLogros = "SELECT *
                  FROM " . static::$tabla . "
                  WHERE habilitado = 1
                    AND tipo = 4
                  ORDER BY id ASC";

        $logrosTipoDesempeno = self::consultarSQL($sqlLogros);

        if (empty($logrosTipoDesempeno)) {
            return [];
        }

        $nuevosLogrosIds = [];
        $iconoEsperado = 'logros/desempeno_' . $slug;

        foreach ($logrosTipoDesempeno as $logro) {
            if (
                $logro->icono === $iconoEsperado &&
                $puntajeObtenido >= (float)$logro->valor_objetivo
            ) {
                $yaExiste = usuarios_logros::existeLogroUsuario($idUsuario, (int)$logro->id);

                if (!$yaExiste) {
                    $registrado = usuarios_logros::registrarLogro($idUsuario, (int)$logro->id);

                    if ($registrado) {
                        $nuevosLogrosIds[] = (int)$logro->id;
                    }
                }
            }
        }

        if (empty($nuevosLogrosIds)) {
            return [];
        }

        return usuarios_logros::obtenerPorIds($nuevosLogrosIds);
    }
    //----------------------------FIN LOGROS----------------------------//

}
