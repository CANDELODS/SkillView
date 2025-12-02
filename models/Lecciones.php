<?php

namespace Model;

class Lecciones extends ActiveRecord{
    protected static $tabla = 'lecciones';
    protected static $columnasDB = ['id', 'id_habilidades', 'titulo', 'descripcion', 'orden', 'habilitado'];

    public $id, $id_habilidades, $titulo, $descripcion, $orden, $habilitado;

    // ================== APRENDIZAJE ================== //
    // Total de lecciones de una habilidad (solo habilitadas)
    public static function totalPorHabilidad($idHabilidad)
    {
        $idHabilidad = self::$db->escape_string($idHabilidad);

        $query = "SELECT COUNT(*) AS total
                  FROM " . static::$tabla . "
                  WHERE id_habilidades = {$idHabilidad}
                  AND habilitado = 1";

        $resultado = self::$db->query($query);
        $total = $resultado->fetch_array();
        return (int) $total['total'];
    }
    // ================== FIN APRENDIZAJE ================== //
}