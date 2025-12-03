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

    //Obtener la lección actual dependiendo del usuario y la habilidad
    public static function leccionActualPorUsuarioYHabilidad($idUsuario, $idHabilidad)
    {
    $idUsuario   = self::$db->escape_string($idUsuario);
    $idHabilidad = self::$db->escape_string($idHabilidad);

    // Buscamos la PRIMERA lección de esa habilidad que el usuario NO tenga completada
    $query = "SELECT l.*
              FROM lecciones l
              LEFT JOIN usuarios_lecciones ul
                ON ul.id_lecciones = l.id
               AND ul.id_usuarios = {$idUsuario}
              WHERE l.id_habilidades = {$idHabilidad}
                AND l.habilitado = 1
                AND (ul.completado IS NULL OR ul.completado = 0)
              ORDER BY l.orden ASC
              LIMIT 1";

    $resultado = self::consultarSQL($query);
    return array_shift($resultado); // puede ser null si ya terminó todas
    }

    // ================== FIN APRENDIZAJE ================== //
}