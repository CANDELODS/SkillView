<?php

declare(strict_types=1);

namespace Classes;

use InvalidArgumentException;

/**
 * Contiene las reglas relacionadas con el cálculo del progreso
 * y la asignación de niveles de las habilidades blandas.
 */
final class ProgresoService
{
    /**
     * Calcula el progreso consolidado de una habilidad.
     *
     * El 50 % corresponde al progreso de las lecciones
     * y el 50 % corresponde al progreso de los retos.
     */
    public static function calcularProgreso(
        float $progresoLecciones,
        float $progresoRetos
    ): int {
        self::validarPorcentaje(
            $progresoLecciones,
            'El progreso de las lecciones'
        );

        self::validarPorcentaje(
            $progresoRetos,
            'El progreso de los retos'
        );

        $progresoConsolidado =
            ($progresoLecciones * 0.50) +
            ($progresoRetos * 0.50);

        return (int) round(
            $progresoConsolidado
        );
    }

    /**
     * Calcula el detalle completo del progreso a partir
     * de las cantidades reales de actividades.
     *
     * Esta operación centraliza:
     *
     * - porcentaje de lecciones;
     * - porcentaje de retos;
     * - ponderación 50/50;
     * - nivel textual;
     * - nivel numérico.
     *
     * @return array{
     *     porcentajeLecciones: int,
     *     porcentajeRetos: int,
     *     progreso: int,
     *     nivelTexto: string,
     *     nivelNumerico: int
     * }
     */
    public static function calcularDetalleDesdeCantidades(
        int $leccionesCompletadas,
        int $totalLecciones,
        int $retosCompletados,
        int $totalRetos
    ): array {
        /*
         * Se reutiliza la regla centralizada para
         * calcular porcentajes de actividades.
         */
        $porcentajeLecciones =
            PorcentajeActividadService::calcular(
                $leccionesCompletadas,
                $totalLecciones
            );

        $porcentajeRetos =
            PorcentajeActividadService::calcular(
                $retosCompletados,
                $totalRetos
            );

        /*
         * Se aplica la ponderación:
         *
         * 50 % lecciones + 50 % retos.
         */
        $progreso =
            self::calcularProgreso(
                (float) $porcentajeLecciones,
                (float) $porcentajeRetos
            );

        $nivelTexto =
            self::determinarNivel(
                $progreso
            );

        $nivelNumerico =
            self::determinarNivelNumerico(
                $progreso
            );

        return [
            'porcentajeLecciones' =>
                $porcentajeLecciones,

            'porcentajeRetos' =>
                $porcentajeRetos,

            'progreso' =>
                $progreso,

            'nivelTexto' =>
                $nivelTexto,

            'nivelNumerico' =>
                $nivelNumerico
        ];
    }

    /**
     * Determina el nivel correspondiente al progreso alcanzado.
     */
    public static function determinarNivel(
        float $progreso
    ): string {
        self::validarPorcentaje(
            $progreso,
            'El progreso consolidado'
        );

        $progresoRedondeado =
            (int) round(
                $progreso
            );

        return match (true) {
            $progresoRedondeado <= 33 =>
                'Básico',

            $progresoRedondeado <= 66 =>
                'Intermedio',

            default =>
                'Avanzado'
        };
    }

    /**
     * Convierte el nivel textual al valor numérico
     * almacenado en usuarios_habilidades.
     *
     * 1 = Básico
     * 2 = Intermedio
     * 3 = Avanzado
     */
    public static function determinarNivelNumerico(
        float $progreso
    ): int {
        return match (
            self::determinarNivel(
                $progreso
            )
        ) {
            'Básico' =>
                1,

            'Intermedio' =>
                2,

            'Avanzado' =>
                3
        };
    }

    /**
     * Valida que un porcentaje esté comprendido entre 0 y 100.
     */
    private static function validarPorcentaje(
        float $porcentaje,
        string $nombre
    ): void {
        if (
            $porcentaje < 0 ||
            $porcentaje > 100
        ) {
            throw new InvalidArgumentException(
                "{$nombre} debe estar entre 0 y 100."
            );
        }
    }
}