<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require_once dirname(__DIR__, 3)
    . '/vendor/autoload.php';

mysqli_report(
    MYSQLI_REPORT_ERROR
    | MYSQLI_REPORT_STRICT
);

$rutaProyecto = dirname(__DIR__, 3);

Dotenv::createImmutable(
    $rutaProyecto . '/includes'
)->safeLoad();

require $rutaProyecto
    . '/includes/database.php';

if (
    !isset($db)
    || !$db instanceof mysqli
) {
    fwrite(
        STDERR,
        "No fue posible conectar con MySQL.\n"
    );

    exit(1);
}

/*
 * Evita ejecutar las precondiciones sobre
 * la base principal del proyecto.
 */
$resultadoBase = $db->query(
    'SELECT DATABASE() AS database_name'
);

$filaBase = $resultadoBase->fetch_assoc();
$resultadoBase->free();

$baseActual =
    (string) (
        $filaBase['database_name']
        ?? ''
    );

if ($baseActual !== 'skillview_test') {
    fwrite(
        STDERR,
        "Ejecución cancelada. Base detectada: "
        . $baseActual
        . ". Se requiere skillview_test.\n"
    );

    exit(1);
}

/*
 * Datos generales del escenario.
 */
const PASSWORD_E2E =
    'CajaNegra1!';

const CORREO_USUARIO =
    'usuario@navtest.skillview.test';

const CORREO_ADMIN =
    'admin@navtest.skillview.test';

const DOMINIO_E2E =
    '%@navtest.skillview.test';

const MARCADOR_E2E =
    'E2E-Navegacion-Filtros';

const BUSQUEDA_USUARIOS =
    'NavPaginacionUsuario';

const BUSQUEDA_HABILIDADES =
    'NavPaginacionHab';

const HABILIDAD_COMUNICACION =
    'Comunicación Navegación E2E';

const HABILIDAD_LIDERAZGO =
    'Liderazgo Navegación E2E';

const RETO_COMUNICACION_BASICO =
    'Reto Comunicación Básico E2E';

const RETO_COMUNICACION_AVANZADO =
    'Reto Comunicación Avanzado E2E';

const RETO_LIDERAZGO_INTERMEDIO =
    'Reto Liderazgo Intermedio E2E';

const RETO_DESHABILITADO =
    'Reto Deshabilitado E2E';

const BLOG_COMUNICACION =
    'E2E Navegación Comunicación';

const BLOG_LIDERAZGO =
    'E2E Navegación Liderazgo';

const BLOG_COMPARTIDO =
    'E2E Navegación Compartido';

const BLOG_DESHABILITADO =
    'E2E Navegación Deshabilitado';

$comando = strtolower(
    trim(
        (string) (
            $argv[1]
            ?? ''
        )
    )
);

if (
    !in_array(
        $comando,
        [
            'reset',
            'cleanup'
        ],
        true
    )
) {
    fwrite(
        STDERR,
        "Usa: php navegacion-filtros-fixtures.php "
        . "reset|cleanup\n"
    );

    exit(1);
}

/**
 * Elimina exclusivamente los registros creados
 * para este escenario de Playwright.
 */
function limpiarDatosNavegacionE2E(
    mysqli $db
): void {
    $patronBlog =
        'E2E Navegación %';

    /*
     * Primero se eliminan las relaciones
     * entre blogs y habilidades.
     */
    $consultaRelaciones = $db->prepare(
        'DELETE bh
         FROM blog_habilidades bh
         INNER JOIN blog b
            ON b.id = bh.id_blog
         WHERE b.titulo LIKE ?'
    );

    $consultaRelaciones->bind_param(
        's',
        $patronBlog
    );

    $consultaRelaciones->execute();
    $consultaRelaciones->close();

    /*
     * Eliminar los artículos controlados.
     */
    $consultaBlogs = $db->prepare(
        'DELETE FROM blog
         WHERE titulo LIKE ?'
    );

    $consultaBlogs->bind_param(
        's',
        $patronBlog
    );

    $consultaBlogs->execute();
    $consultaBlogs->close();

    /*
     * Eliminar los retos controlados.
     */
    $patronMarcador =
        '%' . MARCADOR_E2E . '%';

    $consultaRetos = $db->prepare(
        'DELETE FROM retos
         WHERE tag LIKE ?'
    );

    $consultaRetos->bind_param(
        's',
        $patronMarcador
    );

    $consultaRetos->execute();
    $consultaRetos->close();

    /*
     * Eliminar las habilidades utilizadas
     * para filtros y paginación.
     */
    $consultaHabilidades = $db->prepare(
        'DELETE FROM habilidades_blandas
         WHERE tag LIKE ?'
    );

    $consultaHabilidades->bind_param(
        's',
        $patronMarcador
    );

    $consultaHabilidades->execute();
    $consultaHabilidades->close();

    /*
     * Eliminar todas las cuentas controladas.
     */
    $patronCorreo =
        DOMINIO_E2E;

    $consultaUsuarios = $db->prepare(
        'DELETE FROM usuarios
         WHERE correo LIKE ?'
    );

    $consultaUsuarios->bind_param(
        's',
        $patronCorreo
    );

    $consultaUsuarios->execute();
    $consultaUsuarios->close();
}

