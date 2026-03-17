<?php

namespace Model;

class usuarios_logros extends ActiveRecord
{
    protected static $tabla = 'usuarios_logros';
    protected static $columnasDB = ['id', 'id_usuarios', 'id_logros', 'fecha_obtenido'];

    public $id, $id_usuarios, $id_logros, $fecha_obtenido;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->id_usuarios = $args['id_usuarios'] ?? '';
        $this->id_logros = $args['id_logros'] ?? '';
        $this->fecha_obtenido = $args['fecha_obtenido'] ?? date('Y-m-d');
    }

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

    //---------------------------- PERFIL / LOGROS ----------------------------//
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
    //---------------------------- FIN PERFIL / LOGROS ----------------------------//
    
    //---------------------------- LOGROS ----------------------------//
    /**
     * Verifica si un usuario ya tiene asignado un logro
     */
    public static function existeLogroUsuario(int $idUsuario, int $idLogro): bool
    {
        $idUsuario = (int)$idUsuario;
        $idLogro = (int)$idLogro;

        $sql = "SELECT id 
                FROM " . static::$tabla . " 
                WHERE id_usuarios = {$idUsuario} 
                  AND id_logros = {$idLogro}
                LIMIT 1";

        $resultado = self::$db->query($sql);

        if (!$resultado) {
            return false;
        }

        $existe = $resultado->num_rows > 0;
        $resultado->free();

        return $existe;
    }

    /**
     * Registra un logro para un usuario si aún no existe
     */
    public static function registrarLogro(int $idUsuario, int $idLogro): bool
    {
        if (self::existeLogroUsuario($idUsuario, $idLogro)) {
            return false;
        }

        $logroUsuario = new self([
            'id_usuarios' => $idUsuario,
            'id_logros' => $idLogro,
            'fecha_obtenido' => date('Y-m-d')
        ]);

        $resultado = $logroUsuario->guardar();

        return !empty($resultado['resultado']);
    }

    /**
     * Obtiene la información completa de varios logros por sus IDs
     */
    public static function obtenerPorIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $ids = array_map('intval', $ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            return [];
        }

        $idsString = implode(',', $ids);

        $sql = "SELECT id, nombre, descripcion, icono, tipo, valor_objetivo
            FROM logros
            WHERE id IN ({$idsString})
              AND habilitado = 1";

        return Logros::consultarSQL($sql);
    }
    //---------------------------- FINLOGROS ----------------------------//



}
