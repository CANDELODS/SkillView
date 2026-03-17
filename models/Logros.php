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

        // 1. Traer logros habilitados de tipo 1 (Habilidad completada)
        $logrosTipoHabilidad = self::obtenerLogrosTipoHabilidad();

        if (empty($logrosTipoHabilidad)) {
            return [];
        }

        // 2. Traer progreso real del usuario por habilidad
        // OJO: aquí NO usamos consultarSQL porque esta consulta no devuelve objetos Logros
        $sql = "SELECT uh.id_habilidades, uh.progreso, hb.nombre
            FROM usuarios_habilidades uh
            INNER JOIN habilidades_blandas hb ON hb.id = uh.id_habilidades
            WHERE uh.id_usuarios = {$idUsuario}
              AND hb.habilitado = 1";

        $resultado = self::$db->query($sql);

        if (!$resultado) {
            return [];
        }

        $progresos = [];
        while ($row = $resultado->fetch_assoc()) {
            $progresos[] = $row;
        }
        $resultado->free();

        if (empty($progresos)) {
            return [];
        }

        $nuevosLogrosIds = [];

        // 3. Verificar qué habilidades ya cumplen el objetivo
        foreach ($progresos as $fila) {
            $nombreHabilidad = $fila['nombre'] ?? '';
            $progreso = (float)($fila['progreso'] ?? 0);

            $slug = self::slugHabilidad($nombreHabilidad);

            if (!$slug) {
                continue;
            }

            // IMPORTANTE: en tu BD el icono está guardado SIN .svg
            $iconoEsperado = 'logros/habilidad_' . $slug;

            foreach ($logrosTipoHabilidad as $logro) {
                if (
                    $logro->icono === $iconoEsperado &&
                    $progreso >= (float)$logro->valor_objetivo
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
        }

        // 4. Devolver la información completa de los logros recién asignados
        if (empty($nuevosLogrosIds)) {
            return [];
        }

        return usuarios_logros::obtenerPorIds($nuevosLogrosIds);
    }
    //----------------------------FIN LOGROS----------------------------//

}
