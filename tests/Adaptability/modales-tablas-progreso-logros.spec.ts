import {
  expect,
  test,
  type Locator,
  type Page,
  type TestInfo,
} from '@playwright/test';

import {
  readFileSync,
} from 'node:fs';

import {
  resolve,
} from 'node:path';

type FixtureData = {
  user: {
    id: number;
    email: string;
    password: string;
  };

  admin: {
    id: number;
    email: string;
    password: string;
  };

  skillIds: number[];
  skillNames: string[];
  lessonId: number;
  challengeId: number;
  lessonTitle: string;
  challengeName: string;
  achievementIds: number[];
  achievementNames: string[];
};

function readFixture(): FixtureData {
  return JSON.parse(
    readFileSync(
      resolve(
        process.cwd(),
        'tests/Adaptability/.grupo5-fixture.json',
      ),
      'utf8',
    ),
  ) as FixtureData;
}

/*
|--------------------------------------------------------------------------
| Autenticación
|--------------------------------------------------------------------------
*/

async function login(
  page: Page,
  credentials: {
    email: string;
    password: string;
  },
  expectedUrl: RegExp,
): Promise<void> {
  await page.goto(
    '/',
    {
      waitUntil:
        'domcontentloaded',
    },
  );

  const form =
    page
      .locator(
        'form:visible',
      )
      .first();

  await expect(
    form,
  ).toBeVisible();

  await form
    .locator(
      'input[name="correo"]',
    )
    .fill(
      credentials.email,
    );

  await form
    .locator(
      'input[name="password"]',
    )
    .fill(
      credentials.password,
    );

  const submit =
    form
      .locator(
        [
          'button[type="submit"]:visible',
          'input[type="submit"]:visible',
        ].join(', '),
      )
      .first();

  await Promise.all([
    page.waitForURL(
      expectedUrl,
      {
        waitUntil:
          'domcontentloaded',

        timeout:
          20_000,
      },
    ),

    submit.click(),
  ]);

  await expect(
    page,
  ).toHaveURL(
    expectedUrl,
  );
}

/*
|--------------------------------------------------------------------------
| Comprobaciones geométricas
|--------------------------------------------------------------------------
*/

async function expectNoHorizontalOverflow(
  page: Page,
  context: string,
): Promise<void> {
  const dimensions =
    await page.evaluate(
      () => {
        const root =
          document.documentElement;

        const body =
          document.body;

        return {
          viewport:
            root.clientWidth,

          document:
            Math.max(
              root.scrollWidth,
              body?.scrollWidth ?? 0,
            ),
        };
      },
    );

  expect.soft(
    dimensions.document,
    `${context}: no debe existir desbordamiento horizontal.`,
  ).toBeLessThanOrEqual(
    dimensions.viewport + 1,
  );
}

async function expectInsideViewport(
  locator: Locator,
  context: string,
): Promise<void> {
  await expect(
    locator,
    `${context}: debe estar visible.`,
  ).toBeVisible();

  await locator.scrollIntoViewIfNeeded();

  const geometry =
    await locator.evaluate(
      (element) => {
        const rect =
          element.getBoundingClientRect();

        return {
          left:
            rect.left,

          right:
            rect.right,

          width:
            rect.width,

          viewportWidth:
            window.innerWidth,
        };
      },
    );

  expect.soft(
    geometry.width,
    `${context}: debe conservar un ancho visible.`,
  ).toBeGreaterThan(
    0,
  );

  expect.soft(
    geometry.left,
    `${context}: no debe quedar recortado por la izquierda.`,
  ).toBeGreaterThanOrEqual(
    -1,
  );

  expect.soft(
    geometry.right,
    `${context}: no debe quedar recortado por la derecha.`,
  ).toBeLessThanOrEqual(
    geometry.viewportWidth + 1,
  );
}

/*
|--------------------------------------------------------------------------
| Modales
|--------------------------------------------------------------------------
*/

