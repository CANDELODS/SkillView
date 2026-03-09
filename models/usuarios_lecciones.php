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

    // ================== LECCIONES ================== //
    public static function marcarComoCompletada(int $idUsuario, int $idLeccion): bool
    {
        $idUsuario = (int)$idUsuario;
        $idLeccion = (int)$idLeccion;

        $checkQuery = "SELECT id 
                       FROM " . static::$tabla . " 
                       WHERE id_usuarios = {$idUsuario}
                         AND id_lecciones = {$idLeccion}
                       LIMIT 1";

        $resultado = self::$db->query($checkQuery);
        $existe = $resultado ? $resultado->fetch_assoc() : null;

        $fecha = date('Y-m-d H:i:s');

        if ($existe) {
            $id = (int)$existe['id'];

            $updateQuery = "UPDATE " . static::$tabla . "
                            SET completado = 1,
                                fecha_completado = '{$fecha}'
                            WHERE id = {$id}
                            LIMIT 1";

            return (bool) self::$db->query($updateQuery);
        }

        $insertQuery = "INSERT INTO " . static::$tabla . " 
                        (id_usuarios, id_lecciones, completado, fecha_completado)
                        VALUES ({$idUsuario}, {$idLeccion}, 1, '{$fecha}')";

        return (bool) self::$db->query($insertQuery);
    }
    // ================== FIN LECCIONES ================== //
}