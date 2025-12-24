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


}
