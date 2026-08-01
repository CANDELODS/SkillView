import {
    expect,
    Page,
    test,
} from '@playwright/test';

import {
    cleanupRegistrationFixtures,
    REGISTRATION_USERS,
    resetRegistrationFixtures,
} from './helpers/registro-fixtures';

type RegistrationData = {
    nombres: string;
    apellidos: string;
    edad: string;
    sexo: string;
    universidad: string;
    carrera: string;
    correo: string;
    password: string;
    password2: string;
    autorizaDatos: boolean;
};

const VALID_PASSWORD =
    'CajaNegra1!';

/**
 * Construye datos válidos y permite reemplazar
 * únicamente los valores necesarios para cada caso.
 */
function registrationData(
    changes: Partial<RegistrationData> = {},
): RegistrationData {
    return {
        nombres: 'María Fernanda',
        apellidos: 'Pérez Gómez',
        edad: '24',
        sexo: '3',
        universidad: 'Universidad del Cauca',
        carrera: 'Ingeniería de Sistemas',
        correo: REGISTRATION_USERS.newUser.email,
        password: VALID_PASSWORD,
        password2: VALID_PASSWORD,
        autorizaDatos: true,
        ...changes,
    };
}

/**
 * Abre el formulario y completa todos sus campos.
 */
async function fillRegistrationForm(
    page: Page,
    data: RegistrationData,
): Promise<void> {
    await page.goto('/registro');

    await page.locator('#nombres')
        .fill(data.nombres);

    await page.locator('#apellidos')
        .fill(data.apellidos);

    await page.locator('#edad')
        .fill(data.edad);

    await page.locator('#sexo')
        .selectOption(data.sexo);

    await page.locator('#universidad')
        .fill(data.universidad);

    await page.locator('#carrera')
        .fill(data.carrera);

    await page.locator('#correo')
        .fill(data.correo);

    await page.locator('#password')
        .fill(data.password);

    await page.locator('#password2')
        .fill(data.password2);

    const privacyCheckbox =
        page.locator('#autoriza_tratamiento_datos');

    if (data.autorizaDatos) {
        await privacyCheckbox.check();
    } else {
        await privacyCheckbox.uncheck();
    }
}

async function submitRegistration(
    page: Page,
): Promise<void> {
    await page.getByRole(
        'button',
        {
            name: 'Crear Cuenta',
        },
    ).click();
}

async function disableNativeValidation(
    page: Page,
): Promise<void> {
    await page
        .locator('form.register__form')
        .evaluate((element) => {
            const form = element as HTMLFormElement;

            form.noValidate = true;
        });
}

