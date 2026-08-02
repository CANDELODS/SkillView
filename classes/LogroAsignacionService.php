<?php

declare(strict_types=1);

namespace Classes;

/**
 * Centraliza las decisiones internas utilizadas para evaluar
 * y registrar logros sin realizar consultas directas a MySQL.
 */
final class LogroAsignacionService
{
    public const CONDICION_CUMPLIDA = 'CONDICION_CUMPLIDA';
    public const LOGRO_DESHABILITADO = 'LOGRO_DESHABILITADO';
    public const SIN_LECCIONES = 'SIN_LECCIONES';
    public const LECCIONES_PENDIENTES = 'LECCIONES_PENDIENTES';
    public const RETO_NO_DISPONIBLE = 'RETO_NO_DISPONIBLE';
    public const RETO_NO_COMPLETADO = 'RETO_NO_COMPLETADO';
    public const PUNTAJE_INSUFICIENTE = 'PUNTAJE_INSUFICIENTE';
    public const ICONO_NO_CORRESPONDE = 'ICONO_NO_CORRESPONDE';
    public const CONDICION_NO_CUMPLIDA = 'CONDICION_NO_CUMPLIDA';
    public const LOGRO_YA_ASIGNADO = 'LOGRO_YA_ASIGNADO';
    public const ASIGNABLE = 'ASIGNABLE';

    /**
     * Evalúa la condición de un logro tipo 1 (Habilidad).
     *
     * Este tipo se obtiene exclusivamente al completar todas
     * las lecciones habilitadas de la habilidad correspondiente.
     *
     * @return array{cumple: bool, codigo: string}
     */
    public static function evaluarHabilidad(
        bool $logroHabilitado,
        int $totalLecciones,
        int $leccionesCompletadas,
        string $iconoLogro,
        string $iconoEsperado
    ): array {
        if (!$logroHabilitado) {
            return [
                'cumple' => false,
                'codigo' => self::LOGRO_DESHABILITADO,
            ];
        }

        if ($totalLecciones <= 0) {
            return [
                'cumple' => false,
                'codigo' => self::SIN_LECCIONES,
            ];
        }

        if ($leccionesCompletadas < $totalLecciones) {
            return [
                'cumple' => false,
                'codigo' => self::LECCIONES_PENDIENTES,
            ];
        }

        if (trim($iconoLogro) !== trim($iconoEsperado)) {
            return [
                'cumple' => false,
                'codigo' => self::ICONO_NO_CORRESPONDE,
            ];
        }

        return [
            'cumple' => true,
            'codigo' => self::CONDICION_CUMPLIDA,
        ];
    }

    /**
     * Evalúa la condición de un logro tipo 4 (Desempeño).
     *
     * El reto debe estar habilitado y completado; además, el
     * puntaje obtenido debe ser igual o superior al objetivo.
     *
     * @return array{cumple: bool, codigo: string}
     */
    public static function evaluarDesempeno(
        bool $logroHabilitado,
        bool $retoHabilitado,
        bool $retoCompletado,
        float $puntajeObtenido,
        float $valorObjetivo,
        string $iconoLogro,
        string $iconoEsperado
    ): array {
        if (!$logroHabilitado) {
            return [
                'cumple' => false,
                'codigo' => self::LOGRO_DESHABILITADO,
            ];
        }

        if (!$retoHabilitado) {
            return [
                'cumple' => false,
                'codigo' => self::RETO_NO_DISPONIBLE,
            ];
        }

        if (!$retoCompletado) {
            return [
                'cumple' => false,
                'codigo' => self::RETO_NO_COMPLETADO,
            ];
        }

        if (trim($iconoLogro) !== trim($iconoEsperado)) {
            return [
                'cumple' => false,
                'codigo' => self::ICONO_NO_CORRESPONDE,
            ];
        }

        if ($puntajeObtenido < $valorObjetivo) {
            return [
                'cumple' => false,
                'codigo' => self::PUNTAJE_INSUFICIENTE,
            ];
        }

        return [
            'cumple' => true,
            'codigo' => self::CONDICION_CUMPLIDA,
        ];
    }

    /**
     * Decide si una condición cumplida debe producir una nueva
     * relación en usuarios_logros.
     *
     * @return array{asignar: bool, codigo: string}
     */
    public static function evaluarAsignacion(
        bool $condicionCumplida,
        bool $yaAsignado
    ): array {
        if (!$condicionCumplida) {
            return [
                'asignar' => false,
                'codigo' => self::CONDICION_NO_CUMPLIDA,
            ];
        }

        if ($yaAsignado) {
            return [
                'asignar' => false,
                'codigo' => self::LOGRO_YA_ASIGNADO,
            ];
        }

        return [
            'asignar' => true,
            'codigo' => self::ASIGNABLE,
        ];
    }

    /**
     * Ejecuta el registrador únicamente cuando la decisión
     * de asignación lo permite.
     */
    public static function registrarSiCorresponde(
        array $asignacion,
        callable $registrador
    ): bool {
        if (empty($asignacion['asignar'])) {
            return false;
        }

        return (bool) $registrador();
    }
}