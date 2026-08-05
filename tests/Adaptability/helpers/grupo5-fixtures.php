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

const GENERAL_EMAIL =
    'e2e_auth_general@skillview.test';

const ADMIN_EMAIL =
    'e2e_auth_admin@skillview.test';

const GENERAL_PASSWORD =
    'CajaNegra1!';

const PREFIX_SKILL =
    'Adaptabilidad Progreso ';

const LESSON_TITLE =
    'Lección Modal Adaptabilidad E2E';

const CHALLENGE_NAME =
    'Reto Modal Adaptabilidad E2E';

const PREFIX_ACHIEVEMENT =
    'Logro Adaptabilidad E2E ';

$comando = strtolower(
    trim((string) ($argv[1] ?? ''))
);

if (
    !in_array(
        $comando,
        ['setup', 'reset', 'cleanup'],
        true
    )
) {
    fwrite(
        STDERR,
        "Usa: php grupo5-fixtures.php setup|reset|cleanup\n"
    );

    exit(1);
}

/**
 * Devuelve la definición de columnas de una tabla.
 *
 * @return array<string, array<string, mixed>>
 */
function columnasTabla(
    mysqli $db,
    string $tabla
): array {
    $tablaSegura =
        str_replace(
            '`',
            '``',
            $tabla
        );

    $resultado =
        $db->query(
            "SHOW COLUMNS FROM `{$tablaSegura}`"
        );

    $columnas = [];

    while (
        $fila =
            $resultado->fetch_assoc()
    ) {
        $columnas[
            (string) $fila['Field']
        ] = $fila;
    }

    $resultado->free();

    return $columnas;
}

/**
 * Completa columnas obligatorias sin valor predeterminado.
 *
 * @param array<string, mixed> $valores
 * @param array<string, array<string, mixed>> $columnas
 * @return array<string, mixed>
 */
function completarColumnasObligatorias(
    array $valores,
    array $columnas
): array {
    foreach (
        $columnas
        as $nombre => $definicion
    ) {
        $extra =
            strtolower(
                (string) (
                    $definicion['Extra']
                    ?? ''
                )
            );

        if (
            $nombre === 'id'
            || str_contains(
                $extra,
                'auto_increment'
            )
        ) {
            continue;
        }

        if (
            array_key_exists(
                $nombre,
                $valores
            )
        ) {
            continue;
        }

        $permiteNulo =
            strtoupper(
                (string) (
                    $definicion['Null']
                    ?? 'NO'
                )
            ) === 'YES';

        $tieneDefault =
            array_key_exists(
                'Default',
                $definicion
            )
            && $definicion['Default']
                !== null;

        if (
            $permiteNulo
            || $tieneDefault
        ) {
            continue;
        }

        $tipo =
            strtolower(
                (string) (
                    $definicion['Type']
                    ?? ''
                )
            );

        if (
            preg_match(
                '/int|decimal|float|double|bit|bool/',
                $tipo
            )
        ) {
            $valores[$nombre] = 0;
        } elseif (
            str_starts_with(
                $tipo,
                'date'
            )
        ) {
            $valores[$nombre] =
                date('Y-m-d');
        } elseif (
            str_starts_with(
                $tipo,
                'datetime'
            )
            || str_starts_with(
                $tipo,
                'timestamp'
            )
        ) {
            $valores[$nombre] =
                date('Y-m-d H:i:s');
        } elseif (
            str_starts_with(
                $tipo,
                'time'
            )
        ) {
            $valores[$nombre] =
                date('H:i:s');
        } elseif (
            str_starts_with(
                $tipo,
                'year'
            )
        ) {
            $valores[$nombre] =
                (int) date('Y');
        } else {
            $valores[$nombre] = '';
        }
    }

    return $valores;
}

/**
 * Inserta una fila usando únicamente columnas existentes.
 *
 * @param array<string, mixed> $valores
 */
