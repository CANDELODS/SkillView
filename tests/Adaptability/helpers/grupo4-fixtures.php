<?php

declare(strict_types=1);

/**
 * Datos controlados para la prueba de adaptabilidad del grupo 4.
 *
 * Uso:
 *   php tests/Adaptability/helpers/grupo4-fixtures.php setup
 *   php tests/Adaptability/helpers/grupo4-fixtures.php cleanup
 *   php tests/Adaptability/helpers/grupo4-fixtures.php reset
 */

$projectRoot = dirname(__DIR__, 3);
$autoload = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "No se encontró vendor/autoload.php. Ejecuta composer install.\n");
    exit(1);
}

require_once $autoload;

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(
    $projectRoot . DIRECTORY_SEPARATOR . 'includes'
);
$dotenv->safeLoad();

$databaseName =
    getenv('ADAPT_DB_NAME')
    ?: getenv('TEST_DB_NAME')
    ?: ($_ENV['ADAPT_DB_NAME'] ?? null)
    ?: ($_ENV['TEST_DB_NAME'] ?? null)
    ?: 'skillview_test';

$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
$user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root';
$pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';

$db = new mysqli(
    (string) $host,
    (string) $user,
    (string) $pass,
    (string) $databaseName
);

if ($db->connect_errno) {
    fwrite(
        STDERR,
        "No fue posible conectarse a {$databaseName}: {$db->connect_error}\n"
    );
    exit(1);
}

$db->set_charset('utf8mb4');

const USER_EMAIL = 'e2e_adapt_conversacional@skillview.test';
const USER_PASSWORD = 'CajaNegra1!';
const SKILL_NAME = 'Adaptabilidad Conversacional E2E';
const LESSON_TITLE = 'Lección Conversacional E2E';
const CHALLENGE_NAME = 'Reto Conversacional E2E';

/**
 * Ejecuta una consulta preparada.
 *
 * @param array<int, mixed> $values
 */
function executePrepared(
    mysqli $db,
    string $sql,
    string $types = '',
    array $values = []
): mysqli_stmt {
    $statement = $db->prepare($sql);

    if (!$statement) {
        throw new RuntimeException(
            "No fue posible preparar la consulta: {$db->error}\nSQL: {$sql}"
        );
    }

    if ($types !== '') {
        $statement->bind_param($types, ...$values);
    }

    if (!$statement->execute()) {
        throw new RuntimeException(
            "No fue posible ejecutar la consulta: {$statement->error}\nSQL: {$sql}"
        );
    }

    return $statement;
}

/**
 * Devuelve la definición de columnas de una tabla.
 *
 * @return array<string, array<string, mixed>>
 */
function tableColumns(mysqli $db, string $table): array
{
    $safeTable = str_replace('`', '``', $table);
    $result = $db->query("SHOW COLUMNS FROM `{$safeTable}`");

    if (!$result) {
        throw new RuntimeException(
            "No fue posible consultar la estructura de {$table}: {$db->error}"
        );
    }

    $columns = [];

    while ($row = $result->fetch_assoc()) {
        $columns[(string) $row['Field']] = $row;
    }

    $result->free();

    return $columns;
}

/**
 * Completa valores obligatorios que no tengan default.
 *
 * @param array<string, mixed> $values
 * @param array<string, array<string, mixed>> $columns
 * @return array<string, mixed>
 */
function completeRequiredValues(array $values, array $columns): array
{
    foreach ($columns as $name => $definition) {
        $extra = strtolower((string) ($definition['Extra'] ?? ''));

        if ($name === 'id' || str_contains($extra, 'auto_increment')) {
            continue;
        }

        if (array_key_exists($name, $values)) {
            continue;
        }

        $nullable = strtoupper((string) ($definition['Null'] ?? 'NO')) === 'YES';
        $hasDefault = array_key_exists('Default', $definition)
            && $definition['Default'] !== null;

        if ($nullable || $hasDefault) {
            continue;
        }

        $type = strtolower((string) ($definition['Type'] ?? ''));

        if (preg_match('/int|decimal|float|double|bit|bool/', $type)) {
            $values[$name] = 0;
        } elseif (str_starts_with($type, 'date')) {
            $values[$name] = date('Y-m-d');
        } elseif (
            str_starts_with($type, 'datetime')
            || str_starts_with($type, 'timestamp')
        ) {
            $values[$name] = date('Y-m-d H:i:s');
        } elseif (str_starts_with($type, 'time')) {
            $values[$name] = date('H:i:s');
        } elseif (str_starts_with($type, 'year')) {
            $values[$name] = (int) date('Y');
        } else {
            $values[$name] = '';
        }
    }

    return $values;
}

/**
 * Inserta una fila usando únicamente columnas existentes.
 *
 * @param array<string, mixed> $values
 */
function insertAdaptiveRow(
    mysqli $db,
    string $table,
    array $values
): int {
    $columns = tableColumns($db, $table);
    $values = array_intersect_key($values, $columns);
    $values = completeRequiredValues($values, $columns);

    if ($values === []) {
        throw new RuntimeException(
            "No se encontraron columnas válidas para insertar en {$table}."
        );
    }

    $fieldNames = array_keys($values);
    $escapedFields = array_map(
        static fn(string $field): string => '`' . str_replace('`', '``', $field) . '`',
        $fieldNames
    );

    $placeholders = implode(', ', array_fill(0, count($fieldNames), '?'));
    $types = '';
    $parameters = [];

    foreach ($values as $value) {
        if (is_int($value) || is_bool($value)) {
            $types .= 'i';
            $parameters[] = (int) $value;
        } elseif (is_float($value)) {
            $types .= 'd';
            $parameters[] = $value;
        } else {
            $types .= 's';
            $parameters[] = (string) $value;
        }
    }

    $sql = sprintf(
        'INSERT INTO `%s` (%s) VALUES (%s)',
        str_replace('`', '``', $table),
        implode(', ', $escapedFields),
        $placeholders
    );

    $statement = executePrepared(
        $db,
        $sql,
        $types,
        $parameters
    );

    $insertId = (int) $db->insert_id;
    $statement->close();

    return $insertId;
}