async function expectModalAdaptive(
  page: Page,
  modal: Locator,
  context: string,
): Promise<void> {
  await expect(
    modal,
    `${context}: el modal debe estar visible.`,
  ).toBeVisible();

  const content =
    modal
      .locator(
        [
          '[role="dialog"]:visible',
          '.learning-modal__content:visible',
          '.sv-challenge-modal__content:visible',
          '.profile-modal__content:visible',
          '.modal__content:visible',
          '[class*="modal__content"]:visible',
          '[class*="modal-content"]:visible',
        ].join(', '),
      )
      .first();

  const target =
    await content.count()
    > 0
      ? content
      : modal;

  await expectInsideViewport(
    target,
    `${context} — contenido`,
  );

  const overflow =
    await target.evaluate(
      (element) => {
        const style =
          window.getComputedStyle(
            element,
          );

        return {
          horizontal:
            element.scrollWidth
            > element.clientWidth
              + 1,

          verticalClipped:
            element.scrollHeight
            > element.clientHeight
              + 1
            && ![
              'auto',
              'scroll',
            ].includes(
              style.overflowY,
            ),
        };
      },
    );

  expect.soft(
    overflow.horizontal,
    `${context}: el contenido no debe desbordarse horizontalmente.`,
  ).toBeFalsy();

  expect.soft(
    overflow.verticalClipped,
    `${context}: el contenido alto debe disponer de desplazamiento vertical.`,
  ).toBeFalsy();

  await expectNoHorizontalOverflow(
    page,
    context,
  );
}

async function closeLearningModal(
  page: Page,
  modal: Locator,
): Promise<void> {
  const close =
    modal
      .locator(
        '[data-learning-modal-close]:visible',
      )
      .last();

  await expect(
    close,
  ).toBeVisible();

  await close.click();

  await expect(
    modal,
  ).toBeHidden();
}

async function closeChallengeModal(
  page: Page,
  modal: Locator,
): Promise<void> {
  await page.keyboard.press(
    'Escape',
  );

  await expect(
    modal,
  ).toBeHidden();
}

/*
|--------------------------------------------------------------------------
| Tablas
|--------------------------------------------------------------------------
*/

async function expectAdaptiveTable(
  page: Page,
  context: string,
): Promise<void> {
  const table =
    page
      .locator(
        'main table:visible',
      )
      .first();

  await expect(
    table,
    `${context}: debe existir una tabla visible.`,
  ).toBeVisible();

  const data =
    await table.evaluate(
      (element) => {
        const tableRect =
          element.getBoundingClientRect();

        let parent =
          element.parentElement;

        let scrollContainer:
          HTMLElement | null =
            null;

        while (
          parent
          && parent !== document.body
        ) {
          const style =
            window.getComputedStyle(
              parent,
            );

          if (
            style.overflowX === 'auto'
            || style.overflowX === 'scroll'
          ) {
            scrollContainer =
              parent;

            break;
          }

          parent =
            parent.parentElement;
        }

        const containerRect =
          scrollContainer
            ?.getBoundingClientRect()
          ?? null;

        return {
          tableLeft:
            tableRect.left,

          tableRight:
            tableRect.right,

          tableWidth:
            tableRect.width,

          viewportWidth:
            window.innerWidth,

          hasScrollContainer:
            scrollContainer !== null,

          containerLeft:
            containerRect?.left ?? 0,

          containerRight:
            containerRect?.right ?? 0,
        };
      },
    );

  if (
    data.tableWidth
    > data.viewportWidth + 1
    || data.tableRight
    > data.viewportWidth + 1
  ) {
    expect(
      data.hasScrollContainer,
      `${context}: una tabla ancha debe disponer de desplazamiento interno.`,
    ).toBeTruthy();

    expect.soft(
      data.containerLeft,
    ).toBeGreaterThanOrEqual(
      -1,
    );

    expect.soft(
      data.containerRight,
    ).toBeLessThanOrEqual(
      data.viewportWidth + 1,
    );
  } else {
    await expectInsideViewport(
      table,
      `${context} — tabla`,
    );
  }

  const rows =
    table.locator(
      'tbody tr:visible',
    );

  expect(
    await rows.count(),
    `${context}: la tabla debe mostrar registros.`,
  ).toBeGreaterThan(
    0,
  );

  const actions =
    table.locator(
      [
        'tbody a:visible',
        'tbody button:visible',
        'tbody input[type="submit"]:visible',
      ].join(', '),
    );

  if (
    await actions.count()
    > 0
  ) {
    await expectInsideViewport(
      actions.first(),
      `${context} — primera acción`,
    );
  }
}

