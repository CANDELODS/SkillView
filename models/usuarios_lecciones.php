<?php

// Espacio de nombres del modelo.
// Esta clase pertenece a la capa Model del sistema.
namespace Model;

// Modelo que representa la tabla usuarios_lecciones.
// Esta tabla guarda la relación entre:
// - un usuario
// - y las lecciones que ha completado.
//
// Aunque este modelo pertenece al módulo de aprendizaje,
// sigue siendo importante en la implementación de IA en retos,
// porque el progreso total de una habilidad en SkillView
// se calcula combinando:
// - 50% lecciones
// - 50% retos
class usuarios_lecciones extends ActiveRecord
{
    // Nombre real de la tabla en la base de datos.
    protected static $tabla = 'usuarios_lecciones';

    // Columnas reales de la tabla.
    protected static $columnasDB = ['id', 'id_usuarios', 'id_lecciones', 'completado', 'fecha_completado'];

    // Propiedades públicas del modelo.
    public $id, $id_usuarios, $id_lecciones, $completado, $fecha_completado;

    // ================== APRENDIZAJE ================== //

    // Total de lecciones completadas por el usuario.
    // Este método se usa para mostrar el resumen general de avance
    // en el módulo de aprendizaje o en otras vistas que requieran ese dato.
    public static function totalCompletadasUsuario($idUsuario)
    {
        // Se limpia el valor usando escape_string.
        // Esto protege el query aunque aquí normalmente se esperan enteros.
        $idUsuario = self::$db->escape_string($idUsuario);

        // Cuenta cuántas filas tiene el usuario con completado = 1.
        $query = "SELECT COUNT(*) AS total
                  FROM " . static::$tabla . "
                  WHERE id_usuarios = {$idUsuario}
                  AND completado = 1";

        // Ejecuta la consulta.
        $resultado = self::$db->query($query);

        // Obtiene el resultado como arreglo.
        $total = $resultado->fetch_array();

        // Devuelve el total como entero.
        return (int) $total['total'];
    }

    // Total de lecciones completadas por el usuario dentro de una habilidad específica.
    // Este método es CLAVE porque luego se combina con los retos
    // para calcular el progreso total de la habilidad.
    public static function totalCompletadasPorHabilidad($idUsuario, $idHabilidad)
    {
        // Limpieza básica de los parámetros recibidos.
        $idUsuario  = self::$db->escape_string($idUsuario);
        $idHabilidad = self::$db->escape_string($idHabilidad);

        // Query:
        // - Une usuarios_lecciones con lecciones
        // - Filtra por usuario
        // - Solo cuenta las lecciones marcadas como completadas
        // - Limita la cuenta a una habilidad específica
        $query = "SELECT COUNT(*) AS total
                  FROM usuarios_lecciones ul
                  INNER JOIN lecciones l ON ul.id_lecciones = l.id
                  WHERE ul.id_usuarios = {$idUsuario}
                  AND ul.completado = 1
                  AND l.id_habilidades = {$idHabilidad}";

        // Ejecuta la consulta.
        $resultado = self::$db->query($query);

        // Obtiene el resultado.
        $total = $resultado->fetch_array();

        // Devuelve la cantidad final como entero.
        return (int) $total['total'];
    }
    // ================== FIN APRENDIZAJE ================== //

    // ================== LECCIONES ================== //

    // Marca una lección como completada para un usuario.
    // Este método sigue la misma lógica estructural que luego se reutilizó en retos:
    // - primero se verifica si ya existe un registro,
    // - si existe, se actualiza,
    // - si no existe, se inserta.
    //
    // Esta consistencia entre lecciones y retos ayuda a mantener
    // una arquitectura homogénea en SkillView.
    public static function marcarComoCompletada(int $idUsuario, int $idLeccion): bool
    {
        // Se fuerzan los tipos enteros para garantizar integridad.
        $idUsuario = (int)$idUsuario;
        $idLeccion = (int)$idLeccion;

        // Primero se verifica si ya existe un registro para ese usuario y esa lección.
        $checkQuery = "SELECT id 
                       FROM " . static::$tabla . " 
                       WHERE id_usuarios = {$idUsuario}
                         AND id_lecciones = {$idLeccion}
                       LIMIT 1";

        // Ejecuta la consulta de verificación.
        $resultado = self::$db->query($checkQuery);

        // Si existe, se guarda la fila; si no, queda null.
        $existe = $resultado ? $resultado->fetch_assoc() : null;

        // Fecha exacta de completado.
        $fecha = date('Y-m-d H:i:s');

        // Si ya existe el registro, se actualiza.
        if ($existe) {
            $id = (int)$existe['id'];

            // Se marca completado = 1 y se actualiza la fecha.
            $updateQuery = "UPDATE " . static::$tabla . "
                            SET completado = 1,
                                fecha_completado = '{$fecha}'
                            WHERE id = {$id}
                            LIMIT 1";

            return (bool) self::$db->query($updateQuery);
        }

        // Si no existe, se inserta un registro nuevo.
        $insertQuery = "INSERT INTO " . static::$tabla . " 
                        (id_usuarios, id_lecciones, completado, fecha_completado)
                        VALUES ({$idUsuario}, {$idLeccion}, 1, '{$fecha}')";

        return (bool) self::$db->query($insertQuery);
    }
    // ================== FIN LECCIONES ================== //
}
