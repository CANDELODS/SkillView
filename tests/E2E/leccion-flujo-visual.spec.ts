import {
    expect,
    type Page,
    type Route,
    test,
} from '@playwright/test';

import {
    cleanupLessonFlowFixtures,
    LESSON_FLOW_DATA,
    type LessonFlowFixtureIds,
    resetLessonFlowFixtures,
} from './helpers/leccion-flujo-fixtures';

type TurnRequest = {
    lessonId: number;
    action: 'advance' | 'reply';
    message?: string;
};

type TurnHandler = (
    request: TurnRequest,
    turnNumber: number,
) => Promise<Record<string, unknown>>
    | Record<string, unknown>;

/**
 * Inicia sesión mediante el formulario público.
 */
async function login(
    page: Page,
): Promise<void> {
    await page.goto('/');

    await page
        .locator('input[name="correo"]')
        .fill(
            LESSON_FLOW_DATA.user.email,
        );

    await page
        .locator('input[name="password"]')
        .fill(
            LESSON_FLOW_DATA.user.password,
        );

    await page
        .locator('form.login__form')
        .locator(
            'button[type="submit"], '
            + 'input[type="submit"]',
        )
        .click();

    await expect(page).toHaveURL(
        /\/principal$/,
    );
}

/**
 * Construye la dirección de la lección real.
 */
function lessonUrl(
    ids: LessonFlowFixtureIds,
): string {
    return (
        '/aprendizaje/leccion?id='
        + ids.lesson
    );
}

/**
 * Devuelve el estado de sesión esperado
 * por apiLecciones.js.
 */
function sessionState(
    ids: LessonFlowFixtureIds,
    values: {
        currentStage: string;
        nextExpectedAction: 'advance' | 'reply' | null;
        inputEnabled: boolean;
        requiresUserResponse: boolean;
        completed?: boolean;
    },
): Record<string, unknown> {
    return {
        lessonId:
            ids.lesson,

        skillId:
            ids.skill,

        currentStage:
            values.currentStage,

        nextExpectedAction:
            values.nextExpectedAction,

        inputEnabled:
            values.inputEnabled,

        requiresUserResponse:
            values.requiresUserResponse,

        completed:
            values.completed
            ?? false,

        attempts: {
            microPractice: 0,
            miniEvaluation: 0,
        },

        limits: {
            microPractice: 3,
            miniEvaluation: 3,
        },

        failed: false,
    };
}

/**
 * Construye un mensaje del asistente.
 */
function assistantMessage(
    id: string,
    text: string,
): Record<string, string> {
    return {
        id,
        role: 'assistant',
        type: 'text',
        text,
    };
}

/**
 * Construye un mensaje del usuario.
 */
function userMessage(
    id: string,
    text: string,
): Record<string, string> {
    return {
        id,
        role: 'user',
        type: 'text',
        text,
    };
}

/**
 * Respuesta inicial de /api/lecciones/start.
 */
function startResponse(
    ids: LessonFlowFixtureIds,
): Record<string, unknown> {
    return {
        ok: true,
        error: null,

        lesson: {
            id:
                ids.lesson,

            title:
                LESSON_FLOW_DATA.lesson.title,

            order: 1,
            type: 'standard',

            skill: {
                id:
                    ids.skill,

                name:
                    LESSON_FLOW_DATA.skill.name,
            },
        },

        session:
            sessionState(
                ids,
                {
                    currentStage: 'intro',
                    nextExpectedAction: 'advance',
                    inputEnabled: false,
                    requiresUserResponse: false,
                },
            ),

        messages: [
            assistantMessage(
                'start-welcome',
                LESSON_FLOW_DATA.messages.welcome,
            ),

            assistantMessage(
                'start-explanation',
                LESSON_FLOW_DATA.messages.explanation,
            ),
        ],

        ui: {
            showTyping: true,
            showAvatarSpeaking: false,
            composerPlaceholder:
                'Espera la explicación del asistente...',
            focusInput: false,
            showReturnButton: false,
        },
    };
}

/**
 * Respuesta que abre la micropráctica.
 */