function insertarFilaAdaptable(
    mysqli $db,
    string $tabla,
    array $valores
): int {
    $columnas =
        columnasTabla(
            $db,
            $tabla
        );

    $valores =
        array_intersect_key(
            $valores,
            $columnas
        );

    $valores =
        completarColumnasObligatorias(
            $valores,
            $columnas
        );

    if ($valores === []) {
        throw new RuntimeException(
            "No se encontraron columnas válidas para {$tabla}."
        );
    }

    $nombres =
        array_keys(
            $valores
        );

    $campos =
        array_map(
            static fn (
                string $nombre
            ): string =>
                '`'
                . str_replace(
                    '`',
                    '``',
                    $nombre
                )
                . '`',
            $nombres
        );

    $marcadores =
        implode(
            ', ',
            array_fill(
                0,
                count($nombres),
                '?'
            )
        );

    $tipos = '';
    $parametros = [];

    foreach (
        $valores
        as $valor
    ) {
        if (
            is_int($valor)
            || is_bool($valor)
        ) {
            $tipos .= 'i';
            $parametros[] =
                (int) $valor;
        } elseif (
            is_float($valor)
        ) {
            $tipos .= 'd';
            $parametros[] =
                $valor;
        } else {
            $tipos .= 's';
            $parametros[] =
                (string) $valor;
        }
    }

    $sql =
        'INSERT INTO `'
        . str_replace(
            '`',
            '``',
            $tabla
        )
        . '` ('
        . implode(
            ', ',
            $campos
        )
        . ') VALUES ('
        . $marcadores
        . ')';

    $consulta =
        $db->prepare(
            $sql
        );

    $consulta->bind_param(
        $tipos,
        ...$parametros
    );

    $consulta->execute();

    $id =
        (int) $db->insert_id;

    $consulta->close();

    return $id;
}

/**
 * Busca una columna entre varios nombres posibles.
 *
 * @param string[] $candidatas
 */
function encontrarColumna(
    mysqli $db,
    string $tabla,
    array $candidatas
): ?string {
    $columnas =
        columnasTabla(
            $db,
            $tabla
        );

    foreach (
        $candidatas
        as $candidata
    ) {
        if (
            array_key_exists(
                $candidata,
                $columnas
            )
        ) {
            return $candidata;
        }
    }

    return null;
}

/**
 * Busca el ID de un usuario controlado.
 */
function obtenerUsuarioId(
    mysqli $db,
    string $correo
): int {
    $consulta =
        $db->prepare(
            'SELECT id
             FROM usuarios
             WHERE correo = ?
             LIMIT 1'
        );

    $consulta->bind_param(
        's',
        $correo
    );

    $consulta->execute();

    $resultado =
        $consulta->get_result();

    $fila =
        $resultado->fetch_assoc();

    $resultado->free();
    $consulta->close();

    $id =
        (int) (
            $fila['id']
            ?? 0
        );

    if ($id <= 0) {
        throw new RuntimeException(
            "No se encontró el usuario {$correo}. "
            . "Ejecuta primero auth-fixtures.php reset."
        );
    }

    return $id;
}

/**
 * Elimina datos creados por este fixture.
 */
function limpiarGrupo5(
    mysqli $db
): void {
    $logroIdCol =
        encontrarColumna(
            $db,
            'usuarios_logros',
            [
                'id_logros',
                'id_logro',
                'logro_id'
            ]
        );

    if ($logroIdCol) {
        $db->query(
            "DELETE ul
             FROM usuarios_logros ul
             INNER JOIN logros l
                ON l.id = ul.`{$logroIdCol}`
             WHERE l.nombre LIKE '"
             . PREFIX_ACHIEVEMENT
             . "%'"
        );
    }

    $habilidadIdCol =
        encontrarColumna(
            $db,
            'usuarios_habilidades',
            [
                'id_habilidades',
                'id_habilidad',
                'habilidad_id'
            ]
        );

    if ($habilidadIdCol) {
        $db->query(
            "DELETE uh
             FROM usuarios_habilidades uh
             INNER JOIN habilidades_blandas h
                ON h.id = uh.`{$habilidadIdCol}`
             WHERE h.nombre LIKE '"
             . PREFIX_SKILL
             . "%'"
        );
    }

    $consulta =
        $db->prepare(
            'DELETE FROM retos
             WHERE nombre = ?'
        );

    $nombreReto =
        CHALLENGE_NAME;

    $consulta->bind_param(
        's',
        $nombreReto
    );

    $consulta->execute();
    $consulta->close();

    $consulta =
        $db->prepare(
            'DELETE FROM lecciones
             WHERE titulo = ?'
        );

    $tituloLeccion =
        LESSON_TITLE;

    $consulta->bind_param(
        's',
        $tituloLeccion
    );

    $consulta->execute();
    $consulta->close();

    $patronLogro =
        PREFIX_ACHIEVEMENT
        . '%';

    $consulta =
        $db->prepare(
            'DELETE FROM logros
             WHERE nombre LIKE ?'
        );

    $consulta->bind_param(
        's',
        $patronLogro
    );

    $consulta->execute();
    $consulta->close();

    $patronHabilidad =
        PREFIX_SKILL
        . '%';

    $consulta =
        $db->prepare(
            'DELETE FROM habilidades_blandas
             WHERE nombre LIKE ?'
        );

    $consulta->bind_param(
        's',
        $patronHabilidad
    );

    $consulta->execute();
    $consulta->close();
}

