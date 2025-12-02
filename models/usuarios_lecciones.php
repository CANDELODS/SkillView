<?php

namespace Model;

class usuarios_lecciones extends ActiveRecord{
    protected static $tabla = 'usuarios_lecciones';
    protected static $columnasDB = ['id', 'id_usuarios', 'id_lecciones', 'completado', 'fecha_completado'];

    public $id, $id_usuarios, $id_lecciones, $completado, $fecha_completado;

    // ================== APRENDIZAJE ================== //

    // Total de lecciones completadas por el usuario (para el resumen general)
    public static function totalCompletadasUsuario($idUsuario)
    {
        $idUsuario = self::$db->escape_string($idUsuario);

        $query = "SELECT COUNT(*) AS total
                  FROM " . static::$tabla . "
                  WHERE id_usuarios = {$idUsuario}
                  AND completado = 1";

        $resultado = self::$db->query($query);
        $total = $resultado->fetch_array();
        return (int) $total['total'];
    }

    // Total de lecciones completadas por usuario en UNA habilidad concreta
    public static function totalCompletadasPorHabilidad($idUsuario, $idHabilidad)
    {
        $idUsuario  = self::$db->escape_string($idUsuario);
        $idHabilidad = self::$db->escape_string($idHabilidad);

        $query = "SELECT COUNT(*) AS total
                  FROM usuarios_lecciones ul
                  INNER JOIN lecciones l ON ul.id_lecciones = l.id
                  WHERE ul.id_usuarios = {$idUsuario}
                  AND ul.completado = 1
                  AND l.id_habilidades = {$idHabilidad}";

        $resultado = self::$db->query($query);
        $total = $resultado->fetch_array();
        return (int) $total['total'];
    }
    // ================== FIN APRENDIZAJE ================== //
}