<?php

namespace Model;

// Modelo que representa la tabla usuarios_retos.
// Esta tabla guarda el estado de los retos por usuario:
// - si lo completó,
// - cuándo,
// - y qué puntaje obtuvo.
//
// Es una pieza clave en la integración con IA,
// ya que aquí se persiste el resultado final evaluado por la IA.
class usuarios_retos extends ActiveRecord
{
    // Nombre de la tabla en base de datos.
    protected static $tabla = 'usuarios_retos';

    // Columnas reales de la tabla.
    protected static $columnasDB = ['id', 'id_usuarios', 'id_retos', 'completado', 'fecha_completado', 'puntaje_obtenido'];

    // Propiedades del modelo.
    public $id, $id_usuarios, $id_retos, $completado, $fecha_completado, $puntaje_obtenido;

    // ================== RETOS ================== //

    // ================== CONSULTAS ================== //

    // Obtiene los IDs de los retos completados por un usuario.
    // Solo devuelve IDs porque es lo único necesario en muchos casos (optimización).
    public static function idsRetosCompletados(int $idUsuario): array
    {
        $idUsuario = (int)$idUsuario;

        // Query:
        // - Filtra por usuario
        // - Solo trae retos marcados como completados
        $query = "
        SELECT id_retos
        FROM " . static::$tabla . "
        WHERE id_usuarios = {$idUsuario}
          AND completado = 1
    ";

        $resultado = self::$db->query($query);

        $ids = [];

        // Recorre el resultado y guarda cada id_retos en un array.
        while ($resultado && $row = $resultado->fetch_assoc()) {
            $ids[] = (int)$row['id_retos'];
        }

        // Libera memoria.
        if ($resultado) {
            $resultado->free();
        }

        return $ids;
    }

    // Obtiene el total de retos completados por un usuario.
    public static function totalCompletadosUsuario(int $idUsuario): int
    {
        $idUsuario = (int)$idUsuario;

        // Query COUNT para saber cuántos retos completó.
        $query = "
        SELECT COUNT(*) AS total
        FROM " . static::$tabla . "
        WHERE id_usuarios = {$idUsuario}
          AND completado = 1
    ";

        $resultado = self::$db->query($query);

        // Si no hay resultado, se usa 0.
        $total = $resultado ? $resultado->fetch_assoc() : ['total' => 0];

        if ($resultado) {
            $resultado->free();
        }

        return (int)($total['total'] ?? 0);
    }

    // Obtiene cuántos retos completó el usuario agrupados por habilidad.
    public static function completadosPorHabilidad(int $idUsuario): array
    {
        $idUsuario = (int)$idUsuario;

        // Query:
        // - Une usuarios_retos con retos y habilidades_blandas
        // - Agrupa por habilidad
        // - Cuenta cuántos retos completados tiene cada una
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

        // Se transforma el resultado en un arreglo estructurado.
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
     * Este método es clave para calcular el progreso (50% retos).
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
     * Esto es CRÍTICO en la lógica de IA porque:
     * - evita repetir retos
     * - evita reintentos una vez aprobado
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

        // Si falla la consulta, se asume false.
        if (!$resultado) {
            return false;
        }

        // Si hay al menos un registro, significa que ya fue completado.
        $existe = $resultado->num_rows > 0;
        $resultado->free();

        return $existe;
    }

    // ================== PERSISTENCIA ================== //

    /**
     * Inserta o actualiza el estado exitoso del reto.
     *
     * ESTE ES EL MÉTODO MÁS IMPORTANTE PARA LA IA.
     *
     * Se ejecuta cuando:
     * - la IA marca accepted = true
     * - el flujo decide que el reto fue completado
     *
     * Aquí se guarda:
     * - completado = 1
     * - fecha
     * - puntaje obtenido (calculado a partir de scoreRatio)
     */
    public static function marcarComoCompletado(int $idUsuario, int $idReto, int $puntajeObtenido): bool
    {
        $idUsuario = (int)$idUsuario;
        $idReto = (int)$idReto;
        $puntajeObtenido = (int)$puntajeObtenido;

        // Fecha exacta de finalización.
        $fecha = date('Y-m-d H:i:s');

        // Primero se verifica si ya existe un registro para ese usuario y reto.
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

        // Si ya existe un registro, se actualiza.
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

        // Si no existe, se inserta un nuevo registro.
        $insertQuery = "
        INSERT INTO " . static::$tabla . "
        (id_usuarios, id_retos, completado, fecha_completado, puntaje_obtenido)
        VALUES ({$idUsuario}, {$idReto}, 1, '{$fecha}', {$puntajeObtenido})
    ";

        return (bool) self::$db->query($insertQuery);
    }

    // ================== FIN RETOS ================== //

    // ================== PERFIL ================== //

    // Obtiene la suma total de puntos obtenidos por el usuario en todos los retos.
    // Este valor es clave para:
    // - rankings
    // - logros tipo puntuación
    // - métricas de desempeño
    public static function puntosTotalesUsuario(int $idUsuario): int
    {
        $idUsuario = (int)$idUsuario;

        // SUM de puntaje_obtenido solo en retos completados.
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