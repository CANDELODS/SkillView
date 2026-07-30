<?php

declare(strict_types=1);

namespace Tests\Integration;

use Classes\LeccionProgresoService;
use Model\Lecciones;

require_once __DIR__
    . '/DatabaseIntegrationTestCase.php';

final class LeccionProgresoIntegrationTest extends
    DatabaseIntegrationTestCase
{
    private int $idUsuario;
    private int $idHabilidad;
    private int $idPrimeraLeccion;
    private int $idSegundaLeccion;
    private int $idLeccionDeshabilitada;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idUsuario =
            $this->crearUsuario();

        $this->idHabilidad =
            $this->crearHabilidad(
                'Integración Lecciones'
            );

        $this->idPrimeraLeccion =
            $this->crearLeccion(
                $this->idHabilidad,
                'Primera lección',
                1,
                1
            );

        $this->idSegundaLeccion =
            $this->crearLeccion(
                $this->idHabilidad,
                'Segunda lección',
                2,
                1
            );

        /*
         * Esta actividad no debe incluirse en el
         * total utilizado para calcular el progreso.
         */
        $this->idLeccionDeshabilitada =
            $this->crearLeccion(
                $this->idHabilidad,
                'Lección deshabilitada',
                3,
                0
            );

        $this->crearProgresoInicial(
            $this->idUsuario,
            $this->idHabilidad
        );
    }

    public function test_registra_una_leccion_completada(): void
    {
        $resultado =
            LeccionProgresoService::
                completarLeccion(
                    $this->idUsuario,
                    $this->idPrimeraLeccion
                );

        self::assertTrue(
            $resultado['ok']
        );

        self::assertSame(
            LeccionProgresoService::
                LECCION_COMPLETADA,
            $resultado['estado']
        );

        self::assertSame(
            $this->idUsuario,
            $resultado['idUsuario']
        );

        self::assertSame(
            $this->idPrimeraLeccion,
            $resultado['idLeccion']
        );

        $registro =
            $this->obtenerRegistroLeccion(
                $this->idUsuario,
                $this->idPrimeraLeccion
            );

        self::assertNotNull(
            $registro
        );

        self::assertSame(
            1,
            (int) $registro['completado']
        );
    }

    public function test_actualiza_el_progreso_al_completar_la_primera_leccion(): void
    {
        $resultado =
            LeccionProgresoService::
                completarLeccion(
                    $this->idUsuario,
                    $this->idPrimeraLeccion
                );

        self::assertSame(
            2,
            $resultado['totalLecciones']
        );

        self::assertSame(
            1,
            $resultado['leccionesCompletadas']
        );

        self::assertSame(
            25,
            $resultado['progreso']
        );

        self::assertSame(
            'Básico',
            $resultado['nivel']
        );

        $progreso =
            $this->obtenerProgreso(
                $this->idUsuario,
                $this->idHabilidad
            );

        self::assertSame(
            25.0,
            (float) $progreso['progreso']
        );

        self::assertSame(
            1,
            (int) $progreso['nivel']
        );

        self::assertSame(
            date('Y-m-d'),
            $progreso['ultima_actualizacion']
        );
    }

    public function test_actualiza_el_nivel_al_completar_todas_las_lecciones(): void
    {
        LeccionProgresoService::
            completarLeccion(
                $this->idUsuario,
                $this->idPrimeraLeccion
            );

        $resultado =
            LeccionProgresoService::
                completarLeccion(
                    $this->idUsuario,
                    $this->idSegundaLeccion
                );

        self::assertTrue(
            $resultado['ok']
        );

        self::assertSame(
            2,
            $resultado['leccionesCompletadas']
        );

        self::assertSame(
            50,
            $resultado['progreso']
        );

        self::assertSame(
            'Intermedio',
            $resultado['nivel']
        );

        $progreso =
            $this->obtenerProgreso(
                $this->idUsuario,
                $this->idHabilidad
            );

        self::assertSame(
            50.0,
            (float) $progreso['progreso']
        );

        self::assertSame(
            2,
            (int) $progreso['nivel']
        );
    }

    public function test_no_duplica_una_leccion_completada(): void
    {
        LeccionProgresoService::
            completarLeccion(
                $this->idUsuario,
                $this->idPrimeraLeccion
            );

        LeccionProgresoService::
            completarLeccion(
                $this->idUsuario,
                $this->idPrimeraLeccion
            );

        self::assertSame(
            1,
            $this->contarRegistrosLeccion(
                $this->idUsuario,
                $this->idPrimeraLeccion
            )
        );

        self::assertSame(
            1,
            $this->contarLeccionesCompletadas(
                $this->idUsuario,
                $this->idHabilidad
            )
        );

        $progreso =
            $this->obtenerProgreso(
                $this->idUsuario,
                $this->idHabilidad
            );

        self::assertSame(
            25.0,
            (float) $progreso['progreso']
        );
    }

    public function test_avanza_hasta_la_siguiente_leccion(): void
    {
        $leccionInicial =
            Lecciones::
                leccionActualPorUsuarioYHabilidad(
                    $this->idUsuario,
                    $this->idHabilidad
                );

        self::assertInstanceOf(
            Lecciones::class,
            $leccionInicial
        );

        self::assertSame(
            $this->idPrimeraLeccion,
            (int) $leccionInicial->id
        );

        LeccionProgresoService::
            completarLeccion(
                $this->idUsuario,
                $this->idPrimeraLeccion
            );

        $leccionSiguiente =
            Lecciones::
                leccionActualPorUsuarioYHabilidad(
                    $this->idUsuario,
                    $this->idHabilidad
                );

        self::assertInstanceOf(
            Lecciones::class,
            $leccionSiguiente
        );

        self::assertSame(
            $this->idSegundaLeccion,
            (int) $leccionSiguiente->id
        );

        LeccionProgresoService::
            completarLeccion(
                $this->idUsuario,
                $this->idSegundaLeccion
            );

        self::assertNull(
            Lecciones::
                leccionActualPorUsuarioYHabilidad(
                    $this->idUsuario,
                    $this->idHabilidad
                )
        );
    }

    public function test_rechaza_una_leccion_deshabilitada(): void
    {
        $resultado =
            LeccionProgresoService::
                completarLeccion(
                    $this->idUsuario,
                    $this->idLeccionDeshabilitada
                );

        self::assertFalse(
            $resultado['ok']
        );

        self::assertSame(
            LeccionProgresoService::
                LECCION_NO_HABILITADA,
            $resultado['estado']
        );

        self::assertSame(
            0,
            $this->contarRegistrosLeccion(
                $this->idUsuario,
                $this->idLeccionDeshabilitada
            )
        );

        $progreso =
            $this->obtenerProgreso(
                $this->idUsuario,
                $this->idHabilidad
            );

        self::assertSame(
            0.0,
            (float) $progreso['progreso']
        );
    }

    public function test_rechaza_una_leccion_inexistente(): void
    {
        $idInexistente =
            PHP_INT_MAX;

        $resultado =
            LeccionProgresoService::
                completarLeccion(
                    $this->idUsuario,
                    $idInexistente
                );

        self::assertFalse(
            $resultado['ok']
        );

        self::assertSame(
            LeccionProgresoService::
                LECCION_NO_EXISTE,
            $resultado['estado']
        );

        self::assertSame(
            0,
            $this->contarRegistrosLeccion(
                $this->idUsuario,
                $idInexistente
            )
        );
    }

    public function test_solo_actualiza_la_habilidad_correspondiente(): void
    {
        $idOtraHabilidad =
            $this->crearHabilidad(
                'Habilidad sin cambios'
            );

        $this->crearLeccion(
            $idOtraHabilidad,
            'Lección de otra habilidad',
            1,
            1
        );

        $this->crearProgresoInicial(
            $this->idUsuario,
            $idOtraHabilidad
        );

        LeccionProgresoService::
            completarLeccion(
                $this->idUsuario,
                $this->idPrimeraLeccion
            );

        $progresoPrincipal =
            $this->obtenerProgreso(
                $this->idUsuario,
                $this->idHabilidad
            );

        $progresoSecundario =
            $this->obtenerProgreso(
                $this->idUsuario,
                $idOtraHabilidad
            );

        self::assertSame(
            25.0,
            (float) $progresoPrincipal['progreso']
        );

        self::assertSame(
            0.0,
            (float) $progresoSecundario['progreso']
        );

        self::assertSame(
            1,
            (int) $progresoSecundario['nivel']
        );
    }

    private function crearUsuario(): int
    {
        $correo =
            'leccion_'
            . bin2hex(random_bytes(6))
            . '@skillview.test';

        $password = password_hash(
            'Clave1!',
            PASSWORD_BCRYPT
        );

        $consulta = self::$db->prepare(
            'INSERT INTO usuarios (
                nombres,
                apellidos,
                edad,
                sexo,
                correo,
                password,
                universidad,
                carrera,
                admin,
                debe_cambiar_password,
                habilitado,
                token_recuperacion,
                token_expiracion,
                autoriza_tratamiento_datos
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 1, ?, 0, 1)'
        );

        $nombres =
            'Usuario Lecciones';

        $apellidos =
            'Prueba Integración';

        $edad = 24;
        $sexo = 3;

        $universidad =
            'Universidad del Cauca';

        $carrera =
            'Ingeniería de Sistemas';

        $token = '';

        $consulta->bind_param(
            'ssiisssss',
            $nombres,
            $apellidos,
            $edad,
            $sexo,
            $correo,
            $password,
            $universidad,
            $carrera,
            $token
        );

        $consulta->execute();

        $idUsuario =
            (int) self::$db->insert_id;

        $consulta->close();

        return $idUsuario;
    }

    private function crearHabilidad(
        string $nombre
    ): int {
        $nombre .=
            ' '
            . bin2hex(random_bytes(3));

        $descripcion =
            'Habilidad temporal para pruebas '
            . 'de integración de lecciones.';

        $tag =
            'Lecciones, Integración';

        $habilitado = 1;

        $consulta = self::$db->prepare(
            'INSERT INTO habilidades_blandas (
                nombre,
                descripcion,
                tag,
                habilitado
             ) VALUES (?, ?, ?, ?)'
        );

        $consulta->bind_param(
            'sssi',
            $nombre,
            $descripcion,
            $tag,
            $habilitado
        );

        $consulta->execute();

        $idHabilidad =
            (int) self::$db->insert_id;

        $consulta->close();

        return $idHabilidad;
    }

    private function crearLeccion(
        int $idHabilidad,
        string $titulo,
        int $orden,
        int $habilitado
    ): int {
        $descripcion =
            'Contenido temporal de la lección '
            . 'utilizado en la prueba.';

        $consulta = self::$db->prepare(
            'INSERT INTO lecciones (
                id_habilidades,
                titulo,
                descripcion,
                orden,
                habilitado
             ) VALUES (?, ?, ?, ?, ?)'
        );

        $consulta->bind_param(
            'issii',
            $idHabilidad,
            $titulo,
            $descripcion,
            $orden,
            $habilitado
        );

        $consulta->execute();

        $idLeccion =
            (int) self::$db->insert_id;

        $consulta->close();

        return $idLeccion;
    }

    private function crearProgresoInicial(
        int $idUsuario,
        int $idHabilidad
    ): void {
        $fecha = date('Y-m-d');

        $consulta = self::$db->prepare(
            'INSERT INTO usuarios_habilidades (
                id_usuarios,
                id_habilidades,
                nivel,
                progreso,
                ultima_actualizacion
             ) VALUES (?, ?, 1, 0.00, ?)'
        );

        $consulta->bind_param(
            'iis',
            $idUsuario,
            $idHabilidad,
            $fecha
        );

        $consulta->execute();
        $consulta->close();
    }

    private function obtenerRegistroLeccion(
        int $idUsuario,
        int $idLeccion
    ): ?array {
        $consulta = self::$db->prepare(
            'SELECT id_usuarios,
                    id_lecciones,
                    completado
             FROM usuarios_lecciones
             WHERE id_usuarios = ?
               AND id_lecciones = ?
             LIMIT 1'
        );

        $consulta->bind_param(
            'ii',
            $idUsuario,
            $idLeccion
        );

        $consulta->execute();

        $fila = $consulta
            ->get_result()
            ->fetch_assoc();

        $consulta->close();

        return is_array($fila)
            ? $fila
            : null;
    }

    private function obtenerProgreso(
        int $idUsuario,
        int $idHabilidad
    ): array {
        $consulta = self::$db->prepare(
            'SELECT nivel,
                    progreso,
                    ultima_actualizacion
             FROM usuarios_habilidades
             WHERE id_usuarios = ?
               AND id_habilidades = ?
             LIMIT 1'
        );

        $consulta->bind_param(
            'ii',
            $idUsuario,
            $idHabilidad
        );

        $consulta->execute();

        $fila = $consulta
            ->get_result()
            ->fetch_assoc();

        $consulta->close();

        return is_array($fila)
            ? $fila
            : [];
    }

    private function contarRegistrosLeccion(
        int $idUsuario,
        int $idLeccion
    ): int {
        $consulta = self::$db->prepare(
            'SELECT COUNT(*) AS total
             FROM usuarios_lecciones
             WHERE id_usuarios = ?
               AND id_lecciones = ?'
        );

        $consulta->bind_param(
            'ii',
            $idUsuario,
            $idLeccion
        );

        $consulta->execute();

        $fila = $consulta
            ->get_result()
            ->fetch_assoc();

        $consulta->close();

        return (int) (
            $fila['total']
            ?? 0
        );
    }

    private function contarLeccionesCompletadas(
        int $idUsuario,
        int $idHabilidad
    ): int {
        $consulta = self::$db->prepare(
            'SELECT COUNT(*) AS total
             FROM usuarios_lecciones ul
             INNER JOIN lecciones l
                 ON l.id = ul.id_lecciones
             WHERE ul.id_usuarios = ?
               AND l.id_habilidades = ?
               AND ul.completado = 1
               AND l.habilitado = 1'
        );

        $consulta->bind_param(
            'ii',
            $idUsuario,
            $idHabilidad
        );

        $consulta->execute();

        $fila = $consulta
            ->get_result()
            ->fetch_assoc();

        $consulta->close();

        return (int) (
            $fila['total']
            ?? 0
        );
    }
}