/*
|--------------------------------------------------------------------------
| Barras de progreso
|--------------------------------------------------------------------------
*/

async function expectVisibleExactText(
  page: Page,
  expression: RegExp,
  context: string,
): Promise<Locator> {
  const candidates =
    page.getByText(
      expression,
    );

  const total =
    await candidates.count();

  for (
    let index = 0;
    index < total;
    index += 1
  ) {
    const candidate =
      candidates.nth(
        index,
      );

    if (
      await candidate
        .isVisible()
        .catch(
          () => false,
        )
    ) {
      await expect(
        candidate,
        context,
      ).toBeVisible();

      return candidate;
    }
  }

  throw new Error(
    `${context} No se encontró una coincidencia visible.`,
  );
}

const TABLET_BREAKPOINT =
  768;

async function expectProfileProgress(
  page: Page,
  context: string,
  skillNames: string[],
  expectedPercentages: number[],
): Promise<void> {
  expect(
    skillNames.length,
    `${context}: debe existir un nombre por cada porcentaje.`,
  ).toBe(
    expectedPercentages.length,
  );

  /*
   * La barra general permanece visible en todas las
   * resoluciones y es el único progressbar semántico
   * declarado directamente en la vista.
   */
  const generalProgress =
    page
      .locator(
        '.profile-progress__bar[role="progressbar"]',
      )
      .first();

  await expectInsideViewport(
    generalProgress,
    `${context} — progreso general`,
  );

  const generalValue =
    Number(
      await generalProgress.getAttribute(
        'aria-valuenow',
      ),
    );

  expect(
    Number.isFinite(
      generalValue,
    ),
    `${context}: el progreso general debe ser numérico.`,
  ).toBeTruthy();

  expect(
    generalValue,
    `${context}: el progreso general debe estar entre 0 y 100.`,
  ).toBeGreaterThanOrEqual(
    0,
  );

  expect(
    generalValue,
  ).toBeLessThanOrEqual(
    100,
  );

  const viewportWidth =
    page.viewportSize()
      ?.width
    ?? await page.evaluate(
      () => window.innerWidth,
    );

  const compactLayout =
    viewportWidth
    < TABLET_BREAKPOINT;

  for (
    let index = 0;
    index < skillNames.length;
    index += 1
  ) {
    const skillName =
      skillNames[index];

    const percentage =
      expectedPercentages[index];

    const row =
      page
        .locator(
          '.profile-table__row',
        )
        .filter({
          has:
            page.getByText(
              skillName,
              {
                exact:
                  true,
              },
            ),
        })
        .first();

    await expect(
      row,
      `${context}: debe mostrarse la habilidad ${skillName}.`,
    ).toBeVisible();

    await expectInsideViewport(
      row,
      `${context} — fila ${skillName}`,
    );

    await expect(
      row.locator(
        '.profile-table__cell--skill',
      ),
    ).toContainText(
      skillName,
    );

    await expect(
      row.locator(
        '.profile-table__cell--level',
      ),
      `${context}: el nivel de ${skillName} debe estar visible.`,
    ).toBeVisible();

    const progressCell =
      row.locator(
        '.profile-table__cell--progress',
      );

    const progressTrack =
      progressCell.locator(
        '.profile-mini-progress',
      );

    const progressFill =
      progressCell.locator(
        '.profile-mini-progress__fill',
      );

    const progressText =
      progressCell.locator(
        '.profile-mini-progress__text',
      );

    /*
     * Aunque la columna se oculte en móvil, el porcentaje
     * y el ancho de la barra deben conservarse correctamente
     * dentro del DOM.
     */
    await expect(
      progressCell,
    ).toBeAttached();

    await expect(
      progressTrack,
    ).toBeAttached();

    await expect(
      progressFill,
    ).toBeAttached();

    await expect(
      progressText,
    ).toBeAttached();

    await expect(
      progressText,
    ).toHaveText(
      `${percentage}%`,
    );

    const inlineWidth =
      await progressFill.evaluate(
        (element) =>
          (
            element as HTMLElement
          ).style.width,
      );

    expect(
      inlineWidth,
      `${context}: la barra de ${skillName} debe representar ${percentage} %.`,
    ).toBe(
      `${percentage}%`,
    );

    if (compactLayout) {
      /*
       * El SCSS oculta las columnas Progreso y Fecha
       * antes del breakpoint de tableta. Este es el
       * comportamiento responsive esperado, no un fallo.
       */
      await expect(
        progressCell,
        `${context}: la columna de progreso debe ocultarse en móvil.`,
      ).toBeHidden();

      await expect(
        progressText,
      ).toBeHidden();
    } else {
      await expect(
        progressCell,
        `${context}: la columna de progreso debe mostrarse desde tableta.`,
      ).toBeVisible();

      await expectInsideViewport(
        progressCell,
        `${context} — progreso de ${skillName}`,
      );

      await expect(
        progressTrack,
      ).toBeVisible();

      await expect(
        progressFill,
      ).toBeVisible();

      await expect(
        progressText,
      ).toBeVisible();

      const geometry =
        await progressFill.evaluate(
          (element) => {
            const rect =
              element.getBoundingClientRect();

            const parentRect =
              element.parentElement
                ?.getBoundingClientRect()
              ?? rect;

            return {
              left:
                rect.left,

              right:
                rect.right,

              width:
                rect.width,

              parentLeft:
                parentRect.left,

              parentRight:
                parentRect.right,
            };
          },
        );

      expect.soft(
        geometry.width,
        `${context}: la barra de ${skillName} debe tener ancho visible.`,
      ).toBeGreaterThan(
        0,
      );

      expect.soft(
        geometry.left,
        `${context}: la barra de ${skillName} no debe salir por la izquierda.`,
      ).toBeGreaterThanOrEqual(
        geometry.parentLeft - 1,
      );

      expect.soft(
        geometry.right,
        `${context}: la barra de ${skillName} no debe salir por la derecha.`,
      ).toBeLessThanOrEqual(
        geometry.parentRight + 1,
      );
    }
  }

  const progressHeader =
    page.locator(
      '.profile-table__col--progress',
    );

  if (compactLayout) {
    await expect(
      progressHeader,
    ).toBeHidden();
  } else {
    await expect(
      progressHeader,
    ).toBeVisible();
  }

  await expectNoHorizontalOverflow(
    page,
    context,
  );
}

