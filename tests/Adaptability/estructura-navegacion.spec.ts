import {
  expect,
  test,
  type Locator,
  type Page,
  type TestInfo,
} from '@playwright/test';

/*
|--------------------------------------------------------------------------
| Datos del usuario controlado
|--------------------------------------------------------------------------
|
| Estos valores deben coincidir con el usuario general creado por:
|
| tests/E2E/helpers/auth-fixtures.php
|
| También pueden suministrarse mediante variables de entorno.
|
*/

const USER_EMAIL =
  process.env.ADAPT_USER_EMAIL
  ?? 'e2e_auth_general@skillview.test';

const USER_PASSWORD =
  process.env.ADAPT_USER_PASSWORD
  ?? 'CajaNegra1!';

/**
 * Rutas principales que deben permanecer disponibles
 * desde el encabezado de SkillView.
 */
const mainRoutes = [
  {
    name: 'Aprendizaje',
    path: '/aprendizaje',
  },
  {
    name: 'Retos',
    path: '/retos',
  },
  {
    name: 'Blog',
    path: '/blog',
  },
  {
    name: 'Perfil',
    path: '/perfil',
  },
  {
    name: 'Inicio',
    path: '/principal',
  },
] as const;

/*
|--------------------------------------------------------------------------
| Inicio de sesión
|--------------------------------------------------------------------------
*/

/**
 * Inicia sesión mediante el formulario real de SkillView.
 */
async function login(
  page: Page,
): Promise<void> {
  await page.goto(
    '/',
    {
      waitUntil: 'domcontentloaded',
    },
  );

  const form =
    page.locator(
      'form[action="/"]',
    );

  await expect(
    form,
    'El formulario de inicio de sesión debe estar visible.',
  ).toBeVisible();

  await form
    .locator(
      'input[name="correo"]',
    )
    .fill(
      USER_EMAIL,
    );

  await form
    .locator(
      'input[name="password"]',
    )
    .fill(
      USER_PASSWORD,
    );

  const submit =
    form
      .locator(
        [
          'input[type="submit"]',
          'button[type="submit"]',
        ].join(', '),
      )
      .first();

  await expect(
    submit,
    'El botón para iniciar sesión debe estar visible.',
  ).toBeVisible();

  try {
    /*
     * La espera se registra antes del clic y finaliza
     * cuando el navegador alcanza la página principal.
     *
     * Esto evita que WebKit consulte la URL antes
     * de completar la navegación.
     */
    await Promise.all([
      page.waitForURL(
        /\/principal$/,
        {
          waitUntil: 'domcontentloaded',
          timeout: 20_000,
        },
      ),

      submit.click(),
    ]);
  } catch {
    const mensajes =
      await page
        .locator(
          [
            '.alerta',
            '.alerta-error',
            '.alerta__error',
            '[role="alert"]',
          ].join(', '),
        )
        .allTextContents();

    const mensajesLimpios =
      mensajes
        .map(
          (mensaje) =>
            mensaje.trim(),
        )
        .filter(
          Boolean,
        );

    throw new Error(
      [
        'No fue posible iniciar sesión.',
        `URL final: ${page.url()}`,
        `Correo utilizado: ${USER_EMAIL}`,
        'Mensajes mostrados:',
        mensajesLimpios.length > 0
          ? mensajesLimpios.join(' | ')
          : 'No se encontró una alerta visible.',
      ].join('\n'),
    );
  }

  await expect(
    page,
  ).toHaveURL(
    /\/principal$/,
  );

  await expect(
    page.locator(
      'main:visible',
    ).first(),
    'La página principal debe mostrar su contenido.',
  ).toBeVisible();
}

/*
|--------------------------------------------------------------------------
| Comprobaciones geométricas
|--------------------------------------------------------------------------
*/

/**
 * Comprueba que la página no genere un desplazamiento
 * horizontal inesperado.
 *
 * Se permite un píxel de tolerancia debido a posibles
 * redondeos internos de CSS.
 */
async function expectNoHorizontalOverflow(
  page: Page,
  context: string,
): Promise<void> {
  const dimensions =
    await page.evaluate(
      () => {
        const documentElement =
          document.documentElement;

        const body =
          document.body;

        return {
          viewportWidth:
            documentElement.clientWidth,

          documentWidth:
            Math.max(
              documentElement.scrollWidth,
              body?.scrollWidth ?? 0,
            ),
        };
      },
    );

  expect.soft(
    dimensions.documentWidth,
    `${context}: el documento no debe superar el ancho visible.`,
  ).toBeLessThanOrEqual(
    dimensions.viewportWidth + 1,
  );
}

/**
 * Comprueba que un elemento visible permanezca dentro
 * de los límites horizontales del viewport.
 */
