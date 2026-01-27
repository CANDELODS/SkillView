<?php

namespace Model;

class Logros extends ActiveRecord
{
    protected static $tabla = 'logros';
    protected static $columnasDB = ['id', 'nombre', 'descripcion', 'icono', 'tipo', 'valor_objetivo', 'habilitado'];

    public $id, $nombre, $descripcion, $icono, $tipo, $valor_objetivo, $habilitado;

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
    //----------------------------FIN LOGROS----------------------------//

}
