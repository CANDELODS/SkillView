<?php

declare(strict_types=1);

namespace Classes;

use InvalidArgumentException;

/**
 * Centraliza la regla de secuencialidad de las habilidades
 * mostradas en la sección de aprendizaje.
 */
final class RutaAprendizajeService
{
    public const ESTADO_COMPLETADO = 'completed';
    public const ESTADO_ACTUAL = 'current';
    public const ESTADO_BLOQUEADO = 'locked';

    /**
     * Recibe una lista de habilidades con:
     *
     * [
     *     'total_lecciones' => 3,
     *     'lecciones_completadas' => 1
     * ]
     *
     * Devuelve un arreglo con el estado correspondiente
     * para cada posición.
     *
     * @param array<int, array{
     *     total_lecciones: int,
     *     lecciones_completadas: int
     * }> $habilidades
     *
     * @return array<int, string>
     */
    public static function determinarEstados(
        array $habilidades
    ): array {
        self::validarDatos($habilidades);

        if ($habilidades === []) {
            return [];
        }

        $indiceActual = self::buscarIndiceActual(
            $habilidades
        );

        $estados = [];

        foreach ($habilidades as $indice => $habilidad) {
            $total = $habilidad['total_lecciones'];
            $completadas =
                $habilidad['lecciones_completadas'];

            // Una habilidad sin lecciones no puede iniciarse.
            if ($total === 0) {
                $estados[$indice] =
                    self::ESTADO_BLOQUEADO;

                continue;
            }

            /*
             * Si no existe una habilidad pendiente,
             * todas las habilidades con lecciones están completas.
             */
            if ($indiceActual === null) {
                $estados[$indice] =
                    self::ESTADO_COMPLETADO;

                continue;
            }

            /*
             * Las habilidades anteriores a la actual solo pueden
             * aparecer como completadas.
             */
            if ($indice < $indiceActual) {
                $estados[$indice] =
                    self::ESTADO_COMPLETADO;

                continue;
            }

            // La primera habilidad pendiente es la habilidad actual.
            if ($indice === $indiceActual) {
                $estados[$indice] =
                    self::ESTADO_ACTUAL;

                continue;
            }

            // Todas las habilidades posteriores permanecen bloqueadas.
            $estados[$indice] =
                self::ESTADO_BLOQUEADO;
        }

        return $estados;
    }

    /**
     * Encuentra la primera habilidad que contiene
     * lecciones pendientes.
     */
    private static function buscarIndiceActual(
        array $habilidades
    ): ?int {
        foreach ($habilidades as $indice => $habilidad) {
            $total = $habilidad['total_lecciones'];
            $completadas =
                $habilidad['lecciones_completadas'];

            if (
                $total > 0 &&
                $completadas < $total
            ) {
                return $indice;
            }
        }

        return null;
    }

    /**
     * Evita cantidades negativas o estructuras incompletas.
     */
    private static function validarDatos(
        array $habilidades
    ): void {
        foreach ($habilidades as $habilidad) {
            if (
                !array_key_exists(
                    'total_lecciones',
                    $habilidad
                ) ||
                !array_key_exists(
                    'lecciones_completadas',
                    $habilidad
                )
            ) {
                throw new InvalidArgumentException(
                    'Cada habilidad debe incluir el total '
                    . 'de lecciones y las lecciones completadas.'
                );
            }

            if (
                !is_int($habilidad['total_lecciones']) ||
                !is_int(
                    $habilidad['lecciones_completadas']
                )
            ) {
                throw new InvalidArgumentException(
                    'Las cantidades de lecciones deben ser enteras.'
                );
            }

            if (
                $habilidad['total_lecciones'] < 0 ||
                $habilidad['lecciones_completadas'] < 0
            ) {
                throw new InvalidArgumentException(
                    'Las cantidades de lecciones no pueden ser negativas.'
                );
            }
        }
    }
}