import {
  expect,
  type Locator,
  type Page,
  test,
} from '@playwright/test';

import {
  cleanupProfileFixtures,
  PROFILE_DATA,
  resetProfileFixtures,
} from './helpers/perfil-fixtures';

const LOGIN_URL =
  /^http:\/\/localhost:3000\/$/;

/**
 * Inicia sesión mediante el formulario público.
 */
async function login(
  page: Page,
  email: string =
    PROFILE_DATA.user.email,
  password: string =
    PROFILE_DATA.user.password,
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
 * Cierra la sesión utilizando la misma cookie
 * del navegador.
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

  await page.goto('/');

  await expect(page).toHaveURL(
    LOGIN_URL,
  );
}

/**
 * Localiza el formulario que contiene los cuatro
 * campos autorizados para la edición del perfil.
 */
function profileForm(
  page: Page,
): Locator {
  return page
    .locator('form')
    .filter({
      has: page.locator(
        'input[name="nombres"]',
      ),
    })
    .filter({
      has: page.locator(
        'input[name="apellidos"]',
      ),
    })
    .filter({
      has: page.locator(
        'input[name="universidad"]',
      ),
    })
    .filter({
      has: page.locator(
        'input[name="carrera"]',
      ),
    })
    .first();
}

/**
 * Abre la página del perfil y muestra su formulario
 * de edición cuando se encuentra dentro de un modal.
 */
async function openProfileEditor(
  page: Page,
): Promise<Locator> {
  await page.goto('/perfil');

  await expect(page).toHaveURL(
    /\/perfil$/,
  );

  const form =
    profileForm(page);

  const alreadyVisible =
    await form
      .isVisible()
      .catch(() => false);

  if (!alreadyVisible) {
    const trigger = page
      .locator(
        'button:visible, a:visible',
      )
      .filter({
        hasText:
          /editar perfil/i,
      })
      .first();

    await expect(
      trigger,
    ).toBeVisible();

    await trigger.click();
  }

  await expect(
    form,
  ).toBeVisible();

  return form;
}

/**
 * Completa los cuatro campos permitidos.
 */
async function fillProfileForm(
  form: Locator,
  data: {
    names: string;
    surnames: string;
    university: string;
    career: string;
  },
): Promise<void> {
  await form
    .locator(
      'input[name="nombres"]',
    )
    .fill(data.names);

  await form
    .locator(
      'input[name="apellidos"]',
    )
    .fill(data.surnames);

  await form
    .locator(
      'input[name="universidad"]',
    )
    .fill(data.university);

  await form
    .locator(
      'input[name="carrera"]',
    )
    .fill(data.career);
}

/**
 * Envía el formulario del perfil.
 */