/*
|--------------------------------------------------------------------------
| Logros
|--------------------------------------------------------------------------
*/

function escapeCssAttributeValue(
  value: string,
): string {
  return value
    .replace(
      /\\/g,
      '\\\\',
    )
    .replace(
      /"/g,
      '\\"',
    );
}

function achievementCard(
  page: Page,
  title: string,
): Locator {
  return page
    .locator(
      [
        '.achievement-card',
        '.js-achievement-modal-open',
        `[data-achievement-nombre="${escapeCssAttributeValue(
          title,
        )}"]`,
      ].join(''),
    )
    .first();
}

async function expectAchievementCardsAdaptive(
  page: Page,
  titles: string[],
): Promise<void> {
  /*
   * La vista expone cada logro como un article con la
   * clase .achievement-card y el atributo
   * data-achievement-nombre. Los rectángulos se calculan
   * todos en una sola evaluación para mantener el mismo
   * desplazamiento de página y evitar comparaciones entre
   * coordenadas tomadas después de distintos scrolls.
   */
  const cards =
    await page.evaluate(
      (expectedTitles) => {
        const allCards =
          Array.from(
            document.querySelectorAll(
              '.achievement-card',
            ),
          );

        return expectedTitles.map(
          (title) => {
            const element =
              allCards.find(
                (card) =>
                  card.getAttribute(
                    'data-achievement-nombre',
                  ) === title,
              );

            if (!element) {
              return {
                title,

                found:
                  false,

                visible:
                  false,

                left:
                  0,

                right:
                  0,

                top:
                  0,

                bottom:
                  0,

                width:
                  0,

                height:
                  0,
              };
            }

            const style =
              window.getComputedStyle(
                element,
              );

            const rect =
              element.getBoundingClientRect();

            return {
              title,

              found:
                true,

              visible:
                style.display !== 'none'
                && style.visibility !== 'hidden'
                && style.opacity !== '0'
                && rect.width > 0
                && rect.height > 0,

              left:
                rect.left,

              right:
                rect.right,

              top:
                rect.top,

              bottom:
                rect.bottom,

              width:
                rect.width,

              height:
                rect.height,
            };
          },
        );
      },
      titles,
    );

  const viewportWidth =
    page.viewportSize()
      ?.width
    ?? await page.evaluate(
      () => window.innerWidth,
    );

  for (
    const [
      index,
      card,
    ]
    of cards.entries()
  ) {
    expect(
      card.found,
      `Debe existir la tarjeta del logro ${card.title}.`,
    ).toBeTruthy();

    expect(
      card.visible,
      `La tarjeta del logro ${card.title} debe ser visible.`,
    ).toBeTruthy();

    expect.soft(
      card.width,
      `La tarjeta ${index + 1} debe conservar un ancho visible.`,
    ).toBeGreaterThan(
      0,
    );

    expect.soft(
      card.left,
      `La tarjeta ${index + 1} no debe salir por la izquierda.`,
    ).toBeGreaterThanOrEqual(
      -1,
    );

    expect.soft(
      card.right,
      `La tarjeta ${index + 1} no debe salir por la derecha.`,
    ).toBeLessThanOrEqual(
      viewportWidth + 1,
    );
  }

  /*
   * Como todos los rectángulos se obtuvieron en el mismo
   * instante, la intersección representa una superposición
   * real y no una consecuencia de scrollIntoViewIfNeeded().
   */
  for (
    let first = 0;
    first < cards.length;
    first += 1
  ) {
    for (
      let second =
        first + 1;
      second < cards.length;
      second += 1
    ) {
      const a =
        cards[first];

      const b =
        cards[second];

      const intersectionWidth =
        Math.max(
          0,
          Math.min(
            a.right,
            b.right,
          ) - Math.max(
            a.left,
            b.left,
          ),
        );

      const intersectionHeight =
        Math.max(
          0,
          Math.min(
            a.bottom,
            b.bottom,
          ) - Math.max(
            a.top,
            b.top,
          ),
        );

      const intersectionArea =
        intersectionWidth
        * intersectionHeight;

      const smallerArea =
        Math.min(
          a.width * a.height,
          b.width * b.height,
        );

      const overlapRatio =
        smallerArea > 0
          ? intersectionArea
            / smallerArea
          : 0;

      expect.soft(
        overlapRatio,
        `Las tarjetas de los logros ${first + 1} y ${second + 1} no deben superponerse.`,
      ).toBeLessThan(
        0.15,
      );
    }
  }

  await expectNoHorizontalOverflow(
    page,
    'Página de logros',
  );
}

