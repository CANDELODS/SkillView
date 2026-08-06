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

    //----------------------------ADMIN----------------------------
    /**
     * Valida y normaliza los datos utilizados al crear o editar
     * una habilidad blanda desde el panel administrativo.
     */
    public function validar(): array
    {
        /*
         * Las alertas son estáticas y pueden conservar mensajes de
         * una validación anterior. Se reinician para que cada ejecución
         * refleje únicamente el estado actual del objeto.
         */
        self::$alertas = [];

        /*
         * Se eliminan espacios externos antes de validar y guardar.
         * Esto evita aceptar campos compuestos únicamente por espacios.
         */
        $this->nombre = trim(
            (string) $this->nombre
        );

        $this->descripcion = trim(
            (string) $this->descripcion
        );

        /*
         * Los tags llegan como una cadena separada por comas.
         * Se eliminan valores vacíos y espacios innecesarios.
         */
        $this->tag = $this->normalizarTags(
            (string) $this->tag
        );

        if ($this->nombre === '') {
            self::setAlerta(
                'error',
                'El nombre es obligatorio'
            );
        }

        if ($this->descripcion === '') {
            self::setAlerta(
                'error',
                'La descripción es obligatoria'
            );
        }

        if ($this->tag === '') {
            self::setAlerta(
                'error',
                'Los tags son obligatorios'
            );
        }

        /*
         * Las únicas opciones permitidas son:
         * 0 = Deshabilitada
         * 1 = Habilitada
         *
         * La conversión a string permite validar tanto valores
         * recibidos desde un formulario como valores cargados de MySQL.
         */
        if (
            !in_array(
                (string) $this->habilitado,
                ['0', '1'],
                true
            )
        ) {
            self::setAlerta(
                'error',
                'El estado seleccionado no es válido'
            );
        }

        return self::$alertas;
    }

    /**
     * Normaliza una cadena de etiquetas separadas por comas.
     *
     * Ejemplo:
     * " Liderazgo, , Comunicación , Empatía "
     * se convierte en:
     * "Liderazgo, Comunicación, Empatía"
     */
    private function normalizarTags(string $tags): string
    {
        $listaTags = array_map(
            'trim',
            explode(',', $tags)
        );

        $listaTags = array_filter(
            $listaTags,
            static fn(string $tag): bool =>
                $tag !== ''
        );

        return implode(
            ', ',
            $listaTags
        );
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
    //----------------------------FIN ADMIN----------------------------

    //----------------------------APRENDIZAJE----------------------------
    public static function habilitadas()
    {
        $query = "SELECT * FROM " . static::$tabla . " 
              WHERE habilitado = 1
              ORDER BY id ASC";
        return self::consultarSQL($query);
    }
    //----------------------------FIN APRENDIZAJE----------------------------

    //----------------------------RETOS----------------------------
    public static function conRetosHabilitados(): array
    {
        $query = "
        SELECT DISTINCT hb.*
        FROM habilidades_blandas hb
        INNER JOIN retos r ON r.id_habilidades = hb.id
        WHERE hb.habilitado = 1
          AND r.habilitado = 1
        ORDER BY hb.nombre ASC
    ";

        return self::consultarSQL($query);
    }
    //----------------------------FIN RETOS----------------------------

    //----------------------------BLOG----------------------------
    public static function conBlogs(): array
    {
        $sql = "SELECT DISTINCT h.id, h.nombre
            FROM habilidades_blandas h
            INNER JOIN blog_habilidades bh ON bh.id_habilidades = h.id
            INNER JOIN blog b ON b.id = bh.id_blog
            WHERE b.habilitado = 1 AND h.habilitado = 1
            ORDER BY h.nombre ASC";

        return self::consultarSQL($sql);
    }

    //----------------------------FIN BLOG----------------------------

}