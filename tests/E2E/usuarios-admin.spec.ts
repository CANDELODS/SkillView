import {
    expect,
    Locator,
    Page,
    test,
} from '@playwright/test';

import {
    ADMIN_USER_DATA,
    AdminUserFixtureIds,
    cleanupAdminUserFixtures,
    resetAdminUserFixtures,
} from './helpers/usuarios-admin-fixtures';

const LOGIN_URL =
    /^http:\/\/localhost:3000\/$/;

/**
 * Inicia sesión mediante el formulario
 * público de SkillView.
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
            'button[type="submit"], '
            + 'input[type="submit"]',
        )
        .click();
}

/**
 * Cierra la sesión mediante la ruta POST
 * compartiendo las cookies del navegador.
 */
async function logout(
    page: Page,
): Promise<void> {
    const response =
        await page.request.post(
            'http://localhost:3000/logout',
            {
                maxRedirects: 0,
            },
        );

    expect(
        response.status(),
    ).toBe(302);

    expect(
        response.headers()['location'],
    ).toBe('/');

    await page.goto('/');

    await expect(page).toHaveURL(
        LOGIN_URL,
    );
}

/**
 * Construye la dirección del formulario
 * administrativo de edición.
 */
function editUrl(
    idUsuario: number,
): string {
    return `/admin/usuarios/editar?id=${idUsuario}`;
}

/**
 * Localiza el formulario que contiene
 * los campos de edición del usuario.
 */
function editForm(
    page: Page,
): Locator {
    return page
        .locator('form')
        .filter({
            has: page.locator(
                'input[name="nombres"]',
            ),
        })
        .first();
}

/**
 * Completa los campos del formulario
 * administrativo de edición.
 */
async function fillEditForm(
    page: Page,
    data: {
        nombres: string;
        apellidos: string;
        edad: string;
        sexo: string;
        correo: string;
        universidad: string;
        carrera: string;
        habilitado: string;
        password?: string;
        password2?: string;
    },
): Promise<void> {
    await page
        .locator('input[name="nombres"]')
        .fill(data.nombres);

    await page
        .locator('input[name="apellidos"]')
        .fill(data.apellidos);

    await page
        .locator('input[name="edad"]')
        .fill(data.edad);

    await page
        .locator('select[name="sexo"]')
        .selectOption(data.sexo);

    await page
        .locator('input[name="correo"]')
        .fill(data.correo);

    await page
        .locator('input[name="universidad"]')
        .fill(data.universidad);

    await page
        .locator('input[name="carrera"]')
        .fill(data.carrera);

    await setAccountEnabled(
        page,
        data.habilitado === '1',
    );

    const password =
        page.locator(
            'input[name="password"]',
        );

    const password2 =
        page.locator(
            'input[name="password2"]',
        );

    if (await password.count()) {
        await password.fill(
            data.password
            ?? '',
        );
    }

    if (await password2.count()) {
        await password2.fill(
            data.password2
            ?? '',
        );
    }
}

/**
 * Envía el formulario administrativo.
 */
async function submitEditForm(
    page: Page,
): Promise<void> {
    const form = editForm(page);

    await form
        .locator(
            'button[type="submit"], '
            + 'input[type="submit"]',
        )
        .first()
        .click();

    await page.waitForLoadState(
        'domcontentloaded',
    );
}

/**
 * Omite temporalmente las restricciones HTML
 * para comprobar las validaciones del backend.
 */
async function disableNativeValidation(
    page: Page,
): Promise<void> {
    await editForm(page)
        .evaluate((element) => {
            const form =
                element as HTMLFormElement;

            form.noValidate = true;
        });
}

/**
 * Activa o desactiva la cuenta utilizando
 * el control disponible en el formulario.
 */
async function setAccountEnabled(
    page: Page,
    enabled: boolean,
): Promise<void> {
    const checkbox = page.locator(
        'input[type="checkbox"][name="habilitado"]',
    );

    if (await checkbox.count()) {
        await expect(
            checkbox,
        ).toBeVisible();

        await checkbox.setChecked(
            enabled,
        );

        if (enabled) {
            await expect(
                checkbox,
            ).toBeChecked();
        } else {
            await expect(
                checkbox,
            ).not.toBeChecked();
        }

        return;
    }

    /*
     * Esta alternativa permite que la prueba siga
     * funcionando si posteriormente el control se
     * cambia por una lista desplegable.
     */
    const select = page.locator(
        'select[name="habilitado"]',
    );

    if (await select.count()) {
        await select.selectOption(
            enabled
                ? '1'
                : '0',
        );

        return;
    }

    throw new Error(
        'No se encontró el control habilitado '
        + 'en el formulario de edición.',
    );
}