async function openAchievementDetail(
  page: Page,
  title: string,
): Promise<Locator> {
  const card =
    achievementCard(
      page,
      title,
    );

  await expect(
    card,
    `Debe mostrarse la tarjeta del logro ${title}.`,
  ).toBeVisible();

  await card.click();

  const modal =
    page
      .locator(
        '#sv-achievement-modal.is-open',
      )
      .first();

  await expect(
    modal,
    'Debe abrirse el detalle visible del logro.',
  ).toBeVisible();

  await expect(
    modal,
  ).toHaveAttribute(
    'aria-hidden',
    'false',
  );

  const content =
    modal.locator(
      '.achievement-modal__content',
    );

  await expect(
    content,
  ).toBeVisible();

  await expect(
    modal.locator(
      '#sv-achievement-modal-title',
    ),
  ).toHaveText(
    title,
  );

  return modal;
}

/*
|--------------------------------------------------------------------------
| Progreso de Retos
|--------------------------------------------------------------------------
*/

/**
 * Comprueba la sección real de progreso y logros de Retos.
 *
 * La vista no utiliza clases que contengan las palabras
 * "progress" o "progreso". Su bloque se identifica mediante
 * las clases BEM:
 *
 * - .challenges__pa
 * - .challenges__pa-card
 * - .challenges__pa-bar
 * - .challenges__pa-barFill
 *
 * Por esta razón se utilizan selectores exactos de la vista.
 */
