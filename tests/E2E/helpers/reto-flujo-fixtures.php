<?php

declare(strict_types=1);

use Model\HabilidadesBlandas;
use Model\Retos;
use Model\Usuario;
use Model\usuarios_habilidades;

/*
|--------------------------------------------------------------------------
| Carga de la aplicación
|--------------------------------------------------------------------------
|
| Desde tests/E2E/helpers se suben tres niveles:
| helpers -> E2E -> tests -> raíz del proyecto.
|
*/
$projectRoot = dirname(__DIR__, 3);

require_once $projectRoot . '/includes/app.php';

/*
|--------------------------------------------------------------------------
| Datos controlados para la prueba
|--------------------------------------------------------------------------
*/

const E2E_USER_EMAIL = 'e2e.reto.visual@skillview.test';
const E2E_USER_PASSWORD = 'RetoCaja1!';

const E2E_SKILL_NAME = 'Comunicación Evaluativa E2E';
const E2E_SKILL_TAG = 'e2e-flujo-reto';

const E2E_CHALLENGE_NAME = 'Resolver un conflicto laboral E2E';

/**
 * Busca y elimina los datos creados por este fixture.
 */
function cleanupChallengeVisualFixture(): void
{
    $challenge = Retos::where(
        'nombre',
        E2E_CHALLENGE_NAME
    );

    if ($challenge) {
        $challenge->eliminar();
    }

    $user = Usuario::where(
        'correo',
        E2E_USER_EMAIL
    );

    if ($user) {
        $user->eliminar();
    }

    $skill = HabilidadesBlandas::where(
        'tag',
        E2E_SKILL_TAG
    );

    if ($skill) {
        $skill->eliminar();
    }
}

/**
 * Crea todos los registros necesarios para abrir la vista real del reto.
 *
 * Las respuestas de la API serán interceptadas posteriormente por
 * Playwright, pero la página, el usuario, la habilidad y el reto
 * pertenecen a la aplicación real.
 */
function setupChallengeVisualFixture(): array
{
    cleanupChallengeVisualFixture();

    /*
    |--------------------------------------------------------------------------
    | Crear habilidad
    |--------------------------------------------------------------------------
    */

    $skill = new HabilidadesBlandas([
        'nombre' => E2E_SKILL_NAME,
        'descripcion' =>
            'Habilidad creada exclusivamente para la prueba visual del flujo de retos.',
        'tag' => E2E_SKILL_TAG,
        'habilitado' => 1
    ]);

    $skillResult = $skill->guardar();

    if (!$skillResult) {
        throw new RuntimeException(
            'No fue posible crear la habilidad del fixture.'
        );
    }

    $skill = HabilidadesBlandas::where(
        'tag',
        E2E_SKILL_TAG
    );

    if (!$skill || empty($skill->id)) {
        throw new RuntimeException(
            'No fue posible recuperar la habilidad creada.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Crear usuario
    |--------------------------------------------------------------------------
    */

    $user = new Usuario([
        'nombres' => 'Usuario',
        'apellidos' => 'Reto Visual',
        'edad' => 24,
        'sexo' => 0,
        'correo' => E2E_USER_EMAIL,
        'password' => password_hash(
            E2E_USER_PASSWORD,
            PASSWORD_BCRYPT
        ),
        'universidad' => 'Universidad E2E',
        'carrera' => 'Ingeniería de Sistemas',
        'admin' => 0,
        'debe_cambiar_password' => 0,
        'habilitado' => 1,
        'token_recuperacion' => '',
        'token_expiracion' => 0,
        'autoriza_tratamiento_datos' => 1
    ]);

    $userResult = $user->guardar();

    if (!$userResult) {
        throw new RuntimeException(
            'No fue posible crear el usuario del fixture.'
        );
    }

    $user = Usuario::where(
        'correo',
        E2E_USER_EMAIL
    );

    if (!$user || empty($user->id)) {
        throw new RuntimeException(
            'No fue posible recuperar el usuario creado.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Crear reto
    |--------------------------------------------------------------------------
    */

    $challenge = new Retos();

    $challenge->id_habilidades = (int) $skill->id;
    $challenge->nombre = E2E_CHALLENGE_NAME;
    $challenge->descripcion =
        'Analiza una situación laboral y plantea una respuesta asertiva.';
    $challenge->tag =
        'comunicación, conflicto, solución, entrevista';
    $challenge->tiempo_min = 10;
    $challenge->tiempo_max = 15;
    $challenge->puntos = 30;
    $challenge->dificultad = 2;
    $challenge->habilitado = 1;

    $challengeResult = $challenge->guardar();

    if (!$challengeResult) {
        throw new RuntimeException(
            'No fue posible crear el reto del fixture.'
        );
    }

    $challenge = Retos::where(
        'nombre',
        E2E_CHALLENGE_NAME
    );

    if (!$challenge || empty($challenge->id)) {
        throw new RuntimeException(
            'No fue posible recuperar el reto creado.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Inicializar progreso
    |--------------------------------------------------------------------------
    */

    usuarios_habilidades::inicializarHabilidadesUsuario(
        (int) $user->id,
        date('Y-m-d')
    );

    return [
        'userId' => (int) $user->id,
        'skillId' => (int) $skill->id,
        'challengeId' => (int) $challenge->id,
        'email' => E2E_USER_EMAIL,
        'password' => E2E_USER_PASSWORD,
        'skillName' => E2E_SKILL_NAME,
        'challengeName' => E2E_CHALLENGE_NAME,
        'maxScore' => 30,
        'minimumScore' => 21
    ];
}

/*
|--------------------------------------------------------------------------
| Ejecución desde consola
|--------------------------------------------------------------------------
*/

$action = $argv[1] ?? 'setup';

try {
    if ($action === 'cleanup') {
        cleanupChallengeVisualFixture();

        echo json_encode(
            [
                'ok' => true,
                'action' => 'cleanup'
            ],
            JSON_UNESCAPED_UNICODE
        );

        exit(0);
    }

    if ($action !== 'setup') {
        throw new InvalidArgumentException(
            'La acción debe ser setup o cleanup.'
        );
    }

    $fixture = setupChallengeVisualFixture();

    echo json_encode(
        [
            'ok' => true,
            'action' => 'setup',
            'fixture' => $fixture
        ],
        JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $exception) {
    echo json_encode(
        [
            'ok' => false,
            'error' => $exception->getMessage()
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit(1);
}