/**
 * Crea una cuenta general o administrativa.
 */
function crearUsuarioNavegacionE2E(
    mysqli $db,
    string $nombres,
    string $correo,
    int $admin
): int {
    $apellidos =
        'Prueba E2E';

    $edad = 24;
    $sexo = 3;

    $password = password_hash(
        PASSWORD_E2E,
        PASSWORD_BCRYPT
    );

    $universidad =
        'Universidad del Cauca';

    $carrera =
        'Ingeniería de Sistemas';

    $debeCambiarPassword = 0;
    $habilitado = 1;
    $token = '';
    $tokenExpiracion = 0;
    $autorizaDatos = 1;

    $consulta = $db->prepare(
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
         ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?
         )'
    );

    $consulta->bind_param(
        'ssiissssiiisii',
        $nombres,
        $apellidos,
        $edad,
        $sexo,
        $correo,
        $password,
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
        (int) $db->insert_id;

    $consulta->close();

    return $idUsuario;
}

/**
 * Crea una habilidad temporal.
 */
function crearHabilidadNavegacionE2E(
    mysqli $db,
    string $nombre,
    string $tipo
): int {
    $descripcion =
        'Habilidad temporal utilizada en '
        . 'las pruebas de navegación, filtros '
        . 'y paginación.';

    $tag =
        MARCADOR_E2E
        . ', '
        . $tipo;

    $habilitado = 1;

    $consulta = $db->prepare(
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
        (int) $db->insert_id;

    $consulta->close();

    return $idHabilidad;
}

/**
 * Crea un reto asociado con una habilidad.
 */
function crearRetoNavegacionE2E(
    mysqli $db,
    int $idHabilidad,
    string $nombre,
    int $dificultad,
    int $habilitado
): int {
    $descripcion =
        'Reto temporal utilizado para comprobar '
        . 'los filtros de SkillView.';

    $tag =
        MARCADOR_E2E
        . ', Reto';

    $tiempoMin = 5;
    $tiempoMax = 10;
    $puntos = 30;

    $consulta = $db->prepare(
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
         ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?
         )'
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
        (int) $db->insert_id;

    $consulta->close();

    return $idReto;
}

/**
 * Crea un artículo de blog.
 */
function crearBlogNavegacionE2E(
    mysqli $db,
    string $titulo,
    int $habilitado
): int {
    $descripcionCorta =
        'Artículo temporal utilizado para '
        . 'comprobar el filtrado del blog.';

    $contenido =
        '<p>Contenido controlado para las '
        . 'pruebas automatizadas.</p>';

    $imagen =
        'e2e-blog.webp';

    $consulta = $db->prepare(
        'INSERT INTO blog (
            titulo,
            descripcion_corta,
            contenido,
            imagen,
            habilitado
         ) VALUES (?, ?, ?, ?, ?)'
    );

    $consulta->bind_param(
        'ssssi',
        $titulo,
        $descripcionCorta,
        $contenido,
        $imagen,
        $habilitado
    );

    $consulta->execute();

    $idBlog =
        (int) $db->insert_id;

    $consulta->close();

    return $idBlog;
}

/**
 * Relaciona un artículo con una habilidad.
 */
function relacionarBlogHabilidadE2E(
    mysqli $db,
    int $idBlog,
    int $idHabilidad
): void {
    $consulta = $db->prepare(
        'INSERT INTO blog_habilidades (
            id_blog,
            id_habilidades
         ) VALUES (?, ?)'
    );

    $consulta->bind_param(
        'ii',
        $idBlog,
        $idHabilidad
    );

    $consulta->execute();
    $consulta->close();
}