function cleanup(mysqli $db, string $projectRoot): void
{
    $db->begin_transaction();

    try {
        $statement = executePrepared(
            $db,
            'DELETE FROM retos WHERE nombre = ?',
            's',
            [CHALLENGE_NAME]
        );
        $statement->close();

        $statement = executePrepared(
            $db,
            'DELETE FROM lecciones WHERE titulo = ?',
            's',
            [LESSON_TITLE]
        );
        $statement->close();

        $statement = executePrepared(
            $db,
            'DELETE FROM habilidades_blandas WHERE nombre = ?',
            's',
            [SKILL_NAME]
        );
        $statement->close();

        $statement = executePrepared(
            $db,
            'DELETE FROM usuarios WHERE correo = ?',
            's',
            [USER_EMAIL]
        );
        $statement->close();

        $db->commit();
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }

    $fixtureFile =
        $projectRoot
        . DIRECTORY_SEPARATOR
        . 'tests'
        . DIRECTORY_SEPARATOR
        . 'Adaptability'
        . DIRECTORY_SEPARATOR
        . '.grupo4-fixture.json';

    if (is_file($fixtureFile)) {
        unlink($fixtureFile);
    }
}

/**
 * @return array<string, mixed>
 */
function setup(mysqli $db, string $projectRoot): array
{
    cleanup($db, $projectRoot);

    $db->begin_transaction();

    try {
        $userId = insertAdaptiveRow(
            $db,
            'usuarios',
            [
                'nombres' => 'Adaptabilidad',
                'apellidos' => 'Conversacional',
                'edad' => 24,
                'sexo' => 0,
                'correo' => USER_EMAIL,
                'password' => password_hash(
                    USER_PASSWORD,
                    PASSWORD_DEFAULT
                ),
                'universidad' => 'Universidad de Prueba',
                'carrera' => 'Ingeniería de Sistemas',
                'admin' => 0,
                'debe_cambiar_password' => 0,
                'habilitado' => 1,
                'autoriza_tratamiento_datos' => 1,
            ]
        );

        $skillId = insertAdaptiveRow(
            $db,
            'habilidades_blandas',
            [
                'nombre' => SKILL_NAME,
                'descripcion' =>
                    'Habilidad creada únicamente para comprobar la adaptabilidad '
                    . 'de los flujos conversacionales.',
                'tag' => 'adaptabilidad,conversación,e2e',
                'habilitado' => 1,
            ]
        );

        $lessonId = insertAdaptiveRow(
            $db,
            'lecciones',
            [
                'id_habilidades' => $skillId,
                'titulo' => LESSON_TITLE,
                'descripcion' =>
                    'Objetivo: practicar una respuesta conversacional clara. '
                    . 'Micropráctica: describir una situación. '
                    . 'Minievaluación: proponer una mejora concreta.',
                'orden' => 999,
                'habilitado' => 1,
            ]
        );

        $challengeId = insertAdaptiveRow(
            $db,
            'retos',
            [
                'id_habilidades' => $skillId,
                'nombre' => CHALLENGE_NAME,
                'descripcion' =>
                    'Responder una situación simulada y recibir retroalimentación.',
                'tag' => 'adaptabilidad,conversación,e2e',
                'tiempo_min' => 5,
                'tiempo_max' => 10,
                'puntos' => 30,
                'dificultad' => 1,
                'habilitado' => 1,
            ]
        );

        $db->commit();
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }

    $fixture = [
        'database' => $GLOBALS['databaseName'] ?? 'skillview_test',
        'user' => [
            'id' => $userId,
            'email' => USER_EMAIL,
            'password' => USER_PASSWORD,
        ],
        'skillId' => $skillId,
        'lessonId' => $lessonId,
        'challengeId' => $challengeId,
    ];

    $fixtureFile =
        $projectRoot
        . DIRECTORY_SEPARATOR
        . 'tests'
        . DIRECTORY_SEPARATOR
        . 'Adaptability'
        . DIRECTORY_SEPARATOR
        . '.grupo4-fixture.json';

    $fixtureDirectory = dirname($fixtureFile);

    if (!is_dir($fixtureDirectory)) {
        mkdir($fixtureDirectory, 0777, true);
    }

    file_put_contents(
        $fixtureFile,
        json_encode(
            $fixture,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        )
    );

    return $fixture;
}

$command = strtolower((string) ($argv[1] ?? 'setup'));

try {
    if ($command === 'cleanup') {
        cleanup($db, $projectRoot);
        echo "Fixture del grupo 4 eliminado correctamente.\n";
    } elseif ($command === 'reset') {
        cleanup($db, $projectRoot);
        $fixture = setup($db, $projectRoot);
        echo json_encode(
            $fixture,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        ) . PHP_EOL;
    } elseif ($command === 'setup') {
        $fixture = setup($db, $projectRoot);
        echo json_encode(
            $fixture,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        ) . PHP_EOL;
    } else {
        fwrite(
            STDERR,
            "Comando no reconocido. Usa setup, cleanup o reset.\n"
        );
        exit(1);
    }
} catch (Throwable $error) {
    fwrite(
        STDERR,
        "Error al preparar el grupo 4: {$error->getMessage()}\n"
    );
    exit(1);
} finally {
    $db->close();
}