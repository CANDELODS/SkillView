import {
  expect,
  type Locator,
  type Page,
  test,
} from '@playwright/test';

import {
  ADMIN_SKILL_DATA,
  type AdminSkillFixtureIds,
  cleanupAdminSkillFixtures,
  resetAdminSkillFixtures,
} from './helpers/habilidades-admin-fixtures';

/**
 * Inicia sesión mediante el formulario público.
 */
async function loginAdmin(
  page: Page,
): Promise<void> {
  await page.goto('/');

  await page
    .locator('input[name="correo"]')
    .fill(
      ADMIN_SKILL_DATA.admin.email,
    );

  await page
    .locator('input[name="password"]')
    .fill(
      ADMIN_SKILL_DATA.admin.password,
    );

  await page
    .locator('form.login__form')
    .locator(
      'button[type="submit"], '
      + 'input[type="submit"]',
    )
    .click();

  await expect(page).toHaveURL(
    /\/admin\/dashboard$/,
  );
}

/**
 * Construye la ruta del formulario de edición.
 */
function editSkillUrl(
  idHabilidad: number,
): string {
  return (
    '/admin/habilidades/editar?id='
    + idHabilidad
  );
}

/**
 * Localiza el formulario administrativo de
 * creación o edición de habilidades.
 */
function skillForm(
  page: Page,
): Locator {
  return page
    .locator('form')
    .filter({
      has: page.locator(
        'input[name="nombre"]',
      ),
    })
    .first();
}

/**
 * Localiza únicamente el checkbox visible.
 *
 * La vista también incluye un input hidden
 * con el mismo nombre.
 */
function enabledCheckbox(
  page: Page,
): Locator {
  return page.locator(
    'input[type="checkbox"]'
    + '[name="habilitado"]',
  );
}

/**
 * Cambia el estado de la habilidad utilizando
 * el control real disponible en la vista.
 */
async function setSkillEnabled(
  page: Page,
  enabled: boolean,
): Promise<void> {
  const checkbox =
    enabledCheckbox(page);

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
   * Alternativa de respaldo si en el futuro
   * se cambia el checkbox por un select.
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
    'No se encontró el control del estado '
    + 'de la habilidad.',
  );
}

/**
 * Completa el campo visual de etiquetas y
 * sincroniza el input hidden enviado al servidor.
 */
async function setSkillTags(
  page: Page,
  tags: string,
): Promise<void> {
  const visibleInput =
    page.locator('#tags_input');

  if (await visibleInput.count()) {
    await visibleInput.fill(
      tags,
    );
  }

  const hiddenInput = page.locator(
    'input[type="hidden"][name="tag"]',
  );

  await expect(
    hiddenInput,
  ).toHaveCount(1);

  await hiddenInput.evaluate(
    (
      element,
      value,
    ) => {
      const input =
        element as HTMLInputElement;

      input.value =
        String(value);

      input.dispatchEvent(
        new Event(
          'input',
          {
            bubbles: true,
          },
        ),
      );

      input.dispatchEvent(
        new Event(
          'change',
          {
            bubbles: true,
          },
        ),
      );
    },
    tags,
  );
}

/**
 * Completa los datos comunes del formulario.
 */
async function fillSkillForm(
  page: Page,
  data: {
    name: string;
    description: string;
    tags: string;
    enabled: boolean;
  },
): Promise<void> {
  await page
    .locator('input[name="nombre"]')
    .fill(data.name);

  await page
    .locator(
      'textarea[name="descripcion"]',
    )
    .fill(data.description);

  await setSkillTags(
    page,
    data.tags,
  );

  await setSkillEnabled(
    page,
    data.enabled,
  );
}

/**
 * Envía el formulario administrativo.
 */
