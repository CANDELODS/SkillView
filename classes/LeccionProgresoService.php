<?php

declare(strict_types=1);

namespace Classes;

use Model\Lecciones;
use Model\usuarios_habilidades;
use Model\usuarios_lecciones;

/**
 * Coordina el registro de una lección completada
 * y la actualización del progreso de su habilidad.
 */
final class LeccionProgresoService
{
    public const LECCION_COMPLETADA =
        'LECCION_COMPLETADA';

    public const DATOS_INVALIDOS =
        'DATOS_INVALIDOS';

    public const LECCION_NO_EXISTE =
        'LECCION_NO_EXISTE';

    public const LECCION_NO_HABILITADA =
        'LECCION_NO_HABILITADA';

    public const REGISTRO_NO_REALIZADO =
        'REGISTRO_NO_REALIZADO';

    /**
     * Registra la lección y recalcula el progreso
     * consolidado de la habilidad asociada.
     *
     * @return array{
     *     ok: bool,
     *     estado: string,
     *     idUsuario: int,
     *     idLeccion: int,
     *     idHabilidad: int,
     *     totalLecciones: int,
     *     leccionesCompletadas: int,
     *     progreso: ?int,
     *     nivel: ?string
     * }
     */
    public static function completarLeccion(
        int $idUsuario,
        int $idLeccion
    ): array {
        if (
            $idUsuario <= 0
            || $idLeccion <= 0
        ) {
            return self::respuestaRechazada(
                self::DATOS_INVALIDOS,
                $idUsuario,
                $idLeccion
            );
        }

        $leccion = Lecciones::find(
            $idLeccion
        );

        if (!$leccion instanceof Lecciones) {
            return self::respuestaRechazada(
                self::LECCION_NO_EXISTE,
                $idUsuario,
                $idLeccion
            );
        }

        if ((int) $leccion->habilitado !== 1) {
            return self::respuestaRechazada(
                self::LECCION_NO_HABILITADA,
                $idUsuario,
                $idLeccion,
                (int) $leccion->id_habilidades
            );
        }

        $idHabilidad =
            (int) $leccion->id_habilidades;

        $guardado =
            usuarios_lecciones::
                marcarComoCompletada(
                    $idUsuario,
                    $idLeccion
                );

        if (!$guardado) {
            return self::respuestaRechazada(
                self::REGISTRO_NO_REALIZADO,
                $idUsuario,
                $idLeccion,
                $idHabilidad
            );
        }

        usuarios_habilidades::
            recalcularProgresoHabilidad(
                $idUsuario,
                $idHabilidad
            );

        $totalLecciones =
            (int) Lecciones::totalPorHabilidad(
                $idHabilidad
            );

        $leccionesCompletadas =
            usuarios_lecciones::
                totalCompletadasPorHabilidad(
                    $idUsuario,
                    $idHabilidad
                );

        $detalleProgreso =
            self::buscarProgresoHabilidad(
                $idUsuario,
                $idHabilidad
            );

        return [
            'ok' =>
                true,

            'estado' =>
                self::LECCION_COMPLETADA,

            'idUsuario' =>
                $idUsuario,

            'idLeccion' =>
                $idLeccion,

            'idHabilidad' =>
                $idHabilidad,

            'totalLecciones' =>
                $totalLecciones,

            'leccionesCompletadas' =>
                $leccionesCompletadas,

            'progreso' =>
                $detalleProgreso['progreso']
                ?? null,

            'nivel' =>
                $detalleProgreso['nivel']
                ?? null
        ];
    }

    /**
     * Localiza el progreso de una habilidad dentro
     * de la información preparada para el perfil.
     */
    private static function buscarProgresoHabilidad(
        int $idUsuario,
        int $idHabilidad
    ): ?array {
        $progresos =
            usuarios_habilidades::
                progresoPorHabilidad(
                    $idUsuario
                );

        foreach ($progresos as $progreso) {
            if (
                (int) (
                    $progreso['id_habilidad']
                    ?? 0
                ) === $idHabilidad
            ) {
                return $progreso;
            }
        }

        return null;
    }

    private static function respuestaRechazada(
        string $estado,
        int $idUsuario,
        int $idLeccion,
        int $idHabilidad = 0
    ): array {
        return [
            'ok' =>
                false,

            'estado' =>
                $estado,

            'idUsuario' =>
                $idUsuario,

            'idLeccion' =>
                $idLeccion,

            'idHabilidad' =>
                $idHabilidad,

            'totalLecciones' =>
                0,

            'leccionesCompletadas' =>
                0,

            'progreso' =>
                null,

            'nivel' =>
                null
        ];
    }
}