test.describe(
    'Registro de usuarios y mensajes de validación',
    () => {
        test.beforeEach(() => {
            resetRegistrationFixtures();
        });

        test.afterAll(() => {
            cleanupRegistrationFixtures();
        });

        test(
            'registra correctamente un usuario con datos válidos',
            async ({ page }) => {
                await fillRegistrationForm(
                    page,
                    registrationData(),
                );

                await submitRegistration(page);

                /*
                 * El sistema mostró el modal después
                 * de crear la cuenta.
                 */
                await expect(
                    page.getByRole('heading', {
                        name: 'Cuenta creada',
                    }),
                ).toBeVisible();

                await expect(
                    page.locator(
                        '#modal-registro-exitoso .modal__message',
                    ),
                ).toContainText(
                    'La cuenta se creó correctamente',
                );

                /*
                 * El usuario utilizó la opción
                 * "Prefiero no decirlo".
                 */
                await expect(
                    page.locator('#sexo'),
                ).toHaveValue('3');

                /*
                 * El botón del modal dirigió inmediatamente
                 * a la página principal.
                 */
                await page.getByRole(
                    'button',
                    {
                        name: /Ir ahora a la página principal/i,
                    },
                ).click();

                await expect(page).toHaveURL(
                    /\/principal$/,
                );
            },
        );

        test(
            'muestra mensajes cuando los campos obligatorios están vacíos',
            async ({ page }) => {
                await page.goto('/registro');

                /*
                 * Se simula un cliente que omite las
                 * restricciones HTML del formulario.
                 */
                await disableNativeValidation(page);

                await submitRegistration(page);

                await expect(page).toHaveURL(
                    /\/registro$/,
                );

                await expect(
                    page.getByText(
                        /el nombre es obligatorio y no puede estar vacío/i,
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        /el apellido es obligatorio y no puede estar vacío/i,
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        /la edad debe estar entre 18 y 35 años/i,
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        /el sexo es obligatorio/i,
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        /el correo es obligatorio/i,
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        /la contraseña no puede ir vacía/i,
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        /debes autorizar el tratamiento de tus datos personales/i,
                    ),
                ).toBeVisible();
            },
        );

        test(
            'aplica las restricciones del nombre, la edad y el correo',
            async ({ page }) => {
                await fillRegistrationForm(
                    page,
                    registrationData({
                        nombres: 'María123',
                        edad: '17',
                        correo: 'correo-invalido',
                    }),
                );

                const nombre =
                    page.locator('#nombres');

                const edad =
                    page.locator('#edad');

                const correo =
                    page.locator('#correo');

                /*
                 * Se consulta directamente la API de validación
                 * nativa del navegador.
                 */
                const validezNombre =
                    await nombre.evaluate((element) => {
                        const input =
                            element as HTMLInputElement;

                        return {
                            valid:
                                input.validity.valid,

                            patternMismatch:
                                input.validity.patternMismatch,
                        };
                    });

                const validezEdad =
                    await edad.evaluate((element) => {
                        const input =
                            element as HTMLInputElement;

                        return {
                            valid:
                                input.validity.valid,

                            rangeUnderflow:
                                input.validity.rangeUnderflow,
                        };
                    });

                const validezCorreo =
                    await correo.evaluate((element) => {
                        const input =
                            element as HTMLInputElement;

                        return {
                            valid:
                                input.validity.valid,

                            typeMismatch:
                                input.validity.typeMismatch,
                        };
                    });

                expect(validezNombre).toEqual({
                    valid: false,
                    patternMismatch: true,
                });

                expect(validezEdad).toEqual({
                    valid: false,
                    rangeUnderflow: true,
                });

                expect(validezCorreo).toEqual({
                    valid: false,
                    typeMismatch: true,
                });

                /*
                 * El formulario completo debe ser inválido
                 * antes de intentar enviarlo.
                 */
                const formularioValido =
                    await page
                        .locator('form.register__form')
                        .evaluate((element) => {
                            const form =
                                element as HTMLFormElement;

                            return form.checkValidity();
                        });

                expect(formularioValido).toBe(false);

                /*
                 * El navegador debe impedir el envío y
                 * conservar al usuario en /registro.
                 */
                await submitRegistration(page);

                await expect(page).toHaveURL(
                    /\/registro$/,
                );

                await expect(
                    page.getByRole('heading', {
                        name: 'Cuenta creada',
                    }),
                ).not.toBeVisible();
            },
        );

        test(
            'rechaza una contraseña sin la fortaleza requerida',
            async ({ page }) => {
                await fillRegistrationForm(
                    page,
                    registrationData({
                        password: 'abcdef',
                        password2: 'abcdef',
                    }),
                );

                await submitRegistration(page);

                await expect(page).toHaveURL(
                    /\/registro$/,
                );

                await expect(
                    page.getByText(
                        /debe contener al menos una letra mayúscula/i,
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        /debe contener al menos un número/i,
                    ),
                ).toBeVisible();

                await expect(
                    page.getByText(
                        /debe contener al menos un carácter especial/i,
                    ),
                ).toBeVisible();
            },
        );

        test(
            'rechaza contraseñas diferentes',
            async ({ page }) => {
                await fillRegistrationForm(
                    page,
                    registrationData({
                        password: 'CajaNegra1!',
                        password2: 'CajaNegra2!',
                    }),
                );

                await submitRegistration(page);

                await expect(page).toHaveURL(
                    /\/registro$/,
                );

                await expect(
                    page.getByText(
                        /las contraseñas no coinciden/i,
                    ),
                ).toBeVisible();
            },
        );

        test(
            'exige la autorización para el tratamiento de datos',
            async ({ page }) => {
                await fillRegistrationForm(
                    page,
                    registrationData({
                        autorizaDatos: false,
                    }),
                );

                await submitRegistration(page);

                await expect(page).toHaveURL(
                    /\/registro$/,
                );

                await expect(
                    page.getByText(
                        /debes autorizar el tratamiento de tus datos personales para crear una cuenta/i,
                    ),
                ).toBeVisible();

                await expect(
                    page.locator(
                        '#autoriza_tratamiento_datos',
                    ),
                ).not.toBeChecked();
            },
        );

        test(
            'rechaza un correo previamente registrado',
            async ({ page }) => {
                const data = registrationData({
                    nombres: 'Usuario Repetido',
                    correo:
                        REGISTRATION_USERS.existingUser.email,
                });

                await fillRegistrationForm(
                    page,
                    data,
                );

                await submitRegistration(page);

                await expect(page).toHaveURL(
                    /\/registro$/,
                );

                await expect(
                    page.getByText(
                        /el usuario ya esta registrado/i,
                    ),
                ).toBeVisible();

                /*
                 * Los datos escritos se conservaron para
                 * permitir su corrección.
                 */
                await expect(
                    page.locator('#nombres'),
                ).toHaveValue(
                    data.nombres,
                );

                await expect(
                    page.locator('#correo'),
                ).toHaveValue(
                    data.correo,
                );

                await expect(
                    page.getByRole('heading', {
                        name: 'Cuenta creada',
                    }),
                ).not.toBeVisible();
            },
        );
    },
);