async function submitSkillForm(
  page: Page,
): Promise<void> {
  const form =
    skillForm(page);

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
 * Cierra el modal de creación o edición exitosa.
 *
 * El JavaScript de SkillView redirige después
 * hacia /admin/habilidades.
 */
async function closeSuccessModal(
  page: Page,
): Promise<void> {
  const closeButton = page.locator(
    '#modal-registro-exitoso '
    + '[data-modal-close]',
  );

  await expect(
    closeButton,
  ).toBeVisible();

  await Promise.all([
    page.waitForURL(
      /\/admin\/habilidades(?:\?.*)?$/,
    ),

    closeButton.click(),
  ]);
}

/**
 * Sustituye los controles legítimos por un
 * valor no permitido para comprobar el backend.
 */
async function setInvalidSkillState(
  page: Page,
  invalidValue: string,
): Promise<void> {
  await skillForm(page)
    .evaluate(
      (
        element,
        value,
      ) => {
        const form =
          element as HTMLFormElement;

        const currentInputs =
          form.querySelectorAll(
            '[name="habilitado"]',
          );

        currentInputs.forEach(
          (input) => {
            input.remove();
          },
        );

        const invalidInput =
          document.createElement(
            'input',
          );

        invalidInput.type =
          'hidden';

        invalidInput.name =
          'habilitado';

        invalidInput.value =
          String(value);

        form.appendChild(
          invalidInput,
        );
      },
      invalidValue,
    );
}

/**
 * Localiza el formulario de eliminación que
 * contiene el identificador de la habilidad.
 */
function deleteSkillForm(
  page: Page,
  idHabilidad: number,
): Locator {
  return page
    .locator('form')
    .filter({
      has: page.locator(
        'input[type="hidden"]'
        + `[name="id"][value="${idHabilidad}"]`,
      ),
    })
    .filter({
      has: page.locator(
        'button[type="submit"], '
        + 'input[type="submit"]',
      ),
    })
    .first();
}

test.describe(
  'Gestión administrativa de habilidades blandas',
  () => {
    let ids: AdminSkillFixtureIds;

    test.beforeEach(() => {
      ids =
        resetAdminSkillFixtures();
    });

    test.afterAll(() => {
      cleanupAdminSkillFixtures();
    });

    test(
      'busca y muestra una habilidad registrada',
      async ({ page }) => {
        await loginAdmin(page);

        await page.goto(
          '/admin/habilidades?busqueda='
          + encodeURIComponent(
            ADMIN_SKILL_DATA.editable.name,
          ),
        );

        await expect(page).toHaveURL(
          /\/admin\/habilidades\?busqueda=/,
        );

        await expect(
          page.getByText(
            ADMIN_SKILL_DATA.editable.name,
            {
              exact: true,
            },
          ),
        ).toBeVisible();
      },
    );

    test(
      'crea una habilidad habilitada con datos válidos',
      async ({ page }) => {
        await loginAdmin(page);

        await page.goto(
          '/admin/habilidades/crear',
        );

        await fillSkillForm(
          page,
          {
            name:
              ADMIN_SKILL_DATA.created.name,

            description:
              'Habilidad creada mediante '
              + 'una prueba de caja negra.',

            tags:
              ADMIN_SKILL_DATA.editable.marker
              + ', Comunicación, Empatía',

            enabled:
              true,
          },
        );

        await submitSkillForm(page);

        await expect(
          page.getByRole(
            'heading',
            {
              name:
                'Habilidad Creada',
            },
          ),
        ).toBeVisible();

        await expect(
          page.locator(
            '#modal-registro-exitoso '
            + '.modal__message',
          ),
        ).toContainText(
          /la habilidad se creó correctamente/i,
        );

        await closeSuccessModal(
          page,
        );

        await page.goto(
          '/admin/habilidades?busqueda='
          + encodeURIComponent(
            ADMIN_SKILL_DATA.created.name,
          ),
        );

        await expect(
          page.getByText(
            ADMIN_SKILL_DATA.created.name,
            {
              exact: true,
            },
          ),
        ).toBeVisible();
      },
    );

    test(
      'rechaza los campos obligatorios vacíos',
      async ({ page }) => {
        await loginAdmin(page);

        await page.goto(
          '/admin/habilidades/crear',
        );

        await page
          .locator(
            'input[name="nombre"]',
          )
          .fill('');

        await page
          .locator(
            'textarea[name="descripcion"]',
          )
          .fill('');

        await setSkillTags(
          page,
          '',
        );

        await setSkillEnabled(
          page,
          true,
        );

        await submitSkillForm(page);

        await expect(page).toHaveURL(
          /\/admin\/habilidades\/crear$/,
        );

        await expect(
          page.getByText(
            /el nombre es obligatorio/i,
          ),
        ).toBeVisible();

        await expect(
          page.getByText(
            /la descripción es obligatoria/i,
          ),
        ).toBeVisible();

        await expect(
          page.getByText(
            /los tags son obligatorios/i,
          ),
        ).toBeVisible();

        await expect(
          page.getByRole(
            'heading',
            {
              name:
                'Habilidad Creada',
            },
          ),
        ).not.toBeVisible();
      },
    );

    test(
      'normaliza las etiquetas antes de almacenarlas',
      async ({ page }) => {
        await loginAdmin(page);

        await page.goto(
          editSkillUrl(
            ids.editable,
          ),
        );

        await setSkillTags(
          page,
          '  E2E-Control-Habilidades  , '
          + ' Comunicación , , Empatía ,  ',
        );

        await submitSkillForm(page);

        await expect(
          page.getByRole(
            'heading',
            {
              name:
                'Habilidad Editada',
            },
          ),
        ).toBeVisible();

        await closeSuccessModal(
          page,
        );

        await page.goto(
          editSkillUrl(
            ids.editable,
          ),
        );

        await expect(
          page.locator(
            'input[type="hidden"][name="tag"]',
          ),
        ).toHaveValue(
          'E2E-Control-Habilidades, '
          + 'Comunicación, Empatía',
        );
      },
    );

    test(
      'edita el nombre y la descripción de una habilidad',
      async ({ page }) => {
        await loginAdmin(page);

        await page.goto(
          editSkillUrl(
            ids.editable,
          ),
        );

        await fillSkillForm(
          page,
          {
            name:
              'Habilidad Actualizada E2E',

            description:
              'Descripción actualizada mediante '
              + 'la prueba automatizada.',

            tags:
              ADMIN_SKILL_DATA.editable.marker
              + ', Actualización',

            enabled:
              true,
          },
        );

        await submitSkillForm(page);

        await expect(
          page.getByRole(
            'heading',
            {
              name:
                'Habilidad Editada',
            },
          ),
        ).toBeVisible();

        await closeSuccessModal(
          page,
        );

        await page.goto(
          editSkillUrl(
            ids.editable,
          ),
        );

        await expect(
          page.locator(
            'input[name="nombre"]',
          ),
        ).toHaveValue(
          'Habilidad Actualizada E2E',
        );

        await expect(
          page.locator(
            'textarea[name="descripcion"]',
          ),
        ).toHaveValue(
          'Descripción actualizada mediante '
          + 'la prueba automatizada.',
        );
      },
    );

    test(
      'deshabilita y rehabilita una habilidad',
      async ({ page }) => {
        await loginAdmin(page);

        /*
         * Deshabilitar.
         */
        await page.goto(
          editSkillUrl(
            ids.editable,
          ),
        );

        await expect(
          enabledCheckbox(page),
        ).toBeChecked();

        await setSkillEnabled(
          page,
          false,
        );

        await submitSkillForm(page);

        await expect(
          page.getByRole(
            'heading',
            {
              name:
                'Habilidad Editada',
            },
          ),
        ).toBeVisible();

        await closeSuccessModal(
          page,
        );

        await page.goto(
          editSkillUrl(
            ids.editable,
          ),
        );

        await expect(
          enabledCheckbox(page),
        ).not.toBeChecked();

        /*
         * Rehabilitar.
         */
        await setSkillEnabled(
          page,
          true,
        );

        await submitSkillForm(page);

        await expect(
          page.getByRole(
            'heading',
            {
              name:
                'Habilidad Editada',
            },
          ),
        ).toBeVisible();

        await closeSuccessModal(
          page,
        );

        await page.goto(
          editSkillUrl(
            ids.editable,
          ),
        );

        await expect(
          enabledCheckbox(page),
        ).toBeChecked();
      },
    );

    test(
      'rechaza un estado no permitido',
      async ({ page }) => {
        await loginAdmin(page);

        await page.goto(
          editSkillUrl(
            ids.editable,
          ),
        );

        await setInvalidSkillState(
          page,
          '7',
        );

        await submitSkillForm(page);

        await expect(
          page.getByText(
            /el estado seleccionado no es válido/i,
          ),
        ).toBeVisible();

        await expect(
          page.getByRole(
            'heading',
            {
              name:
                'Habilidad Editada',
            },
          ),
        ).not.toBeVisible();

        /*
         * Se abre nuevamente el registro para
         * comprobar que el valor inválido no se guardó.
         */
        await page.goto(
          editSkillUrl(
            ids.editable,
          ),
        );

        await expect(
          enabledCheckbox(page),
        ).toBeChecked();
      },
    );

    test(
      'elimina una habilidad después de confirmar la operación',
      async ({ page }) => {
        await loginAdmin(page);

        await page.goto(
          '/admin/habilidades?busqueda='
          + encodeURIComponent(
            ADMIN_SKILL_DATA.deletable.name,
          ),
        );

        const skillName =
          page.getByText(
            ADMIN_SKILL_DATA.deletable.name,
            {
              exact: true,
            },
          );

        await expect(
          skillName,
        ).toBeVisible();

        const deleteForm =
          deleteSkillForm(
            page,
            ids.deletable,
          );

        await expect(
          deleteForm,
        ).toHaveCount(1);

        /*
         * Primero se comprueba que cancelar
         * no elimine la habilidad.
         */
        await deleteForm
          .locator(
            'button[type="submit"], '
            + 'input[type="submit"]',
          )
          .first()
          .click();

        await expect(
          page.getByRole(
            'heading',
            {
              name:
                'Confirmar eliminación',
            },
          ),
        ).toBeVisible();

        await page.getByRole(
          'button',
          {
            name:
              'No, cancelar',
          },
        ).click();

        await expect(
          page.locator(
            '.modal-delete',
          ),
        ).toHaveCount(0);

        await expect(
          skillName,
        ).toBeVisible();

        /*
         * Se abre nuevamente el modal y se
         * confirma la operación.
         */
        await deleteForm
          .locator(
            'button[type="submit"], '
            + 'input[type="submit"]',
          )
          .first()
          .click();

        await expect(
          page.getByRole(
            'heading',
            {
              name:
                'Confirmar eliminación',
            },
          ),
        ).toBeVisible();

        await Promise.all([
          page.waitForURL(
            /\/admin\/habilidades(?:\?.*)?$/,
          ),

          page.getByRole(
            'button',
            {
              name:
                'Sí, eliminar',
            },
          ).click(),
        ]);

        await expect(
          page.getByText(
            /la habilidad se eliminó correctamente/i,
          ),
        ).toBeVisible();

        /*
         * La habilidad ya no debe aparecer
         * al buscarla nuevamente.
         */
        await page.goto(
          '/admin/habilidades?busqueda='
          + encodeURIComponent(
            ADMIN_SKILL_DATA.deletable.name,
          ),
        );

        await expect(
          page.getByText(
            ADMIN_SKILL_DATA.deletable.name,
            {
              exact: true,
            },
          ),
        ).toHaveCount(0);
      },
    );
  },
);