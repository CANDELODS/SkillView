import {
    expect,
    type Locator,
    type Page,
    test,
} from '@playwright/test';

import {
    cleanupNavigationFixtures,
    NAVIGATION_DATA,
    type NavigationFixtureIds,
    resetNavigationFixtures,
} from './helpers/navegacion-filtros-fixtures';

/**
 * Inicia sesión mediante el formulario público.
 */
async function login(
    page: Page,
    email: string,
    password: string,
): Promise<void> {
    await page.goto('/');

    await page
        .locator('input[name="correo"]')
        .fill(email);

    await page
        .locator('input[name="password"]')
        .fill(password);

    await page
        .locator('form.login__form')
        .locator(
            'button[type="submit"], '
            + 'input[type="submit"]',
        )
        .click();
}

/**
 * Abre una ruta utilizando un enlace visible
 * de la navegación.
 */
async function clickNavigationLink(
    page: Page,
    route: string,
): Promise<void> {
    const link = page
        .locator(
            `a[href="${route}"]:visible`,
        )
        .first();

    await expect(link).toBeVisible();

    await Promise.all([
        page.waitForURL(
            (url) =>
                url.pathname === route,
        ),

        link.click(),
    ]);
}

/**
 * Localiza el formulario de filtros de Retos.
 */
function challengeFilterForm(
    page: Page,
): Locator {
    return page
        .locator('form')
        .filter({
            has: page.locator(
                'select[name="habilidad"]',
            ),
        })
        .filter({
            has: page.locator(
                'select[name="dificultad"]',
            ),
        })
        .first();
}

/**
 * Localiza el formulario de filtro del Blog.
 */
function blogFilterForm(
    page: Page,
): Locator {
    return page
        .locator('form')
        .filter({
            has: page.locator(
                'select[name="habilidad"]',
            ),
        })
        .first();
}

/**
 * Establece los valores de un formulario GET
 * y ejecuta su envío real.
 */
async function submitGetFilter(
    page: Page,
    form: Locator,
    expectedPath: string,
    values: Record<string, string>,
): Promise<void> {
    await Promise.all([
        page.waitForURL(
            (url) => {
                if (
                    url.pathname
                    !== expectedPath
                ) {
                    return false;
                }

                return Object
                    .entries(values)
                    .every(
                        (
                            [
                                name,
                                expectedValue,
                            ],
                        ) => {
                            const currentValue =
                                url.searchParams
                                    .get(name)
                                ?? '';

                            return currentValue
                                === expectedValue;
                        },
                    );
            },
        ),

        form.evaluate(
            (
                element,
                submittedValues,
            ) => {
                const currentForm =
                    element as HTMLFormElement;

                for (
                    const [
                        name,
                        value,
                    ]
                    of Object.entries(
                        submittedValues,
                    )
                ) {
                    const control =
                        currentForm.elements
                            .namedItem(name);

                    if (
                        control instanceof
                        HTMLSelectElement
                        || control instanceof
                        HTMLInputElement
                    ) {
                        control.value =
                            String(value);
                    }
                }

                currentForm.requestSubmit();
            },
            values,
        ),
    ]);
}

/**
 * Escapa los caracteres que tienen un significado
 * especial dentro de una expresión regular.
 */
function escapeRegularExpression(
    value: string,
): string {
    return value.replace(
        /[.*+?^${}()|[\]\\]/g,
        '\\$&',
    );
}

/**
 * Extrae del contenido visible los nombres paginados.
 *
 * La expresión no exige que todo el elemento contenga
 * exclusivamente el nombre, por lo que también funciona
 * cuando la vista muestra apellidos, estados u otros datos
 * dentro del mismo contenedor.
 */
async function visiblePaginatedNames(
    page: Page,
    prefix: string,
): Promise<string[]> {
    const pageText = await page
        .locator('body')
        .innerText();

    const pattern = new RegExp(
        `${escapeRegularExpression(prefix)}\\s+\\d{2}`,
        'g',
    );

    const matches =
        pageText.match(pattern)
        ?? [];

    /*
     * Set evita duplicados si el mismo nombre se presenta
     * en más de un elemento de la interfaz.
     */
    return Array.from(
        new Set(matches),
    );
}

