<?php

declare(strict_types=1);

namespace Tests\Unit;

use Classes\FuncionesAuxiliaresService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2)
    . '/includes/funciones.php';

final class FuncionesAuxiliaresServiceTest extends TestCase
{
    private bool $pathInfoExistia;
    private mixed $pathInfoOriginal;

    /**
     * Conserva PATH_INFO para evitar que las pruebas
     * modifiquen el entorno de los casos posteriores.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->pathInfoExistia = array_key_exists(
            'PATH_INFO',
            $_SERVER
        );

        $this->pathInfoOriginal =
            $_SERVER['PATH_INFO'] ?? null;
    }

    /**
     * Restaura PATH_INFO después de cada prueba.
     */
    protected function tearDown(): void
    {
        if ($this->pathInfoExistia) {
            $_SERVER['PATH_INFO'] =
                $this->pathInfoOriginal;
        } else {
            unset($_SERVER['PATH_INFO']);
        }

        parent::tearDown();
    }

    #[DataProvider('casosSanitizacion')]
    public function test_sanitiza_contenido_para_mostrarlo_en_html(
        mixed $entrada,
        string $salidaEsperada
    ): void {
        self::assertSame(
            $salidaEsperada,
            FuncionesAuxiliaresService::sanitizarHtml(
                $entrada
            )
        );
    }

    public static function casosSanitizacion(): array
    {
        return [
            'texto sin etiquetas' => [
                'SkillView',
                'SkillView'
            ],

            'etiquetas html' => [
                '<strong>Hola</strong>',
                '&lt;strong&gt;Hola&lt;/strong&gt;'
            ],

            'script con comillas' => [
                '<script>alert("x")</script>',
                '&lt;script&gt;alert(&quot;x&quot;)'
                    . '&lt;/script&gt;'
            ],

            'comillas y ampersand' => [
                'O\'Reilly & "SkillView"',
                'O&#039;Reilly &amp; '
                    . '&quot;SkillView&quot;'
            ],

            'caracteres utf ocho' => [
                'Comunicación y empatía',
                'Comunicación y empatía'
            ]
        ];
    }

    #[DataProvider('casosPaginaActual')]
    public function test_determina_si_una_ruta_es_la_pagina_actual(
        ?string $pathInfo,
        string $ruta,
        bool $resultadoEsperado
    ): void {
        self::assertSame(
            $resultadoEsperado,
            FuncionesAuxiliaresService::esPaginaActual(
                $pathInfo,
                $ruta
            )
        );
    }

    public static function casosPaginaActual(): array
    {
        return [
            'ruta exacta' => [
                '/blog',
                '/blog',
                true
            ],

            'subruta administrativa' => [
                '/admin/usuarios/editar',
                '/admin/usuarios',
                true
            ],

            'ruta con barra final' => [
                '/retos/reto',
                '/retos/',
                true
            ],

            'ruta diferente' => [
                '/blog',
                '/retos',
                false
            ],

            'prefijo parecido pero no relacionado' => [
                '/blogger',
                '/blog',
                false
            ],

            'path info inexistente' => [
                null,
                '/blog',
                false
            ],

            'ruta buscada vacía' => [
                '/blog',
                '',
                false
            ]
        ];
    }

    #[DataProvider('casosSesion')]
    public function test_valida_las_credenciales_basicas_de_la_sesion(
        array $sesion,
        bool $resultadoEsperado
    ): void {
        self::assertSame(
            $resultadoEsperado,
            FuncionesAuxiliaresService::
                sesionTieneCredencialesValidas(
                    $sesion
                )
        );
    }

