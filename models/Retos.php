<?php

namespace Model;

class Retos extends ActiveRecord{
    protected static $tabla = 'retos';
    protected static $columnasDB = ['id', 'id_habilidades', 'nombre', 'descripcion', 'tag', 'tiempo', 'puntos', 'dificultad', 'habilitado'];

    public $id, $id_habilidades, $nombre, $descripcion, $tag, $tiempo, $puntos, $dificultad, $habilitado;

}