/**
 * Abre una página numérica del componente.
 */
async function openPaginationPage(
    page: Page,
    pageNumber: number,
): Promise<void> {
    const numericLink = page
        .locator(
            'a.paginacion__enlace--numero',
        )
        .filter({
            hasText:
                new RegExp(
                    `^${pageNumber}$`,
                ),
        })
        .first();

    await expect(
        numericLink,
    ).toBeVisible();

    await Promise.all([
        page.waitForURL(
            (url) =>
                url.searchParams
                    .get('page')
                === String(pageNumber),
        ),

        numericLink.click(),
    ]);
}

/**
 * Construye los nombres esperados
 * para una página específica.
 */
function expectedNames(
    prefix: string,
    from: number,
    to: number,
): string[] {
    const names: string[] = [];

    for (
        let number = from;
        number <= to;
        number++
    ) {
        names.push(
            `${prefix} ${String(number)
                .padStart(2, '0')
            }`,
        );
    }

    return names;
}

test.describe(
    'Navegación, filtros y paginación',
    () => {
        let ids: NavigationFixtureIds;

        test.beforeEach(() => {
            ids =
                resetNavigationFixtures();
        });

        test.afterAll(() => {
            cleanupNavigationFixtures();
        });

        test(
            'navega entre las secciones principales sin perder la sesión',
            async ({ page }) => {
                await login(
                    page,
                    NAVIGATION_DATA.general.email,
                    NAVIGATION_DATA.general.password,
                );

                await expect(page).toHaveURL(
                    /\/principal$/,
                );

                await clickNavigationLink(
                    page,
                    '/aprendizaje',
                );

                await clickNavigationLink(
                    page,
                    '/retos',
                );

                await clickNavigationLink(
                    page,
                    '/blog',
                );

                await clickNavigationLink(
                    page,
                    '/principal',
                );

                await expect(
                    page.locator(
                        'input[name="correo"]',
                    ),
                ).toHaveCount(0);
            },
        );

        test(
            'filtra los retos por habilidad',
            async ({ page }) => {
                await login(
                    page,
                    NAVIGATION_DATA.general.email,
                    NAVIGATION_DATA.general.password,
                );

                await page.goto('/retos');

                await submitGetFilter(
                    page,
                    challengeFilterForm(page),
                    '/retos',
                    {
                        habilidad:
                            String(
                                ids.skillCommunication,
                            ),

                        dificultad:
                            '',
                    },
                );

                await expect(
                    page.locator(
                        'select[name="habilidad"]',
                    ),
                ).toHaveValue(
                    String(
                        ids.skillCommunication,
                    ),
                );

                await expect(
                    page.getByText(
                        NAVIGATION_DATA
                            .challenges.basic,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        NAVIGATION_DATA
                            .challenges.advanced,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        NAVIGATION_DATA
                            .challenges.intermediate,
                        {
                            exact: true,
                        },
                    ),
                ).toHaveCount(0);

                await expect(
                    page.getByText(
                        NAVIGATION_DATA
                            .challenges.disabled,
                        {
                            exact: true,
                        },
                    ),
                ).toHaveCount(0);
            },
        );

        test(
            'combina los filtros de reto y permite restablecerlos',
            async ({ page }) => {
                await login(
                    page,
                    NAVIGATION_DATA.general.email,
                    NAVIGATION_DATA.general.password,
                );

                await page.goto('/retos');

                /*
                 * Habilidad Comunicación y
                 * dificultad Avanzado.
                 */
                await submitGetFilter(
                    page,
                    challengeFilterForm(page),
                    '/retos',
                    {
                        habilidad:
                            String(
                                ids.skillCommunication,
                            ),

                        dificultad:
                            '3',
                    },
                );

                await expect(
                    page.locator(
                        'select[name="dificultad"]',
                    ),
                ).toHaveValue('3');

                await expect(
                    page.getByText(
                        NAVIGATION_DATA
                            .challenges.advanced,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        NAVIGATION_DATA
                            .challenges.basic,
                        {
                            exact: true,
                        },
                    ),
                ).toHaveCount(0);

                await expect(
                    page.getByText(
                        NAVIGATION_DATA
                            .challenges.intermediate,
                        {
                            exact: true,
                        },
                    ),
                ).toHaveCount(0);

                /*
                 * Restablecer ambos filtros.
                 */
                await submitGetFilter(
                    page,
                    challengeFilterForm(page),
                    '/retos',
                    {
                        habilidad:
                            '',

                        dificultad:
                            '',
                    },
                );

                await expect(
                    page.getByText(
                        NAVIGATION_DATA
                            .challenges.basic,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        NAVIGATION_DATA
                            .challenges.advanced,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        NAVIGATION_DATA
                            .challenges.intermediate,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        NAVIGATION_DATA
                            .challenges.disabled,
                        {
                            exact: true,
                        },
                    ),
                ).toHaveCount(0);
            },
        );

        test(
            'filtra los artículos por habilidad y restablece el filtro',
            async ({ page }) => {
                await login(
                    page,
                    NAVIGATION_DATA.general.email,
                    NAVIGATION_DATA.general.password,
                );

                await page.goto('/blog');

                /*
                 * Filtrar por Liderazgo.
                 */
                await submitGetFilter(
                    page,
                    blogFilterForm(page),
                    '/blog',
                    {
                        habilidad:
                            String(
                                ids.skillLeadership,
                            ),
                    },
                );

                await expect(
                    page.locator(
                        'select[name="habilidad"]',
                    ),
                ).toHaveValue(
                    String(
                        ids.skillLeadership,
                    ),
                );

                await expect(
                    page.getByText(
                        NAVIGATION_DATA
                            .blogs.leadership,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                /*
                 * El artículo compartido también
                 * pertenece a Liderazgo.
                 */
                await expect(
                    page.getByText(
                        NAVIGATION_DATA
                            .blogs.shared,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        NAVIGATION_DATA
                            .blogs.communication,
                        {
                            exact: true,
                        },
                    ),
                ).toHaveCount(0);

                await expect(
                    page.getByText(
                        NAVIGATION_DATA
                            .blogs.disabled,
                        {
                            exact: true,
                        },
                    ),
                ).toHaveCount(0);

                /*
                 * Restablecer el filtro.
                 */
                await submitGetFilter(
                    page,
                    blogFilterForm(page),
                    '/blog',
                    {
                        habilidad:
                            '',
                    },
                );

                await expect(
                    page.getByText(
                        NAVIGATION_DATA
                            .blogs.communication,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        NAVIGATION_DATA
                            .blogs.leadership,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        NAVIGATION_DATA
                            .blogs.shared,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        NAVIGATION_DATA
                            .blogs.disabled,
                        {
                            exact: true,
                        },
                    ),
                ).toHaveCount(0);
            },
        );

        test(
            'pagina los usuarios y conserva el criterio de búsqueda',
            async ({ page }) => {
                await login(
                    page,
                    NAVIGATION_DATA.admin.email,
                    NAVIGATION_DATA.admin.password,
                );

                const search =
                    NAVIGATION_DATA
                        .searches.users;

                await page.goto(
                    '/admin/usuarios?busqueda='
                    + encodeURIComponent(search),
                );

                /*
                 * Primera página: registros 01 al 05.
                 */
                await expect(
                    page.locator(
                        '.paginacion__enlace--actual',
                    ),
                ).toHaveText('1');

                expect(
                    await visiblePaginatedNames(
                        page,
                        search,
                    ),
                ).toEqual(
                    expectedNames(
                        search,
                        1,
                        5,
                    ),
                );

                await expect(
                    page.getByText(
                        'Anterior',
                        {
                            exact: true,
                        },
                    ),
                ).toHaveCount(0);

                /*
                 * Segunda página: registros 06 al 10.
                 */
                await openPaginationPage(
                    page,
                    2,
                );

                expect(
                    new URL(
                        page.url(),
                    ).searchParams
                        .get('busqueda'),
                ).toBe(search);

                expect(
                    await visiblePaginatedNames(
                        page,
                        search,
                    ),
                ).toEqual(
                    expectedNames(
                        search,
                        6,
                        10,
                    ),
                );

                /*
                 * Última página: registros 11 y 12.
                 */
                await openPaginationPage(
                    page,
                    3,
                );

                expect(
                    new URL(
                        page.url(),
                    ).searchParams
                        .get('busqueda'),
                ).toBe(search);

                expect(
                    await visiblePaginatedNames(
                        page,
                        search,
                    ),
                ).toEqual(
                    expectedNames(
                        search,
                        11,
                        12,
                    ),
                );

                await expect(
                    page.getByText(
                        'Siguiente',
                        {
                            exact: true,
                        },
                    ),
                ).toHaveCount(0);
            },
        );

        test(
            'pagina las habilidades y conserva el criterio de búsqueda',
            async ({ page }) => {
                await login(
                    page,
                    NAVIGATION_DATA.admin.email,
                    NAVIGATION_DATA.admin.password,
                );

                const search =
                    NAVIGATION_DATA
                        .searches.skills;

                await page.goto(
                    '/admin/habilidades?busqueda='
                    + encodeURIComponent(search),
                );

                /*
                 * Primera página: registros 01 al 05.
                 */
                await expect(
                    page.locator(
                        '.paginacion__enlace--actual',
                    ),
                ).toHaveText('1');

                expect(
                    await visiblePaginatedNames(
                        page,
                        search,
                    ),
                ).toEqual(
                    expectedNames(
                        search,
                        1,
                        5,
                    ),
                );

                /*
                 * Segunda página: registros 06 al 10.
                 */
                await openPaginationPage(
                    page,
                    2,
                );

                expect(
                    new URL(
                        page.url(),
                    ).searchParams
                        .get('busqueda'),
                ).toBe(search);

                expect(
                    await visiblePaginatedNames(
                        page,
                        search,
                    ),
                ).toEqual(
                    expectedNames(
                        search,
                        6,
                        10,
                    ),
                );

                /*
                 * Última página: registros 11 y 12.
                 */
                await openPaginationPage(
                    page,
                    3,
                );

                expect(
                    new URL(
                        page.url(),
                    ).searchParams
                        .get('busqueda'),
                ).toBe(search);

                expect(
                    await visiblePaginatedNames(
                        page,
                        search,
                    ),
                ).toEqual(
                    expectedNames(
                        search,
                        11,
                        12,
                    ),
                );

                await expect(
                    page.getByText(
                        'Siguiente',
                        {
                            exact: true,
                        },
                    ),
                ).toHaveCount(0);
            },
        );

        test(
            'redirige las páginas inválidas y mantiene la búsqueda',
            async ({ page }) => {
                await login(
                    page,
                    NAVIGATION_DATA.admin.email,
                    NAVIGATION_DATA.admin.password,
                );

                const userSearch =
                    NAVIGATION_DATA
                        .searches.users;

                await page.goto(
                    '/admin/usuarios?page=0&busqueda='
                    + encodeURIComponent(
                        userSearch,
                    ),
                );

                let currentUrl =
                    new URL(
                        page.url(),
                    );

                expect(
                    currentUrl.pathname,
                ).toBe(
                    '/admin/usuarios',
                );

                expect(
                    currentUrl.searchParams
                        .get('page'),
                ).toBe('1');

                expect(
                    currentUrl.searchParams
                        .get('busqueda'),
                ).toBe(userSearch);

                const skillSearch =
                    NAVIGATION_DATA
                        .searches.skills;

                await page.goto(
                    '/admin/habilidades?page=99&busqueda='
                    + encodeURIComponent(
                        skillSearch,
                    ),
                );

                currentUrl =
                    new URL(
                        page.url(),
                    );

                expect(
                    currentUrl.pathname,
                ).toBe(
                    '/admin/habilidades',
                );

                expect(
                    currentUrl.searchParams
                        .get('page'),
                ).toBe('1');

                expect(
                    currentUrl.searchParams
                        .get('busqueda'),
                ).toBe(skillSearch);
            },
        );
    },
);