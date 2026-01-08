<?php

namespace Model;

class blog_habilidades extends ActiveRecord{
    protected static $tabla = 'blog_habilidades';
    protected static $columnasDB = ['id', 'id_blog', 'id_habilidades'];

    public $id, $id_blog, $id_habilidades;

}