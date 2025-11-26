<?php

namespace Model;

class HabilidadesBlandas extends ActiveRecord
{
    protected static $tabla = 'habilidades_blandas';
    protected static $columnasDB = ['id', 'nombre', 'descripcion', 'tag', 'habilitado'];

    public $id;
    public $nombre;
    public $descripcion;
    public $tag;
    public $habilitado;


    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->nombre = $args['nombre'] ?? '';
        $this->descripcion = $args['descripcion'] ?? '';
        $this->tag = $args['tag'] ?? '';
        $this->habilitado = $args['habilitado'] ?? null;
    }

        public function validar() {
        if(!$this->nombre) {
            self::$alertas['error'][] = 'El Nombre es Obligatorio';
        }
        if(!$this->descripcion) {
            self::$alertas['error'][] = 'La descripción es Obligatoria';
        }
        if(!$this->tag) {
            self::$alertas['error'][] = 'Los tags son obligatorios';
        }
    
        return self::$alertas;
    }

     // Busca y devuelve las habilidades que coincidan con el término de búsqueda
    public static function buscarHabilidades($termino)
    { //$termino es la cadena a buscar
        // Utilizamos el método buscar de la clase ActiveRecord, enviandole la cadena a buscar y los campos donde buscar
        return static::buscar($termino, ['nombre']);
    }

    // Total de habilidades que coinciden con la búsqueda
    public static function totalBusquedaHabilidades($termino)
    {
        return static::totalBusqueda($termino, ['nombre']);
    }

    // Habilidades paginadas que coinciden con la búsqueda
    public static function paginarBusquedaHabilidades($termino, $porPagina, $offset, $ordenar = 'nombre')
    {
        return static::paginarBusqueda($termino, ['nombre'], $ordenar, $porPagina, $offset);
    }
}