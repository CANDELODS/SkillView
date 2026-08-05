import {
  expect,
  test,
  type Locator,
  type Page,
  type TestInfo,
} from '@playwright/test';

const GENERAL_USER = {
  email:
    process.env.ADAPT_USER_EMAIL
    ?? 'e2e_auth_general@skillview.test',

  password:
    process.env.ADAPT_USER_PASSWORD
    ?? 'CajaNegra1!',
};

const ADMIN_USER = {
  email:
    process.env.ADAPT_ADMIN_EMAIL
    ?? 'e2e_auth_admin@skillview.test',

  password:
    process.env.ADAPT_ADMIN_PASSWORD
    ?? 'CajaNegra1!',
};

const SKILL_TEXT =
  'Adaptabilidad Prueba 01';

const USER_SEARCH =
  'adapt_cards_';

const SKILL_SEARCH =
  'Adaptabilidad Prueba';

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
    'El formulario de inicio de sesión debe estar visible.',
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

  await expect(
    submit,
    'El control de envío del inicio de sesión debe estar visible.',
  ).toBeVisible();

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
| Geometría y desbordamiento
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

          viewport:
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
    `${context}: no debe quedar recortado a la izquierda.`,
  ).toBeGreaterThanOrEqual(
    -1,
  );

  expect.soft(
    geometry.right,
    `${context}: no debe quedar recortado a la derecha.`,
  ).toBeLessThanOrEqual(
    geometry.viewport + 1,
  );
}

/*
|--------------------------------------------------------------------------
| Tarjetas
|--------------------------------------------------------------------------
*/

type CardRectangle = {
  left: number;
  top: number;
  right: number;
  bottom: number;
  width: number;
  height: number;
};

/**
 * Obtiene solamente los contenedores reales de las tarjetas.
 *
 * La versión anterior unía todos los selectores y terminaba
 * midiendo simultáneamente una tarjeta y varios elementos
 * internos de esa misma tarjeta. Esto producía falsos
 * positivos de superposición con una proporción igual a 1.
 *
 * Esta versión:
 * 1. utiliza el primer selector que encuentre tarjetas visibles;
 * 2. elimina nodos descendientes duplicados;
 * 3. usa los botones o títulos únicamente como respaldo.
 */