function microPromptResponse(
    ids: LessonFlowFixtureIds,
): Record<string, unknown> {
    return {
        ok: true,
        error: null,

        session:
            sessionState(
                ids,
                {
                    currentStage:
                        'micro_practice_answer',

                    nextExpectedAction:
                        'reply',

                    inputEnabled:
                        true,

                    requiresUserResponse:
                        true,
                },
            ),

        messages: [
            assistantMessage(
                'micro-prompt',
                LESSON_FLOW_DATA.messages.microPrompt,
            ),
        ],

        ui: {
            showTyping: true,
            showAvatarSpeaking: false,
            composerPlaceholder:
                'Escribe tu respuesta...',
            focusInput: true,
            showReturnButton: false,
        },
    };
}

/**
 * Respuesta aceptada de la micropráctica.
 */
function microAcceptedResponse(
    ids: LessonFlowFixtureIds,
    answer: string,
): Record<string, unknown> {
    return {
        ok: true,
        error: null,

        session:
            sessionState(
                ids,
                {
                    currentStage:
                        'mini_eval_answer',

                    nextExpectedAction:
                        'reply',

                    inputEnabled:
                        true,

                    requiresUserResponse:
                        true,
                },
            ),

        evaluation: {
            accepted: true,
            needsRetry: false,
            retryReason: null,
            detectedIssues: [],
        },

        messages: [
            userMessage(
                'micro-user',
                answer,
            ),

            assistantMessage(
                'mini-prompt',
                LESSON_FLOW_DATA.messages.miniPrompt,
            ),
        ],

        ui: {
            showTyping: true,
            showAvatarSpeaking: false,
            composerPlaceholder:
                'Responde la mini-evaluación...',
            focusInput: true,
            showReturnButton: false,
        },
    };
}

/**
 * Respuesta de reintento para la micropráctica.
 */
function microRetryResponse(
    ids: LessonFlowFixtureIds,
    answer: string,
): Record<string, unknown> {
    return {
        ok: true,
        error: null,

        session: {
            ...sessionState(
                ids,
                {
                    currentStage:
                        'micro_practice_answer_retry',

                    nextExpectedAction:
                        'reply',

                    inputEnabled:
                        true,

                    requiresUserResponse:
                        true,
                },
            ),

            attempts: {
                microPractice: 1,
                miniEvaluation: 0,
            },
        },

        evaluation: {
            accepted: false,
            needsRetry: true,
            retryReason:
                'ANSWER_NEEDS_DETAIL',
        },

        messages: [
            userMessage(
                'micro-retry-user',
                answer,
            ),

            assistantMessage(
                'micro-retry-feedback',
                LESSON_FLOW_DATA.messages.microRetry,
            ),

            assistantMessage(
                'micro-retry-attempts',
                LESSON_FLOW_DATA.messages
                    .remainingAttempts,
            ),
        ],

        ui: {
            showTyping: true,
            showAvatarSpeaking: false,
            composerPlaceholder:
                'Amplía tu respuesta...',
            focusInput: true,
            showReturnButton: false,
        },
    };
}

/**
 * Respuesta de reintento para la minievaluación.
 */
function miniRetryResponse(
    ids: LessonFlowFixtureIds,
    answer: string,
): Record<string, unknown> {
    return {
        ok: true,
        error: null,

        session: {
            ...sessionState(
                ids,
                {
                    currentStage:
                        'mini_eval_answer',

                    nextExpectedAction:
                        'reply',

                    inputEnabled:
                        true,

                    requiresUserResponse:
                        true,
                },
            ),

            attempts: {
                microPractice: 0,
                miniEvaluation: 1,
            },
        },

        evaluation: {
            accepted: false,
            needsRetry: true,
            retryReason:
                'ANSWER_NEEDS_DETAIL',
        },

        messages: [
            userMessage(
                'mini-retry-user',
                answer,
            ),

            assistantMessage(
                'mini-retry-feedback',
                LESSON_FLOW_DATA.messages.miniRetry,
            ),

            assistantMessage(
                'mini-retry-attempts',
                LESSON_FLOW_DATA.messages
                    .remainingAttempts,
            ),
        ],

        ui: {
            showTyping: true,
            showAvatarSpeaking: false,
            composerPlaceholder:
                'Intenta responder mejor...',
            focusInput: true,
            showReturnButton: false,
        },
    };
}

