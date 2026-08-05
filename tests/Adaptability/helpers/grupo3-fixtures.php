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
        "No fue posible crear la conexión con MySQL.\n"
    );

    exit(1);
}

$resultadoBase = $db->query(
    'SELECT DATABASE() AS database_name'
);

$filaBase = $resultadoBase->fetch_assoc();
$resultadoBase->free();

$baseActual =
    (string) ($filaBase['database_name'] ?? '');

if ($baseActual !== 'skillview_test') {
    fwrite(
        STDERR,
        "Ejecución cancelada. Base detectada: "
        . $baseActual
        . ". Se requiere skillview_test.\n"
    );

    exit(1);
}

const PREFIJO_USUARIO =
    'adapt_cards_';

const PREFIJO_HABILIDAD =
    'Adaptabilidad Prueba ';

const PREFIJO_LECCION =
    'Lección Adaptabilidad ';

const PREFIJO_RETO =
    'Reto Adaptabilidad ';

const PREFIJO_BLOG =
    'Artículo Adaptabilidad ';

const PASSWORD_FIXTURE =
    'Adaptabilidad1!';

$comando = strtolower(
    trim((string) ($argv[1] ?? ''))
);

if (
    !in_array(
        $comando,
        ['reset', 'cleanup'],
        true
    )
) {
    fwrite(
        STDERR,
        "Usa: php grupo3-fixtures.php reset|cleanup\n"
    );

    exit(1);
}

/**
 * Ejecuta una sentencia preparada con un único
 * parámetro de texto.
 */
function ejecutarConTexto(
    mysqli $db,
    string $sql,
    string $valor
): void {
    $consulta = $db->prepare($sql);

    $consulta->bind_param(
        's',
        $valor
    );

    $consulta->execute();
    $consulta->close();
}

/**
 * Elimina todos los datos creados por este fixture.
 */
function limpiarDatosGrupo3(
    mysqli $db
): void {
    ejecutarConTexto(
        $db,
        'DELETE bh
         FROM blog_habilidades bh
         INNER JOIN blog b
            ON b.id = bh.id_blog
         WHERE b.titulo LIKE ?',
        PREFIJO_BLOG . '%'
    );

    ejecutarConTexto(
        $db,
        'DELETE FROM blog
         WHERE titulo LIKE ?',
        PREFIJO_BLOG . '%'
    );

    ejecutarConTexto(
        $db,
        'DELETE FROM retos
         WHERE nombre LIKE ?',
        PREFIJO_RETO . '%'
    );

    ejecutarConTexto(
        $db,
        'DELETE FROM lecciones
         WHERE titulo LIKE ?',
        PREFIJO_LECCION . '%'
    );

    ejecutarConTexto(
        $db,
        'DELETE FROM habilidades_blandas
         WHERE nombre LIKE ?',
        PREFIJO_HABILIDAD . '%'
    );

    ejecutarConTexto(
        $db,
        'DELETE FROM usuarios
         WHERE correo LIKE ?',
        PREFIJO_USUARIO . '%@skillview.test'
    );
}

/**
 * Crea doce usuarios para comprobar la paginación.
 */
function crearUsuarios(
    mysqli $db
): void {
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
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
         )'
    );

    for ($indice = 1; $indice <= 12; $indice++) {
        $numero = str_pad(
            (string) $indice,
            2,
            '0',
            STR_PAD_LEFT
        );

        $nombres =
            'Adaptabilidad Usuario ' . $numero;

        $apellidos =
            'Paginación E2E';

        $edad = 24;
        $sexo = 3;

        $correo =
            PREFIJO_USUARIO
            . $numero
            . '@skillview.test';

        $password = password_hash(
            PASSWORD_FIXTURE,
            PASSWORD_BCRYPT
        );

        $universidad =
            'Universidad del Cauca';

        $carrera =
            'Ingeniería de Sistemas';

        $admin = 0;
        $debeCambiar = 0;
        $habilitado = 1;
        $token = '';
        $tokenExpiracion = 0;
        $autorizaDatos = 1;

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
            $debeCambiar,
            $habilitado,
            $token,
            $tokenExpiracion,
            $autorizaDatos
        );

        $consulta->execute();
    }

    $consulta->close();
}

/**
 * Crea doce habilidades y devuelve sus identificadores.
 */
