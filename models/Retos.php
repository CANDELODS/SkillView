<?php

namespace Model;

class Retos extends ActiveRecord{
    protected static $tabla = 'retos';
    protected static $columnasDB = ['id', 'id_habilidades', 'nombre', 'descripcion', 'tag', 'tiempo_min', 'tiempo_max', 'puntos', 'dificultad', 'habilitado'];

    public $id, $id_habilidades, $nombre, $descripcion, $tag, $tiempo_min, $tiempo_max, $puntos, $dificultad, $habilitado;

    //----------------------------APRENDIZAJE----------------------------
    public static function habilitadas() {
    $query = "SELECT * FROM " . static::$tabla . " 
              WHERE habilitado = 1
              ORDER BY id ASC";
    return self::consultarSQL($query);
    }
    //----------------------------FIN APRENDIZAJE----------------------------
}