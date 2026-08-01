import {
    expect,
    Page,
    test,
} from '@playwright/test';

import {
    AUTH_USERS,
    cleanupAuthFixtures,
    resetAuthFixtures,
} from './helpers/auth-fixtures';

/*
 * Dirección pública esperada después de
 * una redirección al inicio de sesión.
 */
const LOGIN_URL =
    /^http:\/\/localhost:3000\/$/;

/*
 * Rutas privadas destinadas a usuarios
 * generales autenticados.
 */
const USER_PRIVATE_ROUTES = [
    '/principal',
    '/aprendizaje',
    '/retos',
    '/blog',
    '/perfil',
    '/logros',
] as const;

/*
 * Rutas exclusivas del área administrativa.
 */
const ADMIN_PRIVATE_ROUTES = [
    '/admin/dashboard',
    '/admin/usuarios',
    '/admin/habilidades',
] as const;

/**
 * Inicia sesión utilizando el formulario público.
 */
async function login(
    page: Page,
    correo: string,
    password: string,
): Promise<void> {
    await page.goto('/');

    await page
        .locator('input[name="correo"]')
        .fill(correo);

    await page
        .locator('input[name="password"]')
        .fill(password);

    await page
        .locator('form.login__form')
        .locator(
            'input[type="submit"], button[type="submit"]',
        )
        .click();
}

/**
 * Cierra la sesión mediante la ruta POST
 * utilizando las cookies del navegador.
 */
async function logout(
    page: Page,
): Promise<void> {
    const logoutUrl = new URL(
        '/logout',
        page.url(),
    ).toString();

    /*
     * No se siguen automáticamente las redirecciones
     * para poder comprobar la respuesta del servidor.
     */
    const response =
        await page.request.post(
            logoutUrl,
            {
                maxRedirects: 0,
            },
        );

    /*
     * El controlador debe responder con una
     * redirección hacia el formulario de acceso.
     */
    expect(response.status()).toBe(302);

    expect(
        response.headers()['location'],
    ).toBe('/');

    /*
     * page.request comparte las cookies del contexto.
     * Después de destruir la sesión, se visita el inicio.
     */
    await page.goto('/');

    await expect(page).toHaveURL(
        LOGIN_URL,
    );

    await expect(
        page.locator(
            'input[name="correo"]',
        ),
    ).toBeVisible();
}

test.describe(
    'Protección de rutas y cierre de sesión',
    () => {
        test.beforeEach(() => {
            resetAuthFixtures();
        });

        test.afterAll(() => {
            cleanupAuthFixtures();
        });

        test(
            'redirige al inicio al visitar rutas de usuario sin sesión',
            async ({ page }) => {
                for (
                    const route
                    of USER_PRIVATE_ROUTES
                ) {
                    await page.goto(route);

                    await expect(
                        page,
                    ).toHaveURL(
                        LOGIN_URL,
                    );

                    await expect(
                        page.locator(
                            'input[name="correo"]',
                        ),
                    ).toBeVisible();
                }
            },
        );

        test(
            'redirige al inicio al visitar rutas administrativas sin sesión',
            async ({ page }) => {
                for (
                    const route
                    of ADMIN_PRIVATE_ROUTES
                ) {
                    await page.goto(route);

                    await expect(
                        page,
                    ).toHaveURL(
                        LOGIN_URL,
                    );
                }
            },
        );

        test(
            'permite a un usuario general acceder a sus rutas privadas',
            async ({ page }) => {
                await login(
                    page,
                    AUTH_USERS.general.email,
                    AUTH_USERS.general.password,
                );

                await expect(
                    page,
                ).toHaveURL(
                    /\/principal$/,
                );

                /*
                 * Se comprueban varias secciones
                 * pertenecientes al usuario general.
                 */
                for (
                    const route
                    of [
                        '/aprendizaje',
                        '/retos',
                        '/blog',
                    ]
                ) {
                    await page.goto(route);

                    await expect(
                        page,
                    ).toHaveURL(
                        new RegExp(
                            `${route}$`,
                        ),
                    );
                }
            },
        );

        test(
            'impide que un usuario general ingrese al área administrativa',
            async ({ page }) => {
                await login(
                    page,
                    AUTH_USERS.general.email,
                    AUTH_USERS.general.password,
                );

                await page.goto(
                    '/admin/dashboard',
                );

                await expect(
                    page,
                ).toHaveURL(
                    /\/principal$/,
                );

                await expect(
                    page,
                ).not.toHaveURL(
                    /\/admin\/dashboard$/,
                );
            },
        );

        test(
            'permite que un administrador ingrese al dashboard',
            async ({ page }) => {
                await login(
                    page,
                    AUTH_USERS.admin.email,
                    AUTH_USERS.admin.password,
                );

                await expect(
                    page,
                ).toHaveURL(
                    /\/admin\/dashboard$/,
                );

                await page.goto(
                    '/admin/usuarios',
                );

                await expect(
                    page,
                ).toHaveURL(
                    /\/admin\/usuarios(?:\?.*)?$/,
                );

                await page.goto(
                    '/admin/habilidades',
                );

                await expect(
                    page,
                ).toHaveURL(
                    /\/admin\/habilidades(?:\?.*)?$/,
                );
            },
        );

        test(
            'no permite cerrar sesión mediante una petición GET',
            async ({ page }) => {
                await page.goto('/logout');

                /*
                 * El router solo registró /logout
                 * para peticiones POST.
                 */
                await expect(
                    page,
                ).toHaveURL(
                    /\/404$/,
                );
            },
        );

        test(
            'elimina el acceso privado después de cerrar la sesión',
            async ({ page }) => {
                await login(
                    page,
                    AUTH_USERS.general.email,
                    AUTH_USERS.general.password,
                );

                await expect(
                    page,
                ).toHaveURL(
                    /\/principal$/,
                );

                await logout(page);

                await expect(
                    page,
                ).toHaveURL(
                    LOGIN_URL,
                );

                await expect(
                    page.locator(
                        'input[name="correo"]',
                    ),
                ).toBeVisible();

                /*
                 * La sesión anterior ya no debe servir
                 * para volver a entrar directamente.
                 */
                await page.goto(
                    '/principal',
                );

                await expect(
                    page,
                ).toHaveURL(
                    LOGIN_URL,
                );

                await page.goto(
                    '/admin/dashboard',
                );

                await expect(
                    page,
                ).toHaveURL(
                    LOGIN_URL,
                );
            },
        );
    },
);