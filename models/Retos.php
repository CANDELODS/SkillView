<?php

namespace Model;

class Retos extends ActiveRecord
{
    protected static $tabla = 'retos';
    protected static $columnasDB = ['id', 'id_habilidades', 'nombre', 'descripcion', 'tag', 'tiempo_min', 'tiempo_max', 'puntos', 'dificultad', 'habilitado'];

    public $id, $id_habilidades, $nombre, $descripcion, $tag, $tiempo_min, $tiempo_max, $puntos, $dificultad, $habilitado;

    //----------------------------RETOS----------------------------
    //Obtener retos habilitados
    public static function habilitadas()
    {
        $query = "SELECT * FROM " . static::$tabla . " 
              WHERE habilitado = 1
              ORDER BY id ASC";
        return self::consultarSQL($query);
    }

    //Filtrar retos dependiendo de la habilidad y el nivel
    public static function filtrar(?int $idHabilidad, ?int $dificultad): array
    {
        $condiciones = ["habilitado = 1"];

        if ($idHabilidad) {
            $condiciones[] = "id_habilidades = " . (int)$idHabilidad;
        }

        if ($dificultad) {
            $condiciones[] = "dificultad = " . (int)$dificultad;
        }

        $where = implode(" AND ", $condiciones);

        $query = "
        SELECT *
        FROM " . static::$tabla . "
        WHERE $where
        ORDER BY id ASC
    ";

        return self::consultarSQL($query);
    }
    //Obtenemos el total de retos habilitados
    public static function totalHabilitados(): int
    {
        $query = "SELECT COUNT(*) FROM " . static::$tabla . " WHERE habilitado = 1";
        $resultado = self::$db->query($query);
        $total = $resultado->fetch_array();
        return (int) array_shift($total);
    }
    //Obtenemos el total de retos habilitados por habilidad
    public static function totalHabilitadosPorHabilidad(int $idHabilidad): int
    {
        $idHabilidad = (int)$idHabilidad;
        $query = "
        SELECT COUNT(*)
        FROM " . static::$tabla . "
        WHERE habilitado = 1
          AND id_habilidades = {$idHabilidad}
    ";
        $resultado = self::$db->query($query);
        $total = $resultado->fetch_array();
        return (int) array_shift($total);
    }

    // Obtener totales de retos habilitados agrupados por habilidad
    public static function totalesPorHabilidad(): array
    {
        $query = "
        SELECT id_habilidades, COUNT(*) AS total
        FROM " . static::$tabla . "
        WHERE habilitado = 1
        GROUP BY id_habilidades
    ";

        $resultado = self::$db->query($query);

        $data = [];
        while ($row = $resultado->fetch_assoc()) {
            $data[(int)$row['id_habilidades']] = (int)$row['total'];
        }
        $resultado->free();

        return $data; // [id_habilidad => total]
    }
    //----------------------------FIN RETOS----------------------------
}
