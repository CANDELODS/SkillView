import {
  expect,
  test,
  type Locator,
  type Page,
  type Route,
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
  skillId: number;
  lessonId: number;
  challengeId: number;
};

type JsonRecord = Record<string, unknown>;

const LONG_ASSISTANT_TEXT =
  'Analiza la situación con calma, explica qué harías primero, cómo comunicarías '
  + 'tu decisión y de qué manera comprobarías que la otra persona comprendió el '
  + 'mensaje. La respuesta debe conservar una lectura clara incluso cuando el '
  + 'espacio disponible sea reducido.';

function readFixtureData(): FixtureData {
  const fixturePath =
    resolve(
      process.cwd(),
      'tests/Adaptability/.grupo4-fixture.json',
    );

  return JSON.parse(
    readFileSync(
      fixturePath,
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
      /\/principal$/,
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
    /\/principal$/,
  );
}

/*
|--------------------------------------------------------------------------
| Respuestas controladas de API
|--------------------------------------------------------------------------
*/

async function fulfillJson(
  route: Route,
  body: JsonRecord,
  status = 200,
): Promise<void> {
  await route.fulfill({
    status,

    contentType:
      'application/json; charset=utf-8',

    body:
      JSON.stringify(
        body,
      ),
  });
}

function requestPayload(
  route: Route,
): JsonRecord {
  try {
    return route
      .request()
      .postDataJSON() as JsonRecord;
  } catch {
    return {};
  }
}

async function mockLessonConversation(
  page: Page,
  fixture: FixtureData,
): Promise<void> {
  await page.route(
    '**/api/lecciones/start',
    async (route) => {
      await fulfillJson(
        route,
        {
          ok:
            true,

          error:
            null,

          lesson: {
            id:
              fixture.lessonId,

            title:
              'Lección Conversacional E2E',

            order:
              999,

            type:
              'standard',

            skill: {
              id:
                fixture.skillId,

              name:
                'Adaptabilidad Conversacional E2E',
            },
          },

          session: {
            lessonId:
              fixture.lessonId,

            skillId:
              fixture.skillId,

            currentStage:
              'intro',

            nextExpectedAction:
              'advance',

            inputEnabled:
              false,

            requiresUserResponse:
              false,

            completed:
              false,

            failed:
              false,

            attempts: {
              microPractice:
                0,

              miniEvaluation:
                0,
            },

            limits: {
              microPractice:
                3,

              miniEvaluation:
                3,
            },
          },

          content: {
            objective:
              'Practicar una respuesta clara y concreta.',
          },

          messages: [
            {
              id:
                'lesson_intro_1',

              role:
                'assistant',

              type:
                'text',

              text:
                'Bienvenido a la lección de adaptabilidad conversacional.',
            },
          ],

          ui: {
            showTyping:
              false,

            showAvatarSpeaking:
              false,

            composerPlaceholder:
              'Espera la explicación del asistente...',

            focusInput:
              false,

            showReturnButton:
              false,
          },
        },
      );
    },
  );

  let turnNumber = 0;

  await page.route(
    '**/api/lecciones/turn',
    async (route) => {
      turnNumber += 1;

      const payload =
        requestPayload(
          route,
        );

      const userMessage =
        String(
          payload.message
          ?? '',
        );

      if (turnNumber === 1) {
        await fulfillJson(
          route,
          {
            ok:
              true,

            error:
              null,

            session: {
              lessonId:
                fixture.lessonId,

              skillId:
                fixture.skillId,

              currentStage:
                'micro_practice_answer',

              nextExpectedAction:
                'reply',

              inputEnabled:
                true,

              requiresUserResponse:
                true,

              completed:
                false,

              failed:
                false,

              attempts: {
                microPractice:
                  0,

                miniEvaluation:
                  0,
              },

              limits: {
                microPractice:
                  3,

                miniEvaluation:
                  3,
              },
            },

            messages: [
              {
                id:
                  'lesson_micro_prompt',

                role:
                  'assistant',

                type:
                  'text',

                text:
                  `Micropráctica: ${LONG_ASSISTANT_TEXT}`,
              },
            ],

            ui: {
              showTyping:
                false,

              showAvatarSpeaking:
                false,

              composerPlaceholder:
                'Escribe tu respuesta de micropráctica...',

              focusInput:
                true,

              showReturnButton:
                false,
            },
          },
        );

        return;
      }

      if (turnNumber === 2) {
        await fulfillJson(
          route,
          {
            ok:
              true,

            error:
              null,

            session: {
              lessonId:
                fixture.lessonId,

              skillId:
                fixture.skillId,

              currentStage:
                'mini_eval_answer',

              nextExpectedAction:
                'reply',

              inputEnabled:
                true,

              requiresUserResponse:
                true,

              completed:
                false,

              failed:
                false,

              attempts: {
                microPractice:
                  0,

                miniEvaluation:
                  0,
              },

              limits: {
                microPractice:
                  3,

                miniEvaluation:
                  3,
              },
            },

            evaluation: {
              accepted:
                true,

              needsRetry:
                false,

              retryReason:
                null,
            },

            messages: [
              {
                id:
                  'lesson_user_micro',

                role:
                  'user',

                type:
                  'text',

                text:
                  userMessage,
              },

              {
                id:
                  'lesson_micro_feedback',

                role:
                  'assistant',

                type:
                  'text',

                text:
                  'La respuesta fue clara. Ahora realiza la minievaluación.',
              },

              {
                id:
                  'lesson_mini_prompt',

                role:
                  'assistant',

                type:
                  'text',

                text:
                  'Minievaluación: explica una mejora concreta que aplicarías '
                  + 'en una entrevista laboral.',
              },
            ],

            ui: {
              showTyping:
                false,

              showAvatarSpeaking:
                false,

              composerPlaceholder:
                'Responde la minievaluación...',

              focusInput:
                true,

              showReturnButton:
                false,
            },
          },
        );

        return;
      }

      await fulfillJson(
        route,
        {
          ok:
            true,

          error:
            null,

          session: {
            lessonId:
              fixture.lessonId,

            skillId:
              fixture.skillId,

            currentStage:
              'complete',

            nextExpectedAction:
              null,

            inputEnabled:
              false,

            requiresUserResponse:
              false,

            completed:
              true,

            failed:
              false,

            attempts: {
              microPractice:
                0,

              miniEvaluation:
                0,
            },

            limits: {
              microPractice:
                3,

              miniEvaluation:
                3,
            },
          },

          evaluation: {
            accepted:
              true,

            needsRetry:
              false,
          },

          messages: [
            {
              id:
                'lesson_user_final',

              role:
                'user',

              type:
                'text',

              text:
                userMessage,
            },
          ],

          completionModal: {
            type:
              'success',

            title:
              'Lección completada',

            messages: [
              {
                id:
                  'lesson_final_1',

                role:
                  'assistant',

                type:
                  'text',

                text:
                  'Completaste correctamente la micropráctica y la minievaluación.',
              },

              {
                id:
                  'lesson_final_2',

                role:
                  'assistant',

                type:
                  'text',

                text:
                  LONG_ASSISTANT_TEXT,
              },
            ],

            buttonText:
              'Continuar',

            redirectTo:
              '/aprendizaje',
          },

          progress: {
            lessonCompleted:
              true,

            completedAt:
              '2026-08-04 14:00:00',

            redirectTo:
              '/aprendizaje',
          },

          ui: {
            showTyping:
              false,

            showAvatarSpeaking:
              false,

            composerPlaceholder:
              'Lección completada',

            focusInput:
              false,

            showReturnButton:
              true,
          },
        },
      );
    },
  );
}

async function mockChallengeConversation(
  page: Page,
  fixture: FixtureData,
): Promise<void> {
  await page.route(
    '**/api/retos/start',
    async (route) => {
      await fulfillJson(
        route,
        {
          ok:
            true,

          error:
            null,

          challenge: {
            id:
              fixture.challengeId,

            title:
              'Reto Conversacional E2E',

            skill: {
              id:
                fixture.skillId,

              name:
                'Adaptabilidad Conversacional E2E',
            },

            maxScore:
              30,

            minimumScore:
              21,
          },

          session: {
            challengeId:
              fixture.challengeId,

            skillId:
              fixture.skillId,

            currentStage:
              'intro',

            nextExpectedAction:
              'advance',

            inputEnabled:
              false,

            requiresUserResponse:
              false,

            completed:
              false,

            passed:
              false,

            failed:
              false,

            attempts: {
              challengeAnswer:
                0,
            },

            limits: {
              challengeAnswer:
                3,
            },

            scoreAwarded:
              0,

            maxScore:
              30,

            minimumScore:
              21,
          },

          messages: [
            {
              id:
                'challenge_intro_1',

              role:
                'assistant',

              type:
                'text',

              text:
                'Bienvenido al reto de adaptabilidad conversacional.',
            },
          ],

          ui: {
            showTyping:
              false,

            showAvatarSpeaking:
              false,

            composerPlaceholder:
              'Espera la consigna del reto...',

            focusInput:
              false,

            showReturnButton:
              false,
          },
        },
      );
    },
  );

  let turnNumber = 0;

  await page.route(
    '**/api/retos/turn',
    async (route) => {
      turnNumber += 1;

      const payload =
        requestPayload(
          route,
        );

      const userMessage =
        String(
          payload.message
          ?? '',
        );

      if (turnNumber === 1) {
        await fulfillJson(
          route,
          {
            ok:
              true,

            error:
              null,

            session: {
              challengeId:
                fixture.challengeId,

              skillId:
                fixture.skillId,

              currentStage:
                'challenge_answer',

              nextExpectedAction:
                'reply',

              inputEnabled:
                true,

              requiresUserResponse:
                true,

              completed:
                false,

              passed:
                false,

              failed:
                false,

              attempts: {
                challengeAnswer:
                  0,
              },

              limits: {
                challengeAnswer:
                  3,
              },

              scoreAwarded:
                0,

              maxScore:
                30,

              minimumScore:
                21,
            },

            messages: [
              {
                id:
                  'challenge_prompt',

                role:
                  'assistant',

                type:
                  'text',

                text:
                  `Consigna del reto: ${LONG_ASSISTANT_TEXT}`,
              },
            ],

            ui: {
              showTyping:
                false,

              showAvatarSpeaking:
                false,

              composerPlaceholder:
                'Escribe tu respuesta al reto...',

              focusInput:
                true,

              showReturnButton:
                false,
            },
          },
        );

        return;
      }

      if (turnNumber === 2) {
        await fulfillJson(
          route,
          {
            ok:
              true,

            error:
              null,

            session: {
              challengeId:
                fixture.challengeId,

              skillId:
                fixture.skillId,

              currentStage:
                'attempt_retry',

              nextExpectedAction:
                'reply',

              inputEnabled:
                true,

              requiresUserResponse:
                true,

              completed:
                false,

              passed:
                false,

              failed:
                false,

              attempts: {
                challengeAnswer:
                  1,
              },

              limits: {
                challengeAnswer:
                  3,
              },

              scoreAwarded:
                12,

              maxScore:
                30,

              minimumScore:
                21,
            },

            evaluation: {
              accepted:
                false,

              needsRetry:
                true,

              retryReason:
                'INSUFFICIENT_DEVELOPMENT',

              detectedIssues: [
                'La respuesta necesita mayor desarrollo.',
              ],
            },

            messages: [
              {
                id:
                  'challenge_user_retry',

                role:
                  'user',

                type:
                  'text',

                text:
                  userMessage,
              },

              {
                id:
                  'challenge_retry_feedback',

                role:
                  'assistant',

                type:
                  'text',

                text:
                  'La respuesta necesita más detalle. Explica una acción concreta '
                  + 'y cómo verificarías el resultado. Te quedan dos intentos.',
              },
            ],

            ui: {
              showTyping:
                false,

              showAvatarSpeaking:
                false,

              composerPlaceholder:
                'Intenta responder mejor...',

              focusInput:
                true,

              showReturnButton:
                false,
            },
          },
        );

        return;
      }

      await fulfillJson(
        route,
        {
          ok:
            true,

          error:
            null,

          session: {
            challengeId:
              fixture.challengeId,

            skillId:
              fixture.skillId,

            currentStage:
              'complete',

            nextExpectedAction:
              null,

            inputEnabled:
              false,

            requiresUserResponse:
              false,

            completed:
              true,

            passed:
              true,

            failed:
              false,

            attempts: {
              challengeAnswer:
                2,
            },

            limits: {
              challengeAnswer:
                3,
            },

            scoreAwarded:
              27,

            maxScore:
              30,

            minimumScore:
              21,
          },

          evaluation: {
            accepted:
              true,

            needsRetry:
              false,

            retryReason:
              null,

            detectedIssues:
              [],

            scoreRatio:
              0.9,

            performanceLevel:
              'ADVANCED',

            scoreAwarded:
              27,

            maxScore:
              30,
          },

          messages: [
            {
              id:
                'challenge_user_final',

              role:
                'user',

              type:
                'text',

              text:
                userMessage,
            },
          ],

          completionModal: {
            type:
              'success',

            title:
              'Reto completado',

            messages: [
              {
                id:
                  'challenge_final_1',

                role:
                  'assistant',

                type:
                  'text',

                text:
                  'Tu segunda respuesta desarrolló una acción concreta y una forma '
                  + 'de verificar el resultado.',
              },

              {
                id:
                  'challenge_final_2',

                role:
                  'assistant',

                type:
                  'text',

                text:
                  LONG_ASSISTANT_TEXT,
              },
            ],

            scoreAwarded:
              27,

            maxScore:
              30,

            minimumScore:
              21,

            buttonText:
              'Continuar',

            redirectTo:
              '/retos',
          },

          progress: {
            challengeCompleted:
              true,

            failed:
              false,

            scoreAwarded:
              27,

            redirectTo:
              '/retos',
          },

          ui: {
            showTyping:
              false,

            showAvatarSpeaking:
              false,

            composerPlaceholder:
              'Reto completado',

            focusInput:
              false,

            showReturnButton:
              true,
          },
        },
      );
    },
  );
}

/*
|--------------------------------------------------------------------------
| Geometría y controles
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
    `${context}: no debe quedar recortado a la izquierda.`,
  ).toBeGreaterThanOrEqual(
    -1,
  );

  expect.soft(
    geometry.right,
    `${context}: no debe quedar recortado a la derecha.`,
  ).toBeLessThanOrEqual(
    geometry.viewportWidth + 1,
  );
}

async function expectMessagesContained(
  messages: Locator,
  context: string,
): Promise<void> {
  const geometry =
    await messages.evaluate(
      (container) => {
        const containerRect =
          container.getBoundingClientRect();

        const visibleChildren =
          (
            Array.from(
              container.children,
            ) as Element[]
          )
            .filter(
              (child) => {
                const style =
                  window.getComputedStyle(
                    child,
                  );

                const rect =
                  child.getBoundingClientRect();

                return style.display !== 'none'
                  && style.visibility !== 'hidden'
                  && rect.width > 0
                  && rect.height > 0;
              },
            )
            .map(
              (child) => {
                const rect =
                  child.getBoundingClientRect();

                return {
                  left:
                    rect.left,

                  right:
                    rect.right,
                };
              },
            );

        return {
          containerLeft:
            containerRect.left,

          containerRight:
            containerRect.right,

          viewportWidth:
            window.innerWidth,

          children:
            visibleChildren,
        };
      },
    );

  for (
    const [
      index,
      child,
    ]
    of geometry.children.entries()
  ) {
    expect.soft(
      child.left,
      `${context}: el mensaje ${index + 1} no debe salir por la izquierda.`,
    ).toBeGreaterThanOrEqual(
      Math.max(
        -1,
        geometry.containerLeft - 1,
      ),
    );

    expect.soft(
      child.right,
      `${context}: el mensaje ${index + 1} no debe salir por la derecha.`,
    ).toBeLessThanOrEqual(
      Math.min(
        geometry.viewportWidth + 1,
        geometry.containerRight + 1,
      ),
    );
  }
}

async function expectConversationLayout(
  page: Page,
  selectors: {
    root: string;
    messages: string;
    composer: string;
    input: string;
    send: string;
  },
  context: string,
  inputMustBeEnabled: boolean,
): Promise<void> {
  const root =
    page
      .locator(
        selectors.root,
      )
      .first();

  const messages =
    page
      .locator(
        selectors.messages,
      )
      .first();

  const composer =
    page
      .locator(
        selectors.composer,
      )
      .first();

  const input =
    page
      .locator(
        selectors.input,
      )
      .first();

  const send =
    page
      .locator(
        selectors.send,
      )
      .first();

  await expectInsideViewport(
    root,
    `${context} — contenedor principal`,
  );

  await expectInsideViewport(
    messages,
    `${context} — historial de mensajes`,
  );

  await expectInsideViewport(
    composer,
    `${context} — compositor`,
  );

  await expectInsideViewport(
    input,
    `${context} — campo de respuesta`,
  );

  await expectInsideViewport(
    send,
    `${context} — botón de envío`,
  );

  if (inputMustBeEnabled) {
    await expect(
      input,
      `${context}: el campo de respuesta debe estar habilitado.`,
    ).toBeEnabled();

    await expect(
      send,
      `${context}: el botón de envío debe estar habilitado.`,
    ).toBeEnabled();
  } else {
    await expect(
      input,
      `${context}: el campo debe bloquearse al finalizar.`,
    ).toBeDisabled();
  }

  expect(
    await messages
      .locator(
        ':scope > *:visible',
      )
      .count(),
    `${context}: debe existir al menos un mensaje visible.`,
  ).toBeGreaterThan(
    0,
  );

  await expectMessagesContained(
    messages,
    context,
  );

  await expectNoHorizontalOverflow(
    page,
    context,
  );
}

async function expectResultModalAdaptive(
  page: Page,
  selectors: {
    modal: string;
    body: string;
    continueButton: string;
    score?: string;
  },
  context: string,
): Promise<void> {
  const modal =
    page
      .locator(
        `${selectors.modal}.is-open`,
      )
      .first();

  const body =
    modal
      .locator(
        selectors.body,
      )
      .first();

  const continueButton =
    modal
      .locator(
        selectors.continueButton,
      )
      .first();

  await expect(
    modal,
    `${context}: el modal final debe abrirse.`,
  ).toBeVisible();

  await expect(
    modal,
  ).toHaveAttribute(
    'aria-hidden',
    'false',
  );

  await expectInsideViewport(
    body,
    `${context} — contenido del modal`,
  );

  await continueButton.scrollIntoViewIfNeeded();

  await expectInsideViewport(
    continueButton,
    `${context} — acción de continuar`,
  );

  await expect(
    continueButton,
    `${context}: el botón final debe estar habilitado.`,
  ).toBeEnabled();

  if (selectors.score) {
    const score =
      modal
        .locator(
          selectors.score,
        )
        .first();

    await expectInsideViewport(
      score,
      `${context} — puntaje`,
    );

    await expect(
      score,
    ).toContainText(
      '27 / 30',
    );
  }

  const modalOverflow =
    await modal.evaluate(
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

          overflowX:
            style.overflowX,
        };
      },
    );

  expect.soft(
    modalOverflow.horizontal,
    `${context}: el modal no debe producir desbordamiento horizontal interno.`,
  ).toBeFalsy();

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
| Casos
|--------------------------------------------------------------------------
*/

test.describe(
  'Adaptabilidad de los flujos conversacionales de lecciones y retos',
  () => {
    test(
      'adapta el flujo conversacional de una lección',
      async ({
        page,
      }, testInfo) => {
        const fixture =
          readFixtureData();

        await login(
          page,
          fixture.user,
        );

        await mockLessonConversation(
          page,
          fixture,
        );

        await page.goto(
          `/aprendizaje/leccion?id=${fixture.lessonId}`,
          {
            waitUntil:
              'domcontentloaded',
          },
        );

        const lessonInput =
          page
            .locator(
              '.lesson__input',
            )
            .first();

        await expect(
          lessonInput,
          'La micropráctica debe habilitar el campo de respuesta.',
        ).toBeEnabled();

        await expect(
          page.getByText(
            /Micropráctica:/i,
          ),
        ).toBeVisible();

        await expectConversationLayout(
          page,
          {
            root:
              '.lesson',

            messages:
              '.lesson__messages',

            composer:
              '.lesson__composer',

            input:
              '.lesson__input',

            send:
              '.lesson__sendBtn',
          },
          'Lección — micropráctica',
          true,
        );

        await attachScreenshot(
          page,
          testInfo,
          evidenceName(
            testInfo,
            'leccion-micropractica',
          ),
        );

        await lessonInput.fill(
          'Escucharía la situación, resumiría el problema y confirmaría '
          + 'con la otra persona que comprendí correctamente.',
        );

        await page
          .locator(
            '.lesson__sendBtn',
          )
          .click();

        await expect(
          page.getByText(
            /Minievaluación:/i,
          ),
        ).toBeVisible();

        await expect(
          lessonInput,
        ).toBeEnabled();

        await expectConversationLayout(
          page,
          {
            root:
              '.lesson',

            messages:
              '.lesson__messages',

            composer:
              '.lesson__composer',

            input:
              '.lesson__input',

            send:
              '.lesson__sendBtn',
          },
          'Lección — minievaluación',
          true,
        );

        await attachScreenshot(
          page,
          testInfo,
          evidenceName(
            testInfo,
            'leccion-minievaluacion',
          ),
        );

        await lessonInput.fill(
          'En una entrevista organizaría mi respuesta con una situación, '
          + 'la acción aplicada y el resultado obtenido.',
        );

        await page
          .locator(
            '.lesson__sendBtn',
          )
          .click();

        await expectResultModalAdaptive(
          page,
          {
            modal:
              '#sv-lesson-result-modal',

            body:
              '[data-sv-lesson-result-body]',

            continueButton:
              '[data-sv-lesson-result-continue]',
          },
          'Lección — resultado final',
        );

        await expectConversationLayout(
          page,
          {
            root:
              '.lesson',

            messages:
              '.lesson__messages',

            composer:
              '.lesson__composer',

            input:
              '.lesson__input',

            send:
              '.lesson__sendBtn',
          },
          'Lección — flujo completado',
          false,
        );

        await attachScreenshot(
          page,
          testInfo,
          evidenceName(
            testInfo,
            'leccion-resultado-final',
          ),
          false,
        );
      },
    );

    test(
      'adapta el flujo conversacional de un reto',
      async ({
        page,
      }, testInfo) => {
        const fixture =
          readFixtureData();

        await login(
          page,
          fixture.user,
        );

        await mockChallengeConversation(
          page,
          fixture,
        );

        await page.goto(
          `/retos/reto?id=${fixture.challengeId}`,
          {
            waitUntil:
              'domcontentloaded',
          },
        );

        const challengeInput =
          page
            .locator(
              '.challenge__input',
            )
            .first();

        await expect(
          challengeInput,
          'La consigna debe habilitar el campo de respuesta.',
        ).toBeEnabled();

        await expect(
          page.getByText(
            /Consigna del reto:/i,
          ),
        ).toBeVisible();

        await expectConversationLayout(
          page,
          {
            root:
              '.challenge',

            messages:
              '.challenge__messages',

            composer:
              '.challenge__composer',

            input:
              '.challenge__input',

            send:
              '.challenge__sendBtn',
          },
          'Reto — consigna activa',
          true,
        );

        await attachScreenshot(
          page,
          testInfo,
          evidenceName(
            testInfo,
            'reto-consigna',
          ),
        );

        await challengeInput.fill(
          'Hablaría con la persona y trataría de solucionar el problema.',
        );

        await page
          .locator(
            '.challenge__sendBtn',
          )
          .click();

        await expect(
          page.getByText(
            /La respuesta necesita más detalle/i,
          ),
        ).toBeVisible();

        await expect(
          challengeInput,
        ).toBeEnabled();

        await expectConversationLayout(
          page,
          {
            root:
              '.challenge',

            messages:
              '.challenge__messages',

            composer:
              '.challenge__composer',

            input:
              '.challenge__input',

            send:
              '.challenge__sendBtn',
          },
          'Reto — retroalimentación y reintento',
          true,
        );

        await attachScreenshot(
          page,
          testInfo,
          evidenceName(
            testInfo,
            'reto-reintento',
          ),
        );

        await challengeInput.fill(
          'Primero escucharía las dos versiones, resumiría el desacuerdo, '
          + 'propondría una acción verificable y confirmaría el compromiso '
          + 'de cada participante.',
        );

        await page
          .locator(
            '.challenge__sendBtn',
          )
          .click();

        await expectResultModalAdaptive(
          page,
          {
            modal:
              '#sv-challenge-result-modal',

            body:
              '[data-sv-challenge-result-body]',

            continueButton:
              '[data-sv-challenge-result-continue]',

            score:
              '[data-sv-challenge-result-score]',
          },
          'Reto — resultado final',
        );

        await expectConversationLayout(
          page,
          {
            root:
              '.challenge',

            messages:
              '.challenge__messages',

            composer:
              '.challenge__composer',

            input:
              '.challenge__input',

            send:
              '.challenge__sendBtn',
          },
          'Reto — flujo completado',
          false,
        );

        await attachScreenshot(
          page,
          testInfo,
          evidenceName(
            testInfo,
            'reto-resultado-final',
          ),
          false,
        );
      },
    );
  },
);