function crearHabilidades(
    mysqli $db
): array {
    $consulta = $db->prepare(
        'INSERT INTO habilidades_blandas (
            nombre,
            descripcion,
            tag,
            habilitado
         ) VALUES (?, ?, ?, ?)'
    );

    $ids = [];

    for ($indice = 1; $indice <= 12; $indice++) {
        $numero = str_pad(
            (string) $indice,
            2,
            '0',
            STR_PAD_LEFT
        );

        $nombre =
            PREFIJO_HABILIDAD . $numero;

        $descripcion =
            'Habilidad controlada para verificar '
            . 'la adaptación de tarjetas y listados.';

        $tag =
            'adaptabilidad,responsive,prueba';

        $habilitado = 1;

        $consulta->bind_param(
            'sssi',
            $nombre,
            $descripcion,
            $tag,
            $habilitado
        );

        $consulta->execute();

        $ids[] = (int) $db->insert_id;
    }

    $consulta->close();

    return $ids;
}

/**
 * Crea una lección para cada habilidad.
 */
function crearLecciones(
    mysqli $db,
    array $habilidadesIds
): void {
    $consulta = $db->prepare(
        'INSERT INTO lecciones (
            id_habilidades,
            titulo,
            descripcion,
            orden,
            habilitado
         ) VALUES (?, ?, ?, ?, ?)'
    );

    foreach ($habilidadesIds as $indice => $idHabilidad) {
        $numero = str_pad(
            (string) ($indice + 1),
            2,
            '0',
            STR_PAD_LEFT
        );

        $titulo =
            PREFIJO_LECCION . $numero;

        $descripcion =
            'Contenido controlado para la tarjeta '
            . 'de aprendizaje.';

        $orden = 1;
        $habilitado = 1;

        $consulta->bind_param(
            'issii',
            $idHabilidad,
            $titulo,
            $descripcion,
            $orden,
            $habilitado
        );

        $consulta->execute();
    }

    $consulta->close();
}

/**
 * Crea retos asociados con la primera habilidad.
 */
function crearRetos(
    mysqli $db,
    int $idHabilidad
): void {
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
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    for ($indice = 1; $indice <= 6; $indice++) {
        $numero = str_pad(
            (string) $indice,
            2,
            '0',
            STR_PAD_LEFT
        );

        $nombre =
            PREFIJO_RETO . $numero;

        $descripcion =
            'Reto controlado para comprobar la '
            . 'adaptación de tarjetas y filtros.';

        $tag =
            'adaptabilidad,comunicación,responsive';

        $tiempoMin = 5;
        $tiempoMax = 10;
        $puntos = 30;

        $dificultad =
            (($indice - 1) % 3) + 1;

        $habilitado = 1;

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
    }

    $consulta->close();
}

/**
 * Crea artículos y los relaciona con la primera habilidad.
 */
function crearBlogs(
    mysqli $db,
    int $idHabilidad
): void {
    $consultaBlog = $db->prepare(
        'INSERT INTO blog (
            titulo,
            descripcion_corta,
            contenido,
            imagen,
            habilitado
         ) VALUES (?, ?, ?, ?, ?)'
    );

    $consultaRelacion = $db->prepare(
        'INSERT INTO blog_habilidades (
            id_blog,
            id_habilidades
         ) VALUES (?, ?)'
    );

    for ($indice = 1; $indice <= 6; $indice++) {
        $numero = str_pad(
            (string) $indice,
            2,
            '0',
            STR_PAD_LEFT
        );

        $titulo =
            PREFIJO_BLOG . $numero;

        $descripcion =
            'Artículo controlado para revisar '
            . 'la distribución responsive del Blog.';

        $contenido =
            'Contenido de prueba utilizado únicamente '
            . 'en la base skillview_test.';

        $imagen =
            'blog/adaptabilidad.webp';

        $habilitado = 1;

        $consultaBlog->bind_param(
            'ssssi',
            $titulo,
            $descripcion,
            $contenido,
            $imagen,
            $habilitado
        );

        $consultaBlog->execute();

        $idBlog = (int) $db->insert_id;

        $consultaRelacion->bind_param(
            'ii',
            $idBlog,
            $idHabilidad
        );

        $consultaRelacion->execute();
    }

    $consultaBlog->close();
    $consultaRelacion->close();
}

try {
    $db->begin_transaction();

    limpiarDatosGrupo3($db);

    if ($comando === 'reset') {
        crearUsuarios($db);

        $habilidadesIds =
            crearHabilidades($db);

        crearLecciones(
            $db,
            $habilidadesIds
        );

        crearRetos(
            $db,
            $habilidadesIds[0]
        );

        crearBlogs(
            $db,
            $habilidadesIds[0]
        );
    }

    $db->commit();

    echo $comando === 'reset'
        ? "Datos del grupo 3 preparados correctamente.\n"
        : "Datos del grupo 3 eliminados correctamente.\n";
} catch (Throwable $error) {
    $db->rollback();

    fwrite(
        STDERR,
        $error->getMessage() . PHP_EOL
    );

    exit(1);
} finally {
    $db->close();
}