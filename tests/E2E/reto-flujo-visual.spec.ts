import { execFileSync } from 'node:child_process';
import path from 'node:path';
import {
    expect,
    Page,
    Route,
    test,
} from '@playwright/test';

type FixtureData = {
    userId: number;
    skillId: number;
    challengeId: number;
    email: string;
    password: string;
    skillName: string;
    challengeName: string;
    maxScore: number;
    minimumScore: number;
};

type TurnRequest = {
    challengeId: number;
    action: string;
    message?: string;
};

type JsonObject = Record<string, unknown>;

type TurnHandler = (
    body: TurnRequest,
    callNumber: number,
) => JsonObject | Promise<JsonObject>;

const projectRoot = path.resolve(__dirname, '..', '..');

const fixtureScript = path.join(
    projectRoot,
    'tests',
    'E2E',
    'helpers',
    'reto-flujo-fixtures.php',
);

const phpBinary = process.env.PHP_BINARY || 'php';

let fixture: FixtureData;

/*
|--------------------------------------------------------------------------
| Ejecución del fixture PHP
|--------------------------------------------------------------------------
*/

function parseFixtureOutput(output: string): JsonObject {
    const lines = output
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean);

    for (let index = lines.length - 1; index >= 0; index -= 1) {
        try {
            return JSON.parse(lines[index]) as JsonObject;
        } catch {
            // Continúa buscando una línea JSON válida.
        }
    }

    throw new Error(
        `El fixture no produjo una respuesta JSON válida:\n${output}`,
    );
}

function runFixture(action: 'setup' | 'cleanup'): JsonObject {
    const output = execFileSync(
        phpBinary,
        [fixtureScript, action],
        {
            cwd: projectRoot,
            encoding: 'utf8',
        },
    );

    return parseFixtureOutput(output);
}

/*
|--------------------------------------------------------------------------
| Inicio de sesión
|--------------------------------------------------------------------------
*/

/**
 * Inicia sesión mediante el formulario público.
 */
async function login(
    page: Page,
): Promise<void> {
    await page.goto('/');

    const loginForm =
        page.locator('form.login__form');

    await expect(
        loginForm,
    ).toBeVisible();

    await loginForm
        .locator('input[name="correo"]')
        .fill(fixture.email);

    await loginForm
        .locator('input[name="password"]')
        .fill(fixture.password);

    await loginForm
        .locator(
            'button[type="submit"], '
            + 'input[type="submit"]',
        )
        .click();

    await expect(page).toHaveURL(
        /\/principal$/,
    );
}

/*
|--------------------------------------------------------------------------
| Constructores de mensajes
|--------------------------------------------------------------------------
*/

function assistantMessage(
    id: string,
    text: string,
): JsonObject {
    return {
        id,
        role: 'assistant',
        type: 'text',
        text,
    };
}

function userMessage(
    id: string,
    text: string,
): JsonObject {
    return {
        id,
        role: 'user',
        type: 'text',
        text,
    };
}

/*
|--------------------------------------------------------------------------
| Estado de sesión simulado
|--------------------------------------------------------------------------
*/

function challengeSession(
    overrides: JsonObject = {},
): JsonObject {
    return {
        challengeId: fixture.challengeId,
        skillId: fixture.skillId,
        currentStage: 'intro',
        nextExpectedAction: 'advance',
        inputEnabled: false,
        requiresUserResponse: false,
        completed: false,
        passed: false,
        failed: false,
        attempts: {
            challengeAnswer: 0,
        },
        limits: {
            challengeAnswer: 3,
        },
        scoreAwarded: 0,
        maxScore: fixture.maxScore,
        minimumScore: fixture.minimumScore,
        ...overrides,
    };
}

/*
|--------------------------------------------------------------------------
| Respuestas controladas de la API
|--------------------------------------------------------------------------
*/

function startResponse(): JsonObject {
    return {
        ok: true,
        error: null,
        challenge: {
            id: fixture.challengeId,
            title: fixture.challengeName,
            skill: {
                id: fixture.skillId,
                name: fixture.skillName,
            },
            difficulty: 'Intermedio',
            timeMin: 10,
            timeMax: 15,
            maxPoints: fixture.maxScore,
        },
        session: challengeSession(),
        messages: [
            assistantMessage(
                'challenge_intro_1',
                'Bienvenido al reto de comunicación evaluativa.',
            ),
            assistantMessage(
                'challenge_intro_2',
                'En esta actividad analizarás una situación laboral y propondrás una solución.',
            ),
        ],
        ui: {
            showTyping: false,
            showAvatarSpeaking: false,
            composerPlaceholder: 'Espera la consigna del reto...',
            focusInput: false,
            showReturnButton: false,
        },
    };
}

