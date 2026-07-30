<?php

declare(strict_types=1);

namespace Tests\Integration;

use Classes\Paginacion;
use Model\Usuario;

require_once __DIR__
    . '/DatabaseIntegrationTestCase.php';

final class PaginacionBusquedaIntegrationTest extends
    DatabaseIntegrationTestCase
{
    private const REGISTROS_POR_PAGINA = 5;

    private int $totalUsuariosAntes;
    private string $terminoBusqueda;
    private string $passwordHash;
    private int $idUsuarioControl;

    /**
     * Nombres organizados alfabéticamente para comprobar
     * el ORDER BY nombres ASC utilizado por el sistema.
     *
     * @var array<int, string>
     */
    private array $nombresOrdenados = [
        'Adriana',
        'Beatriz',
        'Camila',
        'Daniela',
        'Elena',
        'Fernanda',
        'Gabriela',
        'Helena',
        'Isabel',
        'Juliana',
        'Karina',
        'Laura'
    ];

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Se conserva el total previo para verificar que
         * los registros insertados existan realmente.
         */
        $this->totalUsuariosAntes =
            (int) Usuario::total();

        /*
         * El término no contiene "_" ni "%", porque ambos
         * caracteres tienen significado especial en LIKE.
         */
        $this->terminoBusqueda =
            'paginacion'
            . bin2hex(random_bytes(4));

        $this->passwordHash =
            password_hash(
                'Clave1!',
                PASSWORD_BCRYPT
            );

        /*
         * Crear doce usuarios coincidentes.
         * El término se almacena en el correo.
         */
        foreach (
            $this->nombresOrdenados
            as $indice => $nombre
        ) {
            $numero =
                str_pad(
                    (string) ($indice + 1),
                    2,
                    '0',
                    STR_PAD_LEFT
                );

            $correo =
                $this->terminoBusqueda
                . $numero
                . '@skillview.test';

            $this->crearUsuario(
                $nombre,
                'Prueba Paginación',
                $correo
            );
        }

        /*
         * Este usuario permite comprobar que una cuenta
         * sin el término no aparezca en los resultados.
         */
        $this->idUsuarioControl =
            $this->crearUsuario(
                'Usuario',
                'Fuera de la búsqueda',
                'control'
                    . bin2hex(random_bytes(5))
                    . '@skillview.test'
            );
    }

    public function test_el_total_general_refleja_los_registros_insertados(): void
    {
        /*
         * Se insertaron doce usuarios coincidentes
         * y un usuario de control.
         */
        self::assertSame(
            $this->totalUsuariosAntes + 13,
            (int) Usuario::total()
        );
    }

    public function test_cuenta_y_recupera_todas_las_coincidencias_reales(): void
    {
        $totalCoincidencias =
            (int) Usuario::totalBusquedaUsuarios(
                $this->terminoBusqueda
            );

        self::assertSame(
            12,
            $totalCoincidencias
        );

        $usuarios =
            Usuario::buscarUsuarios(
                $this->terminoBusqueda
            );

        self::assertCount(
            12,
            $usuarios
        );

        $idsEncontrados = [];

        foreach ($usuarios as $usuario) {
            self::assertInstanceOf(
                Usuario::class,
                $usuario
            );

            self::assertStringContainsString(
                $this->terminoBusqueda,
                (string) $usuario->correo
            );

            $idsEncontrados[] =
                (int) $usuario->id;
        }

        self::assertNotContains(
            $this->idUsuarioControl,
            $idsEncontrados
        );
    }

    public function test_recupera_la_primera_pagina_con_cinco_registros(): void
    {
        $total =
            (int) Usuario::totalBusquedaUsuarios(
                $this->terminoBusqueda
            );

        $paginacion = new Paginacion(
            1,
            self::REGISTROS_POR_PAGINA,
            $total,
            'busqueda='
                . urlencode(
                    $this->terminoBusqueda
                )
        );

        $usuarios =
            Usuario::paginarBusquedaUsuarios(
                $this->terminoBusqueda,
                self::REGISTROS_POR_PAGINA,
                $paginacion->offset()
            );

        self::assertSame(
            0,
            $paginacion->offset()
        );

        self::assertSame(
            3,
            (int) $paginacion->totalPaginas()
        );

        self::assertCount(
            5,
            $usuarios
        );

        self::assertSame(
            array_slice(
                $this->nombresOrdenados,
                0,
                5
            ),
            $this->extraerNombres(
                $usuarios
            )
        );

        self::assertFalse(
            $paginacion->paginaAnterior()
        );

        self::assertSame(
            2,
            $paginacion->paginaSiguiente()
        );
    }

    public function test_recupera_la_segunda_pagina_sin_repetir_registros(): void
    {
        $total =
            (int) Usuario::totalBusquedaUsuarios(
                $this->terminoBusqueda
            );

        $primeraPagina = new Paginacion(
            1,
            self::REGISTROS_POR_PAGINA,
            $total
        );

        $segundaPagina = new Paginacion(
            2,
            self::REGISTROS_POR_PAGINA,
            $total
        );

        $usuariosPrimera =
            Usuario::paginarBusquedaUsuarios(
                $this->terminoBusqueda,
                self::REGISTROS_POR_PAGINA,
                $primeraPagina->offset()
            );

        $usuariosSegunda =
            Usuario::paginarBusquedaUsuarios(
                $this->terminoBusqueda,
                self::REGISTROS_POR_PAGINA,
                $segundaPagina->offset()
            );

        self::assertSame(
            5,
            $segundaPagina->offset()
        );

        self::assertCount(
            5,
            $usuariosSegunda
        );

        self::assertSame(
            array_slice(
                $this->nombresOrdenados,
                5,
                5
            ),
            $this->extraerNombres(
                $usuariosSegunda
            )
        );

        self::assertSame(
            [],
            array_values(
                array_intersect(
                    $this->extraerIds(
                        $usuariosPrimera
                    ),
                    $this->extraerIds(
                        $usuariosSegunda
                    )
                )
            )
        );

        self::assertSame(
            1,
            $segundaPagina->paginaAnterior()
        );

        self::assertSame(
            3,
            $segundaPagina->paginaSiguiente()
        );
    }

    public function test_recupera_dos_registros_en_la_ultima_pagina(): void
    {
        $total =
            (int) Usuario::totalBusquedaUsuarios(
                $this->terminoBusqueda
            );

        $paginacion = new Paginacion(
            3,
            self::REGISTROS_POR_PAGINA,
            $total
        );

        $usuarios =
            Usuario::paginarBusquedaUsuarios(
                $this->terminoBusqueda,
                self::REGISTROS_POR_PAGINA,
                $paginacion->offset()
            );

        self::assertSame(
            10,
            $paginacion->offset()
        );

        self::assertCount(
            2,
            $usuarios
        );

        self::assertSame(
            array_slice(
                $this->nombresOrdenados,
                10,
                2
            ),
            $this->extraerNombres(
                $usuarios
            )
        );

        self::assertSame(
            2,
            $paginacion->paginaAnterior()
        );

        self::assertFalse(
            $paginacion->paginaSiguiente()
        );
    }

    public function test_busca_registros_por_nombre_apellido_y_correo(): void
    {
        $terminoNombre =
            'Nombre'
            . $this->generarLetras(
                6
            );

        $terminoApellido =
            'Apellido'
            . $this->generarLetras(
                6
            );

        $terminoCorreo =
            'correo'
            . strtolower(
                $this->generarLetras(
                    6
                )
            );

        $idPorNombre =
            $this->crearUsuario(
                $terminoNombre,
                'Búsqueda Nombre',
                $this->correoAleatorio()
            );

        $idPorApellido =
            $this->crearUsuario(
                'Usuario Apellido',
                $terminoApellido,
                $this->correoAleatorio()
            );

        $idPorCorreo =
            $this->crearUsuario(
                'Usuario Correo',
                'Búsqueda Correo',
                $terminoCorreo
                    . '@skillview.test'
            );

        $casos = [
            $terminoNombre =>
                $idPorNombre,

            $terminoApellido =>
                $idPorApellido,

            $terminoCorreo =>
                $idPorCorreo
        ];

        foreach (
            $casos
            as $termino => $idEsperado
        ) {
            self::assertSame(
                1,
                (int) Usuario::
                    totalBusquedaUsuarios(
                        $termino
                    )
            );

            $resultados =
                Usuario::paginarBusquedaUsuarios(
                    $termino,
                    5,
                    0
                );

            self::assertCount(
                1,
                $resultados
            );

            self::assertSame(
                $idEsperado,
                (int) $resultados[0]->id
            );
        }
    }

    public function test_busca_un_nombre_con_caracteres_acentuados(): void
    {
        $termino =
            'Ámbito'
            . $this->generarLetras(
                6
            );

        $idUsuario =
            $this->crearUsuario(
                $termino,
                'Prueba UTF Ocho',
                $this->correoAleatorio()
            );

        self::assertSame(
            1,
            (int) Usuario::
                totalBusquedaUsuarios(
                    $termino
                )
        );

        $resultados =
            Usuario::paginarBusquedaUsuarios(
                $termino,
                5,
                0
            );

        self::assertCount(
            1,
            $resultados
        );

        self::assertSame(
            $idUsuario,
            (int) $resultados[0]->id
        );

        self::assertSame(
            $termino,
            $resultados[0]->nombres
        );
    }

    public function test_devuelve_una_coleccion_vacia_si_no_hay_coincidencias(): void
    {
        $terminoInexistente =
            'sinresultado'
            . bin2hex(
                random_bytes(8)
            );

        self::assertSame(
            0,
            (int) Usuario::
                totalBusquedaUsuarios(
                    $terminoInexistente
                )
        );

        self::assertSame(
            [],
            Usuario::paginarBusquedaUsuarios(
                $terminoInexistente,
                5,
                0
            )
        );
    }

    public function test_conserva_la_busqueda_en_los_enlaces_de_paginacion(): void
    {
        $total =
            (int) Usuario::totalBusquedaUsuarios(
                $this->terminoBusqueda
            );

        $terminoCodificado =
            urlencode(
                $this->terminoBusqueda
            );

        /*
         * Se incluyó un signo de interrogación al inicio
         * para comprobar la normalización de extraQuery.
         */
        $paginacion = new Paginacion(
            2,
            self::REGISTROS_POR_PAGINA,
            $total,
            '?busqueda='
                . $terminoCodificado
        );

        $html =
            $paginacion->paginacion();

        self::assertStringContainsString(
            '?page=1&busqueda='
                . $terminoCodificado,
            $html
        );

        self::assertStringContainsString(
            'paginacion__enlace--actual">2</span>',
            $html
        );

        self::assertStringContainsString(
            '?page=3&busqueda='
                . $terminoCodificado,
            $html
        );

        self::assertStringContainsString(
            'Anterior',
            $html
        );

        self::assertStringContainsString(
            'Siguiente',
            $html
        );
    }

    /**
     * Inserta un usuario real dentro de la transacción.
     */
    private function crearUsuario(
        string $nombres,
        string $apellidos,
        string $correo
    ): int {
        $edad = 24;
        $sexo = 3;

        $universidad =
            'Universidad del Cauca';

        $carrera =
            'Ingeniería de Sistemas';

        $admin = 0;
        $debeCambiarPassword = 0;
        $habilitado = 1;
        $token = '';
        $tokenExpiracion = 0;
        $autorizaDatos = 1;

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
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $consulta->bind_param(
            'ssiissssiiisii',
            $nombres,
            $apellidos,
            $edad,
            $sexo,
            $correo,
            $this->passwordHash,
            $universidad,
            $carrera,
            $admin,
            $debeCambiarPassword,
            $habilitado,
            $token,
            $tokenExpiracion,
            $autorizaDatos
        );

        $consulta->execute();

        $idUsuario =
            (int) self::$db->insert_id;

        $consulta->close();

        return $idUsuario;
    }

    /**
     * Extrae los nombres conservando el orden
     * devuelto por la consulta.
     *
     * @param array<int, Usuario> $usuarios
     * @return array<int, string>
     */
    private function extraerNombres(
        array $usuarios
    ): array {
        return array_map(
            static fn(Usuario $usuario): string =>
                (string) $usuario->nombres,
            $usuarios
        );
    }

    /**
     * Extrae los identificadores de una página.
     *
     * @param array<int, Usuario> $usuarios
     * @return array<int, int>
     */
    private function extraerIds(
        array $usuarios
    ): array {
        return array_map(
            static fn(Usuario $usuario): int =>
                (int) $usuario->id,
            $usuarios
        );
    }

    private function correoAleatorio(): string
    {
        return 'campo'
            . bin2hex(
                random_bytes(6)
            )
            . '@skillview.test';
    }

    /**
     * Genera letras para crear términos de búsqueda
     * válidos y diferentes en cada ejecución.
     */
    private function generarLetras(
        int $cantidad
    ): string {
        $resultado = '';

        for (
            $indice = 0;
            $indice < $cantidad;
            $indice++
        ) {
            $resultado .= chr(
                random_int(
                    65,
                    90
                )
            );
        }

        return $resultado;
    }
}