try {
    $db->begin_transaction();

    limpiarDatosNavegacionE2E(
        $db
    );

    $ids = [];

    if ($comando === 'reset') {
        /*
         * Cuentas utilizadas para la navegación
         * pública y administrativa.
         */
        $ids['general'] =
            crearUsuarioNavegacionE2E(
                $db,
                'Usuario Navegacion',
                CORREO_USUARIO,
                0
            );

        $ids['admin'] =
            crearUsuarioNavegacionE2E(
                $db,
                'Administrador Nav',
                CORREO_ADMIN,
                1
            );

        /*
         * Doce usuarios para distribuirlos
         * en páginas de 5, 5 y 2 registros.
         */
        $ids['paginationUsers'] = [];

        for (
            $numero = 1;
            $numero <= 12;
            $numero++
        ) {
            $consecutivo =
                str_pad(
                    (string) $numero,
                    2,
                    '0',
                    STR_PAD_LEFT
                );

            $nombre =
                BUSQUEDA_USUARIOS
                . ' '
                . $consecutivo;

            $correo =
                'navuser'
                . $consecutivo
                . '@navtest.skillview.test';

            $ids['paginationUsers'][] =
                crearUsuarioNavegacionE2E(
                    $db,
                    $nombre,
                    $correo,
                    0
                );
        }

        /*
         * Habilidades utilizadas por los filtros.
         */
        $ids['skillCommunication'] =
            crearHabilidadNavegacionE2E(
                $db,
                HABILIDAD_COMUNICACION,
                'Filtro'
            );

        $ids['skillLeadership'] =
            crearHabilidadNavegacionE2E(
                $db,
                HABILIDAD_LIDERAZGO,
                'Filtro'
            );

        /*
         * Doce habilidades para la paginación.
         */
        $ids['paginationSkills'] = [];

        for (
            $numero = 1;
            $numero <= 12;
            $numero++
        ) {
            $consecutivo =
                str_pad(
                    (string) $numero,
                    2,
                    '0',
                    STR_PAD_LEFT
                );

            $nombre =
                BUSQUEDA_HABILIDADES
                . ' '
                . $consecutivo;

            $ids['paginationSkills'][] =
                crearHabilidadNavegacionE2E(
                    $db,
                    $nombre,
                    'Paginacion'
                );
        }

        /*
         * Retos habilitados y uno deshabilitado.
         */
        $ids['challengeBasic'] =
            crearRetoNavegacionE2E(
                $db,
                $ids['skillCommunication'],
                RETO_COMUNICACION_BASICO,
                1,
                1
            );

        $ids['challengeAdvanced'] =
            crearRetoNavegacionE2E(
                $db,
                $ids['skillCommunication'],
                RETO_COMUNICACION_AVANZADO,
                3,
                1
            );

        $ids['challengeIntermediate'] =
            crearRetoNavegacionE2E(
                $db,
                $ids['skillLeadership'],
                RETO_LIDERAZGO_INTERMEDIO,
                2,
                1
            );

        $ids['challengeDisabled'] =
            crearRetoNavegacionE2E(
                $db,
                $ids['skillCommunication'],
                RETO_DESHABILITADO,
                1,
                0
            );

        /*
         * Artículos individuales, compartido
         * y deshabilitado.
         */
        $ids['blogCommunication'] =
            crearBlogNavegacionE2E(
                $db,
                BLOG_COMUNICACION,
                1
            );

        $ids['blogLeadership'] =
            crearBlogNavegacionE2E(
                $db,
                BLOG_LIDERAZGO,
                1
            );

        $ids['blogShared'] =
            crearBlogNavegacionE2E(
                $db,
                BLOG_COMPARTIDO,
                1
            );

        $ids['blogDisabled'] =
            crearBlogNavegacionE2E(
                $db,
                BLOG_DESHABILITADO,
                0
            );

        relacionarBlogHabilidadE2E(
            $db,
            $ids['blogCommunication'],
            $ids['skillCommunication']
        );

        relacionarBlogHabilidadE2E(
            $db,
            $ids['blogLeadership'],
            $ids['skillLeadership']
        );

        /*
         * El artículo compartido pertenece
         * a las dos habilidades.
         */
        relacionarBlogHabilidadE2E(
            $db,
            $ids['blogShared'],
            $ids['skillCommunication']
        );

        relacionarBlogHabilidadE2E(
            $db,
            $ids['blogShared'],
            $ids['skillLeadership']
        );

        relacionarBlogHabilidadE2E(
            $db,
            $ids['blogDisabled'],
            $ids['skillCommunication']
        );
    }

    $db->commit();

    if ($comando === 'reset') {
        echo json_encode(
            $ids,
            JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
        );
    } else {
        echo "Datos E2E de navegación eliminados.\n";
    }
} catch (Throwable $error) {
    $db->rollback();

    fwrite(
        STDERR,
        $error->getMessage()
        . PHP_EOL
    );

    exit(1);
} finally {
    $db->close();
}