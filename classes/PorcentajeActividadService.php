<?php

declare(strict_types=1);

namespace Classes;

use InvalidArgumentException;

/**
 * Centraliza el cálculo del porcentaje de actividades
 * completadas con relación al total disponible.
 */
final class PorcentajeActividadService
{
    /**
     * Calcula un porcentaje entero entre 0 y 100.
     */
    public static function calcular(
        int $actividadesCompletadas,
        int $totalActividades
    ): int {
        self::validarCantidades(
            $actividadesCompletadas,
            $totalActividades
        );

        /*
         * Cuando no existen actividades disponibles
         * y tampoco hay actividades completadas,
         * el progreso se representa como 0 %.
         */
        if ($totalActividades === 0) {
            return 0;
        }

        $porcentaje = (
            $actividadesCompletadas
            / $totalActividades
        ) * 100;

        /*
         * Se redondea al entero más cercano y se limita
         * a 100 para evitar porcentajes superiores
         * ante posibles datos duplicados o inconsistentes.
         */
        return min(
            100,
            (int) round($porcentaje)
        );
    }

    /**
     * Valida que las cantidades recibidas sean coherentes.
     */
    private static function validarCantidades(
        int $actividadesCompletadas,
        int $totalActividades
    ): void {
        if ($actividadesCompletadas < 0) {
            throw new InvalidArgumentException(
                'La cantidad de actividades completadas '
                . 'no puede ser negativa.'
            );
        }

        if ($totalActividades < 0) {
            throw new InvalidArgumentException(
                'El total de actividades no puede ser negativo.'
            );
        }

        if (
            $totalActividades === 0
            && $actividadesCompletadas > 0
        ) {
            throw new InvalidArgumentException(
                'No puede haber actividades completadas '
                . 'cuando el total es cero.'
            );
        }
    }
}