/**
 * Registra progreso para una habilidad.
 */
function crearProgreso(
    mysqli $db,
    int $idUsuario,
    int $idHabilidad,
    int $nivel,
    int $progreso
): void {
    insertarFilaAdaptable(
        $db,
        'usuarios_habilidades',
        [
            'id_usuarios' =>
                $idUsuario,

            'id_usuario' =>
                $idUsuario,

            'usuario_id' =>
                $idUsuario,

            'id_habilidades' =>
                $idHabilidad,

            'id_habilidad' =>
                $idHabilidad,

            'habilidad_id' =>
                $idHabilidad,

            'nivel' =>
                $nivel,

            'progreso' =>
                $progreso,

            'ultima_actualizacion' =>
                '2026-08-04'
        ]
    );
}

/**
 * Asigna un logro al usuario.
 */
function asignarLogro(
    mysqli $db,
    int $idUsuario,
    int $idLogro,
    string $fecha
): void {
    insertarFilaAdaptable(
        $db,
        'usuarios_logros',
        [
            'id_usuarios' =>
                $idUsuario,

            'id_usuario' =>
                $idUsuario,

            'usuario_id' =>
                $idUsuario,

            'id_logros' =>
                $idLogro,

            'id_logro' =>
                $idLogro,

            'logro_id' =>
                $idLogro,

            'fecha_obtenido' =>
                $fecha,

            'fecha' =>
                $fecha,

            'created_at' =>
                $fecha
        ]
    );
}

/**
 * Crea todos los datos controlados del escenario.
 *
 * @return array<string, mixed>
 */
