<?php

declare(strict_types=1);

namespace Tests\WhiteBox;

use Classes\LogroAsignacionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogroAsignacionService::class)]
final class LogroAsignacionWhiteBoxTest extends TestCase
{
    #[TestDox('Rechaza un logro deshabilitado antes de evaluar su condición')]
    public function test_rechaza_logro_deshabilitado(): void
    {
        $habilidad = LogroAsignacionService::evaluarHabilidad(
            false,
            3,
            3,
            'logros/habilidad_autoconfianza',
            'logros/habilidad_autoconfianza'
        );

        self::assertFalse($habilidad['cumple']);
        self::assertSame(
            LogroAsignacionService::LOGRO_DESHABILITADO,
            $habilidad['codigo']
        );

        $desempeno = LogroAsignacionService::evaluarDesempeno(
            false,
            true,
            true,
            80,
            75,
            'logros/desempeno_autoconfianza',
            'logros/desempeno_autoconfianza'
        );

        self::assertFalse($desempeno['cumple']);
        self::assertSame(
            LogroAsignacionService::LOGRO_DESHABILITADO,
            $desempeno['codigo']
        );
    }

    #[TestDox('Rechaza el logro de habilidad sin lecciones o con lecciones pendientes')]
    public function test_rechaza_habilidad_incompleta(): void
    {
        $sinLecciones = LogroAsignacionService::evaluarHabilidad(
            true,
            0,
            0,
            'logros/habilidad_autoconfianza',
            'logros/habilidad_autoconfianza'
        );

        self::assertFalse($sinLecciones['cumple']);
        self::assertSame(
            LogroAsignacionService::SIN_LECCIONES,
            $sinLecciones['codigo']
        );

        $pendiente = LogroAsignacionService::evaluarHabilidad(
            true,
            3,
            2,
            'logros/habilidad_autoconfianza',
            'logros/habilidad_autoconfianza'
        );

        self::assertFalse($pendiente['cumple']);
        self::assertSame(
            LogroAsignacionService::LECCIONES_PENDIENTES,
            $pendiente['codigo']
        );
    }

    #[TestDox('Rechaza un logro cuyo icono no corresponde con la habilidad')]
    public function test_rechaza_icono_incorrecto(): void
    {
        $resultado = LogroAsignacionService::evaluarHabilidad(
            true,
            3,
            3,
            'logros/habilidad_liderazgo',
            'logros/habilidad_autoconfianza'
        );

        self::assertFalse($resultado['cumple']);
        self::assertSame(
            LogroAsignacionService::ICONO_NO_CORRESPONDE,
            $resultado['codigo']
        );
    }

    #[TestDox('Acepta el logro al completar todas las lecciones habilitadas')]
    public function test_acepta_logro_de_habilidad(): void
    {
        $resultado = LogroAsignacionService::evaluarHabilidad(
            true,
            3,
            3,
            'logros/habilidad_autoconfianza',
            'logros/habilidad_autoconfianza'
        );

        self::assertTrue($resultado['cumple']);
        self::assertSame(
            LogroAsignacionService::CONDICION_CUMPLIDA,
            $resultado['codigo']
        );
    }

    #[TestDox('Rechaza el desempeño si el reto no está disponible o no fue completado')]
    public function test_rechaza_reto_no_valido(): void
    {
        $deshabilitado = LogroAsignacionService::evaluarDesempeno(
            true,
            false,
            true,
            80,
            75,
            'logros/desempeno_autoconfianza',
            'logros/desempeno_autoconfianza'
        );

        self::assertFalse($deshabilitado['cumple']);
        self::assertSame(
            LogroAsignacionService::RETO_NO_DISPONIBLE,
            $deshabilitado['codigo']
        );

        $noCompletado = LogroAsignacionService::evaluarDesempeno(
            true,
            true,
            false,
            80,
            75,
            'logros/desempeno_autoconfianza',
            'logros/desempeno_autoconfianza'
        );

        self::assertFalse($noCompletado['cumple']);
        self::assertSame(
            LogroAsignacionService::RETO_NO_COMPLETADO,
            $noCompletado['codigo']
        );
    }