function promptResponse(): JsonObject {
    return {
        ok: true,
        error: null,
        session: challengeSession({
            currentStage: 'challenge_answer',
            nextExpectedAction: 'reply',
            inputEnabled: true,
            requiresUserResponse: true,
        }),
        messages: [
            assistantMessage(
                'challenge_prompt_1',
                'Imagina que dos compañeros no logran ponerse de acuerdo durante una actividad importante.',
            ),
            assistantMessage(
                'challenge_prompt_2',
                '¿Cómo intervendrías para facilitar el diálogo y encontrar una solución?',
            ),
        ],
        ui: {
            showTyping: false,
            showAvatarSpeaking: false,
            composerPlaceholder: 'Escribe tu respuesta al reto...',
            focusInput: true,
            showReturnButton: false,
        },
    };
}

function genericRetryResponse(
    answer: string,
): JsonObject {
    return {
        ok: true,
        error: null,
        session: challengeSession({
            currentStage: 'challenge_answer_retry',
            nextExpectedAction: 'reply',
            inputEnabled: true,
            requiresUserResponse: true,
            attempts: {
                challengeAnswer: 1,
            },
        }),
        evaluation: {
            accepted: false,
            needsRetry: true,
            retryReason: 'TOO_GENERIC',
            detectedIssues: [
                'La respuesta no presentó una acción concreta.',
            ],
        },
        messages: [
            userMessage(
                'challenge_user_retry_1',
                answer,
            ),
            assistantMessage(
                'challenge_retry_1',
                'Tu respuesta todavía es demasiado general. Explica qué harías y cómo lo comunicarías.',
            ),
            assistantMessage(
                'challenge_retry_2',
                'Te quedan 2 intentos más para responder correctamente.',
            ),
        ],
        ui: {
            showTyping: false,
            showAvatarSpeaking: false,
            composerPlaceholder: 'Intenta responder mejor...',
            focusInput: true,
            showReturnButton: false,
        },
    };
}

function belowMinimumResponse(
    answer: string,
): JsonObject {
    return {
        ok: true,
        error: null,
        session: challengeSession({
            currentStage: 'challenge_answer_retry',
            nextExpectedAction: 'reply',
            inputEnabled: true,
            requiresUserResponse: true,
            attempts: {
                challengeAnswer: 1,
            },
            scoreAwarded: 18,
        }),
        evaluation: {
            accepted: false,
            needsRetry: true,
            retryReason: 'BELOW_MINIMUM_SCORE',
            detectedIssues: [
                'La propuesta necesitó mayor claridad y profundidad.',
            ],
        },
        messages: [
            userMessage(
                'challenge_user_score_retry',
                answer,
            ),
            assistantMessage(
                'challenge_score_retry_1',
                'Tu respuesta obtuvo 18 de 30 puntos, pero necesitabas al menos 21 puntos para completar el reto.',
            ),
            assistantMessage(
                'challenge_score_retry_2',
                'Te quedan 2 intentos más para mejorar tu respuesta.',
            ),
        ],
        ui: {
            showTyping: false,
            showAvatarSpeaking: false,
            composerPlaceholder: 'Intenta responder mejor...',
            focusInput: true,
            showReturnButton: false,
        },
    };
}

function completedResponse(
    answer: string,
): JsonObject {
    return {
        ok: true,
        error: null,
        session: challengeSession({
            currentStage: 'complete',
            nextExpectedAction: null,
            inputEnabled: false,
            requiresUserResponse: false,
            completed: true,
            passed: true,
            failed: false,
            scoreAwarded: 24,
        }),
        evaluation: {
            accepted: true,
            needsRetry: false,
            retryReason: null,
            detectedIssues: [],
            scoreRatio: 0.8,
            performanceLevel: 'GOOD',
            scoreAwarded: 24,
            maxScore: fixture.maxScore,
        },
        messages: [
            userMessage(
                'challenge_user_completed',
                answer,
            ),
        ],
        completionModal: {
            type: 'success',
            title: 'Reto completado',
            messages: [
                assistantMessage(
                    'challenge_final_1',
                    'Buen trabajo. Has completado este reto.',
                ),
                assistantMessage(
                    'challenge_final_2',
                    'Propusiste una intervención clara, respetuosa y orientada a solucionar el conflicto.',
                ),
                assistantMessage(
                    'challenge_final_3',
                    'Continúa fortaleciendo tu comunicación para responder con mayor seguridad.',
                ),
            ],
            scoreAwarded: 24,
            maxScore: fixture.maxScore,
            buttonText: 'Continuar',
            redirectTo: '/retos',
        },
        progress: {
            challengeCompleted: true,
            failed: false,
            scoreAwarded: 24,
            redirectTo: '/retos',
        },
        ui: {
            showTyping: false,
            showAvatarSpeaking: false,
            composerPlaceholder: 'Reto completado',
            focusInput: false,
            showReturnButton: true,
        },
    };
}