async function expectChallengesProgress(
  page: Page,
  context: string,
): Promise<void> {
  const section =
    page
      .locator(
        'section.challenges__pa:visible',
      )
      .first();

  await expect(
    section,
    `${context}: debe mostrarse la sección "Tu Progreso y Logros".`,
  ).toBeVisible();

  await expectInsideViewport(
    section,
    `${context} — sección`,
  );

  await expect(
    section.locator(
      '.challenges__pa-tittle',
    ),
    `${context}: debe mostrarse el título de la sección.`,
  ).toHaveText(
    'Tu Progreso y Logros',
  );

  const grid =
    section
      .locator(
        '.challenges__pa-grid:visible',
      )
      .first();

  await expectInsideViewport(
    grid,
    `${context} — distribución de tarjetas`,
  );

  const cards =
    grid.locator(
      'article.challenges__pa-card:visible',
    );

  expect(
    await cards.count(),
    `${context}: deben mostrarse las tarjetas de progreso y medallas.`,
  ).toBeGreaterThanOrEqual(
    2,
  );

  const progressCard =
    cards
      .filter({
        has:
          page.getByText(
            'Progreso General',
            {
              exact:
                true,
            },
          ),
      })
      .first();

  await expectInsideViewport(
    progressCard,
    `${context} — tarjeta de progreso general`,
  );

  await expect(
    progressCard.getByText(
      'Retos completados',
      {
        exact:
          true,
      },
    ),
  ).toBeVisible();

  await expect(
    progressCard.getByText(
      'Nivel por habilidad:',
      {
        exact:
          true,
      },
    ),
  ).toBeVisible();

  const bars =
    progressCard.locator(
      '.challenges__pa-bar:visible',
    );

  expect(
    await bars.count(),
    `${context}: debe existir al menos una barra visible.`,
  ).toBeGreaterThan(
    0,
  );

  const fills =
    progressCard.locator(
      '.challenges__pa-barFill',
    );

  expect(
    await fills.count(),
    `${context}: cada barra debe conservar su elemento de relleno.`,
  ).toBe(
    await bars.count(),
  );

  const barGeometry =
    await fills.evaluateAll(
      (elements) =>
        elements.map(
          (element) => {
            const fill =
              element as HTMLElement;

            const fillRect =
              fill.getBoundingClientRect();

            const parentRect =
              fill.parentElement
                ?.getBoundingClientRect()
              ?? fillRect;

            const inlineWidth =
              fill.style.width.trim();

            const numericWidth =
              Number.parseFloat(
                inlineWidth,
              );

            return {
              inlineWidth,

              numericWidth,

              fillLeft:
                fillRect.left,

              fillRight:
                fillRect.right,

              parentLeft:
                parentRect.left,

              parentRight:
                parentRect.right,

              viewportWidth:
                window.innerWidth,
            };
          },
        ),
    );

  for (
    const [
      index,
      geometry,
    ]
    of barGeometry.entries()
  ) {
    expect.soft(
      geometry.inlineWidth,
      `${context}: la barra ${index + 1} debe conservar un porcentaje en línea.`,
    ).toMatch(
      /^\d+(?:\.\d+)?%$/,
    );

    expect.soft(
      geometry.numericWidth,
      `${context}: el porcentaje de la barra ${index + 1} no debe ser negativo.`,
    ).toBeGreaterThanOrEqual(
      0,
    );

    expect.soft(
      geometry.numericWidth,
      `${context}: el porcentaje de la barra ${index + 1} no debe superar 100.`,
    ).toBeLessThanOrEqual(
      100,
    );

    expect.soft(
      geometry.fillLeft,
      `${context}: la barra ${index + 1} no debe salir por la izquierda.`,
    ).toBeGreaterThanOrEqual(
      geometry.parentLeft - 1,
    );

    expect.soft(
      geometry.fillRight,
      `${context}: la barra ${index + 1} debe permanecer dentro de su contenedor.`,
    ).toBeLessThanOrEqual(
      Math.min(
        geometry.parentRight + 1,
        geometry.viewportWidth + 1,
      ),
    );
  }

  const skillRows =
    progressCard.locator(
      '.challenges__pa-skill:visible',
    );

  expect(
    await skillRows.count(),
    `${context}: debe mostrarse al menos una habilidad con retos.`,
  ).toBeGreaterThan(
    0,
  );

  await expectInsideViewport(
    skillRows.first(),
    `${context} — primera habilidad`,
  );

  const medalsCard =
    cards
      .filter({
        has:
          page.getByText(
            'Medallas Desbloqueadas',
            {
              exact:
                true,
            },
          ),
      })
      .first();

  await expectInsideViewport(
    medalsCard,
    `${context} — tarjeta de medallas`,
  );

  await expectNoHorizontalOverflow(
    page,
    context,
  );
}