async function expectInsideViewportHorizontally(
  locator: Locator,
  context: string,
): Promise<void> {
  await expect(
    locator,
    `${context}: el elemento debe estar visible.`,
  ).toBeVisible();

  const geometry =
    await locator.evaluate(
      (element) => {
        const rectangle =
          element.getBoundingClientRect();

        return {
          left:
            rectangle.left,

          right:
            rectangle.right,

          width:
            rectangle.width,

          viewportWidth:
            window.innerWidth,
        };
      },
    );

  expect.soft(
    geometry.width,
    `${context}: el elemento debe tener un ancho mayor que cero.`,
  ).toBeGreaterThan(
    0,
  );

  expect.soft(
    geometry.left,
    `${context}: el elemento no debe quedar recortado por la izquierda.`,
  ).toBeGreaterThanOrEqual(
    -1,
  );

  expect.soft(
    geometry.right,
    `${context}: el elemento no debe quedar recortado por la derecha.`,
  ).toBeLessThanOrEqual(
    geometry.viewportWidth + 1,
  );
}

/**
 * Verifica los componentes estructurales compartidos
 * por las páginas privadas de SkillView.
 */
async function expectGeneralStructure(
  page: Page,
  routeName: string,
): Promise<void> {
  /*
   * Algunas páginas pueden contener variantes ocultas
   * del encabezado o del pie de página.
   *
   * Se seleccionan únicamente elementos visibles para
   * evitar que Playwright espere componentes con
   * display: none.
   */
  const header =
    page
      .locator(
        [
          'header.site-header:visible',
          '.site-header:visible',
        ].join(', '),
      )
      .first();

  const main =
    page
      .locator(
        'main:visible',
      )
      .first();

  const footer =
    page
      .locator(
        'footer:visible',
      )
      .last();

  await expectInsideViewportHorizontally(
    header,
    `${routeName} — encabezado`,
  );

  await expect(
    header,
    `${routeName}: el encabezado debe identificar la plataforma.`,
  ).toContainText(
    /SkillView/i,
  );

  await expectInsideViewportHorizontally(
    main,
    `${routeName} — contenido principal`,
  );

  await expectNoHorizontalOverflow(
    page,
    routeName,
  );

  /*
   * El pie de página puede encontrarse fuera del área
   * visible inicial. Primero se comprueba que exista una
   * variante visible y luego se realiza el desplazamiento.
   */
  await expect(
    footer,
    `${routeName}: debe existir un pie de página visible.`,
  ).toBeVisible();

  await footer.evaluate(
    (element) => {
      element.scrollIntoView({
        block: 'center',
        inline: 'nearest',
      });
    },
  );

  await expectInsideViewportHorizontally(
    footer,
    `${routeName} — pie de página`,
  );

  /*
   * Regresar a la parte superior para que la siguiente
   * comprobación empiece desde el encabezado.
   */
  await page.evaluate(
    () => {
      window.scrollTo(
        0,
        0,
      );
    },
  );
}

/*
|--------------------------------------------------------------------------
| Navegación
|--------------------------------------------------------------------------
*/

/**
 * Escapa una ruta para utilizarla dentro
 * de una expresión regular.
 */
function escapeRegExp(
  value: string,
): string {
  return value.replace(
    /[.*+?^${}()|[\]\\]/g,
    '\\$&',
  );
}

/**
 * Obtiene un enlace visible del encabezado
 * que lleve hacia una ruta concreta.
 */
function visibleHeaderRouteLink(
  page: Page,
  route: string,
): Locator {
  return page
    .locator(
      [
        `header.site-header:visible a[href="${route}"]:visible`,
        `.site-header:visible a[href="${route}"]:visible`,
      ].join(', '),
    )
    .first();
}

/**
 * Determina si la resolución actual utiliza
 * el menú de navegación adaptable.
 */
async function usesCompactNavigation(
  page: Page,
): Promise<boolean> {
  return page
    .locator(
      '.site-header__toggle',
    )
    .isVisible();
}

/**
 * Abre el menú adaptable y devuelve sus componentes.
 */
async function openCompactMenu(
  page: Page,
): Promise<{
  toggle: Locator;
  mobileNav: Locator;
}> {
  const toggle =
    page.locator(
      '.site-header__toggle',
    );

  const mobileNav =
    page.locator(
      '.site-nav--mobile',
    );

  await expect(
    toggle,
    'El botón del menú adaptable debe estar visible.',
  ).toBeVisible();

  await expect(
    mobileNav,
    'El contenedor del menú adaptable debe existir.',
  ).toBeAttached();

  const expanded =
    await toggle.getAttribute(
      'aria-expanded',
    );

  if (expanded !== 'true') {
    await toggle.click();
  }

  await expect(
    toggle,
  ).toHaveAttribute(
    'aria-expanded',
    'true',
  );

  await expect(
    mobileNav,
  ).toHaveClass(
    /site-nav--mobile-open/,
  );

  await expect(
    mobileNav,
    'El menú adaptable debe mostrarse después de abrirlo.',
  ).toBeVisible();

  return {
    toggle,
    mobileNav,
  };
}

/**
 * Cierra el menú adaptable mediante su botón interno.
 */