async function getCardRectangles(
  page: Page,
  cardSelectors: string[],
  triggerSelectors: string[] = [],
  titlePrefix?: string,
): Promise<CardRectangle[]> {
  return page.evaluate(
    ({
      cardSelectors,
      triggerSelectors,
      titlePrefix,
    }) => {
      const isVisible =
        (element: Element): boolean => {
          const style =
            window.getComputedStyle(
              element,
            );

          const rect =
            element.getBoundingClientRect();

          return (
            style.display !== 'none'
            && style.visibility !== 'hidden'
            && style.opacity !== '0'
            && rect.width > 0
            && rect.height > 0
          );
        };

      const keepOutermost =
        (elements: Element[]): Element[] => {
          const unique =
            [...new Set(elements)]
              .filter(
                isVisible,
              );

          return unique.filter(
            (element) =>
              !unique.some(
                (other) =>
                  other !== element
                  && other.contains(
                    element,
                  ),
              ),
          );
        };

      const toRectangles =
        (elements: Element[]): CardRectangle[] =>
          elements.map(
            (element) => {
              const rect =
                element.getBoundingClientRect();

              return {
                left:
                  rect.left,

                top:
                  rect.top,

                right:
                  rect.right,

                bottom:
                  rect.bottom,

                width:
                  rect.width,

                height:
                  rect.height,
              };
            },
          );

      /*
       * Se usa el primer selector específico que
       * encuentre elementos. No se mezclan varios
       * selectores para evitar medir nodos anidados.
       */
      for (
        const selector
        of cardSelectors
      ) {
        const matches =
          keepOutermost(
            Array.from(
              document.querySelectorAll(
                selector,
              ),
            ),
          );

        if (matches.length > 0) {
          return toRectangles(
            matches,
          );
        }
      }

      /*
       * Respaldo mediante controles que abren las
       * tarjetas o sus modales.
       */
      const triggerCards:
        Element[] = [];

      for (
        const selector
        of triggerSelectors
      ) {
        document
          .querySelectorAll(
            selector,
          )
          .forEach(
            (trigger) => {
              const card =
                trigger.closest(
                  [
                    '.challenges-card',
                    '.learning-card',
                    '.blog-card',
                    '.article-card',
                    '.skill-card',
                    'article',
                    'li',
                    '[class*="__card"]',
                    '[class*="-card"]',
                  ].join(', '),
                );

              if (card) {
                triggerCards.push(
                  card,
                );
              }
            },
          );
      }

      const cardsFromTriggers =
        keepOutermost(
          triggerCards,
        );

      if (
        cardsFromTriggers.length
        > 0
      ) {
        return toRectangles(
          cardsFromTriggers,
        );
      }

      /*
       * Último respaldo: localizar títulos creados por
       * el fixture y subir al contenedor de tarjeta.
       */
      if (titlePrefix) {
        const titleCards:
          Element[] = [];

        document
          .querySelectorAll(
            [
              'h1',
              'h2',
              'h3',
              'h4',
              'h5',
              'h6',
              'a',
              'p',
            ].join(', '),
          )
          .forEach(
            (titleElement) => {
              const text =
                titleElement
                  .textContent
                  ?.trim()
                ?? '';

              if (
                !text.startsWith(
                  titlePrefix,
                )
              ) {
                return;
              }

              const card =
                titleElement.closest(
                  [
                    '.challenges-card',
                    '.learning-card',
                    '.blog-card',
                    '.article-card',
                    '.skill-card',
                    'article',
                    'li',
                    '[class*="__card"]',
                    '[class*="-card"]',
                  ].join(', '),
                );

              if (card) {
                titleCards.push(
                  card,
                );
              }
            },
          );

        return toRectangles(
          keepOutermost(
            titleCards,
          ),
        );
      }

      return [];
    },
    {
      cardSelectors,
      triggerSelectors,
      titlePrefix:
        titlePrefix ?? null,
    },
  );
}

async function expectAdaptiveCards(
  page: Page,
  context: string,
  cardSelectors: string[],
  triggerSelectors: string[] = [],
  titlePrefix?: string,
): Promise<void> {
  const rectangles =
    await getCardRectangles(
      page,
      cardSelectors,
      triggerSelectors,
      titlePrefix,
    );

  expect(
    rectangles.length,
    `${context}: debe existir al menos una tarjeta visible.`,
  ).toBeGreaterThan(
    0,
  );

  const viewportWidth =
    await page.evaluate(
      () => window.innerWidth,
    );

  for (
    const [index, rectangle]
    of rectangles.entries()
  ) {
    expect.soft(
      rectangle.width,
      `${context}: la tarjeta ${index + 1} debe tener ancho.`,
    ).toBeGreaterThan(
      0,
    );

    expect.soft(
      rectangle.left,
      `${context}: la tarjeta ${index + 1} no debe recortarse a la izquierda.`,
    ).toBeGreaterThanOrEqual(
      -1,
    );

    expect.soft(
      rectangle.right,
      `${context}: la tarjeta ${index + 1} debe caber en pantalla.`,
    ).toBeLessThanOrEqual(
      viewportWidth + 1,
    );
  }

  /*
   * Solo se comparan contenedores de tarjetas
   * distintos, no elementos internos de una tarjeta.
   */
  for (
    let first = 0;
    first < rectangles.length;
    first += 1
  ) {
    for (
      let second =
        first + 1;
      second < rectangles.length;
      second += 1
    ) {
      const a =
        rectangles[first];

      const b =
        rectangles[second];

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

      const ratio =
        smallerArea > 0
          ? intersectionArea
            / smallerArea
          : 0;

      expect.soft(
        ratio,
        `${context}: las tarjetas ${first + 1} y ${second + 1} no deben superponerse.`,
      ).toBeLessThan(
        0.15,
      );
    }
  }
}