function failedResponse(
    answer: string,
): JsonObject {
    return {
        ok: true,
        error: null,
        session: challengeSession({
            currentStage: 'failed',
            nextExpectedAction: null,
            inputEnabled: false,
            requiresUserResponse: false,
            completed: true,
            passed: false,
            failed: true,
            attempts: {
                challengeAnswer: 3,
            },
            scoreAwarded: 18,
        }),
        evaluation: {
            accepted: false,
            needsRetry: false,
            retryReason: 'BELOW_MINIMUM_SCORE',
            detectedIssues: [
                'No se alcanzó el puntaje mínimo.',
            ],
            scoreRatio: 0.6,
            performanceLevel: 'INSUFFICIENT',
            scoreAwarded: 18,
            maxScore: fixture.maxScore,
            minimumScore: fixture.minimumScore,
        },
        messages: [
            userMessage(
                'challenge_user_failed',
                answer,
            ),
        ],
        completionModal: {
            type: 'error',
            title: 'Reto no completado',
            messages: [
                assistantMessage(
                    'challenge_failed_1',
                    'No lograste completar este reto en esta ocasión.',
                ),
                assistantMessage(
                    'challenge_failed_2',
                    'Tu respuesta obtuvo 18 de 30 puntos, pero necesitabas al menos 21 puntos para completar el reto.',
                ),
                assistantMessage(
                    'challenge_failed_3',
                    `Te recomiendo seguir practicando la habilidad de ${fixture.skillName} antes de intentarlo nuevamente.`,
                ),
            ],
            scoreAwarded: 18,
            maxScore: fixture.maxScore,
            minimumScore: fixture.minimumScore,
            buttonText: 'Volver a retos',
            redirectTo: '/retos',
        },
        progress: {
            challengeCompleted: false,
            failed: true,
            scoreAwarded: 18,
            redirectTo: '/retos',
        },
        ui: {
            showTyping: false,
            showAvatarSpeaking: false,
            composerPlaceholder: 'Reto finalizado',
            focusInput: false,
            showReturnButton: true,
        },
    };
}

function unavailableResponse(): JsonObject {
    return {
        ok: true,
        error: null,
        serviceError: {
            code: 'AI_UNAVAILABLE',
            message:
                'El servicio de inteligencia artificial no está disponible.',
        },
        challenge: {
            id: fixture.challengeId,
            title: fixture.challengeName,
            skill: {
                id: fixture.skillId,
                name: fixture.skillName,
            },
            difficulty: 'Intermedio',
            timeMin: 10,
            timeMax: 15,
            maxPoints: fixture.maxScore,
        },
        session: challengeSession({
            nextExpectedAction: null,
            inputEnabled: false,
            requiresUserResponse: false,
        }),
        messages: [
            assistantMessage(
                'challenge_ai_unavailable',
                'En este momento no fue posible conectarse con el servicio de inteligencia artificial. Verifica tu conexión a internet e inténtalo nuevamente más tarde. Tu progreso y tus intentos no se verán afectados.',
            ),
        ],
        redirectTo: '/retos',
        ui: {
            showTyping: false,
            showAvatarSpeaking: false,
            composerPlaceholder:
                'Actividad pausada por falta de conexión',
            focusInput: false,
            showReturnButton: true,
        },
    };
}

/*
|--------------------------------------------------------------------------
| Interceptación de endpoints
|--------------------------------------------------------------------------
*/

async function fulfillJson(
    route: Route,
    payload: JsonObject,
    status = 200,
): Promise<void> {
    await route.fulfill({
        status,
        contentType: 'application/json; charset=utf-8',
        body: JSON.stringify(payload),
    });
}