test.describe(
    'Gestión administrativa de usuarios',
    () => {
        let ids: AdminUserFixtureIds;

        test.beforeEach(() => {
            ids = resetAdminUserFixtures();
        });

        test.afterAll(() => {
            cleanupAdminUserFixtures();
        });

        test(
            'busca y muestra un usuario registrado',
            async ({ page }) => {
                await login(
                    page,
                    ADMIN_USER_DATA.admin.email,
                    ADMIN_USER_DATA.admin.password,
                );

                await page.goto(
                    '/admin/usuarios?busqueda='
                    + encodeURIComponent(
                        ADMIN_USER_DATA.editable.email,
                    ),
                );

                await expect(page).toHaveURL(
                    /\/admin\/usuarios\?busqueda=/,
                );

                await expect(
                    page.getByText(
                        ADMIN_USER_DATA.editable.email,
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        'Usuario Editable',
                        {
                            exact: true,
                        },
                    ),
                ).toBeVisible();
            },
        );

        test(
            'actualiza los datos y conserva la contraseña existente',
            async ({ page }) => {
                await login(
                    page,
                    ADMIN_USER_DATA.admin.email,
                    ADMIN_USER_DATA.admin.password,
                );

                await page.goto(
                    editUrl(ids.editable),
                );

                await expect(
                    page.locator(
                        'input[name="correo"]',
                    ),
                ).toHaveValue(
                    ADMIN_USER_DATA.editable.email,
                );

                await fillEditForm(
                    page,
                    {
                        nombres:
                            'María Fernanda',

                        apellidos:
                            'Pérez Gómez',

                        edad:
                            '28',

                        sexo:
                            '1',

                        correo:
                            ADMIN_USER_DATA.editable.email,

                        universidad:
                            'Universidad del Cauca',

                        carrera:
                            'Ingeniería de Sistemas',

                        habilitado:
                            '1',
                    },
                );

                await submitEditForm(page);

                /*
                 * Se abre nuevamente el formulario para
                 * comprobar los datos persistidos.
                 */
                await page.goto(
                    editUrl(ids.editable),
                );

                await expect(
                    page.locator(
                        'input[name="nombres"]',
                    ),
                ).toHaveValue(
                    'María Fernanda',
                );

                await expect(
                    page.locator(
                        'input[name="apellidos"]',
                    ),
                ).toHaveValue(
                    'Pérez Gómez',
                );

                await expect(
                    page.locator(
                        'input[name="edad"]',
                    ),
                ).toHaveValue('28');

                await expect(
                    page.locator(
                        'select[name="sexo"]',
                    ),
                ).toHaveValue('1');

                /*
                 * Al no proporcionar otra contraseña,
                 * la credencial original debe conservarse.
                 */
                await logout(page);

                await login(
                    page,
                    ADMIN_USER_DATA.editable.email,
                    ADMIN_USER_DATA.editable.password,
                );

                await expect(page).toHaveURL(
                    /\/principal$/,
                );
            },
        );

        test(
            'rechaza datos administrativos inválidos',
            async ({ page }) => {
                await login(
                    page,
                    ADMIN_USER_DATA.admin.email,
                    ADMIN_USER_DATA.admin.password,
                );

                await page.goto(
                    editUrl(ids.editable),
                );

                await disableNativeValidation(
                    page,
                );

                await fillEditForm(
                    page,
                    {
                        nombres:
                            'Usuario 123',

                        apellidos:
                            'Prueba E Dos E',

                        edad:
                            '17',

                        sexo:
                            '3',

                        correo:
                            ADMIN_USER_DATA.editable.email,

                        universidad:
                            'Universidad del Cauca',

                        carrera:
                            'Ingeniería de Sistemas',

                        habilitado:
                            '1',
                    },
                );

                await submitEditForm(page);

                await expect(
                    page.getByText(
                        /el nombre solo puede contener letras y espacios/i,
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        /la edad debe estar entre 18 y 35 años/i,
                    ),
                ).toBeVisible();

                /*
                 * La información inválida no debe haberse
                 * almacenado en la base de datos.
                 */
                await page.goto(
                    editUrl(ids.editable),
                );

                await expect(
                    page.locator(
                        'input[name="nombres"]',
                    ),
                ).toHaveValue(
                    'Usuario Editable',
                );
            },
        );

        test(
            'rechaza un correo asignado a otra cuenta',
            async ({ page }) => {
                await login(
                    page,
                    ADMIN_USER_DATA.admin.email,
                    ADMIN_USER_DATA.admin.password,
                );

                await page.goto(
                    editUrl(ids.editable),
                );

                await page
                    .locator(
                        'input[name="correo"]',
                    )
                    .fill(
                        ADMIN_USER_DATA.existing.email,
                    );

                await submitEditForm(page);

                await expect(
                    page.getByText(
                        /el correo ya pertenece a otro usuario/i,
                    ),
                ).toBeVisible();

                /*
                 * El correo original debe mantenerse.
                 */
                await page.goto(
                    editUrl(ids.editable),
                );

                await expect(
                    page.locator(
                        'input[name="correo"]',
                    ),
                ).toHaveValue(
                    ADMIN_USER_DATA.editable.email,
                );
            },
        );

        test(
            'deshabilita y rehabilita una cuenta',
            async ({ page }) => {
                await login(
                    page,
                    ADMIN_USER_DATA.admin.email,
                    ADMIN_USER_DATA.admin.password,
                );

                await page.goto(
                    editUrl(ids.editable),
                );

                await setAccountEnabled(
                    page,
                    false,
                );

                await submitEditForm(page);

                await logout(page);

                /*
                 * Una cuenta deshabilitada no debe
                 * poder iniciar sesión.
                 */
                await login(
                    page,
                    ADMIN_USER_DATA.editable.email,
                    ADMIN_USER_DATA.editable.password,
                );

                await expect(page).toHaveURL(
                    LOGIN_URL,
                );

                await expect(
                    page.getByText(
                        /tu cuenta se encuentra deshabilitada/i,
                    ),
                ).toBeVisible();

                /*
                 * El administrador vuelve a iniciar sesión
                 * y rehabilita la cuenta.
                 */
                await login(
                    page,
                    ADMIN_USER_DATA.admin.email,
                    ADMIN_USER_DATA.admin.password,
                );

                await page.goto(
                    editUrl(ids.editable),
                );

                await setAccountEnabled(
                    page,
                    true,
                );

                await submitEditForm(page);

                await logout(page);

                /*
                 * La cuenta rehabilitada recupera
                 * el acceso normal.
                 */
                await login(
                    page,
                    ADMIN_USER_DATA.editable.email,
                    ADMIN_USER_DATA.editable.password,
                );

                await expect(page).toHaveURL(
                    /\/principal$/,
                );
            },
        );

        test(
            'asigna una contraseña temporal y exige su cambio',
            async ({ page }) => {
                await login(
                    page,
                    ADMIN_USER_DATA.admin.email,
                    ADMIN_USER_DATA.admin.password,
                );

                await page.goto(
                    editUrl(ids.editable),
                );

                const temporal =
                    ADMIN_USER_DATA.editable
                        .temporaryPassword;

                await page
                    .locator(
                        'input[name="password"]',
                    )
                    .fill(temporal);

                await page
                    .locator(
                        'input[name="password2"]',
                    )
                    .fill(temporal);

                await submitEditForm(page);

                await logout(page);

                /*
                 * La contraseña anterior debe dejar
                 * de ser válida.
                 */
                await login(
                    page,
                    ADMIN_USER_DATA.editable.email,
                    ADMIN_USER_DATA.editable.password,
                );

                await expect(page).toHaveURL(
                    LOGIN_URL,
                );

                await expect(
                    page.getByText(
                        /la contraseña es incorrecta/i,
                    ),
                ).toBeVisible();

                /*
                 * La contraseña temporal permite continuar,
                 * pero dirige al cambio obligatorio.
                 */
                await login(
                    page,
                    ADMIN_USER_DATA.editable.email,
                    temporal,
                );

                await expect(page).toHaveURL(
                    /\/cambiar-password$/,
                );
            },
        );

        test(
            'impide que el administrador deshabilite su propia cuenta',
            async ({ page }) => {
                await login(
                    page,
                    ADMIN_USER_DATA.admin.email,
                    ADMIN_USER_DATA.admin.password,
                );

                await page.goto(
                    editUrl(ids.admin),
                );

                await setAccountEnabled(
                    page,
                    false,
                );

                await submitEditForm(page);

                await expect(
                    page.getByText(
                        /no puedes deshabilitar tu propia cuenta/i,
                    ),
                ).toBeVisible();

                /*
                 * La sesión administrativa debe continuar
                 * activa después del rechazo.
                 */
                await page.goto(
                    '/admin/dashboard',
                );

                await expect(page).toHaveURL(
                    /\/admin\/dashboard$/,
                );
            },
        );
    },
);