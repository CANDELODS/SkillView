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

/**
 * Completa y envía el formulario público
 * de inicio de sesión.
 */
async function iniciarSesion(
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

test.describe(
    'Inicio de sesión, roles y estados de la cuenta',
    () => {
        /*
         * Antes de cada caso se restauran los estados
         * originales de las cinco cuentas.
         */
        test.beforeEach(() => {
            resetAuthFixtures();
        });

        /*
         * Al finalizar el archivo se eliminan los registros.
         */
        test.afterAll(() => {
            cleanupAuthFixtures();
        });

        test(
            'permite el acceso de un usuario general',
            async ({ page }) => {
                await iniciarSesion(
                    page,
                    AUTH_USERS.general.email,
                    AUTH_USERS.general.password,
                );

                await expect(page).toHaveURL(
                    /\/principal$/,
                );

                await expect(
                    page.locator(
                        'input[name="correo"]',
                    ),
                ).toHaveCount(0);
            },
        );

        test(
            'dirige al administrador hacia el dashboard',
            async ({ page }) => {
                await iniciarSesion(
                    page,
                    AUTH_USERS.admin.email,
                    AUTH_USERS.admin.password,
                );

                await expect(page).toHaveURL(
                    /\/admin\/dashboard$/,
                );

                await expect(
                    page.locator(
                        'input[name="correo"]',
                    ),
                ).toHaveCount(0);
            },
        );

        test(
            'rechaza una contraseña incorrecta',
            async ({ page }) => {
                await iniciarSesion(
                    page,
                    AUTH_USERS.general.email,
                    'ContraseñaIncorrecta1!',
                );

                await expect(page).toHaveURL(
                    'http://localhost:3000/',
                );

                await expect(
                    page.getByText(
                        /la contraseña es incorrecta/i,
                    ),
                ).toBeVisible();

                await expect(
                    page.locator(
                        'input[name="correo"]',
                    ),
                ).toBeVisible();
            },
        );

        test(
            'rechaza un correo no registrado',
            async ({ page }) => {
                await iniciarSesion(
                    page,
                    AUTH_USERS.nonexistent.email,
                    AUTH_USERS.nonexistent.password,
                );

                await expect(page).toHaveURL(
                    'http://localhost:3000/',
                );

                await expect(
                    page.getByText(
                        /el usuario no existe/i,
                    ),
                ).toBeVisible();
            },
        );

        test(
            'bloquea una cuenta deshabilitada',
            async ({ page }) => {
                await iniciarSesion(
                    page,
                    AUTH_USERS.disabled.email,
                    AUTH_USERS.disabled.password,
                );

                await expect(page).toHaveURL(
                    'http://localhost:3000/',
                );

                await expect(
                    page.getByText(
                        /Tu cuenta se encuentra deshabilitada/i,
                    ),
                ).toBeVisible();

                await expect(page).not.toHaveURL(
                    /\/principal$/,
                );
            },
        );

        test(
            'prioriza el bloqueo sobre el cambio de contraseña',
            async ({ page }) => {
                await iniciarSesion(
                    page,
                    AUTH_USERS.disabledTemporary.email,
                    AUTH_USERS.disabledTemporary.password,
                );

                await expect(page).toHaveURL(
                    'http://localhost:3000/',
                );

                await expect(
                    page.getByText(
                        /Tu cuenta se encuentra deshabilitada/i,
                    ),
                ).toBeVisible();

                await expect(page).not.toHaveURL(
                    /\/cambiar-password$/,
                );
            },
        );

        test(
            'obliga a sustituir la contraseña temporal',
            async ({ page }) => {
                await iniciarSesion(
                    page,
                    AUTH_USERS.temporary.email,
                    AUTH_USERS.temporary.password,
                );

                await expect(page).toHaveURL(
                    /\/cambiar-password$/,
                );

                await expect(
                    page.getByRole('heading', {
                        name: 'Crea una nueva contraseña',
                    }),
                ).toBeVisible();

                await page
                    .locator('#password')
                    .fill(
                        AUTH_USERS.temporary.newPassword,
                    );

                await page
                    .locator('#password2')
                    .fill(
                        AUTH_USERS.temporary.newPassword,
                    );

                await page
                    .locator(
                        'form[action="/cambiar-password"]',
                    )
                    .locator(
                        'input[type="submit"], button[type="submit"]',
                    )
                    .click();

                await expect(page).toHaveURL(
                    /\/\?password_actualizado=1$/,
                );

                await expect(
                    page.getByText(
                        /Tu contraseña se actualizó correctamente/i,
                    ),
                ).toBeVisible();

                /*
                 * La contraseña temporal dejó de ser válida.
                 */
                await iniciarSesion(
                    page,
                    AUTH_USERS.temporary.email,
                    AUTH_USERS.temporary.password,
                );

                await expect(page).toHaveURL(
                    'http://localhost:3000/',
                );

                await expect(
                    page.getByText(
                        /la contraseña es incorrecta/i,
                    ),
                ).toBeVisible();

                /*
                 * La nueva contraseña permitió el acceso normal.
                 */
                await iniciarSesion(
                    page,
                    AUTH_USERS.temporary.email,
                    AUTH_USERS.temporary.newPassword,
                );

                await expect(page).toHaveURL(
                    /\/principal$/,
                );

                await expect(page).not.toHaveURL(
                    /\/cambiar-password$/,
                );
            },
        );
    },
);