async function mockChallengeApi(
    page: Page,
    turnHandler: TurnHandler,
    customStartResponse?: JsonObject,
): Promise<void> {
    await page.route(
        '**/api/retos/start',
        async (route) => {
            const requestBody =
                route.request().postDataJSON() as {
                    challengeId: number;
                    skillId: number;
                };

            expect(requestBody.challengeId).toBe(
                fixture.challengeId,
            );

            expect(requestBody.skillId).toBe(
                fixture.skillId,
            );

            await fulfillJson(
                route,
                customStartResponse ?? startResponse(),
            );
        },
    );

    let turnCalls = 0;

    await page.route(
        '**/api/retos/turn',
        async (route) => {
            const body =
                route.request().postDataJSON() as TurnRequest;

            const response = await turnHandler(
                body,
                turnCalls,
            );

            turnCalls += 1;

            await fulfillJson(route, response);
        },
    );
}

/*
|--------------------------------------------------------------------------
| Acciones reutilizables
|--------------------------------------------------------------------------
*/

async function openChallenge(page: Page): Promise<void> {
    await page.goto(
        `/retos/reto?id=${fixture.challengeId}`,
    );

    await expect(
        page.locator('.challenge'),
    ).toBeVisible();
}

async function waitForPrompt(page: Page): Promise<void> {
    await expect(
        page.getByText(
            '¿Cómo intervendrías para facilitar el diálogo y encontrar una solución?',
            {
                exact: true,
            },
        ),
    ).toBeVisible();

    await expect(
        page.locator('.challenge__input'),
    ).toBeEnabled();

    await expect(
        page.locator('.challenge__sendBtn'),
    ).toBeEnabled();

    await expect(
        page.locator('.challenge__input'),
    ).toHaveAttribute(
        'placeholder',
        'Escribe tu respuesta al reto...',
    );
}

async function submitAnswer(
    page: Page,
    answer: string,
): Promise<void> {
    await page
        .locator('.challenge__input')
        .fill(answer);

    await page
        .locator('.challenge__sendBtn')
        .click();
}

/*
|--------------------------------------------------------------------------
| Grupo de pruebas
|--------------------------------------------------------------------------
*/

