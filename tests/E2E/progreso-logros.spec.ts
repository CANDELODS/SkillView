import {
    expect,
    type Locator,
    type Page,
    test,
} from '@playwright/test';

import {
    cleanupProgressAchievementFixtures,
    PROGRESS_ACHIEVEMENT_DATA,
    type ProgressAchievementFixture,
    resetProgressAchievementFixtures,
} from './helpers/progreso-logros-fixtures';

let fixture:
    ProgressAchievementFixture;

/*
|--------------------------------------------------------------------------
| Inicio de sesión
|--------------------------------------------------------------------------
*/

/**
 * Inicia sesión con la cuenta controlada.
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
        .locator(
            'input[name="correo"]',
        )
        .fill(
            PROGRESS_ACHIEVEMENT_DATA
                .user.email,
        );

    await loginForm
        .locator(
            'input[name="password"]',
        )
        .fill(
            PROGRESS_ACHIEVEMENT_DATA
                .user.password,
        );

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
| Navegación
|--------------------------------------------------------------------------
*/

async function openProfile(
    page: Page,
): Promise<void> {
    await page.goto('/perfil');

    await expect(page).toHaveURL(
        /\/perfil$/,
    );
}

async function openAchievements(
    page: Page,
): Promise<void> {
    await page.goto('/logros');

    await expect(page).toHaveURL(
        /\/logros$/,
    );
}

/*
|--------------------------------------------------------------------------
| Lectura flexible de la interfaz
|--------------------------------------------------------------------------
*/

/**
 * Normaliza espacios y saltos de línea.
 */
function normalizeText(
    value: string,
): string {
    return value
        .replace(/\s+/g, ' ')
        .trim();
}

/**
 * Devuelve el texto visible de toda la página.
 */
async function pageText(
    page: Page,
): Promise<string> {
    return normalizeText(
        await page
            .locator('body')
            .innerText(),
    );
}

/**
 * Localiza un texto exacto.
 */
function exactText(
    page: Page,
    value: string,
): Locator {
    return page
        .getByText(
            value,
            {
                exact: true,
            },
        )
        .first();
}

/**
 * Recorre los elementos padres hasta encontrar
 * un contenedor que incluya todos los fragmentos.
 *
 * Esto permite trabajar con las clases reales
 * de la vista sin depender de un nombre CSS único.
 */
async function contextText(
    locator: Locator,
    fragments: string[],
): Promise<string> {
    return locator.evaluate(
        (
            element,
            requiredFragments,
        ) => {
            let current:
                HTMLElement | null =
                element as HTMLElement;

            while (
                current
                && current !== document.body
            ) {
                const text =
                    (
                        current.innerText
                        || ''
                    )
                        .replace(/\s+/g, ' ')
                        .trim();

                const containsAll =
                    requiredFragments.every(
                        (fragment) =>
                            text
                                .toLowerCase()
                                .includes(
                                    fragment.toLowerCase(),
                                ),
                    );

                if (containsAll) {
                    return text;
                }

                current =
                    current.parentElement;
            }

            return (
                element.parentElement
                    ?.innerText
                || ''
            )
                .replace(/\s+/g, ' ')
                .trim();
        },
        fragments,
    );
}

/**
 * Comprueba que el porcentaje se represente
 * mediante width, aria-valuenow o progress[value].
 */
async function hasProgressRepresentation(
    skillName: Locator,
    progress: number,
): Promise<boolean> {
    return skillName.evaluate(
        (
            element,
            expectedProgress,
        ) => {
            let current:
                HTMLElement | null =
                element as HTMLElement;

            const expected =
                String(expectedProgress);

            for (
                let level = 0;
                level < 8 && current;
                level++
            ) {
                const candidates =
                    current.querySelectorAll(
                        '[style*="width"], '
                        + '[aria-valuenow], '
                        + 'progress',
                    );

                for (
                    const candidate
                    of Array.from(candidates)
                ) {
                    const htmlElement =
                        candidate as HTMLElement;

                    const style =
                        htmlElement.getAttribute(
                            'style',
                        )
                        || '';

                    const ariaValue =
                        htmlElement.getAttribute(
                            'aria-valuenow',
                        );

                    const value =
                        htmlElement.getAttribute(
                            'value',
                        );

                    const widthPattern =
                        new RegExp(
                            `width\\s*:\\s*${expected}%`,
                            'i',
                        );

                    if (
                        widthPattern.test(style)
                        || ariaValue === expected
                        || value === expected
                    ) {
                        return true;
                    }
                }

                current =
                    current.parentElement;
            }

            return false;
        },
        progress,
    );
}