function prepararGrupo5(
    mysqli $db
): array {
    limpiarGrupo5(
        $db
    );

    $idUsuario =
        obtenerUsuarioId(
            $db,
            GENERAL_EMAIL
        );

    $idAdmin =
        obtenerUsuarioId(
            $db,
            ADMIN_EMAIL
        );

    $habilidades = [
        [
            'nombre' =>
                PREFIX_SKILL . '33',

            'progreso' =>
                33,

            'nivel' =>
                1
        ],
        [
            'nombre' =>
                PREFIX_SKILL . '50',

            'progreso' =>
                50,

            'nivel' =>
                2
        ],
        [
            'nombre' =>
                PREFIX_SKILL . '100',

            'progreso' =>
                100,

            'nivel' =>
                3
        ],
    ];

    $habilidadesIds = [];

    foreach (
        $habilidades
        as $indice => $habilidad
    ) {
        $idHabilidad =
            insertarFilaAdaptable(
                $db,
                'habilidades_blandas',
                [
                    'nombre' =>
                        $habilidad['nombre'],

                    'descripcion' =>
                        'Habilidad controlada para verificar '
                        . 'barras de progreso, modales y logros.',

                    'tag' =>
                        'adaptabilidad,progreso,e2e',

                    'habilitado' =>
                        1
                ]
            );

        $habilidadesIds[] =
            $idHabilidad;

        crearProgreso(
            $db,
            $idUsuario,
            $idHabilidad,
            (int) $habilidad['nivel'],
            (int) $habilidad['progreso']
        );
    }

    $idLeccion =
        insertarFilaAdaptable(
            $db,
            'lecciones',
            [
                'id_habilidades' =>
                    $habilidadesIds[0],

                'titulo' =>
                    LESSON_TITLE,

                'descripcion' =>
                    'Lección creada para abrir y revisar '
                    . 'el modal de Aprendizaje.',

                'orden' =>
                    999,

                'habilitado' =>
                    1
            ]
        );

    $idReto =
        insertarFilaAdaptable(
            $db,
            'retos',
            [
                'id_habilidades' =>
                    $habilidadesIds[1],

                'nombre' =>
                    CHALLENGE_NAME,

                'descripcion' =>
                    'Reto creado para verificar la adaptación '
                    . 'del modal informativo.',

                'tag' =>
                    'adaptabilidad,modal,e2e',

                'tiempo_min' =>
                    5,

                'tiempo_max' =>
                    10,

                'puntos' =>
                    30,

                'dificultad' =>
                    1,

                'habilitado' =>
                    1
            ]
        );

    $logros = [
        [
            'nombre' =>
                PREFIX_ACHIEVEMENT
                . 'Desbloqueado 01',

            'descripcion' =>
                'Logro desbloqueado con una descripción '
                . 'extensa para comprobar el ajuste del contenido.',

            'tipo' =>
                1,

            'valor' =>
                1,

            'habilitado' =>
                1,

            'asignar' =>
                true
        ],
        [
            'nombre' =>
                PREFIX_ACHIEVEMENT
                . 'Desbloqueado 02',

            'descripcion' =>
                'Segundo logro obtenido por el usuario de prueba.',

            'tipo' =>
                2,

            'valor' =>
                50,

            'habilitado' =>
                1,

            'asignar' =>
                true
        ],
        [
            'nombre' =>
                PREFIX_ACHIEVEMENT
                . 'Bloqueado 01',

            'descripcion' =>
                'Logro pendiente mostrado como bloqueado.',

            'tipo' =>
                3,

            'valor' =>
                5,

            'habilitado' =>
                1,

            'asignar' =>
                false
        ],
        [
            'nombre' =>
                PREFIX_ACHIEVEMENT
                . 'Bloqueado 02',

            'descripcion' =>
                'Segundo logro pendiente del escenario.',

            'tipo' =>
                4,

            'valor' =>
                90,

            'habilitado' =>
                1,

            'asignar' =>
                false
        ],
        [
            'nombre' =>
                PREFIX_ACHIEVEMENT
                . 'Oculto',

            'descripcion' =>
                'Este logro no debe aparecer porque está deshabilitado.',

            'tipo' =>
                1,

            'valor' =>
                1,

            'habilitado' =>
                0,

            'asignar' =>
                false
        ],
    ];

    $logrosIds = [];

    foreach (
        $logros
        as $logro
    ) {
        $idLogro =
            insertarFilaAdaptable(
                $db,
                'logros',
                [
                    'nombre' =>
                        $logro['nombre'],

                    'descripcion' =>
                        $logro['descripcion'],

                    'icono' =>
                        'logros/habilidad_autoconfianza',

                    'tipo' =>
                        $logro['tipo'],

                    'valor_objetivo' =>
                        $logro['valor'],

                    'habilitado' =>
                        $logro['habilitado']
                ]
            );

        $logrosIds[] =
            $idLogro;

        if ($logro['asignar']) {
            asignarLogro(
                $db,
                $idUsuario,
                $idLogro,
                '2026-08-04 14:00:00'
            );
        }
    }

    return [
        'user' => [
            'id' =>
                $idUsuario,

            'email' =>
                GENERAL_EMAIL,

            'password' =>
                GENERAL_PASSWORD
        ],

        'admin' => [
            'id' =>
                $idAdmin,

            'email' =>
                ADMIN_EMAIL,

            'password' =>
                GENERAL_PASSWORD
        ],

        'skillIds' =>
            $habilidadesIds,

        'skillNames' =>
            array_column(
                $habilidades,
                'nombre'
            ),

        'lessonId' =>
            $idLeccion,

        'challengeId' =>
            $idReto,

        'lessonTitle' =>
            LESSON_TITLE,

        'challengeName' =>
            CHALLENGE_NAME,

        'achievementIds' =>
            $logrosIds,

        'achievementNames' =>
            array_column(
                $logros,
                'nombre'
            )
    ];
}

$rutaFixture =
    dirname(__DIR__)
    . '/.grupo5-fixture.json';

try {
    if (
        $comando === 'cleanup'
    ) {
        $db->begin_transaction();

        limpiarGrupo5(
            $db
        );

        $db->commit();

        if (
            is_file(
                $rutaFixture
            )
        ) {
            unlink(
                $rutaFixture
            );
        }

        echo "Datos del grupo 5 eliminados correctamente.\n";
    } else {
        $db->begin_transaction();

        $datos =
            prepararGrupo5(
                $db
            );

        $db->commit();

        file_put_contents(
            $rutaFixture,
            json_encode(
                $datos,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
            )
        );

        echo json_encode(
            $datos,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_UNICODE
        ) . PHP_EOL;
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