/**
 * Respuesta final satisfactoria.
 */
function completionResponse(
    ids: LessonFlowFixtureIds,
    answer: string,
): Record<string, unknown> {
    return {
        ok: true,
        error: null,

        session:
            sessionState(
                ids,
                {
                    currentStage:
                        'complete',

                    nextExpectedAction:
                        null,

                    inputEnabled:
                        false,

                    requiresUserResponse:
                        false,

                    completed:
                        true,
                },
            ),

        evaluation: {
            accepted: true,
            result: 'COMPLETED',
        },

        messages: [
            userMessage(
                'mini-final-user',
                answer,
            ),
        ],

        completionModal: {
            type: 'success',
            title: 'Lección completada',

            messages: [
                assistantMessage(
                    'final-feedback',
                    LESSON_FLOW_DATA.messages.finalFeedback,
                ),
            ],

            buttonText:
                'Continuar',

            redirectTo:
                '/aprendizaje',
        },

        progress: {
            lessonCompleted: true,
            completedAt:
                '2026-08-01 12:00:00',
            redirectTo:
                '/aprendizaje',
        },

        ui: {
            showTyping: true,
            showAvatarSpeaking: false,
            composerPlaceholder:
                'Lección completada',
            focusInput: false,
            showReturnButton: true,
        },
    };
}

/**
 * Respuesta cuando la IA no está disponible.
 */
function unavailableResponse(
    ids: LessonFlowFixtureIds,
): Record<string, unknown> {
    return {
        ok: true,
        error: null,

        serviceError: {
            code:
                'AI_UNAVAILABLE',

            message:
                'El servicio de inteligencia artificial no está disponible.',
        },

        session:
            sessionState(
                ids,
                {
                    currentStage:
                        'intro',

                    nextExpectedAction:
                        null,

                    inputEnabled:
                        false,

                    requiresUserResponse:
                        false,
                },
            ),

        messages: [
            assistantMessage(
                'service-unavailable',
                LESSON_FLOW_DATA.messages
                    .serviceUnavailable,
            ),
        ],

        redirectTo:
            '/aprendizaje',

        ui: {
            showTyping: true,
            showAvatarSpeaking: false,
            composerPlaceholder:
                'Actividad pausada por falta de conexión',
            focusInput: false,
            showReturnButton: true,
        },
    };
}

/**
 * Envía una respuesta JSON controlada.
 */
async function fulfillJson(
    route: Route,
    body: Record<string, unknown>,
    status: number = 200,
): Promise<void> {
    await route.fulfill({
        status,
        contentType:
            'application/json; charset=utf-8',

        body:
            JSON.stringify(body),
    });
}

/**
 * Intercepta los dos endpoints utilizados
 * por el frontend de la lección.
 */
async function mockLessonApi(
    page: Page,
    ids: LessonFlowFixtureIds,
    turnHandler: TurnHandler,
    customStartResponse?: Record<string, unknown>,
): Promise<void> {
    let turnNumber = 0;

    await page.route(
        '**/api/lecciones/start',
        async (route) => {
            await fulfillJson(
                route,
                customStartResponse
                ?? startResponse(ids),
            );
        },
    );

    await page.route(
        '**/api/lecciones/turn',
        async (route) => {
            turnNumber++;

            const request =
                route.request()
                    .postDataJSON() as TurnRequest;

            const response =
                await turnHandler(
                    request,
                    turnNumber,
                );

            await fulfillJson(
                route,
                response,
            );
        },
    );
}

/**
 * Configura la apertura normal de la micropráctica.
 */
async function mockUntilMicroPractice(
    page: Page,
    ids: LessonFlowFixtureIds,
): Promise<void> {
    await mockLessonApi(
        page,
        ids,
        (request) => {
            expect(
                request.lessonId,
            ).toBe(ids.lesson);

            expect(
                request.action,
            ).toBe('advance');

            return microPromptResponse(
                ids,
            );
        },
    );
}

/**
 * Abre la vista real de la lección.
 */
