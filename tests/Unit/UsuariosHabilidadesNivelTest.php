<?php

declare(strict_types=1);

namespace Tests\Unit;

use Model\usuarios_habilidades;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UsuariosHabilidadesNivelTest extends TestCase
{
    #[DataProvider('casosNivelNumerico')]
    public function test_convierte_el_progreso_al_nivel_numerico_correcto(
        float $progreso,
        int $nivelEsperado
    ): void {
        $nivelObtenido =
            usuarios_habilidades::calcularNivelPorProgreso($progreso);

        self::assertSame(
            $nivelEsperado,
            $nivelObtenido
        );
    }

    public static function casosNivelNumerico(): array
    {
        return [
            'límite superior de básico' => [
                33,
                1
            ],

            'inicio de intermedio' => [
                34,
                2
            ],

            'límite superior de intermedio' => [
                66,
                2
            ],

            'inicio de avanzado' => [
                67,
                3
            ]
        ];
    }
}