/*
|--------------------------------------------------------------------------
| Evidencias
|--------------------------------------------------------------------------
*/

function evidenceName(
  testInfo: TestInfo,
  section: string,
): string {
  const metadata =
    testInfo
      .project
      .metadata as {
        evidencia?: string;
      };

  const project =
    metadata.evidencia
    ?? testInfo.project.name;

  return `${project}-${section}`;
}

async function attachScreenshot(
  page: Page,
  testInfo: TestInfo,
  name: string,
  fullPage = true,
): Promise<void> {
  const path =
    testInfo.outputPath(
      `${name}.png`,
    );

  await page.screenshot({
    path,

    fullPage,

    animations:
      'disabled',
  });

  await testInfo.attach(
    name,
    {
      path,

      contentType:
        'image/png',
    },
  );
}

/*
|--------------------------------------------------------------------------
| Casos
|--------------------------------------------------------------------------
*/

test.describe(
  'Adaptabilidad de modales, tablas, barras de progreso y logros',
  () => {
    test(
      'adapta el modal de Aprendizaje',
      async ({
        page,
      }, testInfo) => {
        const fixture =
          readFixture();

        await login(
          page,
          fixture.user,
          /\/principal$/,
        );

        await page.goto(
          '/aprendizaje',
          {
            waitUntil:
              'domcontentloaded',
          },
        );

        const trigger =
          page
            .locator(
              '.js-learning-modal-open:visible',
            )
            .filter({
              hasText:
                fixture.skillNames[0],
            })
            .first();

        const finalTrigger =
          await trigger.count()
          > 0
            ? trigger
            : page
                .locator(
                  '.js-learning-modal-open:visible',
                )
                .first();

        await expect(
          finalTrigger,
        ).toBeVisible();

        await finalTrigger.click();

        const modal =
          page
            .locator(
              '.learning-modal.learning-modal--visible',
            )
            .first();

        await expectModalAdaptive(
          page,
          modal,
          'Modal de Aprendizaje',
        );

        await attachScreenshot(
          page,
          testInfo,
          evidenceName(
            testInfo,
            'modal-aprendizaje',
          ),
          false,
        );

        await closeLearningModal(
          page,
          modal,
        );
      },
    );

    test(
      'adapta el modal informativo de Retos',
      async ({
        page,
      }, testInfo) => {
        const fixture =
          readFixture();

        await login(
          page,
          fixture.user,
          /\/principal$/,
        );

        await page.goto(
          '/retos',
          {
            waitUntil:
              'domcontentloaded',
          },
        );

        const trigger =
          page
            .locator(
              '[data-sv-challenge-open]:visible',
            )
            .filter({
              hasText:
                fixture.challengeName,
            })
            .first();

        const finalTrigger =
          await trigger.count()
          > 0
            ? trigger
            : page
                .locator(
                  '[data-sv-challenge-open]:visible',
                )
                .first();

        await expect(
          finalTrigger,
        ).toBeVisible();

        await finalTrigger.click();

        const modal =
          page
            .locator(
              '#sv-challenge-modal.is-open',
            )
            .first();

        await expect(
          modal,
        ).toHaveAttribute(
          'aria-hidden',
          'false',
        );

        await expectModalAdaptive(
          page,
          modal,
          'Modal informativo de Retos',
        );

        await attachScreenshot(
          page,
          testInfo,
          evidenceName(
            testInfo,
            'modal-retos',
          ),
          false,
        );

        await closeChallengeModal(
          page,
          modal,
        );
      },
    );

    test(
      'adapta las tablas administrativas',
      async ({
        page,
      }, testInfo) => {
        const fixture =
          readFixture();

        await login(
          page,
          fixture.admin,
          /\/admin\/dashboard$/,
        );

        await page.goto(
          '/admin/usuarios',
          {
            waitUntil:
              'domcontentloaded',
          },
        );

        await expectAdaptiveTable(
          page,
          'Tabla de usuarios',
        );

        await expectNoHorizontalOverflow(
          page,
          'Administración de usuarios',
        );

        await attachScreenshot(
          page,
          testInfo,
          evidenceName(
            testInfo,
            'tabla-usuarios',
          ),
        );

        await page.goto(
          '/admin/habilidades',
          {
            waitUntil:
              'domcontentloaded',
          },
        );

        await expectAdaptiveTable(
          page,
          'Tabla de habilidades',
        );

        await expectNoHorizontalOverflow(
          page,
          'Administración de habilidades',
        );

        await attachScreenshot(
          page,
          testInfo,
          evidenceName(
            testInfo,
            'tabla-habilidades',
          ),
        );
      },
    );

    test(
      'adapta las barras de progreso del perfil y los retos',
      async ({
        page,
      }, testInfo) => {
        const fixture =
          readFixture();

        await login(
          page,
          fixture.user,
          /\/principal$/,
        );

        await page.goto(
          '/perfil',
          {
            waitUntil:
              'domcontentloaded',
          },
        );

        await expectProfileProgress(
          page,
          'Perfil',
          fixture.skillNames,
          [
            33,
            50,
            100,
          ],
        );

        await attachScreenshot(
          page,
          testInfo,
          evidenceName(
            testInfo,
            'barras-progreso-perfil',
          ),
        );

        await page.goto(
          '/retos',
          {
            waitUntil:
              'domcontentloaded',
          },
        );

        await expectChallengesProgress(
          page,
          'Progreso de Retos',
        );

        await attachScreenshot(
          page,
          testInfo,
          evidenceName(
            testInfo,
            'barras-progreso-retos',
          ),
        );
      },
    );

    test(
      'adapta las tarjetas y el detalle de logros',
      async ({
        page,
      }, testInfo) => {
        const fixture =
          readFixture();

        await login(
          page,
          fixture.user,
          /\/principal$/,
        );

        await page.goto(
          '/logros',
          {
            waitUntil:
              'domcontentloaded',
          },
        );

        const visibleAchievements =
          fixture
            .achievementNames
            .slice(
              0,
              4,
            );

        await expectAchievementCardsAdaptive(
          page,
          visibleAchievements,
        );

        await expect(
          page.getByText(
            fixture.achievementNames[4],
            {
              exact:
                true,
            },
          ),
        ).toHaveCount(
          0,
        );

        const detailModal =
          await openAchievementDetail(
            page,
            fixture.achievementNames[0],
          );

        await expectModalAdaptive(
          page,
          detailModal,
          'Detalle de logro',
        );

        await expect(
          detailModal,
        ).toContainText(
          fixture.achievementNames[0],
        );

        await attachScreenshot(
          page,
          testInfo,
          evidenceName(
            testInfo,
            'logros-detalle',
          ),
          false,
        );
      },
    );
  },
);