<?php

namespace Model;

class Blog extends ActiveRecord{
    protected static $tabla = 'blog';
    protected static $columnasDB = ['id', 'titulo', 'descripcion_corta', 'contenido', 'imagen', 'habilitado'];

    public $id, $titulo, $descripcion_corta, $contenido, $imagen, $habilitado;

}