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


    // ================== FIN RETOS ================== //
}
