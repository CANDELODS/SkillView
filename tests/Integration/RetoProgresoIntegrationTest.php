<?php

declare(strict_types=1);

namespace Tests\Integration;

use Classes\RetoProgresoService;

require_once __DIR__
    . '/DatabaseIntegrationTestCase.php';

final class RetoProgresoIntegrationTest extends
    DatabaseIntegrationTestCase
{
    private const PUNTAJE_MAXIMO = 30;
    private const PUNTAJE_MINIMO = 21;

    private int $idUsuario;
    private int $idHabilidad;
    private int $idPrimerReto;
    private int $idSegundoReto;
    private int $idRetoDeshabilitado;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idUsuario =
            $this->crearUsuario();

        $this->idHabilidad =
            $this->crearHabilidad(
                'Integración Retos'
            );

        $this->idPrimerReto =
            $this->crearReto(
                $this->idHabilidad,
                'Primer reto',
                1
            );

        $this->idSegundoReto =
            $this->crearReto(
                $this->idHabilidad,
                'Segundo reto',
                1
            );

        /*
         * El reto deshabilitado no debe incluirse
         * en el cálculo del progreso.
         */
        $this->idRetoDeshabilitado =
            $this->crearReto(
                $this->idHabilidad,
                'Reto deshabilitado',
                0
            );

        $this->crearProgresoInicial(
            $this->idUsuario,
            $this->idHabilidad
        );
    }

    public function test_registra_un_reto_aprobado_con_el_puntaje_minimo(): void
    {
        $resultado =
            RetoProgresoService::completarReto(
                $this->idUsuario,
                $this->idPrimerReto,
                $this->idHabilidad,
                self::PUNTAJE_MINIMO,
                self::PUNTAJE_MINIMO,
                true
            );

        self::assertTrue(
            $resultado['ok']
        );

        self::assertSame(
            RetoProgresoService::RETO_COMPLETADO,
            $resultado['estado']
        );

        self::assertSame(
            self::PUNTAJE_MINIMO,
            $resultado['puntajeObtenido']
        );

        $registro =
            $this->obtenerRegistroReto(
                $this->idUsuario,
                $this->idPrimerReto
            );

        self::assertNotNull(
            $registro
        );

        self::assertSame(
            1,
            (int) $registro['completado']
        );

        self::assertSame(
            self::PUNTAJE_MINIMO,
            (int) $registro['puntaje_obtenido']
        );
    }

    public function test_no_registra_un_reto_con_puntaje_inferior_al_minimo(): void
    {
        $resultado =
            RetoProgresoService::completarReto(
                $this->idUsuario,
                $this->idPrimerReto,
                $this->idHabilidad,
                20,
                self::PUNTAJE_MINIMO,
                true
            );

        self::assertFalse(
            $resultado['ok']
        );

        self::assertSame(
            RetoProgresoService::RETO_NO_APROBADO,
            $resultado['estado']
        );

        self::assertSame(
            0,
            $this->contarRegistrosReto(
                $this->idUsuario,
                $this->idPrimerReto
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

    public function test_actualiza_el_progreso_al_aprobar_el_primer_reto(): void
    {
        $resultado =
            RetoProgresoService::completarReto(
                $this->idUsuario,
                $this->idPrimerReto,
                $this->idHabilidad,
                24,
                self::PUNTAJE_MINIMO,
                true
            );

        self::assertSame(
            2,
            $resultado['totalRetos']
        );

        self::assertSame(
            1,
            $resultado['retosCompletados']
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

    public function test_actualiza_el_nivel_al_aprobar_todos_los_retos(): void
    {
        RetoProgresoService::completarReto(
            $this->idUsuario,
            $this->idPrimerReto,
            $this->idHabilidad,
            21,
            self::PUNTAJE_MINIMO,
            true
        );

        $resultado =
            RetoProgresoService::completarReto(
                $this->idUsuario,
                $this->idSegundoReto,
                $this->idHabilidad,
                27,
                self::PUNTAJE_MINIMO,
                true
            );

        self::assertTrue(
            $resultado['ok']
        );

        self::assertSame(
            2,
            $resultado['retosCompletados']
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

    public function test_no_duplica_un_reto_ya_completado(): void
    {
        RetoProgresoService::completarReto(
            $this->idUsuario,
            $this->idPrimerReto,
            $this->idHabilidad,
            21,
            self::PUNTAJE_MINIMO,
            true
        );

        $segundoResultado =
            RetoProgresoService::completarReto(
                $this->idUsuario,
                $this->idPrimerReto,
                $this->idHabilidad,
                30,
                self::PUNTAJE_MINIMO,
                true
            );

        self::assertFalse(
            $segundoResultado['ok']
        );

        self::assertSame(
            RetoProgresoService::RETO_YA_COMPLETADO,
            $segundoResultado['estado']
        );

        self::assertSame(
            1,
            $this->contarRegistrosReto(
                $this->idUsuario,
                $this->idPrimerReto
            )
        );

        /*
         * Se conservó el puntaje registrado durante
         * la primera aprobación.
         */
        $registro =
            $this->obtenerRegistroReto(
                $this->idUsuario,
                $this->idPrimerReto
            );

        self::assertSame(
            21,
            (int) $registro['puntaje_obtenido']
        );
    }

    public function test_rechaza_un_reto_deshabilitado(): void
    {
        $resultado =
            RetoProgresoService::completarReto(
                $this->idUsuario,
                $this->idRetoDeshabilitado,
                $this->idHabilidad,
                30,
                self::PUNTAJE_MINIMO,
                true
            );

        self::assertFalse(
            $resultado['ok']
        );

        self::assertSame(
            RetoProgresoService::RETO_NO_HABILITADO,
            $resultado['estado']
        );

        self::assertSame(
            0,
            $this->contarRegistrosReto(
                $this->idUsuario,
                $this->idRetoDeshabilitado
            )
        );
    }

    public function test_rechaza_un_reto_inexistente(): void
    {
        $idInexistente =
            PHP_INT_MAX;

        $resultado =
            RetoProgresoService::completarReto(
                $this->idUsuario,
                $idInexistente,
                $this->idHabilidad,
                21,
                self::PUNTAJE_MINIMO,
                true
            );

        self::assertFalse(
            $resultado['ok']
        );

        self::assertSame(
            RetoProgresoService::RETO_NO_EXISTE,
            $resultado['estado']
        );

        self::assertSame(
            0,
            $this->contarRegistrosReto(
                $this->idUsuario,
                $idInexistente
            )
        );
    }

    public function test_rechaza_una_habilidad_que_no_corresponde_al_reto(): void
    {
        $idOtraHabilidad =
            $this->crearHabilidad(
                'Habilidad incorrecta'
            );

        $this->crearProgresoInicial(
            $this->idUsuario,
            $idOtraHabilidad
        );

        $resultado =
            RetoProgresoService::completarReto(
                $this->idUsuario,
                $this->idPrimerReto,
                $idOtraHabilidad,
                21,
                self::PUNTAJE_MINIMO,
                true
            );

        self::assertFalse(
            $resultado['ok']
        );

        self::assertSame(
            RetoProgresoService::HABILIDAD_NO_COINCIDE,
            $resultado['estado']
        );

        self::assertSame(
            0,
            $this->contarRegistrosReto(
                $this->idUsuario,
                $this->idPrimerReto
            )
        );
    }

    public function test_solo_actualiza_la_habilidad_correspondiente(): void
    {
        $idOtraHabilidad =
            $this->crearHabilidad(
                'Habilidad sin cambios'
            );

        $this->crearReto(
            $idOtraHabilidad,
            'Reto de otra habilidad',
            1
        );

        $this->crearProgresoInicial(
            $this->idUsuario,
            $idOtraHabilidad
        );

        RetoProgresoService::completarReto(
            $this->idUsuario,
            $this->idPrimerReto,
            $this->idHabilidad,
            25,
            self::PUNTAJE_MINIMO,
            true
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
            'reto_'
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
            'Usuario Retos';

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
            . 'de integración de retos.';

        $tag =
            'Retos, Integración';

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

    private function crearReto(
        int $idHabilidad,
        string $nombre,
        int $habilitado
    ): int {
        $descripcion =
            'Reto temporal utilizado durante '
            . 'la prueba de integración.';

        $tag =
            'Reto, Integración';

        $tiempoMin = 5;
        $tiempoMax = 10;

        $puntos =
            self::PUNTAJE_MAXIMO;

        $dificultad = 1;

        $consulta = self::$db->prepare(
            'INSERT INTO retos (
                id_habilidades,
                nombre,
                descripcion,
                tag,
                tiempo_min,
                tiempo_max,
                puntos,
                dificultad,
                habilitado
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $consulta->bind_param(
            'isssiiiii',
            $idHabilidad,
            $nombre,
            $descripcion,
            $tag,
            $tiempoMin,
            $tiempoMax,
            $puntos,
            $dificultad,
            $habilitado
        );

        $consulta->execute();

        $idReto =
            (int) self::$db->insert_id;

        $consulta->close();

        return $idReto;
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

    private function obtenerRegistroReto(
        int $idUsuario,
        int $idReto
    ): ?array {
        $consulta = self::$db->prepare(
            'SELECT id_usuarios,
                    id_retos,
                    completado,
                    puntaje_obtenido
             FROM usuarios_retos
             WHERE id_usuarios = ?
               AND id_retos = ?
             LIMIT 1'
        );

        $consulta->bind_param(
            'ii',
            $idUsuario,
            $idReto
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

    private function contarRegistrosReto(
        int $idUsuario,
        int $idReto
    ): int {
        $consulta = self::$db->prepare(
            'SELECT COUNT(*) AS total
             FROM usuarios_retos
             WHERE id_usuarios = ?
               AND id_retos = ?'
        );

        $consulta->bind_param(
            'ii',
            $idUsuario,
            $idReto
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