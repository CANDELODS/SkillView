<?php

namespace Model;

// Importamos ActiveRecord para heredar la conexión y utilidades base del ORM propio.
// También importamos los modelos que se usan para consultar progreso en lecciones y retos.
use Model\ActiveRecord;
use Model\usuarios_lecciones;
use Model\usuarios_retos;

// Modelo que representa la tabla usuarios_habilidades.
// Esta tabla guarda el progreso consolidado del usuario por cada habilidad blanda.
class usuarios_habilidades extends ActiveRecord
{
    // Nombre de la tabla real en base de datos.
    protected static $tabla = 'usuarios_habilidades';

    // Columnas que existen en la tabla.
    protected static $columnasDB = ['id', 'id_usuarios', 'id_habilidades', 'nivel', 'progreso', 'ultima_actualizacion'];

    // Propiedades públicas que representan cada columna.
    public $id, $id_usuarios, $id_habilidades, $nivel, $progreso, $ultima_actualizacion;

    // ================== REGISTRO (Inicializar Tabla Para /Perfil) ================== //

    // Este método inicializa la tabla usuarios_habilidades para un usuario nuevo.
    // Su propósito es crear una fila por cada habilidad habilitada,
    // de manera que el perfil del usuario tenga una base de progreso desde el inicio.
    public static function inicializarHabilidadesUsuario(int $idUsuario, ?string $fecha = null): bool
    {
        // Si no se recibe fecha, se usa la fecha actual.
        $fecha = $fecha ?: date('Y-m-d');

        // Inserta una fila por cada habilidad habilitada que aún no exista para el usuario.
        // Explicación del query:
        // - Toma todas las habilidades blandas habilitadas.
        // - Inserta nivel = 1 (Básico), progreso = 0.00 y fecha actual.
        // - Usa NOT EXISTS para evitar duplicados si la fila ya fue creada antes.
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

        // Ejecuta la consulta.
        $resultado = self::$db->query($query);

        // Devuelve true si la consulta fue exitosa.
        return (bool)$resultado;
    }
    // ================== FIN REGISTRO (Inicializar Tabla Para /Perfil) ================== //


    // ================== PERFIL ================== //

    // Calcula el progreso general del usuario.
    // Este progreso es el promedio del progreso de todas sus habilidades habilitadas.
    public static function progresoGeneral(int $idUsuario): int
    {
        // Aseguramos tipo entero.
        $idUsuario = (int)$idUsuario;

        // Query:
        // - Hace promedio de la columna progreso en usuarios_habilidades.
        // - Solo toma habilidades blandas habilitadas.
        // - Si no hay datos, devuelve 0 con IFNULL.
        // - ROUND redondea el valor final.
        $sql = "SELECT ROUND(IFNULL(AVG(uh.progreso), 0)) AS progreso_general
                FROM usuarios_habilidades uh
                INNER JOIN habilidades_blandas hb ON hb.id = uh.id_habilidades
                WHERE uh.id_usuarios = {$idUsuario}
                  AND hb.habilitado = 1";

        // Ejecuta la consulta.
        $resultado = self::$db->query($sql);

        // Si la consulta responde, toma la fila; si no, usa 0 por defecto.
        $row = $resultado ? $resultado->fetch_assoc() : ['progreso_general' => 0];

        // Libera memoria del resultado.
        if ($resultado) {
            $resultado->free();
        }

        // Retorna el progreso general como entero.
        return (int)($row['progreso_general'] ?? 0);
    }