async function closeCompactMenu(
  page: Page,
): Promise<void> {
  const toggle =
    page.locator(
      '.site-header__toggle',
    );

  const mobileNav =
    page.locator(
      '.site-nav--mobile',
    );

  const closeButton =
    mobileNav.locator(
      '.site-nav__mobile-close',
    );

  await expect(
    closeButton,
    'El menú adaptable debe incluir un botón para cerrarlo.',
  ).toBeVisible();

  await closeButton.click();

  await expect(
    toggle,
  ).toHaveAttribute(
    'aria-expanded',
    'false',
  );

  await expect(
    mobileNav,
  ).not.toHaveClass(
    /site-nav--mobile-open/,
  );
}

/**
 * Navega utilizando el mecanismo correspondiente
 * a la resolución actual.
 */
async function navigateToRoute(
  page: Page,
  route: string,
  compactLayout: boolean,
): Promise<void> {
  let link: Locator;

  if (compactLayout) {
    const {
      mobileNav,
    } = await openCompactMenu(
      page,
    );

    link =
      mobileNav
        .locator(
          `a[href="${route}"]`,
        )
        .first();
  } else {
    link =
      visibleHeaderRouteLink(
        page,
        route,
      );
  }

  await expect(
    link,
    `Debe existir un enlace visible hacia ${route}.`,
  ).toBeVisible();

  await link.click();

  await expect(
    page,
  ).toHaveURL(
    new RegExp(
      `${escapeRegExp(route)}$`,
    ),
  );

  await expect(
    page
      .locator(
        'main:visible',
      )
      .first(),
    `La ruta ${route} debe mostrar su contenido principal.`,
  ).toBeVisible();
}

/*
|--------------------------------------------------------------------------
| Evidencias
|--------------------------------------------------------------------------
*/

/**
 * Guarda una captura y la adjunta al reporte HTML.
 */
async function attachScreenshot(
  page: Page,
  testInfo: TestInfo,
  name: string,
  fullPage = false,
): Promise<void> {
  const filePath =
    testInfo.outputPath(
      `${name}.png`,
    );

  await page.screenshot({
    path:
      filePath,

    fullPage,

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
| Escenario
|--------------------------------------------------------------------------
*/

test.describe(
  'Adaptabilidad de la estructura general, encabezado y navegación',
  () => {
    test(
      'conserva la estructura y la navegación en la configuración evaluada',
      async ({
        page,
      }, testInfo) => {
        const metadata =
          testInfo.project.metadata as {
            evidencia?: string;
          };

        const evidenceName =
          metadata.evidencia
          ?? testInfo.project.name;

        /*
         * El tipo de navegación se determina usando
         * los componentes visibles de la interfaz.
         *
         * No se infiere únicamente desde el tamaño de
         * pantalla, porque el breakpoint de tableta puede
         * activar directamente la navegación horizontal.
         */
        let compactLayout =
          false;

        await test.step(
          'Iniciar sesión con el usuario general',
          async () => {
            await login(
              page,
            );

            compactLayout =
              await usesCompactNavigation(
                page,
              );
          },
        );

        await test.step(
          'Comprobar la estructura inicial',
          async () => {
            await expectGeneralStructure(
              page,
              'Página principal',
            );
          },
        );

        await test.step(
          'Comprobar el mecanismo de navegación',
          async () => {
            const toggle =
              page.locator(
                '.site-header__toggle',
              );

            const mobileNav =
              page.locator(
                '.site-nav--mobile',
              );

            if (compactLayout) {
              /*
               * En una resolución compacta debe estar
               * disponible el menú adaptable.
               */
              await expect(
                toggle,
              ).toBeVisible();

              await expect(
                toggle,
              ).toHaveAttribute(
                'aria-expanded',
                'false',
              );

              const opened =
                await openCompactMenu(
                  page,
                );

              await expectInsideViewportHorizontally(
                opened.mobileNav,
                'Menú adaptable abierto',
              );

              await attachScreenshot(
                page,
                testInfo,
                `${evidenceName}-menu-abierto`,
              );

              await closeCompactMenu(
                page,
              );
            } else {
              /*
               * Cuando la navegación horizontal se encuentra
               * disponible, el botón compacto debe permanecer
               * oculto.
               */
              await expect(
                toggle,
              ).toBeHidden();

              /*
               * El contenedor móvil puede seguir presente en
               * el DOM, pero debe permanecer cerrado.
               */
              await expect(
                mobileNav,
              ).not.toHaveClass(
                /site-nav--mobile-open/,
              );

              for (
                const route
                of mainRoutes
              ) {
                await expect(
                  visibleHeaderRouteLink(
                    page,
                    route.path,
                  ),
                  `El enlace de ${route.name} debe estar visible en el encabezado.`,
                ).toBeVisible();
              }
            }
          },
        );

        await test.step(
          'Recorrer las secciones principales',
          async () => {
            for (
              const route
              of mainRoutes
            ) {
              await navigateToRoute(
                page,
                route.path,
                compactLayout,
              );

              await expectGeneralStructure(
                page,
                route.name,
              );
            }
          },
        );

        await test.step(
          'Conservar la evidencia visual de la configuración',
          async () => {
            await attachScreenshot(
              page,
              testInfo,
              `${evidenceName}-estructura-general`,
              true,
            );
          },
        );
      },
    );
  },
);