<?php

namespace Model;

class usuarios_retos extends ActiveRecord
{
    protected static $tabla = 'usuarios_retos';
    protected static $columnasDB = ['id', 'id_usuarios', 'id_retos', 'completado', 'fecha_completado', 'puntaje_obtenido'];

    public $id, $id_usuarios, $id_retos, $completado, $fecha_completado, $puntaje_obtenido;

    // ================== RETOS ================== //

    // Verificar los retos completados por un usuario
    //Solo necesitamos los IDs, por lo cual no vamos a usar consultarSQL
    public static function idsRetosCompletados(int $idUsuario): array
    {
        $idUsuario = (int)$idUsuario;

        $query = "
        SELECT id_retos
        FROM " . static::$tabla . "
        WHERE id_usuarios = {$idUsuario}
          AND completado = 1
    ";

        $resultado = self::$db->query($query);

        $ids = [];
        while ($resultado && $row = $resultado->fetch_assoc()) {
            $ids[] = (int)$row['id_retos'];
        }

        if ($resultado) {
            $resultado->free();
        }

        return $ids;
    }

    //Obtenemos el total de retos completados por un usuario
    public static function totalCompletadosUsuario(int $idUsuario): int
    {
        $idUsuario = (int)$idUsuario;
        $query = "
        SELECT COUNT(*) AS total
        FROM " . static::$tabla . "
        WHERE id_usuarios = {$idUsuario}
          AND completado = 1
    ";
        $resultado = self::$db->query($query);
        $total = $resultado ? $resultado->fetch_assoc() : ['total' => 0];

        if ($resultado) {
            $resultado->free();
        }

        return (int)($total['total'] ?? 0);
    }

    //Obtenemos el total de retos completados por habilidad para un usuario
    public static function completadosPorHabilidad(int $idUsuario): array
    {
        $idUsuario = (int)$idUsuario;

        $query = "
        SELECT 
            r.id_habilidades AS id_habilidad,
            hb.nombre AS nombre,
            COUNT(*) AS completados
        FROM " . static::$tabla . " ur
        INNER JOIN retos r ON r.id = ur.id_retos
        INNER JOIN habilidades_blandas hb ON hb.id = r.id_habilidades
        WHERE ur.id_usuarios = {$idUsuario}
          AND ur.completado = 1
          AND r.habilitado = 1
          AND hb.habilitado = 1
        GROUP BY r.id_habilidades, hb.nombre
        ORDER BY hb.nombre ASC
    ";

        $resultado = self::$db->query($query);

        $data = [];
        while ($resultado && $row = $resultado->fetch_assoc()) {
            $data[] = [
                'id_habilidad' => (int)$row['id_habilidad'],
                'nombre'       => $row['nombre'],
                'completados'  => (int)$row['completados'],
            ];
        }

        if ($resultado) {
            $resultado->free();
        }

        return $data;
    }

    /**
     * Total de retos completados por un usuario en una habilidad específica.
     */
    public static function totalCompletadosPorHabilidad(int $idUsuario, int $idHabilidad): int
    {
        $idUsuario = (int)$idUsuario;
        $idHabilidad = (int)$idHabilidad;

        $query = "
        SELECT COUNT(*) AS total
        FROM " . static::$tabla . " ur
        INNER JOIN retos r ON r.id = ur.id_retos
        WHERE ur.id_usuarios = {$idUsuario}
          AND ur.completado = 1
          AND r.id_habilidades = {$idHabilidad}
          AND r.habilitado = 1
    ";

        $resultado = self::$db->query($query);
        $row = $resultado ? $resultado->fetch_assoc() : ['total' => 0];

        if ($resultado) {
            $resultado->free();
        }

        return (int)($row['total'] ?? 0);
    }

    /**
     * Verifica si el usuario ya completó un reto.
     */
    public static function yaCompletado(int $idUsuario, int $idReto): bool
    {
        $idUsuario = (int)$idUsuario;
        $idReto = (int)$idReto;

        $query = "
        SELECT id
        FROM " . static::$tabla . "
        WHERE id_usuarios = {$idUsuario}
          AND id_retos = {$idReto}
          AND completado = 1
        LIMIT 1
    ";

        $resultado = self::$db->query($query);

        if (!$resultado) {
            return false;
        }

        $existe = $resultado->num_rows > 0;
        $resultado->free();

        return $existe;
    }

    /**
     * Inserta o actualiza el estado exitoso del reto.
     */
    public static function marcarComoCompletado(int $idUsuario, int $idReto, int $puntajeObtenido): bool
    {
        $idUsuario = (int)$idUsuario;
        $idReto = (int)$idReto;
        $puntajeObtenido = (int)$puntajeObtenido;
        $fecha = date('Y-m-d H:i:s');

        $checkQuery = "
        SELECT id
        FROM " . static::$tabla . "
        WHERE id_usuarios = {$idUsuario}
          AND id_retos = {$idReto}
        LIMIT 1
    ";

        $resultado = self::$db->query($checkQuery);
        $existe = $resultado ? $resultado->fetch_assoc() : null;

        if ($resultado) {
            $resultado->free();
        }

        if ($existe) {
            $id = (int)$existe['id'];

            $updateQuery = "
            UPDATE " . static::$tabla . "
            SET completado = 1,
                fecha_completado = '{$fecha}',
                puntaje_obtenido = {$puntajeObtenido}
            WHERE id = {$id}
            LIMIT 1
        ";

            return (bool) self::$db->query($updateQuery);
        }

        $insertQuery = "
        INSERT INTO " . static::$tabla . "
        (id_usuarios, id_retos, completado, fecha_completado, puntaje_obtenido)
        VALUES ({$idUsuario}, {$idReto}, 1, '{$fecha}', {$puntajeObtenido})
    ";

        return (bool) self::$db->query($insertQuery);
    }

    // ================== FIN RETOS ================== //

    // ================== PERFIL ================== //
    // Puntos totales obtenidos por un usuario
    public static function puntosTotalesUsuario(int $idUsuario): int
    {
        $idUsuario = (int)$idUsuario;

        $sql = "SELECT IFNULL(SUM(puntaje_obtenido), 0) AS puntos
            FROM usuarios_retos
            WHERE id_usuarios = {$idUsuario}
              AND completado = 1";

        $resultado = self::$db->query($sql);
        $row = $resultado ? $resultado->fetch_assoc() : ['puntos' => 0];

        if ($resultado) {
            $resultado->free();
        }

        return (int)($row['puntos'] ?? 0);
    }

    // ================== FIN PERFIL ================== //
}
