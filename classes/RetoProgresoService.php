<?php

declare(strict_types=1);

namespace Classes;

use Model\Retos;
use Model\usuarios_habilidades;
use Model\usuarios_retos;

/**
 * Coordina la persistencia de un reto aprobado
 * y el recálculo del progreso de su habilidad.
 */
final class RetoProgresoService
{
    public const RETO_COMPLETADO =
        'RETO_COMPLETADO';

    public const DATOS_INVALIDOS =
        'DATOS_INVALIDOS';

    public const RETO_NO_EXISTE =
        'RETO_NO_EXISTE';

    public const RETO_NO_HABILITADO =
        'RETO_NO_HABILITADO';

    public const HABILIDAD_NO_COINCIDE =
        'HABILIDAD_NO_COINCIDE';

    public const RETO_NO_APROBADO =
        'RETO_NO_APROBADO';

    public const RETO_YA_COMPLETADO =
        'RETO_YA_COMPLETADO';

    public const REGISTRO_NO_REALIZADO =
        'REGISTRO_NO_REALIZADO';

    /**
     * Registra un reto únicamente cuando finalizó
     * y el puntaje alcanzó el mínimo requerido.
     *
     * @return array{
     *     ok: bool,
     *     estado: string,
     *     idUsuario: int,
     *     idReto: int,
     *     idHabilidad: int,
     *     puntajeObtenido: int,
     *     puntajeMinimo: int,
     *     totalRetos: int,
     *     retosCompletados: int,
     *     progreso: ?int,
     *     nivel: ?string
     * }
     */
    public static function completarReto(
        int $idUsuario,
        int $idReto,
        int $idHabilidad,
        int $puntajeObtenido,
        int $puntajeMinimo,
        bool $retoFinalizado
    ): array {
        if (
            $idUsuario <= 0
            || $idReto <= 0
            || $idHabilidad <= 0
        ) {
            return self::respuestaRechazada(
                self::DATOS_INVALIDOS,
                $idUsuario,
                $idReto,
                $idHabilidad,
                $puntajeObtenido,
                $puntajeMinimo
            );
        }

        $reto = Retos::find(
            $idReto
        );

        if (!$reto instanceof Retos) {
            return self::respuestaRechazada(
                self::RETO_NO_EXISTE,
                $idUsuario,
                $idReto,
                $idHabilidad,
                $puntajeObtenido,
                $puntajeMinimo
            );
        }

        if ((int) $reto->habilitado !== 1) {
            return self::respuestaRechazada(
                self::RETO_NO_HABILITADO,
                $idUsuario,
                $idReto,
                $idHabilidad,
                $puntajeObtenido,
                $puntajeMinimo
            );
        }

        if (
            (int) $reto->id_habilidades
            !== $idHabilidad
        ) {
            return self::respuestaRechazada(
                self::HABILIDAD_NO_COINCIDE,
                $idUsuario,
                $idReto,
                $idHabilidad,
                $puntajeObtenido,
                $puntajeMinimo
            );
        }

        $puntajeMaximo =
            (int) $reto->puntos;

        if (
            $puntajeMaximo <= 0
            || $puntajeMinimo <= 0
            || $puntajeMinimo > $puntajeMaximo
            || $puntajeObtenido < 0
            || $puntajeObtenido > $puntajeMaximo
        ) {
            return self::respuestaRechazada(
                self::DATOS_INVALIDOS,
                $idUsuario,
                $idReto,
                $idHabilidad,
                $puntajeObtenido,
                $puntajeMinimo
            );
        }

        /*
         * El reto no se almacena cuando el flujo no
         * finalizó o el puntaje quedó por debajo
         * del mínimo requerido.
         */
        if (
            !$retoFinalizado
            || $puntajeObtenido < $puntajeMinimo
        ) {
            return self::respuestaRechazada(
                self::RETO_NO_APROBADO,
                $idUsuario,
                $idReto,
                $idHabilidad,
                $puntajeObtenido,
                $puntajeMinimo
            );
        }

        /*
         * Impide modificar o duplicar un reto que
         * el usuario ya había completado.
         */
        if (
            usuarios_retos::yaCompletado(
                $idUsuario,
                $idReto
            )
        ) {
            return self::respuestaRechazada(
                self::RETO_YA_COMPLETADO,
                $idUsuario,
                $idReto,
                $idHabilidad,
                $puntajeObtenido,
                $puntajeMinimo
            );
        }

        $guardado =
            usuarios_retos::marcarComoCompletado(
                $idUsuario,
                $idReto,
                $puntajeObtenido
            );

        if (!$guardado) {
            return self::respuestaRechazada(
                self::REGISTRO_NO_REALIZADO,
                $idUsuario,
                $idReto,
                $idHabilidad,
                $puntajeObtenido,
                $puntajeMinimo
            );
        }

        usuarios_habilidades::
            recalcularProgresoHabilidad(
                $idUsuario,
                $idHabilidad
            );

        $totalRetos =
            Retos::totalHabilitadosPorHabilidad(
                $idHabilidad
            );

        $retosCompletados =
            usuarios_retos::
                totalCompletadosPorHabilidad(
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
                self::RETO_COMPLETADO,

            'idUsuario' =>
                $idUsuario,

            'idReto' =>
                $idReto,

            'idHabilidad' =>
                $idHabilidad,

            'puntajeObtenido' =>
                $puntajeObtenido,

            'puntajeMinimo' =>
                $puntajeMinimo,

            'totalRetos' =>
                $totalRetos,

            'retosCompletados' =>
                $retosCompletados,

            'progreso' =>
                $detalleProgreso['progreso']
                ?? null,

            'nivel' =>
                $detalleProgreso['nivel']
                ?? null
        ];
    }

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
        int $idReto,
        int $idHabilidad,
        int $puntajeObtenido,
        int $puntajeMinimo
    ): array {
        return [
            'ok' =>
                false,

            'estado' =>
                $estado,

            'idUsuario' =>
                $idUsuario,

            'idReto' =>
                $idReto,

            'idHabilidad' =>
                $idHabilidad,

            'puntajeObtenido' =>
                $puntajeObtenido,

            'puntajeMinimo' =>
                $puntajeMinimo,

            'totalRetos' =>
                0,

            'retosCompletados' =>
                0,

            'progreso' =>
                null,

            'nivel' =>
                null
        ];
    }
}