async function openLesson(
    page: Page,
    ids: LessonFlowFixtureIds,
): Promise<void> {
    await page.goto(
        lessonUrl(ids),
    );

    await expect(page).toHaveURL(
        new RegExp(
            `/aprendizaje/leccion\\?id=${ids.lesson}$`,
        ),
    );

    await expect(
        page.locator('.lesson'),
    ).toBeVisible();
}

/**
 * Espera hasta que la micropráctica
 * se encuentre disponible.
 */
async function waitForMicroPractice(
    page: Page,
): Promise<void> {
    await expect(
        page.getByText(
            LESSON_FLOW_DATA.messages.microPrompt,
            {
                exact: true,
            },
        ),
    ).toBeVisible();

    await expect(
        page.locator('.lesson__input'),
    ).toBeEnabled();

    await expect(
        page.locator('.lesson__sendBtn'),
    ).toBeEnabled();
}

/**
 * Envía una respuesta mediante el formulario real.
 */
async function submitAnswer(
    page: Page,
    answer: string,
): Promise<void> {
    const input =
        page.locator('.lesson__input');

    await expect(
        input,
    ).toBeEnabled();

    await input.fill(
        answer,
    );

    await page
        .locator('.lesson__composer')
        .locator(
            'button[type="submit"], '
            + 'input[type="submit"], '
            + '.lesson__sendBtn',
        )
        .first()
        .click();
}

