<?php

namespace Model;

class Blog extends ActiveRecord
{
    protected static $tabla = 'blog';
    protected static $columnasDB = ['id', 'titulo', 'descripcion_corta', 'contenido', 'imagen', 'habilitado'];

    public $id, $titulo, $descripcion_corta, $contenido, $imagen, $habilitado;

    //----------------------------BLOG----------------------------

    public static function filtrar(?int $habilidadId = null): array
    {
        $habilidadId = $habilidadId ? (int)$habilidadId : null;

        // Base: solo habilitados
        $sql = "SELECT b.*
                FROM blog b
                WHERE b.habilitado = 1";

        // Si hay filtro por habilidad, verificamos relación en tabla puente
        if ($habilidadId) {
            $sql .= " AND EXISTS (
                        SELECT 1
                        FROM blog_habilidades bh
                        WHERE bh.id_blog = b.id
                          AND bh.id_habilidades = {$habilidadId}
                    )";
        }

        $sql .= " ORDER BY b.id DESC"; // o por fecha si luego agregas created_at
        return self::consultarSQL($sql);
    }

    public static function habilidadesDisponibles(): array
    {
        // Solo habilidades que tengan blogs habilitados
        $sql = "SELECT DISTINCT h.id, h.nombre
                FROM habilidades_blandas h
                INNER JOIN blog_habilidades bh ON bh.id_habilidades = h.id
                INNER JOIN blog b ON b.id = bh.id_blog
                WHERE b.habilitado = 1
                ORDER BY h.nombre ASC";

        return self::consultarSQL($sql);
    }

    public static function tagsPorBlogs(array $blogsIds): array
    {
        if (empty($blogsIds)) return [];

        $ids = implode(',', array_map('intval', $blogsIds));

        $sql = "SELECT bh.id_blog, h.id AS habilidad_id, h.nombre
            FROM blog_habilidades bh
            INNER JOIN habilidades_blandas h ON h.id = bh.id_habilidades
            WHERE bh.id_blog IN ({$ids})
            ORDER BY h.nombre ASC";

        $resultado = self::$db->query($sql);

        $tags = [];
        while ($row = $resultado->fetch_assoc()) {
            $blogId = (int)$row['id_blog'];

            $tags[$blogId][] = [
                'id' => (int)$row['habilidad_id'],
                'nombre' => $row['nombre']
            ];
        }

        $resultado->free();
        return $tags;
    }
    //----------------------------FIN BLOG----------------------------

}
