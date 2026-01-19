<?php

namespace Model;

class usuarios_logros extends ActiveRecord
{
    protected static $tabla = 'usuarios_logros';
    protected static $columnasDB = ['id', 'id_usuarios', 'id_logros', 'fecha_obtenido'];

    public $id, $id_usuarios, $id_logros, $fecha_obtenido;

    //----------------------------RETOS----------------------------//
    //Obtenemos los ids logros de un usuario
    public static function idsLogrosUsuario(int $idUsuario): array
    {
        $idUsuario = (int)$idUsuario;

        $query = "
        SELECT id_logros
        FROM " . static::$tabla . "
        WHERE id_usuarios = {$idUsuario}
    ";

        $resultado = self::$db->query($query);

        $ids = [];
        while ($row = $resultado->fetch_assoc()) {
            $ids[] = (int)$row['id_logros'];
        }
        $resultado->free();

        return $ids;
    }

    //----------------------------FIN RETOS----------------------------//

    //---------------------------- PERFIL ----------------------------//
    public static function lookupLogrosUsuario(int $idUsuario): array
    {
        $idUsuario = (int)$idUsuario;

        $sql = "SELECT id_logros, fecha_obtenido
            FROM usuarios_logros
            WHERE id_usuarios = {$idUsuario}";

        $resultado = self::$db->query($sql);

        $lookup = [];
        while ($row = $resultado->fetch_assoc()) {
            $lookup[(int)$row['id_logros']] = $row['fecha_obtenido'];
        }

        $resultado->free();
        return $lookup; // [id_logro => 'YYYY-MM-DD']
    }
    //---------------------------- FIN PERFIL ----------------------------//

}
