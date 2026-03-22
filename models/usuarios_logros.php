<?php

namespace Model;

// Modelo que representa la tabla usuarios_logros.
// Esta tabla guarda la relación entre:
// - un usuario
// - y los logros que ha desbloqueado.
//
// En la implementación con IA, este modelo es importante porque
// cuando el usuario completa retos y se evalúan condiciones,
// aquí se persisten los logros obtenidos.
class usuarios_logros extends ActiveRecord
{
    // Nombre real de la tabla en base de datos.
    protected static $tabla = 'usuarios_logros';

    // Columnas reales de la tabla.
    protected static $columnasDB = ['id', 'id_usuarios', 'id_logros', 'fecha_obtenido'];

    // Propiedades del modelo.
    public $id, $id_usuarios, $id_logros, $fecha_obtenido;

    // Constructor del modelo.
    // Inicializa propiedades con valores del arreglo $args
    // o con valores por defecto si no vienen definidos.
    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->id_usuarios = $args['id_usuarios'] ?? '';
        $this->id_logros = $args['id_logros'] ?? '';
        $this->fecha_obtenido = $args['fecha_obtenido'] ?? date('Y-m-d');
    }

    //----------------------------RETOS----------------------------//

    // Obtiene los ids de los logros que pertenecen a un usuario.
    // Este método es útil cuando solo se necesita saber
    // qué logros ya tiene desbloqueados, sin traer información extra.
    public static function idsLogrosUsuario(int $idUsuario): array
    {
        $idUsuario = (int)$idUsuario;

        // Consulta simple que trae solo id_logros para ese usuario.
        $query = "
        SELECT id_logros
        FROM " . static::$tabla . "
        WHERE id_usuarios = {$idUsuario}
    ";

        $resultado = self::$db->query($query);

        $ids = [];

        // Recorre el resultado y almacena cada id_logros como entero.
        while ($row = $resultado->fetch_assoc()) {
            $ids[] = (int)$row['id_logros'];
        }

        // Libera memoria del resultado.
        $resultado->free();

        return $ids;
    }

    //----------------------------FIN RETOS----------------------------//

    //---------------------------- PERFIL / LOGROS ----------------------------//

    // Construye un lookup de logros del usuario.
    // Devuelve un arreglo tipo:
    // [id_logro => fecha_obtenido]
    //
    // Esto es útil para interfaces donde se necesita saber:
    // - qué logros tiene el usuario
    // - y cuándo los obtuvo
    public static function lookupLogrosUsuario(int $idUsuario): array
    {
        $idUsuario = (int)$idUsuario;

        // Consulta que trae id del logro y fecha obtenida.
        $sql = "SELECT id_logros, fecha_obtenido
            FROM usuarios_logros
            WHERE id_usuarios = {$idUsuario}";

        $resultado = self::$db->query($sql);

        $lookup = [];

        // Convierte el resultado en un arreglo asociativo.
        while ($row = $resultado->fetch_assoc()) {
            $lookup[(int)$row['id_logros']] = $row['fecha_obtenido'];
        }

        $resultado->free();

        // Ejemplo de salida:
        // [3 => '2026-03-21', 7 => '2026-03-22']
        return $lookup;
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

        // Consulta que busca si ya existe una fila con ese usuario y ese logro.
        $sql = "SELECT id 
                FROM " . static::$tabla . " 
                WHERE id_usuarios = {$idUsuario} 
                  AND id_logros = {$idLogro}
                LIMIT 1";

        $resultado = self::$db->query($sql);

        // Si la consulta falla, asumimos false.
        if (!$resultado) {
            return false;
        }

        // Si hay al menos una fila, el logro ya existe para el usuario.
        $existe = $resultado->num_rows > 0;
        $resultado->free();

        return $existe;
    }

    /**
     * Registra un logro para un usuario si aún no existe
     */
    public static function registrarLogro(int $idUsuario, int $idLogro): bool
    {
        // Antes de registrar, valida que el usuario no tenga ya ese logro.
        // Esto evita duplicados.
        if (self::existeLogroUsuario($idUsuario, $idLogro)) {
            return false;
        }

        // Crea una nueva instancia del modelo con los datos necesarios.
        $logroUsuario = new self([
            'id_usuarios' => $idUsuario,
            'id_logros' => $idLogro,
            'fecha_obtenido' => date('Y-m-d')
        ]);

        // Usa guardar() heredado de ActiveRecord para persistir en la base de datos.
        $resultado = $logroUsuario->guardar();

        // Retorna true si guardar() devolvió un resultado exitoso.
        return !empty($resultado['resultado']);
    }

    /**
     * Obtiene la información completa de varios logros por sus IDs
     */
    public static function obtenerPorIds(array $ids): array
    {
        // Si el arreglo viene vacío, retorna vacío.
        if (empty($ids)) {
            return [];
        }

        // Convierte todos los valores a enteros.
        $ids = array_map('intval', $ids);

        // Elimina valores falsy o no válidos.
        $ids = array_filter($ids);

        // Si después del filtrado quedó vacío, retorna vacío.
        if (empty($ids)) {
            return [];
        }

        // Convierte el arreglo en una lista separada por comas.
        // Ejemplo: [1, 2, 5] => "1,2,5"
        $idsString = implode(',', $ids);

        // Consulta que trae la información completa del logro desde la tabla logros.
        // Solo toma logros habilitados.
        $sql = "SELECT id, nombre, descripcion, icono, tipo, valor_objetivo
            FROM logros
            WHERE id IN ({$idsString})
              AND habilitado = 1";

        // Retorna los resultados usando consultarSQL del modelo Logros.
        return Logros::consultarSQL($sql);
    }

    //---------------------------- FINLOGROS ----------------------------//
}