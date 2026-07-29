<?php

declare(strict_types=1);

namespace Classes;

use InvalidArgumentException;

/**
 * Centraliza las reglas aisladas relacionadas con
 * la evaluación y presentación de los logros.
 */
final class LogroService
{
    /**
     * Relación entre los nombres registrados en la base
     * de datos y los identificadores usados en los iconos.
     */
    private const MAPA_HABILIDADES = [
        'Autoconfianza' =>
            'autoconfianza',

        'Manejo del Estrés' =>
            'estres',

        'Inteligencia Emocional' =>
            'inteligencia-emocional',

        'Comunicación Asertiva' =>
            'comunicacion-asertiva',

        'Comunicación No Verbal' =>
            'comunicacion-no-verbal',

        'Empatía y Escucha Activa' =>
            'empatia-y-escucha-activa',

        'Trabajo en Equipo' =>
            'trabajo-en-equipo',

        'Responsabilidad' =>
            'responsabilidad',

        'Adaptabilidad' =>
            'adaptabilidad',

        'Actitud Positiva' =>
            'actitud-positiva',

        'Liderazgo' =>
            'liderazgo'
    ];

    /**
     * Obtiene el identificador asociado con una habilidad.
     */
    public static function slugHabilidad(
        string $nombreHabilidad
    ): ?string {
        $nombreHabilidad = trim($nombreHabilidad);

        return self::MAPA_HABILIDADES[
            $nombreHabilidad
        ] ?? null;
    }

    /**
     * Construye el nombre del icono correspondiente
     * a un logro por finalización de habilidad.
     */
    public static function iconoHabilidad(
        string $nombreHabilidad
    ): ?string {
        $slug = self::slugHabilidad(
            $nombreHabilidad
        );

        return $slug === null
            ? null
            : 'logros/habilidad_' . $slug;
    }

    /**
     * Construye el nombre del icono correspondiente
     * a un logro de desempeño en un reto.
     */
    public static function iconoDesempeno(
        string $nombreHabilidad
    ): ?string {
        $slug = self::slugHabilidad(
            $nombreHabilidad
        );

        return $slug === null
            ? null
            : 'logros/desempeno_' . $slug;
    }

    /**
     * Determina si el usuario completó todas
     * las lecciones de una habilidad.
     */
    public static function habilidadCompletada(
        int $totalLecciones,
        int $leccionesCompletadas
    ): bool {
        if (
            $totalLecciones < 0 ||
            $leccionesCompletadas < 0
        ) {
            throw new InvalidArgumentException(
                'Las cantidades de lecciones '
                . 'no pueden ser negativas.'
            );
        }

        /*
         * Una habilidad sin lecciones no puede generar
         * un logro de finalización.
         */
        return $totalLecciones > 0
            && $leccionesCompletadas >= $totalLecciones;
    }

    /**
     * Determina si el resultado de un reto cumple
     * el objetivo de un logro de desempeño.
     */
    public static function retoCumpleDesempeno(
        int $completado,
        float $puntajeObtenido,
        float $valorObjetivo
    ): bool {
        if (!in_array($completado, [0, 1], true)) {
            throw new InvalidArgumentException(
                'El estado de finalización del reto '
                . 'no es válido.'
            );
        }

        if ($puntajeObtenido < 0) {
            throw new InvalidArgumentException(
                'El puntaje obtenido no puede ser negativo.'
            );
        }

        if ($valorObjetivo < 0) {
            throw new InvalidArgumentException(
                'El valor objetivo no puede ser negativo.'
            );
        }

        return $completado === 1
            && $puntajeObtenido >= $valorObjetivo;
    }

    /**
     * Devuelve la etiqueta correspondiente
     * al tipo numérico del logro.
     */
    public static function etiquetaTipo(
        int $tipo
    ): string {
        return match ($tipo) {
            1 => 'Habilidad',
            2 => 'Puntaje',
            3 => 'Retos',
            4 => 'Desempeño',
            default => 'General'
        };
    }

    /**
     * Convierte una fecha al formato usado
     * en la interfaz de logros.
     *
     * Ejemplo:
     * 2026-07-25 → 25 jul 2026
     */
    public static function formatearFecha(
        ?string $fecha
    ): string {
        if ($fecha === null || trim($fecha) === '') {
            return '';
        }

        $timestamp = strtotime($fecha);

        if ($timestamp === false) {
            return '';
        }

        $meses = [
            'ene',
            'feb',
            'mar',
            'abr',
            'may',
            'jun',
            'jul',
            'ago',
            'sep',
            'oct',
            'nov',
            'dic'
        ];

        $dia = (int) date(
            'd',
            $timestamp
        );

        $indiceMes =
            (int) date('m', $timestamp) - 1;

        $mes = $meses[$indiceMes] ?? '';

        $anio = date(
            'Y',
            $timestamp
        );

        return "{$dia} {$mes} {$anio}";
    }
}