async function submitProfileForm(
  page: Page,
  form: Locator,
): Promise<void> {
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
 * Omite temporalmente la validación HTML
 * para comprobar las reglas del servidor.
 */
async function disableNativeValidation(
  form: Locator,
): Promise<void> {
  await form.evaluate(
    (element) => {
      const currentForm =
        element as HTMLFormElement;

      currentForm.noValidate =
        true;
    },
  );
}

/**
 * Elimina temporalmente los maxlength para poder
 * enviar textos superiores al límite al servidor.
 */
async function removeLengthLimits(
  form: Locator,
): Promise<void> {
  await form
    .locator(
      'input[name="nombres"], '
      + 'input[name="apellidos"], '
      + 'input[name="universidad"], '
      + 'input[name="carrera"]',
    )
    .evaluateAll(
      (elements) => {
        elements.forEach(
          (element) => {
            element.removeAttribute(
              'maxlength',
            );
          },
        );
      },
    );
}

/**
 * Comprueba que un dato restringido no esté
 * disponible como control editable.
 */
async function expectRestrictedControl(
  form: Locator,
  fieldName: string,
): Promise<void> {
  const controls =
    form.locator(
      `[name="${fieldName}"]`,
    );

  const editableControls =
    await controls.evaluateAll(
      (elements) => {
        return elements.filter(
          (element) => {
            if (
              element instanceof
              HTMLInputElement
            ) {
              return (
                element.type !== 'hidden'
                && !element.disabled
                && !element.readOnly
              );
            }

            if (
              element instanceof
              HTMLSelectElement
              || element instanceof
              HTMLTextAreaElement
            ) {
              return !element.disabled;
            }

            return false;
          },
        ).length;
      },
    );

  expect(
    editableControls,
  ).toBe(0);
}

/**
 * Cierra el formulario o modal sin enviar datos.
 */
async function cancelProfileEdition(
  page: Page,
  form: Locator,
): Promise<void> {
  const cancelInsideForm =
    form
      .locator(
        'button:visible, a:visible',
      )
      .filter({
        hasText:
          /cancelar|cerrar/i,
      })
      .first();

  if (
    await cancelInsideForm
      .isVisible()
      .catch(() => false)
  ) {
    await cancelInsideForm.click();

    return;
  }

  const modalClose = page
    .locator(
      '[data-modal-close]:visible, '
      + '[data-profile-modal-close]:visible',
    )
    .first();

  await expect(
    modalClose,
  ).toBeVisible();

  await modalClose.click();
}

/**
 * Agrega campos internos que no forman parte
 * del formulario legítimo.
 */
async function injectRestrictedFields(
  form: Locator,
): Promise<void> {
  await form.evaluate(
    (
      element,
      values,
    ) => {
      const currentForm =
        element as HTMLFormElement;

      for (
        const [
          name,
          value,
        ]
        of Object.entries(values)
      ) {
        const input =
          document.createElement(
            'input',
          );

        input.type =
          'hidden';

        input.name =
          name;

        input.value =
          String(value);

        currentForm.appendChild(
          input,
        );
      }
    },
    {
      correo:
        PROFILE_DATA.injected.email,

      edad:
        '35',

      sexo:
        '0',

      admin:
        '1',

      habilitado:
        '0',

      debe_cambiar_password:
        '1',
    },
  );
}

test.describe(
  'Edición del perfil',
  () => {
    test.beforeEach(() => {
      resetProfileFixtures();
    });

    test.afterAll(() => {
      cleanupProfileFixtures();
    });

    test(
      'muestra únicamente los campos autorizados para editar',
      async ({ page }) => {
        await login(page);

        await expect(page).toHaveURL(
          /\/principal$/,
        );

        const form =
          await openProfileEditor(
            page,
          );

        await expect(
          form.locator(
            'input[name="nombres"]',
          ),
        ).toHaveValue(
          PROFILE_DATA.user.names,
        );

        await expect(
          form.locator(
            'input[name="apellidos"]',
          ),
        ).toHaveValue(
          PROFILE_DATA.user.surnames,
        );

        await expect(
          form.locator(
            'input[name="universidad"]',
          ),
        ).toHaveValue(
          PROFILE_DATA.user.university,
        );

        await expect(
          form.locator(
            'input[name="carrera"]',
          ),
        ).toHaveValue(
          PROFILE_DATA.user.career,
        );

        /*
         * Estos datos no deben poder modificarse
         * desde la edición del perfil.
         */
        for (
          const restrictedField
          of [
            'correo',
            'edad',
            'sexo',
            'password',
            'password2',
            'admin',
            'habilitado',
          ]
        ) {
          await expectRestrictedControl(
            form,
            restrictedField,
          );
        }
      },
    );

    test(
      'actualiza y normaliza los datos autorizados',
      async ({ page }) => {
        await login(page);

        const form =
          await openProfileEditor(
            page,
          );

        await fillProfileForm(
          form,
          {
            names:
              '  María Fernanda  ',

            surnames:
              '  Pérez Gómez  ',

            university:
              '  Universidad Autónoma del Cauca  ',

            career:
              '  Ingeniería de Sistemas  ',
          },
        );

        await submitProfileForm(
          page,
          form,
        );

        /*
         * Se carga nuevamente la página para
         * comprobar los valores persistidos.
         */
        const persistedForm =
          await openProfileEditor(
            page,
          );

        await expect(
          persistedForm.locator(
            'input[name="nombres"]',
          ),
        ).toHaveValue(
          PROFILE_DATA.updated.names,
        );

        await expect(
          persistedForm.locator(
            'input[name="apellidos"]',
          ),
        ).toHaveValue(
          PROFILE_DATA.updated.surnames,
        );

        await expect(
          persistedForm.locator(
            'input[name="universidad"]',
          ),
        ).toHaveValue(
          PROFILE_DATA.updated.university,
        );

        await expect(
          persistedForm.locator(
            'input[name="carrera"]',
          ),
        ).toHaveValue(
          PROFILE_DATA.updated.career,
        );

        /*
         * El correo y la contraseña originales
         * continúan permitiendo el acceso.
         */
        await logout(page);

        await login(page);

        await expect(page).toHaveURL(
          /\/principal$/,
        );
      },
    );

    test(
      'descarta los cambios cuando se cancela la edición',
      async ({ page }) => {
        await login(page);

        const form =
          await openProfileEditor(
            page,
          );

        await fillProfileForm(
          form,
          {
            names:
              'Nombre No Guardado',

            surnames:
              'Apellido No Guardado',

            university:
              'Universidad No Guardada',

            career:
              'Carrera No Guardada',
          },
        );

        await cancelProfileEdition(
          page,
          form,
        );

        const reopenedForm =
          await openProfileEditor(
            page,
          );

        await expect(
          reopenedForm.locator(
            'input[name="nombres"]',
          ),
        ).toHaveValue(
          PROFILE_DATA.user.names,
        );

        await expect(
          reopenedForm.locator(
            'input[name="universidad"]',
          ),
        ).toHaveValue(
          PROFILE_DATA.user.university,
        );
      },
    );

    test(
      'rechaza los campos obligatorios vacíos',
      async ({ page }) => {
        await login(page);

        const form =
          await openProfileEditor(
            page,
          );

        await disableNativeValidation(
          form,
        );

        await fillProfileForm(
          form,
          {
            names:
              '',

            surnames:
              '',

            university:
              '',

            career:
              '',
          },
        );

        await submitProfileForm(
          page,
          form,
        );

        await expect(
          page.getByText(
            /el nombre.*obligatorio.*vacío/i,
          ),
        ).toBeVisible();

        await expect(
          page.getByText(
            /los apellidos.*obligatorio.*vacío/i,
          ),
        ).toBeVisible();

        await expect(
          page.getByText(
            /la universidad.*obligatori.*vacío/i,
          ),
        ).toBeVisible();

        await expect(
          page.getByText(
            /la carrera.*obligatori.*vacío/i,
          ),
        ).toBeVisible();

        /*
         * Los datos originales deben conservarse.
         */
        const persistedForm =
          await openProfileEditor(
            page,
          );

        await expect(
          persistedForm.locator(
            'input[name="nombres"]',
          ),
        ).toHaveValue(
          PROFILE_DATA.user.names,
        );
      },
    );

    test(
      'rechaza números y caracteres no permitidos',
      async ({ page }) => {
        await login(page);

        const form =
          await openProfileEditor(
            page,
          );

        await disableNativeValidation(
          form,
        );

        await fillProfileForm(
          form,
          {
            names:
              'Usuario123',

            surnames:
              'Apellido#',

            university:
              'Universidad 123',

            career:
              'Ingeniería 456',
          },
        );

        await submitProfileForm(
          page,
          form,
        );

        await expect(
          page.getByText(
            /el nombre solo puede contener letras y espacios/i,
          ),
        ).toBeVisible();

        await expect(
          page.getByText(
            /los apellidos solo puede contener letras y espacios/i,
          ),
        ).toBeVisible();

        await expect(
          page.getByText(
            /la universidad solo puede contener letras y espacios/i,
          ),
        ).toBeVisible();

        await expect(
          page.getByText(
            /la carrera solo puede contener letras y espacios/i,
          ),
        ).toBeVisible();

        const persistedForm =
          await openProfileEditor(
            page,
          );

        await expect(
          persistedForm.locator(
            'input[name="nombres"]',
          ),
        ).toHaveValue(
          PROFILE_DATA.user.names,
        );
      },
    );

    test(
      'rechaza textos superiores a las longitudes permitidas',
      async ({ page }) => {
        await login(page);

        const form =
          await openProfileEditor(
            page,
          );

        await disableNativeValidation(
          form,
        );

        await removeLengthLimits(
          form,
        );

        await fillProfileForm(
          form,
          {
            names:
              'A'.repeat(26),

            surnames:
              'B'.repeat(26),

            university:
              'C'.repeat(46),

            career:
              'D'.repeat(46),
          },
        );

        await submitProfileForm(
          page,
          form,
        );

        await expect(
          page.getByText(
            /el nombre no puede superar 25 caracteres/i,
          ),
        ).toBeVisible();

        await expect(
          page.getByText(
            /los apellidos no puede superar 25 caracteres/i,
          ),
        ).toBeVisible();

        await expect(
          page.getByText(
            /la universidad no puede superar 45 caracteres/i,
          ),
        ).toBeVisible();

        await expect(
          page.getByText(
            /la carrera no puede superar 45 caracteres/i,
          ),
        ).toBeVisible();

        const persistedForm =
          await openProfileEditor(
            page,
          );

        await expect(
          persistedForm.locator(
            'input[name="nombres"]',
          ),
        ).toHaveValue(
          PROFILE_DATA.user.names,
        );
      },
    );

    test(
      'ignora los campos restringidos agregados a la solicitud',
      async ({ page }) => {
        await login(page);

        const form =
          await openProfileEditor(
            page,
          );

        await injectRestrictedFields(
          form,
        );

        await submitProfileForm(
          page,
          form,
        );

        await logout(page);

        /*
         * El correo inyectado no debe permitir
         * encontrar la cuenta.
         */
        await login(
          page,
          PROFILE_DATA.injected.email,
          PROFILE_DATA.user.password,
        );

        await expect(page).toHaveURL(
          LOGIN_URL,
        );

        /*
         * El correo y la contraseña originales
         * continúan funcionando.
         *
         * También se comprueba que la cuenta sigue
         * habilitada, mantiene el rol general y no
         * exige un cambio de contraseña.
         */
        await login(
          page,
          PROFILE_DATA.user.email,
          PROFILE_DATA.user.password,
        );

        await expect(page).toHaveURL(
          /\/principal$/,
        );

        await expect(page).not.toHaveURL(
          /\/admin\/dashboard$/,
        );

        await expect(page).not.toHaveURL(
          /\/cambiar-password$/,
        );

        await page.goto('/perfil');

        await expect(
          page.getByText(
            PROFILE_DATA.user.email,
            {
              exact: true,
            },
          ),
        ).toBeVisible();
      },
    );
  },
);