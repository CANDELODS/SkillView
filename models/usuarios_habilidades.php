<?php

namespace Model;

use Model\ActiveRecord;
use MVC\Router;


class usuarios_habilidades extends ActiveRecord
{
    protected static $tabla = 'usuarios_habilidades';
    protected static $columnasDB = ['id', 'id_usuarios', 'id_habilidades', 'nivel', 'progreso', 'ultima_actualización'];

    public $id, $id_usuarios, $id_habilidades, $nivel, $progreso, $ultima_actualización;

    // ================== PERFIL ================== //

    // Promedio del progreso del usuario en todas las habilidades habilitadas
    public static function progresoGeneral(int $idUsuario): int
    {
        $idUsuario = (int)$idUsuario;

        $sql = "SELECT ROUND(IFNULL(AVG(uh.progreso), 0)) AS progreso_general
                FROM usuarios_habilidades uh
                INNER JOIN habilidades_blandas hb ON hb.id = uh.id_habilidades
                WHERE uh.id_usuarios = {$idUsuario}
                  AND hb.habilitado = 1";

        $resultado = self::$db->query($sql);
        $row = $resultado->fetch_assoc();
        $resultado->free();

        return (int)($row['progreso_general'] ?? 0);
    }

    // Progreso por habilidad para la tabla (nombre, nivel, progreso, fecha)
    public static function progresoPorHabilidad(int $idUsuario): array
    {
        $idUsuario = (int)$idUsuario;

        // CASE para devolver el nivel en texto y evitar lógica en la vista
        $sql = "SELECT 
                    hb.id AS id_habilidad,
                    hb.nombre AS habilidad,
                    uh.progreso,
                    uh.ultima_actualizacion,
                    CASE uh.nivel
                        WHEN 1 THEN 'Básico'
                        WHEN 2 THEN 'Intermedio'
                        WHEN 3 THEN 'Avanzado'
                        ELSE 'Básico'
                    END AS nivel_texto
                FROM usuarios_habilidades uh
                INNER JOIN habilidades_blandas hb ON hb.id = uh.id_habilidades
                WHERE uh.id_usuarios = {$idUsuario}
                  AND hb.habilitado = 1
                ORDER BY hb.nombre ASC";

        // OJO: consultarSQL crea objetos del modelo (usuarios_habilidades)
        // y aquí también estamos trayendo columnas "extra" (habilidad, nivel_texto, id_habilidad).
        // Como tu ActiveRecord asigna propiedades solo si existen, estas columnas extra NO se asignan.
        // Por eso aquí usamos query + fetch_assoc y devolvemos array listo para la vista.
        $resultado = self::$db->query($sql);

        $data = [];
        while ($row = $resultado->fetch_assoc()) {
            $data[] = [
                'id_habilidad' => (int)$row['id_habilidad'],
                'habilidad' => $row['habilidad'],
                'nivel' => $row['nivel_texto'],
                'progreso' => (int)round((float)$row['progreso']),
                'ultima_actualizacion' => $row['ultima_actualizacion'] // la formateas en vista
            ];
        }
        $resultado->free();

        return $data;
    }

    // ================== FIN PERFIL ================== //
}
