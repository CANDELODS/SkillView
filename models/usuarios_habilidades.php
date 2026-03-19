<?php

namespace Model;

use Model\ActiveRecord;
use Model\usuarios_lecciones;
use Model\usuarios_retos;

class usuarios_habilidades extends ActiveRecord
{
    protected static $tabla = 'usuarios_habilidades';
    protected static $columnasDB = ['id', 'id_usuarios', 'id_habilidades', 'nivel', 'progreso', 'ultima_actualizacion'];

    public $id, $id_usuarios, $id_habilidades, $nivel, $progreso, $ultima_actualizacion;

    // ================== REGISTRO (Inicializar Tabla Para /Perfil) ================== //
    public static function inicializarHabilidadesUsuario(int $idUsuario, ?string $fecha = null): bool
    {
        $fecha = $fecha ?: date('Y-m-d');

        $query = "INSERT INTO usuarios_habilidades (id_usuarios, id_habilidades, nivel, progreso, ultima_actualizacion)
              SELECT {$idUsuario}, hb.id, 1, 0.00, '{$fecha}'
              FROM habilidades_blandas hb
              WHERE hb.habilitado = 1
              AND NOT EXISTS (
                    SELECT 1
                    FROM usuarios_habilidades uh
                    WHERE uh.id_usuarios = {$idUsuario}
                    AND uh.id_habilidades = hb.id
              )";

        $resultado = self::$db->query($query);

        return (bool)$resultado;
    }
    // ================== FIN REGISTRO (Inicializar Tabla Para /Perfil) ================== //


    // ================== PERFIL ================== //

    public static function progresoGeneral(int $idUsuario): int
    {
        $idUsuario = (int)$idUsuario;

        $sql = "SELECT ROUND(IFNULL(AVG(uh.progreso), 0)) AS progreso_general
                FROM usuarios_habilidades uh
                INNER JOIN habilidades_blandas hb ON hb.id = uh.id_habilidades
                WHERE uh.id_usuarios = {$idUsuario}
                  AND hb.habilitado = 1";

        $resultado = self::$db->query($sql);
        $row = $resultado ? $resultado->fetch_assoc() : ['progreso_general' => 0];

        if ($resultado) {
            $resultado->free();
        }

        return (int)($row['progreso_general'] ?? 0);
    }

    public static function progresoPorHabilidad(int $idUsuario): array
    {
        $idUsuario = (int)$idUsuario;

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

        $resultado = self::$db->query($sql);

        $data = [];
        while ($resultado && $row = $resultado->fetch_assoc()) {
            $data[] = [
                'id_habilidad' => (int)$row['id_habilidad'],
                'habilidad' => $row['habilidad'],
                'nivel' => $row['nivel_texto'],
                'progreso' => (int)round((float)$row['progreso']),
                'ultima_actualizacion' => $row['ultima_actualizacion']
            ];
        }

        if ($resultado) {
            $resultado->free();
        }

        return $data;
    }

    // ================== FIN PERFIL ================== //


    // ================== LECCIONES ================== //
    // ================== PROGRESO DE HABILIDAD ================== //

    /**
     * Determina el nivel numérico de la habilidad según el progreso.
     * 1 = Básico
     * 2 = Intermedio
     * 3 = Avanzado
     */
    public static function calcularNivelPorProgreso(float $progreso): int
    {
        if ($progreso >= 67) {
            return 3;
        }

        if ($progreso >= 34) {
            return 2;
        }

        return 1;
    }

    /**
     * Recalcula el progreso total de una habilidad para un usuario
     * usando la regla:
     * - 50% lecciones
     * - 50% retos
     *
     * Además actualiza el nivel y la última fecha de actualización.
     */
    public static function recalcularProgresoHabilidad(int $idUsuario, int $idHabilidad): void
    {
        $idUsuario = (int)$idUsuario;
        $idHabilidad = (int)$idHabilidad;

        // -------------------------
        // TOTAL DE LECCIONES DE LA HABILIDAD
        // -------------------------
        $sqlTotalLecciones = "
        SELECT COUNT(*) AS total
        FROM lecciones
        WHERE id_habilidades = {$idHabilidad}
          AND habilitado = 1
    ";

        $resultadoLecciones = self::$db->query($sqlTotalLecciones);
        $rowLecciones = $resultadoLecciones ? $resultadoLecciones->fetch_assoc() : ['total' => 0];
        $totalLecciones = (int)($rowLecciones['total'] ?? 0);

        if ($resultadoLecciones) {
            $resultadoLecciones->free();
        }

        // -------------------------
        // LECCIONES COMPLETADAS POR EL USUARIO EN ESA HABILIDAD
        // -------------------------
        $leccionesCompletadas = usuarios_lecciones::totalCompletadasPorHabilidad(
            $idUsuario,
            $idHabilidad
        );

        // -------------------------
        // TOTAL DE RETOS DE LA HABILIDAD
        // -------------------------
        $sqlTotalRetos = "
        SELECT COUNT(*) AS total
        FROM retos
        WHERE id_habilidades = {$idHabilidad}
          AND habilitado = 1
    ";

        $resultadoRetos = self::$db->query($sqlTotalRetos);
        $rowRetos = $resultadoRetos ? $resultadoRetos->fetch_assoc() : ['total' => 0];
        $totalRetos = (int)($rowRetos['total'] ?? 0);

        if ($resultadoRetos) {
            $resultadoRetos->free();
        }

        // -------------------------
        // RETOS COMPLETADOS POR EL USUARIO EN ESA HABILIDAD
        // -------------------------
        $retosCompletados = usuarios_retos::totalCompletadosPorHabilidad($idUsuario, $idHabilidad);

        // -------------------------
        // PORCENTAJE DE LECCIONES Y RETOS
        // -------------------------
        $porcentajeLecciones = 0;
        if ($totalLecciones > 0) {
            $porcentajeLecciones = $leccionesCompletadas / $totalLecciones;
        }

        $porcentajeRetos = 0;
        if ($totalRetos > 0) {
            $porcentajeRetos = $retosCompletados / $totalRetos;
        }

        // -------------------------
        // PROGRESO FINAL (50% + 50%)
        // -------------------------
        $progreso =
            ($porcentajeLecciones * 50) +
            ($porcentajeRetos * 50);

        $progreso = round($progreso, 2);

        // -------------------------
        // NIVEL SEGÚN PROGRESO
        // -------------------------
        $nivel = self::calcularNivelPorProgreso($progreso);

        $fecha = date('Y-m-d');

        // -------------------------
        // ACTUALIZAR REGISTRO EXISTENTE
        // -------------------------
        $update = "
        UPDATE " . static::$tabla . "
        SET progreso = {$progreso},
            nivel = {$nivel},
            ultima_actualizacion = '{$fecha}'
        WHERE id_usuarios = {$idUsuario}
          AND id_habilidades = {$idHabilidad}
        LIMIT 1
    ";

        self::$db->query($update);
    }

    // ================== FIN PROGRESO DE HABILIDAD ================== //

    // ================== FIN LECCIONES ================== //
}