    public static function casosSesion(): array
    {
        return [
            'id entero y correo válido' => [
                [
                    'id' => 5,
                    'correo' => 'usuario@correo.com'
                ],
                true
            ],

            'id numérico como cadena' => [
                [
                    'id' => '5',
                    'correo' => 'usuario@correo.com'
                ],
                true
            ],

            'sesión sin id' => [
                [
                    'correo' => 'usuario@correo.com'
                ],
                false
            ],

            'sesión sin correo' => [
                [
                    'id' => 5
                ],
                false
            ],

            'id igual a cero' => [
                [
                    'id' => 0,
                    'correo' => 'usuario@correo.com'
                ],
                false
            ],

            'id negativo' => [
                [
                    'id' => -1,
                    'correo' => 'usuario@correo.com'
                ],
                false
            ],

            'id no numérico' => [
                [
                    'id' => 'abc',
                    'correo' => 'usuario@correo.com'
                ],
                false
            ],

            'correo compuesto por espacios' => [
                [
                    'id' => 5,
                    'correo' => '   '
                ],
                false
            ]
        ];
    }

    #[DataProvider('casosDatosHeader')]
    public function test_construye_los_datos_del_encabezado(
        ?string $nombres,
        ?string $apellidos,
        array $resultadoEsperado
    ): void {
        self::assertSame(
            $resultadoEsperado,
            FuncionesAuxiliaresService::
                construirDatosHeader(
                    $nombres,
                    $apellidos
                )
        );
    }

    public static function casosDatosHeader(): array
    {
        return [
            'nombre y apellido simples' => [
                'Juan',
                'Pérez',
                [
                    'nombreUsuario' => 'Juan Pérez',
                    'inicialesUsuario' => 'JP'
                ]
            ],

            'varios nombres y apellidos' => [
                'Juan Carlos',
                'Reyes Quiceno',
                [
                    'nombreUsuario' => 'Juan Reyes',
                    'inicialesUsuario' => 'JR'
                ]
            ],

            'iniciales con acentos' => [
                'Óscar',
                'Álvarez',
                [
                    'nombreUsuario' => 'Óscar Álvarez',
                    'inicialesUsuario' => 'ÓÁ'
                ]
            ],

            'espacios tabulaciones y saltos' => [
                "  Laura\tSofía  ",
                "\nReyes   Quiceno  ",
                [
                    'nombreUsuario' => 'Laura Reyes',
                    'inicialesUsuario' => 'LR'
                ]
            ],

            'solo nombre' => [
                'Laura Sofía',
                '',
                [
                    'nombreUsuario' => 'Laura',
                    'inicialesUsuario' => 'L'
                ]
            ],

            'solo apellido' => [
                '',
                'Reyes Quiceno',
                [
                    'nombreUsuario' => 'Reyes',
                    'inicialesUsuario' => 'R'
                ]
            ],

            'campos vacíos' => [
                '',
                '',
                [
                    'nombreUsuario' => 'Juan Candelo',
                    'inicialesUsuario' => 'JC'
                ]
            ],

            'valores nulos' => [
                null,
                null,
                [
                    'nombreUsuario' => 'Juan Candelo',
                    'inicialesUsuario' => 'JC'
                ]
            ]
        ];
    }

    /**
     * Comprueba que la función global s()
     * utilice la regla centralizada.
     */
    public function test_la_funcion_s_aplica_la_sanitizacion(): void
    {
        self::assertSame(
            '&lt;b&gt;Texto&lt;/b&gt;',
            \s('<b>Texto</b>')
        );
    }

    /**
     * Comprueba el envoltorio global con una subruta.
     */
    public function test_pagina_actual_detecta_una_subruta(): void
    {
        $_SERVER['PATH_INFO'] =
            '/admin/usuarios/editar';

        self::assertTrue(
            \pagina_actual('/admin/usuarios')
        );
    }

    /**
     * La ausencia de PATH_INFO no debe producir
     * un warning ni un error.
     */
    public function test_pagina_actual_no_falla_si_path_info_no_existe(): void
    {
        unset($_SERVER['PATH_INFO']);

        self::assertFalse(
            \pagina_actual('/blog')
        );
    }
}