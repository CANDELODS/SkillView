<?php

namespace Model;

class Retos extends ActiveRecord{
    protected static $tabla = 'retos';
    protected static $columnasDB = ['id', 'id_habilidades', 'nombre', 'descripcion', 'tag', 'tiempo_min', 'tiempo_max', 'puntos', 'dificultad', 'habilitado'];

    public $id, $id_habilidades, $nombre, $descripcion, $tag, $tiempo_min, $tiempo_max, $puntos, $dificultad, $habilitado;

}