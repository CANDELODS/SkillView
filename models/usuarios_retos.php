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
        //Esta variable contiene un cursos apuntando a una tabla virtual con los resultados
        $resultado = self::$db->query($query);

        //Vamos a recorrer esa tabla virtual y guardar los IDs en un array
        //fetch_assoc() nos devuelve un array asociativo por cada fila de resultado
        //Y usamos while ya que el número de filas es deconocido, no usamos foreacho ya que no es un array
        $ids = [];
        while ($row = $resultado->fetch_assoc()) {
            //Casteamos a int ya que MySQL lo devuelve como string
            $ids[] = (int)$row['id_retos'];
        }
        //Liberamos memoria
        $resultado->free();

        return $ids;
    }
    //Obtenemos el total de retos completados por un usuario
    public static function totalCompletadosUsuario(int $idUsuario): int
    {
        $idUsuario = (int)$idUsuario;
        $query = "
        SELECT COUNT(*)
        FROM " . static::$tabla . "
        WHERE id_usuarios = {$idUsuario}
          AND completado = 1
    ";
        $resultado = self::$db->query($query);
        $total = $resultado->fetch_array();
        return (int) array_shift($total);
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
        while ($row = $resultado->fetch_assoc()) {
            $data[] = [
                'id_habilidad' => (int)$row['id_habilidad'],
                'nombre'       => $row['nombre'],
                'completados'  => (int)$row['completados'],
            ];
        }
        $resultado->free();

        return $data;

            /**
     * Devuelve un array así:
     * [
     *   ['id_habilidad'=>1, 'nombre'=>'Comunicación', 'completados'=>3],
     *   ['id_habilidad'=>4, 'nombre'=>'Liderazgo', 'completados'=>1],
     * ]
     */
    }

    // ================== FIN RETOS ================== //
}