    // Devuelve el progreso detallado por cada habilidad del usuario.
    // Este método es útil para pantallas tipo perfil o dashboard.
    public static function progresoPorHabilidad(int $idUsuario): array
    {
        $idUsuario = (int)$idUsuario;

        // Query:
        // - Trae id y nombre de la habilidad.
        // - Trae progreso y última actualización desde usuarios_habilidades.
        // - Convierte el nivel numérico a texto usando CASE.
        // - Ordena alfabéticamente por nombre de habilidad.
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

        // Ejecuta la consulta.
        $resultado = self::$db->query($sql);

        // Arreglo final que contendrá una fila por habilidad.
        $data = [];

        // Recorre los resultados y los transforma en un arreglo más cómodo para el sistema.
        while ($resultado && $row = $resultado->fetch_assoc()) {
            $data[] = [
                'id_habilidad' => (int)$row['id_habilidad'],
                'habilidad' => $row['habilidad'],
                'nivel' => $row['nivel_texto'],
                'progreso' => (int)round((float)$row['progreso']),
                'ultima_actualizacion' => $row['ultima_actualizacion']
            ];
        }

        // Libera memoria del resultado.
        if ($resultado) {
            $resultado->free();
        }

        // Devuelve el arreglo final.
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
        // Si el progreso es 67 o más, el nivel es Avanzado.
        if ($progreso >= 67) {
            return 3;
        }

        // Si el progreso es 34 o más, el nivel es Intermedio.
        if ($progreso >= 34) {
            return 2;
        }

        // En cualquier otro caso, queda en Básico.
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
        // Se fuerzan los tipos enteros para evitar inconsistencias.
        $idUsuario = (int)$idUsuario;
        $idHabilidad = (int)$idHabilidad;

        // -------------------------
        // TOTAL DE LECCIONES DE LA HABILIDAD
        // -------------------------
        // Consulta cuántas lecciones habilitadas existen para la habilidad.
        $sqlTotalLecciones = "
        SELECT COUNT(*) AS total
        FROM lecciones
        WHERE id_habilidades = {$idHabilidad}
          AND habilitado = 1
    ";

        // Ejecuta el query de total de lecciones.
        $resultadoLecciones = self::$db->query($sqlTotalLecciones);

        // Si la consulta responde, toma la fila; si no, usa 0.
        $rowLecciones = $resultadoLecciones ? $resultadoLecciones->fetch_assoc() : ['total' => 0];
        $totalLecciones = (int)($rowLecciones['total'] ?? 0);

        // Libera memoria del resultado.
        if ($resultadoLecciones) {
            $resultadoLecciones->free();
        }

        // -------------------------
        // LECCIONES COMPLETADAS POR EL USUARIO EN ESA HABILIDAD
        // -------------------------
        // Consulta cuántas lecciones ya completó el usuario en esa habilidad.
        // Este dato lo aporta el modelo usuarios_lecciones.
        $leccionesCompletadas = usuarios_lecciones::totalCompletadasPorHabilidad(
            $idUsuario,
            $idHabilidad
        );

        // -------------------------
        // TOTAL DE RETOS DE LA HABILIDAD
        // -------------------------
        // Consulta cuántos retos habilitados existen para esa habilidad.
        $sqlTotalRetos = "
        SELECT COUNT(*) AS total
        FROM retos
        WHERE id_habilidades = {$idHabilidad}
          AND habilitado = 1
    ";

        // Ejecuta el query de total de retos.
        $resultadoRetos = self::$db->query($sqlTotalRetos);

        // Si la consulta responde, toma la fila; si no, usa 0.
        $rowRetos = $resultadoRetos ? $resultadoRetos->fetch_assoc() : ['total' => 0];
        $totalRetos = (int)($rowRetos['total'] ?? 0);

        // Libera memoria del resultado.
        if ($resultadoRetos) {
            $resultadoRetos->free();
        }

        // -------------------------
        // RETOS COMPLETADOS POR EL USUARIO EN ESA HABILIDAD
        // -------------------------
        // Consulta cuántos retos completó el usuario en la habilidad.
        // Este dato es especialmente importante en la implementación de IA,
        // porque ahora los retos ya no son simples actividades, sino retos evaluados y cerrados con IA.
        $retosCompletados = usuarios_retos::totalCompletadosPorHabilidad($idUsuario, $idHabilidad);

        // -------------------------
        // PORCENTAJE DE LECCIONES Y RETOS
        // -------------------------
        // Calcula el porcentaje de avance en lecciones.
        // Si no hay lecciones, se mantiene en 0 para evitar división por cero.
        $porcentajeLecciones = 0;
        if ($totalLecciones > 0) {
            $porcentajeLecciones = $leccionesCompletadas / $totalLecciones;
        }

        // Calcula el porcentaje de avance en retos.
        // Si no hay retos, se mantiene en 0.
        $porcentajeRetos = 0;
        if ($totalRetos > 0) {
            $porcentajeRetos = $retosCompletados / $totalRetos;
        }

        // -------------------------
        // PROGRESO FINAL (50% + 50%)
        // -------------------------
        // La regla de negocio de SkillView define que:
        // - las lecciones representan el 50% del progreso,
        // - los retos representan el otro 50%.
        //
        // Por eso cada porcentaje se multiplica por 50.
        $progreso =
            ($porcentajeLecciones * 50) +
            ($porcentajeRetos * 50);

        // Redondea el resultado a 2 decimales para mantener consistencia visual y numérica.
        $progreso = round($progreso, 2);

        // -------------------------
        // NIVEL SEGÚN PROGRESO
        // -------------------------
        // Convierte el porcentaje final a un nivel numérico:
        // 1, 2 o 3.
        $nivel = self::calcularNivelPorProgreso($progreso);

        // Fecha actual para registrar cuándo se recalculó el progreso.
        $fecha = date('Y-m-d');

        // -------------------------
        // ACTUALIZAR REGISTRO EXISTENTE
        // -------------------------
        // Actualiza la fila del usuario para esa habilidad específica.
        // Se actualizan:
        // - progreso
        // - nivel
        // - ultima_actualizacion
        $update = "
        UPDATE " . static::$tabla . "
        SET progreso = {$progreso},
            nivel = {$nivel},
            ultima_actualizacion = '{$fecha}'
        WHERE id_usuarios = {$idUsuario}
          AND id_habilidades = {$idHabilidad}
        LIMIT 1
    ";

        // Ejecuta la actualización.
        // Este método no devuelve valor porque su objetivo es recalcular y persistir.
        self::$db->query($update);
    }

    // ================== FIN PROGRESO DE HABILIDAD ================== //

    // ================== FIN LECCIONES ================== //
}