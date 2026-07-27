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

        return (int) round($progresoConsolidado);
    }

    /**
     * Determina el nivel correspondiente al progreso alcanzado.
     */
    public static function determinarNivel(float $progreso): string
    {
        self::validarPorcentaje(
            $progreso,
            'El progreso consolidado'
        );

        $progresoRedondeado = (int) round($progreso);

        return match (true) {
            $progresoRedondeado <= 33 => 'Básico',
            $progresoRedondeado <= 66 => 'Intermedio',
            default => 'Avanzado'
        };
    }

    /**
     * Valida que un porcentaje esté comprendido entre 0 y 100.
     */
    private static function validarPorcentaje(
        float $porcentaje,
        string $nombre
    ): void {
        if ($porcentaje < 0 || $porcentaje > 100) {
            throw new InvalidArgumentException(
                "{$nombre} debe estar entre 0 y 100."
            );
        }
    }
}