test.describe(
    'Flujo visual y resultado de un reto',
    () => {
        test.describe.configure({
            mode: 'serial',
        });

        test.beforeAll(() => {
            const response = runFixture('setup');

            expect(response.ok).toBe(true);

            fixture = response.fixture as FixtureData;
        });

        test.afterAll(() => {
            try {
                runFixture('cleanup');
            } catch (error) {
                console.error(
                    'No fue posible limpiar el fixture:',
                    error,
                );
            }
        });

        test(
            'muestra la información inicial y el loader mientras inicia',
            async ({ page }) => {
                await login(page);

                let releaseStart:
                    | (() => void)
                    | undefined;

                const startGate = new Promise<void>(
                    (resolve) => {
                        releaseStart = resolve;
                    },
                );

                await page.route(
                    '**/api/retos/start',
                    async (route) => {
                        await startGate;
                        await fulfillJson(
                            route,
                            startResponse(),
                        );
                    },
                );

                await page.route(
                    '**/api/retos/turn',
                    async (route) => {
                        await fulfillJson(
                            route,
                            promptResponse(),
                        );
                    },
                );

                await openChallenge(page);

                await expect(page).toHaveTitle(
                    new RegExp(fixture.challengeName, 'i'),
                );

                const challengeRoot =
                    page.locator('.challenge');

                await expect(challengeRoot).toHaveAttribute(
                    'data-reto-id',
                    String(fixture.challengeId),
                );

                await expect(challengeRoot).toHaveAttribute(
                    'data-habilidad-id',
                    String(fixture.skillId),
                );

                await expect(
                    page.locator('#challenge-loader'),
                ).toHaveClass(/is-visible/);

                await expect(
                    page.locator('.challenge__input'),
                ).toBeDisabled();

                await expect(
                    page.locator('.challenge__sendBtn'),
                ).toBeDisabled();

                releaseStart?.();

                await waitForPrompt(page);

                await expect(
                    page.locator('#challenge-loader'),
                ).not.toHaveClass(/is-visible/);

                await expect(
                    page.locator('#challenge-loader'),
                ).toHaveAttribute(
                    'aria-hidden',
                    'true',
                );
            },
        );

        test(
            'renderiza la introducción, la consigna y habilita la respuesta',
            async ({ page }) => {
                await login(page);

                await mockChallengeApi(
                    page,
                    async (body, callNumber) => {
                        expect(callNumber).toBe(0);
                        expect(body.action).toBe('advance');
                        expect(body.challengeId).toBe(
                            fixture.challengeId,
                        );

                        return promptResponse();
                    },
                );

                await openChallenge(page);

                await expect(
                    page.getByText(
                        'Bienvenido al reto de comunicación evaluativa.',
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        'En esta actividad analizarás una situación laboral y propondrás una solución.',
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        'Imagina que dos compañeros no logran ponerse de acuerdo durante una actividad importante.',
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await waitForPrompt(page);
            },
        );

        test(
            'mantiene el reto abierto cuando la respuesta requiere reintento',
            async ({ page }) => {
                await login(page);

                const answer =
                    'Yo hablaría con ellos para solucionar el problema.';

                await mockChallengeApi(
                    page,
                    async (body, callNumber) => {
                        if (callNumber === 0) {
                            expect(body.action).toBe('advance');
                            return promptResponse();
                        }

                        expect(body.action).toBe('reply');
                        expect(body.message).toBe(answer);

                        return genericRetryResponse(answer);
                    },
                );

                await openChallenge(page);
                await waitForPrompt(page);
                await submitAnswer(page, answer);

                await expect(
                    page.getByText(answer, {
                        exact: true,
                    }),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        'Tu respuesta todavía es demasiado general. Explica qué harías y cómo lo comunicarías.',
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        'Te quedan 2 intentos más para responder correctamente.',
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.locator('.challenge__input'),
                ).toBeEnabled();

                await expect(
                    page.locator('.challenge__sendBtn'),
                ).toBeEnabled();

                await expect(
                    page.locator('.challenge__input'),
                ).toHaveAttribute(
                    'placeholder',
                    'Intenta responder mejor...',
                );

                await expect(
                    page.locator(
                        '#sv-challenge-result-modal',
                    ),
                ).not.toHaveClass(/is-open/);
            },
        );

        test(
            'permite reintentar cuando el puntaje no alcanza el mínimo',
            async ({ page }) => {
                await login(page);

                const answer =
                    'Escucharía a cada compañero y luego les pediría que eligieran una solución entre ambos.';

                await mockChallengeApi(
                    page,
                    async (body, callNumber) => {
                        if (callNumber === 0) {
                            return promptResponse();
                        }

                        expect(body.action).toBe('reply');
                        expect(body.message).toBe(answer);

                        return belowMinimumResponse(answer);
                    },
                );

                await openChallenge(page);
                await waitForPrompt(page);
                await submitAnswer(page, answer);

                await expect(
                    page.getByText(
                        'Tu respuesta obtuvo 18 de 30 puntos, pero necesitabas al menos 21 puntos para completar el reto.',
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        'Te quedan 2 intentos más para mejorar tu respuesta.',
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.locator('.challenge__input'),
                ).toBeEnabled();

                await expect(
                    page.locator('.challenge__sendBtn'),
                ).toBeEnabled();

                await expect(
                    page.locator('.challenge__input'),
                ).toHaveAttribute(
                    'placeholder',
                    'Intenta responder mejor...',
                );

                await expect(
                    page.locator(
                        '#sv-challenge-result-modal',
                    ),
                ).not.toHaveClass(/is-open/);
            },
        );

        test(
            'completa el reto y muestra el puntaje en el modal final',
            async ({ page }) => {
                await login(page);

                const answer =
                    'Primero escucharía por separado las razones de ambos compañeros. Después reuniría sus puntos en común, explicaría el objetivo del equipo y propondría una solución que distribuya las responsabilidades de manera equilibrada.';

                await mockChallengeApi(
                    page,
                    async (body, callNumber) => {
                        if (callNumber === 0) {
                            return promptResponse();
                        }

                        expect(body.action).toBe('reply');
                        expect(body.message).toBe(answer);

                        return completedResponse(answer);
                    },
                );

                await openChallenge(page);
                await waitForPrompt(page);
                await submitAnswer(page, answer);

                const modal = page.locator(
                    '#sv-challenge-result-modal',
                );

                await expect(modal).toHaveClass(/is-open/);

                await expect(modal).toHaveAttribute(
                    'aria-hidden',
                    'false',
                );

                await expect(
                    page.getByRole('heading', {
                        name: 'Reto completado',
                    }),
                ).toBeVisible();

                await expect(
                    page.locator(
                        '[data-sv-challenge-result-score]',
                    ),
                ).toHaveText(
                    'Puntaje obtenido: 24 / 30',
                );

                await expect(
                    page.getByText(
                        'Buen trabajo. Has completado este reto.',
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        'Propusiste una intervención clara, respetuosa y orientada a solucionar el conflicto.',
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.locator('.challenge__input'),
                ).toBeDisabled();

                await expect(
                    page.locator('.challenge__sendBtn'),
                ).toBeDisabled();

                await expect(
                    page.locator('.challenge__input'),
                ).toHaveAttribute(
                    'placeholder',
                    'Reto completado',
                );

                await page
                    .locator(
                        '[data-sv-challenge-result-continue]',
                    )
                    .click();

                await expect(page).toHaveURL(/\/retos$/);
            },
        );

        test(
            'muestra el resultado fallido cuando se agotan los intentos',
            async ({ page }) => {
                await login(page);

                const answer =
                    'Les diría que deben hablar y ponerse de acuerdo para poder continuar.';

                await mockChallengeApi(
                    page,
                    async (body, callNumber) => {
                        if (callNumber === 0) {
                            return promptResponse();
                        }

                        expect(body.action).toBe('reply');
                        expect(body.message).toBe(answer);

                        return failedResponse(answer);
                    },
                );

                await openChallenge(page);
                await waitForPrompt(page);
                await submitAnswer(page, answer);

                const modal = page.locator(
                    '#sv-challenge-result-modal',
                );

                await expect(modal).toHaveClass(/is-open/);

                await expect(modal).toHaveAttribute(
                    'aria-hidden',
                    'false',
                );

                await expect(
                    page.getByRole('heading', {
                        name: 'Reto no completado',
                    }),
                ).toBeVisible();

                await expect(
                    page.locator(
                        '[data-sv-challenge-result-score]',
                    ),
                ).toHaveText(
                    'Puntaje obtenido: 18 / 30',
                );

                await expect(
                    page.getByText(
                        'No lograste completar este reto en esta ocasión.',
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        'Tu respuesta obtuvo 18 de 30 puntos, pero necesitabas al menos 21 puntos para completar el reto.',
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.locator('.challenge__input'),
                ).toBeDisabled();

                await expect(
                    page.locator('.challenge__sendBtn'),
                ).toBeDisabled();

                await expect(
                    page.locator('.challenge__input'),
                ).toHaveAttribute(
                    'placeholder',
                    'Reto finalizado',
                );

                await expect(
                    page.locator(
                        '[data-sv-challenge-result-continue]',
                    ),
                ).toHaveText('Volver a retos');

                await page
                    .locator(
                        '[data-sv-challenge-result-continue]',
                    )
                    .click();

                await expect(page).toHaveURL(/\/retos$/);
            },
        );

        test(
            'pausa la actividad cuando la IA no está disponible',
            async ({ page }) => {
                await login(page);

                let turnRequests = 0;

                await page.route(
                    '**/api/retos/start',
                    async (route) => {
                        await fulfillJson(
                            route,
                            unavailableResponse(),
                        );
                    },
                );

                await page.route(
                    '**/api/retos/turn',
                    async (route) => {
                        turnRequests += 1;

                        await fulfillJson(
                            route,
                            {
                                ok: false,
                                error: {
                                    code: 'UNEXPECTED_REQUEST',
                                    message:
                                        'No debía enviarse un turno adicional.',
                                },
                            },
                            500,
                        );
                    },
                );

                await openChallenge(page);

                await expect(
                    page.getByText(
                        'En este momento no fue posible conectarse con el servicio de inteligencia artificial. Verifica tu conexión a internet e inténtalo nuevamente más tarde. Tu progreso y tus intentos no se verán afectados.',
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.locator('.challenge__input'),
                ).toBeDisabled();

                await expect(
                    page.locator('.challenge__sendBtn'),
                ).toBeDisabled();

                await expect(
                    page.locator('.challenge__input'),
                ).toHaveAttribute(
                    'placeholder',
                    'Actividad pausada por falta de conexión',
                );

                await expect(
                    page.locator(
                        '#sv-challenge-result-modal',
                    ),
                ).not.toHaveClass(/is-open/);

                await page.waitForTimeout(500);

                expect(turnRequests).toBe(0);
            },
        );
    },
);