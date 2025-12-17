<?php

namespace Model;

class Retos extends ActiveRecord{
    protected static $tabla = 'retos';
    protected static $columnasDB = ['id', 'id_habilidades', 'nombre', 'descripcion', 'tag', 'tiempo_min', 'tiempo_max', 'puntos', 'dificultad', 'habilitado'];

    public $id, $id_habilidades, $nombre, $descripcion, $tag, $tiempo_min, $tiempo_max, $puntos, $dificultad, $habilitado;

    //----------------------------RETOS----------------------------
    //Obtener retos habilitados
    public static function habilitadas() {
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
    //----------------------------FIN RETOS----------------------------
}