test.describe(
    'Flujo visual de una lección',
    () => {
        let ids: LessonFlowFixtureIds;

        test.beforeEach(() => {
            ids =
                resetLessonFlowFixtures();
        });

        test.afterAll(() => {
            cleanupLessonFlowFixtures();
        });

        test(
            'muestra la información inicial y el loader mientras inicia',
            async ({ page }) => {
                await login(page);

                let releaseStart:
                    (() => void)
                    | undefined;

                const startGate =
                    new Promise<void>(
                        (resolve) => {
                            releaseStart =
                                resolve;
                        },
                    );

                await page.route(
                    '**/api/lecciones/start',
                    async (route) => {
                        await startGate;

                        await fulfillJson(
                            route,
                            startResponse(ids),
                        );
                    },
                );

                await page.route(
                    '**/api/lecciones/turn',
                    async (route) => {
                        await fulfillJson(
                            route,
                            microPromptResponse(ids),
                        );
                    },
                );

                await openLesson(
                    page,
                    ids,
                );

                const lessonRoot =
                    page.locator('.lesson');

                await expect(
                    lessonRoot,
                ).toHaveAttribute(
                    'data-leccion-id',
                    String(ids.lesson),
                );

                await expect(
                    lessonRoot,
                ).toHaveAttribute(
                    'data-habilidad-id',
                    String(ids.skill),
                );

                /*
                 * El título se envía al layout y se presenta
                 * en la pestaña del navegador.
                 */
                await expect(page).toHaveTitle(
                    /Comunicación clara en una entrevista E2E/i,
                );

                /*
                 * Los identificadores visibles para el módulo
                 * frontend deben corresponder con los registros
                 * preparados para la prueba.
                 */
                await expect(
                    lessonRoot,
                ).toHaveAttribute(
                    'data-leccion-id',
                    String(ids.lesson),
                );

                await expect(
                    lessonRoot,
                ).toHaveAttribute(
                    'data-habilidad-id',
                    String(ids.skill),
                );

                await expect(
                    page.locator('#lesson-loader'),
                ).toHaveClass(
                    /is-visible/,
                );

                await expect(
                    page.locator('.lesson__input'),
                ).toBeDisabled();

                await expect(
                    page.locator('.lesson__sendBtn'),
                ).toBeDisabled();

                releaseStart?.();

                await waitForMicroPractice(
                    page,
                );

                await expect(
                    page.locator('#lesson-loader'),
                ).not.toHaveClass(
                    /is-visible/,
                );
            },
        );

        test(
            'renderiza la explicación y habilita la micropráctica',
            async ({ page }) => {
                await login(page);

                await mockUntilMicroPractice(
                    page,
                    ids,
                );

                await openLesson(
                    page,
                    ids,
                );

                await waitForMicroPractice(
                    page,
                );

                await expect(
                    page.getByText(
                        LESSON_FLOW_DATA.messages.welcome,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        LESSON_FLOW_DATA.messages.explanation,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.locator('.lesson__input'),
                ).toHaveAttribute(
                    'placeholder',
                    'Escribe tu respuesta...',
                );
            },
        );

        test(
            'muestra la respuesta y avanza hacia la minievaluación',
            async ({ page }) => {
                await login(page);

                const microAnswer =
                    'Presentaría mi fortaleza con un ejemplo '
                    + 'concreto y explicaría el resultado obtenido.';

                await mockLessonApi(
                    page,
                    ids,
                    (request, turnNumber) => {
                        if (turnNumber === 1) {
                            expect(
                                request.action,
                            ).toBe('advance');

                            return microPromptResponse(
                                ids,
                            );
                        }

                        expect(
                            request.action,
                        ).toBe('reply');

                        expect(
                            request.message,
                        ).toBe(microAnswer);

                        return microAcceptedResponse(
                            ids,
                            microAnswer,
                        );
                    },
                );

                await openLesson(
                    page,
                    ids,
                );

                await waitForMicroPractice(
                    page,
                );

                await submitAnswer(
                    page,
                    microAnswer,
                );

                await expect(
                    page.locator(
                        '.lesson__msg--user '
                        + '.lesson__text',
                    ).filter({
                        hasText:
                            microAnswer,
                    }),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        LESSON_FLOW_DATA.messages.miniPrompt,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.locator('.lesson__input'),
                ).toBeEnabled();

                await expect(
                    page.locator('.lesson__input'),
                ).toHaveAttribute(
                    'placeholder',
                    'Responde la mini-evaluación...',
                );
            },
        );

        test(
            'mantiene la micropráctica abierta cuando requiere reintento',
            async ({ page }) => {
                await login(page);

                const shortAnswer =
                    'Soy bueno comunicando.';

                await mockLessonApi(
                    page,
                    ids,
                    (request, turnNumber) => {
                        if (turnNumber === 1) {
                            return microPromptResponse(
                                ids,
                            );
                        }

                        expect(
                            request.message,
                        ).toBe(shortAnswer);

                        return microRetryResponse(
                            ids,
                            shortAnswer,
                        );
                    },
                );

                await openLesson(
                    page,
                    ids,
                );

                await waitForMicroPractice(
                    page,
                );

                await submitAnswer(
                    page,
                    shortAnswer,
                );

                await expect(
                    page.getByText(
                        LESSON_FLOW_DATA.messages.microRetry,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        LESSON_FLOW_DATA.messages
                            .remainingAttempts,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.locator('.lesson__input'),
                ).toBeEnabled();

                await expect(
                    page.locator('.lesson__input'),
                ).toHaveAttribute(
                    'placeholder',
                    'Amplía tu respuesta...',
                );

                await expect(
                    page.locator(
                        '#sv-lesson-result-modal',
                    ),
                ).not.toHaveClass(
                    /is-open/,
                );
            },
        );

        test(
            'mantiene la minievaluación abierta cuando requiere reintento',
            async ({ page }) => {
                await login(page);

                const microAnswer =
                    'Presentaría una fortaleza, explicaría '
                    + 'un ejemplo y mencionaría su resultado.';

                const miniAnswer =
                    'Expliqué una idea.';

                await mockLessonApi(
                    page,
                    ids,
                    (request, turnNumber) => {
                        if (turnNumber === 1) {
                            return microPromptResponse(
                                ids,
                            );
                        }

                        if (turnNumber === 2) {
                            return microAcceptedResponse(
                                ids,
                                microAnswer,
                            );
                        }

                        expect(
                            request.message,
                        ).toBe(miniAnswer);

                        return miniRetryResponse(
                            ids,
                            miniAnswer,
                        );
                    },
                );

                await openLesson(
                    page,
                    ids,
                );

                await waitForMicroPractice(
                    page,
                );

                await submitAnswer(
                    page,
                    microAnswer,
                );

                await expect(
                    page.getByText(
                        LESSON_FLOW_DATA.messages.miniPrompt,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await submitAnswer(
                    page,
                    miniAnswer,
                );

                await expect(
                    page.getByText(
                        LESSON_FLOW_DATA.messages.miniRetry,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        LESSON_FLOW_DATA.messages
                            .remainingAttempts,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.locator('.lesson__input'),
                ).toBeEnabled();

                await expect(
                    page.locator('.lesson__input'),
                ).toHaveAttribute(
                    'placeholder',
                    'Intenta responder mejor...',
                );
            },
        );

        test(
            'completa la lección y muestra el modal final',
            async ({ page }) => {
                await login(page);

                const microAnswer =
                    'Presentaría mi capacidad de comunicación '
                    + 'mediante una situación concreta y '
                    + 'explicaría el resultado alcanzado.';

                const miniAnswer =
                    'Durante un proyecto organicé la explicación, '
                    + 'presenté un ejemplo y confirmé que el equipo '
                    + 'comprendiera la idea.';

                await mockLessonApi(
                    page,
                    ids,
                    (request, turnNumber) => {
                        if (turnNumber === 1) {
                            return microPromptResponse(
                                ids,
                            );
                        }

                        if (turnNumber === 2) {
                            return microAcceptedResponse(
                                ids,
                                microAnswer,
                            );
                        }

                        expect(
                            request.message,
                        ).toBe(miniAnswer);

                        return completionResponse(
                            ids,
                            miniAnswer,
                        );
                    },
                );

                await openLesson(
                    page,
                    ids,
                );

                await waitForMicroPractice(
                    page,
                );

                await submitAnswer(
                    page,
                    microAnswer,
                );

                await expect(
                    page.getByText(
                        LESSON_FLOW_DATA.messages.miniPrompt,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await submitAnswer(
                    page,
                    miniAnswer,
                );

                const modal =
                    page.locator(
                        '#sv-lesson-result-modal',
                    );

                await expect(
                    modal,
                ).toHaveClass(
                    /is-open/,
                );

                await expect(
                    modal,
                ).toHaveAttribute(
                    'aria-hidden',
                    'false',
                );

                await expect(
                    page.getByRole(
                        'heading',
                        {
                            name:
                                'Lección completada',
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        LESSON_FLOW_DATA.messages.finalFeedback,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.locator('.lesson__input'),
                ).toBeDisabled();

                const continueButton =
                    page.locator(
                        '[data-sv-lesson-result-continue]',
                    );

                await expect(
                    continueButton,
                ).toBeVisible();

                await Promise.all([
                    page.waitForURL(
                        /\/aprendizaje$/,
                    ),

                    continueButton.click(),
                ]);

                await expect(page).toHaveURL(
                    /\/aprendizaje$/,
                );
            },
        );

        test(
            'pausa la actividad cuando la IA no está disponible',
            async ({ page }) => {
                await login(page);

                let turnRequests = 0;

                await page.route(
                    '**/api/lecciones/start',
                    async (route) => {
                        await fulfillJson(
                            route,
                            unavailableResponse(ids),
                        );
                    },
                );

                await page.route(
                    '**/api/lecciones/turn',
                    async (route) => {
                        turnRequests++;

                        await fulfillJson(
                            route,
                            {
                                ok: false,
                                error: {
                                    code:
                                        'UNEXPECTED_TURN',

                                    message:
                                        'No debía ejecutarse otro turno.',
                                },
                            },
                            409,
                        );
                    },
                );

                await openLesson(
                    page,
                    ids,
                );

                await expect(
                    page.getByText(
                        LESSON_FLOW_DATA.messages
                            .serviceUnavailable,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.locator('.lesson__input'),
                ).toBeDisabled();

                await expect(
                    page.locator('.lesson__sendBtn'),
                ).toBeDisabled();

                await expect(
                    page.locator('.lesson__input'),
                ).toHaveAttribute(
                    'placeholder',
                    'Actividad pausada por falta de conexión',
                );

                await expect(
                    page.locator('.lesson__avatar'),
                ).toHaveAttribute(
                    'data-avatar-state',
                    'unavailable',
                );

                expect(
                    turnRequests,
                ).toBe(0);
            },
        );
    },
);