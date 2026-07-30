<?php

declare(strict_types=1);

namespace Tests\Integration;

use Model\Logros;
use Model\usuarios_lecciones;
use Model\usuarios_logros;
use Model\usuarios_retos;
use RuntimeException;

require_once __DIR__
    . '/DatabaseIntegrationTestCase.php';

final class LogrosAsignacionIntegrationTest extends
    DatabaseIntegrationTestCase
{
    private const ICONO_HABILIDAD =
        'logros/habilidad_autoconfianza';

    private const ICONO_DESEMPENO =
        'logros/desempeno_autoconfianza';

    private const PUNTAJE_OBJETIVO = 21;

    private int $idUsuario;
    private int $idHabilidad;
    private int $idPrimeraLeccion;
    private int $idSegundaLeccion;
    private int $idLeccionDeshabilitada;
    private int $idReto;
    private int $idLogroHabilidad;
    private int $idLogroDesempeno;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Se deshabilitan temporalmente los logros
         * existentes con los mismos iconos para que
         * la prueba evalúe únicamente sus datos.
         *
         * El rollback restaurará los valores originales.
         */
        $this->deshabilitarLogrosExistentes();

        $this->idUsuario =
            $this->crearUsuario();

        /*
         * El nombre debe coincidir con el mapa utilizado
         * por LogroService y el modelo Logros.
         */
        $this->idHabilidad =
            $this->crearHabilidad();

        $this->idPrimeraLeccion =
            $this->crearLeccion(
                'Primera lección',
                1,
                1
            );

        $this->idSegundaLeccion =
            $this->crearLeccion(
                'Segunda lección',
                2,
                1
            );

        $this->idLeccionDeshabilitada =
            $this->crearLeccion(
                'Lección deshabilitada',
                3,
                0
            );

        $this->idReto =
            $this->crearReto();

        $this->idLogroHabilidad =
            $this->crearLogro(
                'Dominio de Autoconfianza',
                self::ICONO_HABILIDAD,
                1,
                100,
                1
            );

        $this->idLogroDesempeno =
            $this->crearLogro(
                'Desempeño en Autoconfianza',
                self::ICONO_DESEMPENO,
                4,
                self::PUNTAJE_OBJETIVO,
                1
            );
    }

    public function test_no_asigna_el_logro_si_existen_lecciones_pendientes(): void
    {
        $this->completarLeccion(
            $this->idPrimeraLeccion
        );

        $logrosNuevos =
            Logros::evaluarYAsignarNuevosPorLeccion(
                $this->idUsuario
            );

        self::assertSame(
            [],
            $logrosNuevos
        );

        self::assertSame(
            0,
            $this->contarLogroUsuario(
                $this->idUsuario,
                $this->idLogroHabilidad
            )
        );
    }

    public function test_asigna_el_logro_al_completar_todas_las_lecciones_habilitadas(): void
    {
        $this->completarLeccionesHabilitadas();

        $logrosNuevos =
            Logros::evaluarYAsignarNuevosPorLeccion(
                $this->idUsuario
            );

        self::assertCount(
            1,
            $logrosNuevos
        );

        self::assertSame(
            $this->idLogroHabilidad,
            (int) $logrosNuevos[0]->id
        );

        self::assertSame(
            1,
            $this->contarLogroUsuario(
                $this->idUsuario,
                $this->idLogroHabilidad
            )
        );

        $registro =
            $this->obtenerRegistroLogro(
                $this->idUsuario,
                $this->idLogroHabilidad
            );

        self::assertNotNull(
            $registro
        );

        self::assertSame(
            date('Y-m-d'),
            $registro['fecha_obtenido']
        );

        /*
         * La lección deshabilitada no fue completada,
         * pero tampoco impidió conceder el logro.
         */
        self::assertSame(
            0,
            $this->contarRegistroLeccion(
                $this->idUsuario,
                $this->idLeccionDeshabilitada
            )
        );
    }

    public function test_no_duplica_un_logro_de_habilidad_ya_asignado(): void
    {
        $this->completarLeccionesHabilitadas();

        $primerResultado =
            Logros::evaluarYAsignarNuevosPorLeccion(
                $this->idUsuario
            );

        $segundoResultado =
            Logros::evaluarYAsignarNuevosPorLeccion(
                $this->idUsuario
            );

        self::assertCount(
            1,
            $primerResultado
        );

        /*
         * En la segunda evaluación no se devolvieron
         * logros nuevos.
         */
        self::assertSame(
            [],
            $segundoResultado
        );

        self::assertSame(
            1,
            $this->contarLogroUsuario(
                $this->idUsuario,
                $this->idLogroHabilidad
            )
        );

        self::assertTrue(
            usuarios_logros::existeLogroUsuario(
                $this->idUsuario,
                $this->idLogroHabilidad
            )
        );
    }

    public function test_no_asigna_un_logro_deshabilitado(): void
    {
        $this->actualizarEstadoLogro(
            $this->idLogroHabilidad,
            0
        );

        $this->completarLeccionesHabilitadas();

        $logrosNuevos =
            Logros::evaluarYAsignarNuevosPorLeccion(
                $this->idUsuario
            );

        self::assertSame(
            [],
            $logrosNuevos
        );

        self::assertSame(
            0,
            $this->contarLogroUsuario(
                $this->idUsuario,
                $this->idLogroHabilidad
            )
        );
    }

    public function test_asigna_el_logro_de_desempeno_al_alcanzar_el_objetivo(): void
    {
        $this->completarReto(
            self::PUNTAJE_OBJETIVO
        );

        $logrosNuevos =
            Logros::evaluarYAsignarNuevosPorReto(
                $this->idUsuario,
                $this->idReto
            );

        self::assertCount(
            1,
            $logrosNuevos
        );

        self::assertSame(
            $this->idLogroDesempeno,
            (int) $logrosNuevos[0]->id
        );

        self::assertSame(
            1,
            $this->contarLogroUsuario(
                $this->idUsuario,
                $this->idLogroDesempeno
            )
        );

        $registro =
            $this->obtenerRegistroLogro(
                $this->idUsuario,
                $this->idLogroDesempeno
            );

        self::assertSame(
            date('Y-m-d'),
            $registro['fecha_obtenido']
        );

        $lookup =
            usuarios_logros::lookupLogrosUsuario(
                $this->idUsuario
            );

        self::assertArrayHasKey(
            $this->idLogroDesempeno,
            $lookup
        );

        self::assertSame(
            date('Y-m-d'),
            $lookup[$this->idLogroDesempeno]
        );
    }

    public function test_no_asigna_logro_con_puntaje_inferior_al_objetivo(): void
    {
        $this->completarReto(
            self::PUNTAJE_OBJETIVO - 1
        );

        $logrosNuevos =
            Logros::evaluarYAsignarNuevosPorReto(
                $this->idUsuario,
                $this->idReto
            );

        self::assertSame(
            [],
            $logrosNuevos
        );

        self::assertSame(
            0,
            $this->contarLogroUsuario(
                $this->idUsuario,
                $this->idLogroDesempeno
            )
        );
    }

    public function test_no_asigna_logro_si_el_reto_no_fue_completado(): void
    {
        /*
         * No se crea ningún registro en usuarios_retos.
         */
        $logrosNuevos =
            Logros::evaluarYAsignarNuevosPorReto(
                $this->idUsuario,
                $this->idReto
            );

        self::assertSame(
            [],
            $logrosNuevos
        );

        self::assertSame(
            0,
            $this->contarLogroUsuario(
                $this->idUsuario,
                $this->idLogroDesempeno
            )
        );
    }

    public function test_no_duplica_un_logro_de_desempeno_ya_asignado(): void
    {
        $this->completarReto(
            self::PUNTAJE_OBJETIVO + 5
        );

        $primerResultado =
            Logros::evaluarYAsignarNuevosPorReto(
                $this->idUsuario,
                $this->idReto
            );

        $segundoResultado =
            Logros::evaluarYAsignarNuevosPorReto(
                $this->idUsuario,
                $this->idReto
            );

        self::assertCount(
            1,
            $primerResultado
        );

        self::assertSame(
            [],
            $segundoResultado
        );

        self::assertSame(
            1,
            $this->contarLogroUsuario(
                $this->idUsuario,
                $this->idLogroDesempeno
            )
        );
    }

    public function test_mantiene_los_logros_independientes_entre_usuarios(): void
    {
        $this->completarLeccionesHabilitadas();

        Logros::evaluarYAsignarNuevosPorLeccion(
            $this->idUsuario
        );

        $idSegundoUsuario =
            $this->crearUsuario();

        self::assertSame(
            1,
            $this->contarLogroUsuario(
                $this->idUsuario,
                $this->idLogroHabilidad
            )
        );

        self::assertSame(
            0,
            $this->contarLogroUsuario(
                $idSegundoUsuario,
                $this->idLogroHabilidad
            )
        );

        self::assertFalse(
            usuarios_logros::existeLogroUsuario(
                $idSegundoUsuario,
                $this->idLogroHabilidad
            )
        );

        $idsPrimerUsuario =
            usuarios_logros::idsLogrosUsuario(
                $this->idUsuario
            );

        $idsSegundoUsuario =
            usuarios_logros::idsLogrosUsuario(
                $idSegundoUsuario
            );

        self::assertContains(
            $this->idLogroHabilidad,
            $idsPrimerUsuario
        );

        self::assertNotContains(
            $this->idLogroHabilidad,
            $idsSegundoUsuario
        );
    }

    private function completarLeccionesHabilitadas(): void
    {
        $this->completarLeccion(
            $this->idPrimeraLeccion
        );

        $this->completarLeccion(
            $this->idSegundaLeccion
        );
    }

    private function completarLeccion(
        int $idLeccion
    ): void {
        $resultado =
            usuarios_lecciones::marcarComoCompletada(
                $this->idUsuario,
                $idLeccion
            );

        if (!$resultado) {
            throw new RuntimeException(
                'No fue posible registrar la lección '
                . 'durante la prueba de logros.'
            );
        }
    }

    private function completarReto(
        int $puntaje
    ): void {
        $resultado =
            usuarios_retos::marcarComoCompletado(
                $this->idUsuario,
                $this->idReto,
                $puntaje
            );

        if (!$resultado) {
            throw new RuntimeException(
                'No fue posible registrar el reto '
                . 'durante la prueba de logros.'
            );
        }
    }

    private function crearUsuario(): int
    {
        $correo =
            'logros_'
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
            'Usuario Logros';

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

    private function crearHabilidad(): int
    {
        $nombre =
            'Autoconfianza';

        $descripcion =
            'Habilidad temporal utilizada para '
            . 'evaluar la asignación de logros.';

        $tag =
            'Autoconfianza, Integración';

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
        string $titulo,
        int $orden,
        int $habilitado
    ): int {
        $descripcion =
            'Lección temporal utilizada en '
            . 'la prueba de integración de logros.';

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
            $this->idHabilidad,
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

    private function crearReto(): int
    {
        $nombre =
            'Reto de autoconfianza';

        $descripcion =
            'Reto temporal utilizado en '
            . 'la prueba de integración de logros.';

        $tag =
            'Autoconfianza, Integración';

        $tiempoMin = 5;
        $tiempoMax = 10;
        $puntos = 30;
        $dificultad = 1;
        $habilitado = 1;

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
            $this->idHabilidad,
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

    private function crearLogro(
        string $nombre,
        string $icono,
        int $tipo,
        int $valorObjetivo,
        int $habilitado
    ): int {
        $descripcion =
            'Logro temporal utilizado en '
            . 'una prueba de integración.';

        $consulta = self::$db->prepare(
            'INSERT INTO logros (
                nombre,
                descripcion,
                icono,
                tipo,
                valor_objetivo,
                habilitado
             ) VALUES (?, ?, ?, ?, ?, ?)'
        );

        $consulta->bind_param(
            'sssiii',
            $nombre,
            $descripcion,
            $icono,
            $tipo,
            $valorObjetivo,
            $habilitado
        );

        $consulta->execute();

        $idLogro =
            (int) self::$db->insert_id;

        $consulta->close();

        return $idLogro;
    }

    private function deshabilitarLogrosExistentes(): void
    {
        $consulta = self::$db->prepare(
            'UPDATE logros
             SET habilitado = 0
             WHERE icono IN (?, ?)'
        );

        $iconoHabilidad =
            self::ICONO_HABILIDAD;

        $iconoDesempeno =
            self::ICONO_DESEMPENO;

        $consulta->bind_param(
            'ss',
            $iconoHabilidad,
            $iconoDesempeno
        );

        $consulta->execute();
        $consulta->close();
    }

    private function actualizarEstadoLogro(
        int $idLogro,
        int $habilitado
    ): void {
        $consulta = self::$db->prepare(
            'UPDATE logros
             SET habilitado = ?
             WHERE id = ?
             LIMIT 1'
        );

        $consulta->bind_param(
            'ii',
            $habilitado,
            $idLogro
        );

        $consulta->execute();
        $consulta->close();
    }

    private function contarLogroUsuario(
        int $idUsuario,
        int $idLogro
    ): int {
        $consulta = self::$db->prepare(
            'SELECT COUNT(*) AS total
             FROM usuarios_logros
             WHERE id_usuarios = ?
               AND id_logros = ?'
        );

        $consulta->bind_param(
            'ii',
            $idUsuario,
            $idLogro
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

    private function obtenerRegistroLogro(
        int $idUsuario,
        int $idLogro
    ): ?array {
        $consulta = self::$db->prepare(
            'SELECT id_usuarios,
                    id_logros,
                    fecha_obtenido
             FROM usuarios_logros
             WHERE id_usuarios = ?
               AND id_logros = ?
             LIMIT 1'
        );

        $consulta->bind_param(
            'ii',
            $idUsuario,
            $idLogro
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

    private function contarRegistroLeccion(
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
}