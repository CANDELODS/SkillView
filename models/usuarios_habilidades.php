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
        // Si no nos pasan fecha, usamos la fecha actual (columna DATE)
        $fecha = $fecha ?: date('Y-m-d');

        // Insert masivo de todas las habilidades habilitadas para el usuario
        // Usamos NOT EXISTS para evitar duplicados si por alguna razón ya existen filas
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
            return 3; // Avanzado
        }

        if ($progreso >= 34) {
            return 2; // Intermedio
        }

        return 1; // Básico
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
        $rowLecciones = $resultadoLecciones->fetch_assoc();
        $totalLecciones = (int)($rowLecciones['total'] ?? 0);
        $resultadoLecciones->free();

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
        $rowRetos = $resultadoRetos->fetch_assoc();
        $totalRetos = (int)($rowRetos['total'] ?? 0);
        $resultadoRetos->free();

        // -------------------------
        // RETOS COMPLETADOS POR EL USUARIO EN ESA HABILIDAD
        // -------------------------
        $sqlRetosCompletados = "
        SELECT COUNT(*) AS total
        FROM usuarios_retos ur
        INNER JOIN retos r ON r.id = ur.id_retos
        WHERE ur.id_usuarios = {$idUsuario}
          AND ur.completado = 1
          AND r.id_habilidades = {$idHabilidad}
          AND r.habilitado = 1
    ";

        $resultadoRetosCompletados = self::$db->query($sqlRetosCompletados);
        $rowRetosCompletados = $resultadoRetosCompletados->fetch_assoc();
        $retosCompletados = (int)($rowRetosCompletados['total'] ?? 0);
        $resultadoRetosCompletados->free();

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