/**
 * Localiza una sección a partir de un encabezado
 * y comprueba que contenga un logro específico.
 */
async function achievementSectionText(
    page: Page,
    headingPattern: RegExp,
    achievementName: string,
): Promise<string> {
    const heading = page
        .getByText(
            headingPattern,
        )
        .first();

    await expect(
        heading,
    ).toBeVisible();

    return heading.evaluate(
        (
            element,
            expectedAchievement,
        ) => {
            let current:
                HTMLElement | null =
                element as HTMLElement;

            while (
                current
                && current !== document.body
            ) {
                const text =
                    (
                        current.innerText
                        || ''
                    )
                        .replace(/\s+/g, ' ')
                        .trim();

                if (
                    text.includes(
                        expectedAchievement,
                    )
                ) {
                    return text;
                }

                current =
                    current.parentElement;
            }

            return '';
        },
        achievementName,
    );
}

/*
|--------------------------------------------------------------------------
| Pruebas
|--------------------------------------------------------------------------
*/

test.describe(
    'Visualización de progreso y logros',
    () => {
        test.beforeEach(() => {
            fixture =
                resetProgressAchievementFixtures();
        });

        test.afterAll(() => {
            cleanupProgressAchievementFixtures();
        });

        test(
            'muestra el progreso general y los puntos acumulados',
            async ({ page }) => {
                await login(page);
                await openProfile(page);

                const body =
                    await pageText(page);

                /*
                 * Promedio:
                 * round((33 + 50 + 100) / 3) = 61.
                 */
                expect(body).toMatch(
                    new RegExp(
                        `${fixture.expected.generalProgress}\\s*%`,
                        'i',
                    ),
                );

                /*
                 * La interfaz presenta primero la etiqueta
                 * y después el valor: "Puntos Totales 54".
                 */
                expect(body).toMatch(
                    new RegExp(
                        'Puntos\\s+Totales\\s*'
                        + `${fixture.expected.totalPoints}\\b`,
                        'i',
                    ),
                );

                /*
                 * El promedio del 61 % corresponde
                 * al nivel Intermedio.
                 */
                expect(body).toMatch(
                    /Intermedio/i,
                );
            },
        );

        test(
            'muestra los porcentajes y niveles de cada habilidad',
            async ({ page }) => {
                await login(page);
                await openProfile(page);

                const skills = [
                    PROGRESS_ACHIEVEMENT_DATA
                        .skills.basic,

                    PROGRESS_ACHIEVEMENT_DATA
                        .skills.intermediate,

                    PROGRESS_ACHIEVEMENT_DATA
                        .skills.advanced,
                ];

                for (
                    const skill of skills
                ) {
                    const name =
                        exactText(
                            page,
                            skill.name,
                        );

                    await expect(
                        name,
                    ).toBeVisible();

                    const text =
                        await contextText(
                            name,
                            [
                                skill.name,
                                skill.level,
                                `${skill.progress}%`,
                            ],
                        );

                    expect(text).toContain(
                        skill.name,
                    );

                    expect(text).toContain(
                        skill.level,
                    );

                    expect(text).toMatch(
                        new RegExp(
                            `${skill.progress}\\s*%`,
                        ),
                    );
                }
            },
        );

        test(
            'representa visualmente el porcentaje de cada habilidad',
            async ({ page }) => {
                await login(page);
                await openProfile(page);

                const skills = [
                    PROGRESS_ACHIEVEMENT_DATA
                        .skills.basic,

                    PROGRESS_ACHIEVEMENT_DATA
                        .skills.intermediate,

                    PROGRESS_ACHIEVEMENT_DATA
                        .skills.advanced,
                ];

                for (
                    const skill of skills
                ) {
                    const name =
                        exactText(
                            page,
                            skill.name,
                        );

                    await expect(
                        name,
                    ).toBeVisible();

                    expect(
                        await hasProgressRepresentation(
                            name,
                            skill.progress,
                        ),
                    ).toBe(true);
                }
            },
        );

        test(
            'muestra en el perfil los logros destacados del catálogo',
            async ({ page }) => {
                await login(page);
                await openProfile(page);

                /*
                 * El perfil debe presentar los primeros
                 * seis logros habilitados del catálogo.
                 */
                expect(
                    fixture
                        .profileAchievements
                        .allFeatured,
                ).toHaveLength(6);

                for (
                    const achievement
                    of fixture
                        .profileAchievements
                        .allFeatured
                ) {
                    await expect(
                        page.getByText(
                            achievement.name,
                            {
                                exact: true,
                            },
                        ),
                    ).toBeVisible();
                }

                /*
                 * Los dos primeros logros destacados fueron
                 * asignados al usuario por el fixture.
                 */
                await expect(
                    page.getByText(
                        fixture
                            .profileAchievements
                            .firstUnlocked
                            .name,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        fixture
                            .profileAchievements
                            .secondUnlocked
                            .name,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                /*
                 * Los logros personalizados creados al final
                 * del catálogo no forman parte de los seis
                 * destacados que presenta el perfil.
                 */
                await expect(
                    page.getByText(
                        PROGRESS_ACHIEVEMENT_DATA
                            .achievements
                            .skillUnlocked
                            .name,
                        {
                            exact: true,
                        },
                    ),
                ).toHaveCount(0);
            },
        );

        test(
            'separa los logros desbloqueados y bloqueados',
            async ({ page }) => {
                await login(page);
                await openAchievements(page);

                const unlockedName =
                    PROGRESS_ACHIEVEMENT_DATA
                        .achievements
                        .skillUnlocked
                        .name;

                const lockedName =
                    PROGRESS_ACHIEVEMENT_DATA
                        .achievements
                        .scoreLocked
                        .name;

                const unlockedSection =
                    await achievementSectionText(
                        page,
                        /desbloqueados|obtenidos/i,
                        unlockedName,
                    );

                expect(
                    unlockedSection,
                ).toContain(
                    unlockedName,
                );

                const lockedSection =
                    await achievementSectionText(
                        page,
                        /bloqueados|por desbloquear/i,
                        lockedName,
                    );

                expect(
                    lockedSection,
                ).toContain(
                    lockedName,
                );
            },
        );

        test(
            'muestra las etiquetas y la fecha de los logros',
            async ({ page }) => {
                await login(page);
                await openAchievements(page);

                const skillAchievement =
                    PROGRESS_ACHIEVEMENT_DATA
                        .achievements
                        .skillUnlocked;

                const performanceAchievement =
                    PROGRESS_ACHIEVEMENT_DATA
                        .achievements
                        .performanceUnlocked;

                const scoreAchievement =
                    PROGRESS_ACHIEVEMENT_DATA
                        .achievements
                        .scoreLocked;

                /*
                 * Logro tipo Habilidad.
                 */
                const skillName =
                    exactText(
                        page,
                        skillAchievement.name,
                    );

                const skillContext =
                    await contextText(
                        skillName,
                        [
                            skillAchievement.name,
                            skillAchievement.type,
                            fixture.expected
                                .achievementDate,
                        ],
                    );

                expect(
                    skillContext,
                ).toContain(
                    skillAchievement.type,
                );

                expect(
                    skillContext,
                ).toContain(
                    fixture.expected
                        .achievementDate,
                );

                /*
                 * Logro tipo Desempeño.
                 */
                const performanceName =
                    exactText(
                        page,
                        performanceAchievement.name,
                    );

                const performanceContext =
                    await contextText(
                        performanceName,
                        [
                            performanceAchievement.name,
                            performanceAchievement.type,
                            fixture.expected
                                .achievementDate,
                        ],
                    );

                expect(
                    performanceContext,
                ).toContain(
                    performanceAchievement.type,
                );

                /*
                 * Logro tipo Puntaje todavía bloqueado.
                 */
                const scoreName =
                    exactText(
                        page,
                        scoreAchievement.name,
                    );

                const scoreContext =
                    await contextText(
                        scoreName,
                        [
                            scoreAchievement.name,
                            scoreAchievement.type,
                        ],
                    );

                expect(
                    scoreContext,
                ).toContain(
                    scoreAchievement.type,
                );
            },
        );

        test(
            'oculta los logros deshabilitados',
            async ({ page }) => {
                await login(page);
                await openAchievements(page);

                const disabledName =
                    PROGRESS_ACHIEVEMENT_DATA
                        .achievements
                        .disabled
                        .name;

                await expect(
                    page.getByText(
                        disabledName,
                        {
                            exact: true,
                        },
                    ),
                ).toHaveCount(0);

                /*
                 * Tampoco debe presentarse como
                 * logro reciente en el perfil.
                 */
                await openProfile(page);

                await expect(
                    page.getByText(
                        disabledName,
                        {
                            exact: true,
                        },
                    ),
                ).toHaveCount(0);
            },
        );
    },
);