<?php

declare(strict_types=1);

namespace Classes;

use InvalidArgumentException;

/**
 * Centraliza las reglas relacionadas con el puntaje
 * mínimo y la aprobación de los retos de SkillView.
 */
final class PuntajeRetoService
{
    /**
     * Porcentaje mínimo requerido para aprobar un reto.
     */
    private const PORCENTAJE_APROBACION = 0.70;

    /**
     * Calcula el puntaje mínimo necesario para aprobar.
     *
     * Se utiliza ceil() para redondear hacia arriba
     * cuando el 70 % produce un valor decimal.
     */
    public static function calcularPuntajeMinimo(
        int $puntajeTotal
    ): int {
        self::validarPuntajeTotal($puntajeTotal);

        return (int) ceil(
            $puntajeTotal * self::PORCENTAJE_APROBACION
        );
    }

    /**
     * Determina si el puntaje obtenido permite aprobar el reto.
     */
    public static function aprobo(
        int $puntajeObtenido,
        int $puntajeTotal
    ): bool {
        self::validarPuntajeTotal($puntajeTotal);
        self::validarPuntajeObtenido(
            $puntajeObtenido,
            $puntajeTotal
        );

        $puntajeMinimo = self::calcularPuntajeMinimo(
            $puntajeTotal
        );

        return $puntajeObtenido >= $puntajeMinimo;
    }

    /**
     * El puntaje total debe ser mayor que cero.
     */
    private static function validarPuntajeTotal(
        int $puntajeTotal
    ): void {
        if ($puntajeTotal <= 0) {
            throw new InvalidArgumentException(
                'El puntaje total debe ser mayor que cero.'
            );
        }
    }

    /**
     * El puntaje obtenido debe estar entre cero
     * y el puntaje máximo disponible.
     */
    private static function validarPuntajeObtenido(
        int $puntajeObtenido,
        int $puntajeTotal
    ): void {
        if ($puntajeObtenido < 0) {
            throw new InvalidArgumentException(
                'El puntaje obtenido no puede ser negativo.'
            );
        }

        if ($puntajeObtenido > $puntajeTotal) {
            throw new InvalidArgumentException(
                'El puntaje obtenido no puede superar el puntaje total.'
            );
        }
    }
}