/*
|--------------------------------------------------------------------------
| Filtros
|--------------------------------------------------------------------------
*/

async function selectOptionByText(
  select: Locator,
  text: string,
): Promise<string> {
  await expect(
    select,
  ).toBeVisible();

  await select.focus();

  await expect(
    select,
  ).toBeFocused();

  const option =
    select
      .locator(
        'option',
      )
      .filter({
        hasText:
          text,
      })
      .first();

  await expect(
    option,
    `Debe existir la opción ${text}.`,
  ).toBeAttached();

  const value =
    await option.getAttribute(
      'value',
    );

  expect(
    value,
    `La opción ${text} debe tener valor.`,
  ).toBeTruthy();

  await select.selectOption(
    value!,
  );

  await expect(
    select,
  ).toHaveValue(
    value!,
  );

  return value!;
}

/*
|--------------------------------------------------------------------------
| Tablas y listados administrativos
|--------------------------------------------------------------------------
*/

async function expectAdaptiveAdminList(
  page: Page,
  context: string,
): Promise<void> {
  const table =
    page
      .locator(
        'main table:visible',
      )
      .first();

  if (
    await table.count()
    > 0
    && await table.isVisible()
  ) {
    const tableData =
      await table.evaluate(
        (element) => {
          const rect =
            element
              .getBoundingClientRect();

          let parent =
            element.parentElement;

          let scrollContainer:
            HTMLElement | null =
              null;

          while (
            parent
            && parent
              !== document.body
          ) {
            const style =
              window.getComputedStyle(
                parent,
              );

            if (
              style.overflowX
                === 'auto'
              || style.overflowX
                === 'scroll'
            ) {
              scrollContainer =
                parent;

              break;
            }

            parent =
              parent.parentElement;
          }

          const scrollRect =
            scrollContainer
              ?.getBoundingClientRect()
            ?? null;

          return {
            tableWidth:
              rect.width,

            tableLeft:
              rect.left,

            tableRight:
              rect.right,

            viewportWidth:
              window.innerWidth,

            hasScrollContainer:
              scrollContainer
              !== null,

            containerLeft:
              scrollRect?.left
              ?? 0,

            containerRight:
              scrollRect?.right
              ?? 0,
          };
        },
      );

    if (
      tableData.tableWidth
        > tableData.viewportWidth
          + 1
      || tableData.tableRight
        > tableData.viewportWidth
          + 1
    ) {
      expect(
        tableData.hasScrollContainer,
        `${context}: una tabla ancha debe tener desplazamiento interno.`,
      ).toBeTruthy();

      expect.soft(
        tableData.containerLeft,
      ).toBeGreaterThanOrEqual(
        -1,
      );

      expect.soft(
        tableData.containerRight,
      ).toBeLessThanOrEqual(
        tableData.viewportWidth
        + 1,
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
      `${context}: debe mostrar registros.`,
    ).toBeGreaterThan(
      0,
    );

    return;
  }

  await expectAdaptiveCards(
    page,
    context,
    [
      '.admin-card',
      '.user-card',
      '.skill-admin-card',
      '[class*="admin-card"]',
    ],
  );
}

/*
|--------------------------------------------------------------------------
| Paginación
|--------------------------------------------------------------------------
*/

/**
 * Verifica la paginación sin exigir que los números
 * sean visibles en móvil.
 *
 * SkillView conserva los enlaces numéricos en el DOM,
 * pero puede ocultarlos visualmente en resoluciones
 * pequeñas y dejar visibles los controles Anterior y
 * Siguiente. Esto es un comportamiento responsive válido.
 */
async function expectPagination(
  page: Page,
  expectedPage: string,
): Promise<void> {
  const pagination =
    page
      .locator(
        '.paginacion:visible',
      )
      .first();

  await expect(
    pagination,
    'La paginación debe estar visible.',
  ).toBeVisible();

  await expectInsideViewport(
    pagination,
    'Paginación',
  );

  const current =
    pagination
      .locator(
        '.paginacion__enlace--actual',
      )
      .filter({
        hasText:
          new RegExp(
            `^${expectedPage}$`,
          ),
      })
      .first();

  /*
   * El número actual puede estar oculto por CSS en
   * móvil, pero debe existir y representar la página.
   */
  await expect(
    current,
    `La página actual debe ser ${expectedPage}.`,
  ).toBeAttached();

  await expect(
    current,
  ).toHaveText(
    expectedPage,
  );

  const visibleLinks =
    pagination.locator(
      'a.paginacion__enlace:visible',
    );

  const totalVisibleLinks =
    await visibleLinks.count();

  expect(
    totalVisibleLinks,
    'La paginación debe conservar al menos un enlace utilizable.',
  ).toBeGreaterThan(
    0,
  );

  await expectInsideViewport(
    visibleLinks.first(),
    'Primer enlace visible de paginación',
  );

  await expectInsideViewport(
    visibleLinks.last(),
    'Último enlace visible de paginación',
  );
}

/**
 * Navega a la página 2 usando el enlace numérico
 * cuando esté visible o el botón Siguiente en móvil.
 */
async function navigateToSecondPage(
  page: Page,
): Promise<void> {
  const pagination =
    page
      .locator(
        '.paginacion:visible',
      )
      .first();

  const numericPageTwo =
    pagination
      .locator(
        'a.paginacion__enlace--numero',
      )
      .filter({
        hasText:
          /^2$/,
      })
      .first();

  const nextLink =
    pagination
      .locator(
        'a.paginacion__enlace--texto:visible',
      )
      .filter({
        hasText:
          /Siguiente/i,
      })
      .first();

  const control =
    await numericPageTwo
      .isVisible()
      .catch(
        () => false,
      )
      ? numericPageTwo
      : nextLink;

  await expect(
    control,
    'Debe existir un control visible para avanzar a la página 2.',
  ).toBeVisible();

  const currentUrl =
    new URL(
      page.url(),
    );

  const expectedSearch =
    currentUrl
      .searchParams
      .get(
        'busqueda',
      );

  await Promise.all([
    page.waitForURL(
      (url) =>
        url.searchParams.get(
          'page',
        ) === '2',

      {
        waitUntil:
          'domcontentloaded',

        timeout:
          20_000,
      },
    ),

    control.click(),
  ]);

  const finalUrl =
    new URL(
      page.url(),
    );

  expect(
    finalUrl
      .searchParams
      .get(
        'page',
      ),
  ).toBe(
    '2',
  );

  expect(
    finalUrl
      .searchParams
      .get(
        'busqueda',
      ),
    'La paginación debe conservar el criterio de búsqueda.',
  ).toBe(
    expectedSearch,
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
): Promise<void> {
  const filePath =
    testInfo.outputPath(
      `${name}.png`,
    );

  await page.screenshot({
    path:
      filePath,

    fullPage:
      true,

    animations:
      'disabled',
  });

  await testInfo.attach(
    name,
    {
      path:
        filePath,

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
  'Adaptabilidad de tarjetas, filtros y paginación',
  () => {
    test(
      'adapta las tarjetas de Aprendizaje',
      async ({
        page,
      }, testInfo) => {
        await login(
          page,
          GENERAL_USER,
          /\/principal$/,
        );

        await page.goto(
          '/aprendizaje',
          {
            waitUntil:
              'domcontentloaded',
          },
        );

        await expect(
          page
            .getByText(
              SKILL_TEXT,
              {
                exact:
                  true,
              },
            )
            .first(),
        ).toBeVisible();

        await expectAdaptiveCards(
          page,
          'Tarjetas de Aprendizaje',
          [
            '.learning-card',
            '.learning__card',
            '.learning-path__card',
            '.skill-card',
            '.learning-cards article',
          ],
          [
            '.js-learning-modal-open',
          ],
          'Adaptabilidad Prueba',
        );

        await expectNoHorizontalOverflow(
          page,
          'Aprendizaje',
        );

        await attachScreenshot(
          page,
          testInfo,
          evidenceName(
            testInfo,
            'tarjetas-aprendizaje',
          ),
        );
      },
    );

    test(
      'adapta las tarjetas y filtros de Retos',
      async ({
        page,
      }, testInfo) => {
        await login(
          page,
          GENERAL_USER,
          /\/principal$/,
        );

        await page.goto(
          '/retos',
          {
            waitUntil:
              'domcontentloaded',
          },
        );

        const skillSelect =
          page
            .locator(
              'select[name="habilidad"]:visible',
            )
            .first();

        const difficultySelect =
          page
            .locator(
              'select[name="dificultad"]:visible',
            )
            .first();

        await expectInsideViewport(
          skillSelect,
          'Filtro de habilidad de Retos',
        );

        await expectInsideViewport(
          difficultySelect,
          'Filtro de dificultad de Retos',
        );

        const skillValue =
          await selectOptionByText(
            skillSelect,
            SKILL_TEXT,
          );

        const difficultyValue =
          await difficultySelect
            .locator(
              'option[value="1"]',
            )
            .getAttribute(
              'value',
            );

        expect(
          difficultyValue,
        ).toBeTruthy();

        await page.goto(
          `/retos?habilidad=${encodeURIComponent(
            skillValue,
          )}&dificultad=${encodeURIComponent(
            difficultyValue!,
          )}`,
          {
            waitUntil:
              'domcontentloaded',
          },
        );

        await expect(
          page.locator(
            'select[name="habilidad"]',
          ),
        ).toHaveValue(
          skillValue,
        );

        await expect(
          page.locator(
            'select[name="dificultad"]',
          ),
        ).toHaveValue(
          difficultyValue!,
        );

        await expect(
          page
            .getByText(
              'Reto Adaptabilidad 01',
              {
                exact:
                  true,
              },
            )
            .first(),
        ).toBeVisible();

        await expectAdaptiveCards(
          page,
          'Tarjetas de Retos',
          [
            /*
             * Clase real utilizada por la vista de Retos.
             */
            '.challenges-card',
            '.challenge-card',
            '.challenge-cards__card',
            '.challenges-cards article',
          ],
          [
            '[data-sv-challenge-open]',
          ],
          'Reto Adaptabilidad',
        );

        const openButton =
          page
            .locator(
              '[data-sv-challenge-open]:visible',
            )
            .first();

        await expectInsideViewport(
          openButton,
          'Botón de una tarjeta de reto',
        );

        await expectNoHorizontalOverflow(
          page,
          'Retos',
        );

        await attachScreenshot(
          page,
          testInfo,
          evidenceName(
            testInfo,
            'tarjetas-filtros-retos',
          ),
        );
      },
    );

    test(
      'adapta las tarjetas y el filtro de Blog',
      async ({
        page,
      }, testInfo) => {
        await login(
          page,
          GENERAL_USER,
          /\/principal$/,
        );

        await page.goto(
          '/blog',
          {
            waitUntil:
              'domcontentloaded',
          },
        );

        const skillSelect =
          page
            .locator(
              'select[name="habilidad"]:visible',
            )
            .first();

        await expectInsideViewport(
          skillSelect,
          'Filtro de habilidad de Blog',
        );

        const skillValue =
          await selectOptionByText(
            skillSelect,
            SKILL_TEXT,
          );

        await page.goto(
          `/blog?habilidad=${encodeURIComponent(
            skillValue,
          )}`,
          {
            waitUntil:
              'domcontentloaded',
          },
        );

        await expect(
          page.locator(
            'select[name="habilidad"]',
          ),
        ).toHaveValue(
          skillValue,
        );

        await expect(
          page
            .getByText(
              'Artículo Adaptabilidad 01',
              {
                exact:
                  true,
              },
            )
            .first(),
        ).toBeVisible();

        await expectAdaptiveCards(
          page,
          'Tarjetas de Blog',
          [
            /*
             * Se prueban selectores específicos en orden.
             * Ya no se usa el selector genérico:
             * [class*="blog"][class*="card"]
             * porque también seleccionaba elementos internos.
             */
            '.blog-card',
            '.blog__card',
            '.article-card',
            '.blog-cards__card',
            '.blog-cards article',
            'main article',
          ],
          [
            '[data-sv-blog-open]',
            '[data-blog-open]',
            '.js-blog-modal-open',
          ],
          'Artículo Adaptabilidad',
        );

        await expectNoHorizontalOverflow(
          page,
          'Blog',
        );

        await attachScreenshot(
          page,
          testInfo,
          evidenceName(
            testInfo,
            'tarjetas-filtro-blog',
          ),
        );
      },
    );

    test(
      'adapta el listado y la paginación de usuarios',
      async ({
        page,
      }, testInfo) => {
        await login(
          page,
          ADMIN_USER,
          /\/admin\/dashboard$/,
        );

        await page.goto(
          `/admin/usuarios?page=1&busqueda=${encodeURIComponent(
            USER_SEARCH,
          )}`,
          {
            waitUntil:
              'domcontentloaded',
          },
        );

        const search =
          page
            .locator(
              'input[name="busqueda"]:visible',
            )
            .first();

        await expectInsideViewport(
          search,
          'Buscador de usuarios',
        );

        await expect(
          search,
        ).toHaveValue(
          USER_SEARCH,
        );

        await expectAdaptiveAdminList(
          page,
          'Listado de usuarios',
        );

        await expectPagination(
          page,
          '1',
        );

        await expectNoHorizontalOverflow(
          page,
          'Administración de usuarios',
        );

        /*
         * Se navega usando el control visible:
         * número 2 en escritorio o Siguiente en móvil.
         */
        await navigateToSecondPage(
          page,
        );

        await expectPagination(
          page,
          '2',
        );

        await expectAdaptiveAdminList(
          page,
          'Segunda página de usuarios',
        );

        await expectNoHorizontalOverflow(
          page,
          'Segunda página de usuarios',
        );

        await attachScreenshot(
          page,
          testInfo,
          evidenceName(
            testInfo,
            'paginacion-usuarios',
          ),
        );
      },
    );

    test(
      'adapta el listado y la paginación de habilidades',
      async ({
        page,
      }, testInfo) => {
        await login(
          page,
          ADMIN_USER,
          /\/admin\/dashboard$/,
        );

        await page.goto(
          `/admin/habilidades?page=1&busqueda=${encodeURIComponent(
            SKILL_SEARCH,
          )}`,
          {
            waitUntil:
              'domcontentloaded',
          },
        );

        const search =
          page
            .locator(
              'input[name="busqueda"]:visible',
            )
            .first();

        await expectInsideViewport(
          search,
          'Buscador de habilidades',
        );

        await expect(
          search,
        ).toHaveValue(
          SKILL_SEARCH,
        );

        await expectAdaptiveAdminList(
          page,
          'Listado de habilidades',
        );

        await expectPagination(
          page,
          '1',
        );

        await expectNoHorizontalOverflow(
          page,
          'Administración de habilidades',
        );

        await navigateToSecondPage(
          page,
        );

        await expectPagination(
          page,
          '2',
        );

        await expectAdaptiveAdminList(
          page,
          'Segunda página de habilidades',
        );

        await expectNoHorizontalOverflow(
          page,
          'Segunda página de habilidades',
        );

        await attachScreenshot(
          page,
          testInfo,
          evidenceName(
            testInfo,
            'paginacion-habilidades',
          ),
        );
      },
    );
  },
);