    #[TestDox('Rechaza el desempeño cuando el icono no corresponde')]
    public function test_rechaza_icono_de_desempeno_incorrecto(): void
    {
        $resultado = LogroAsignacionService::evaluarDesempeno(
            true,
            true,
            true,
            80,
            75,
            'logros/desempeno_liderazgo',
            'logros/desempeno_autoconfianza'
        );

        self::assertFalse($resultado['cumple']);
        self::assertSame(
            LogroAsignacionService::ICONO_NO_CORRESPONDE,
            $resultado['codigo']
        );
    }

    #[TestDox('Exige el puntaje objetivo y acepta exactamente el valor mínimo')]
    public function test_evalua_umbral_de_desempeno(): void
    {
        $inferior = LogroAsignacionService::evaluarDesempeno(
            true,
            true,
            true,
            74,
            75,
            'logros/desempeno_autoconfianza',
            'logros/desempeno_autoconfianza'
        );

        self::assertFalse($inferior['cumple']);
        self::assertSame(
            LogroAsignacionService::PUNTAJE_INSUFICIENTE,
            $inferior['codigo']
        );

        $exacto = LogroAsignacionService::evaluarDesempeno(
            true,
            true,
            true,
            75,
            75,
            'logros/desempeno_autoconfianza',
            'logros/desempeno_autoconfianza'
        );

        self::assertTrue($exacto['cumple']);
        self::assertSame(
            LogroAsignacionService::CONDICION_CUMPLIDA,
            $exacto['codigo']
        );
    }

    #[TestDox('Previene duplicados y permite únicamente una asignación nueva')]
    public function test_evalua_prevencion_de_duplicados(): void
    {
        $sinCondicion = LogroAsignacionService::evaluarAsignacion(
            false,
            false
        );

        self::assertFalse($sinCondicion['asignar']);
        self::assertSame(
            LogroAsignacionService::CONDICION_NO_CUMPLIDA,
            $sinCondicion['codigo']
        );

        $duplicado = LogroAsignacionService::evaluarAsignacion(
            true,
            true
        );

        self::assertFalse($duplicado['asignar']);
        self::assertSame(
            LogroAsignacionService::LOGRO_YA_ASIGNADO,
            $duplicado['codigo']
        );

        $nuevo = LogroAsignacionService::evaluarAsignacion(
            true,
            false
        );

        self::assertTrue($nuevo['asignar']);
        self::assertSame(
            LogroAsignacionService::ASIGNABLE,
            $nuevo['codigo']
        );
    }

    #[TestDox('Ejecuta el registro solo cuando corresponde y conserva su resultado')]
    public function test_ejecuta_registro_controlado(): void
    {
        $invocaciones = 0;

        $noAsignable = LogroAsignacionService::evaluarAsignacion(
            false,
            false
        );

        $resultadoNoAsignable =
            LogroAsignacionService::registrarSiCorresponde(
                $noAsignable,
                static function () use (&$invocaciones): bool {
                    $invocaciones++;
                    return true;
                }
            );

        self::assertFalse($resultadoNoAsignable);
        self::assertSame(0, $invocaciones);

        $asignable = LogroAsignacionService::evaluarAsignacion(
            true,
            false
        );

        $resultadoExitoso =
            LogroAsignacionService::registrarSiCorresponde(
                $asignable,
                static function () use (&$invocaciones): bool {
                    $invocaciones++;
                    return true;
                }
            );

        self::assertTrue($resultadoExitoso);
        self::assertSame(1, $invocaciones);

        $resultadoFallido =
            LogroAsignacionService::registrarSiCorresponde(
                $asignable,
                static function () use (&$invocaciones): bool {
                    $invocaciones++;
                    return false;
                }
            );

        self::assertFalse($resultadoFallido);
        self::assertSame(2, $invocaciones);
    }
}