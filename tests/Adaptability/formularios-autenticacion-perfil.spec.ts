import {
    expect,
    test,
    type Locator,
    type Page,
    type TestInfo,
} from '@playwright/test';

/*
|--------------------------------------------------------------------------
| Usuarios controlados
|--------------------------------------------------------------------------
*/

const GENERAL_USER = {
    email:
        process.env.ADAPT_USER_EMAIL
        ?? 'e2e_auth_general@skillview.test',

    password:
        process.env.ADAPT_USER_PASSWORD
        ?? 'CajaNegra1!',
};

const TEMPORARY_USER = {
    email:
        process.env.ADAPT_TEMP_EMAIL
        ?? 'e2e_auth_temporal@skillview.test',

    password:
        process.env.ADAPT_TEMP_PASSWORD
        ?? 'Temporal1!',
};

/*
|--------------------------------------------------------------------------
| Tipos
|--------------------------------------------------------------------------
*/

type FormCheckOptions = {
    name: string;
    form: Locator;
    expectedFields: string[];
    evidenceName: string;
    page: Page;
    testInfo: TestInfo;
    container?: Locator;
    fullPageScreenshot?: boolean;
};

/*
|--------------------------------------------------------------------------
| Autenticación
|--------------------------------------------------------------------------
*/

/**
 * Inicia sesión con el usuario general y espera
 * la redirección hacia la página principal.
 */
async function loginGeneral(
    page: Page,
): Promise<void> {
    await page.goto(
        '/',
        {
            waitUntil:
                'domcontentloaded',
        },
    );

    const form =
        page.locator(
            'form:visible',
        ).first();

    await expect(
        form,
        'El formulario de inicio de sesión debe estar visible.',
    ).toBeVisible();

    await form
        .locator(
            'input[name="correo"]',
        )
        .fill(
            GENERAL_USER.email,
        );

    await form
        .locator(
            'input[name="password"]',
        )
        .fill(
            GENERAL_USER.password,
        );

    const submit =
        getSubmitControl(
            form,
        );

    await expect(
        submit,
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

/**
 * Inicia sesión con la contraseña temporal y espera
 * la redirección al cambio obligatorio de contraseña.
 */
async function loginTemporary(
    page: Page,
): Promise<void> {
    await page.goto(
        '/',
        {
            waitUntil:
                'domcontentloaded',
        },
    );

    const form =
        page.locator(
            'form:visible',
        ).first();

    await expect(
        form,
    ).toBeVisible();

    await form
        .locator(
            'input[name="correo"]',
        )
        .fill(
            TEMPORARY_USER.email,
        );

    await form
        .locator(
            'input[name="password"]',
        )
        .fill(
            TEMPORARY_USER.password,
        );

    const submit =
        getSubmitControl(
            form,
        );

    await expect(
        submit,
    ).toBeVisible();

    await Promise.all([
        page.waitForURL(
            /\/cambiar-password$/,
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
        /\/cambiar-password$/,
    );
}

/*
|--------------------------------------------------------------------------
| Selectores generales
|--------------------------------------------------------------------------
*/

/**
 * Obtiene el control principal de envío de un formulario.
 */
function getSubmitControl(
    form: Locator,
): Locator {
    return form
        .locator(
            [
                'button[type="submit"]:visible',
                'input[type="submit"]:visible',
            ].join(', '),
        )
        .first();
}

/**
 * Obtiene todos los controles interactivos visibles.
 */
function getVisibleControls(
    form: Locator,
): Locator {
    return form.locator(
        [
            'input:not([type="hidden"]):visible',
            'select:visible',
            'textarea:visible',
            'button:visible',
            'a:visible',
        ].join(', '),
    );
}

/**
 * Busca el contenedor visual principal de un formulario público.
 */
function getPublicFormContainer(
    page: Page,
    form: Locator,
): Locator {
    const knownContainer =
        page.locator(
            [
                '.login__card:visible',
                '.register__card:visible',
                '.login:visible form:visible',
                '.register:visible form:visible',
            ].join(', '),
        ).first();

    return knownContainer.or(
        form,
    ).first();
}

/*
|--------------------------------------------------------------------------
| Comprobaciones de adaptabilidad
|--------------------------------------------------------------------------
*/

/**
 * Comprueba que la página no produzca desplazamiento
 * horizontal inesperado.
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
                    viewportWidth:
                        root.clientWidth,

                    documentWidth:
                        Math.max(
                            root.scrollWidth,
                            body?.scrollWidth ?? 0,
                        ),
                };
            },
        );

    expect.soft(
        dimensions.documentWidth,
        `${context}: el documento no debe superar el ancho disponible.`,
    ).toBeLessThanOrEqual(
        dimensions.viewportWidth + 1,
    );
}

/**
 * Comprueba que un elemento permanezca dentro
 * del área horizontal visible.
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
        `${context}: el ancho debe ser mayor que cero.`,
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

/**
 * Comprueba que un elemento pueda alcanzarse
 * verticalmente mediante desplazamiento.
 */
async function expectReachableVertically(
    locator: Locator,
    context: string,
): Promise<void> {
    await expect(
        locator,
        `${context}: el control debe estar visible.`,
    ).toBeVisible();

    await locator.scrollIntoViewIfNeeded();

    const geometry =
        await locator.evaluate(
            (element) => {
                const rect =
                    element.getBoundingClientRect();

                return {
                    top:
                        rect.top,

                    bottom:
                        rect.bottom,

                    height:
                        rect.height,

                    viewportHeight:
                        window.innerHeight,
                };
            },
        );

    expect.soft(
        geometry.height,
        `${context}: el control debe conservar una altura visible.`,
    ).toBeGreaterThan(
        0,
    );

    expect.soft(
        geometry.top,
        `${context}: debe poder alcanzarse desde la parte superior.`,
    ).toBeGreaterThanOrEqual(
        -1,
    );

    expect.soft(
        geometry.bottom,
        `${context}: debe poder alcanzarse dentro del alto visible.`,
    ).toBeLessThanOrEqual(
        geometry.viewportHeight + 1,
    );
}

/**
 * Comprueba que un campo pueda recibir foco.
 */
async function expectFieldCanReceiveFocus(
    field: Locator,
    context: string,
): Promise<void> {
    await expect(
        field,
        `${context}: el campo debe estar visible.`,
    ).toBeVisible();

    await field.scrollIntoViewIfNeeded();
    await field.focus();

    await expect(
        field,
        `${context}: el campo debe poder recibir foco.`,
    ).toBeFocused();
}

/**
 * Verifica que todos los controles visibles tengan
 * dimensiones válidas y permanezcan dentro del viewport.
 */
async function expectControlsInsideViewport(
    form: Locator,
    context: string,
): Promise<void> {
    const controls =
        getVisibleControls(
            form,
        );

    const total =
        await controls.count();

    expect(
        total,
        `${context}: debe existir al menos un control visible.`,
    ).toBeGreaterThan(
        0,
    );

    for (
        let index = 0;
        index < total;
        index += 1
    ) {
        const control =
            controls.nth(
                index,
            );

        await expectInsideViewportHorizontally(
            control,
            `${context} — control ${index + 1}`,
        );
    }
}

/**
 * Verifica la separación básica entre campos
 * consecutivos del formulario.
 *
 * Los campos pueden estar en columnas diferentes,
 * por lo que únicamente se considera superposición
 * cuando sus áreas se intersectan de forma significativa.
 */
async function expectNoSignificantControlOverlap(
    form: Locator,
    context: string,
): Promise<void> {
    const controls =
        getVisibleControls(
            form,
        );

    const rectangles =
        await controls.evaluateAll(
            (elements) =>
                elements.map(
                    (element) => {
                        const rect =
                            element.getBoundingClientRect();

                        return {
                            tag:
                                element.tagName,

                            type:
                                element instanceof HTMLInputElement
                                    ? element.type
                                    : '',

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
                ),
        );

    for (
        let first = 0;
        first < rectangles.length;
        first += 1
    ) {
        for (
            let second = first + 1;
            second < rectangles.length;
            second += 1
        ) {
            const a =
                rectangles[first];

            const b =
                rectangles[second];

            /*
             * Se ignoran intersecciones pequeñas entre controles
             * inline, enlaces, checkboxes y botones próximos.
             */
            if (
                a.type === 'checkbox'
                || b.type === 'checkbox'
                || a.tag === 'A'
                || b.tag === 'A'
            ) {
                continue;
            }

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
                    ? intersectionArea / smallerArea
                    : 0;

            expect.soft(
                overlapRatio,
                `${context}: los controles ${first + 1} y ${second + 1} no deben superponerse.`,
            ).toBeLessThan(
                0.25,
            );
        }
    }
}

/**
 * Comprueba la adaptabilidad general de un formulario.
 */
async function checkAdaptiveForm(
    options: FormCheckOptions,
): Promise<void> {
    const {
        name,
        form,
        expectedFields,
        evidenceName,
        page,
        testInfo,
        container = form,
        fullPageScreenshot = true,
    } = options;

    await expect(
        form,
        `${name}: el formulario debe estar visible.`,
    ).toBeVisible();

    await expectInsideViewportHorizontally(
        container,
        `${name} — contenedor`,
    );

    await expectInsideViewportHorizontally(
        form,
        `${name} — formulario`,
    );

    await expectNoHorizontalOverflow(
        page,
        name,
    );

    for (
        const fieldName
        of expectedFields
    ) {
        const field =
            form
                .locator(
                    `[name="${fieldName}"]:visible`,
                )
                .first();

        await expect(
            field,
            `${name}: debe existir el campo ${fieldName}.`,
        ).toBeVisible();

        await expectInsideViewportHorizontally(
            field,
            `${name} — campo ${fieldName}`,
        );
    }

    await expectControlsInsideViewport(
        form,
        name,
    );

    await expectNoSignificantControlOverlap(
        form,
        name,
    );

    const firstEditableField =
        form
            .locator(
                [
                    'input:not([type="hidden"]):not([type="submit"]):not([type="checkbox"]):visible',
                    'select:visible',
                    'textarea:visible',
                ].join(', '),
            )
            .first();

    if (
        await firstEditableField.count()
        > 0
    ) {
        await expectFieldCanReceiveFocus(
            firstEditableField,
            `${name} — primer campo editable`,
        );
    }

    const submit =
        getSubmitControl(
            form,
        );

    await expect(
        submit,
        `${name}: debe existir un control de envío.`,
    ).toBeVisible();

    await expectReachableVertically(
        submit,
        `${name} — control de envío`,
    );

    const lastControl =
        getVisibleControls(
            form,
        ).last();

    await expectReachableVertically(
        lastControl,
        `${name} — último control`,
    );

    await attachScreenshot(
        page,
        testInfo,
        evidenceName,
        fullPageScreenshot,
    );
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

/**
 * Construye el nombre de la evidencia según
 * el proyecto que se está ejecutando.
 */
function evidenceName(
    testInfo: TestInfo,
    formName: string,
): string {
    const metadata =
        testInfo.project.metadata as {
            evidencia?: string;
        };

    const projectName =
        metadata.evidencia
        ?? testInfo.project.name;

    return `${projectName}-${formName}`;
}

/*
|--------------------------------------------------------------------------
| Modal del perfil
|--------------------------------------------------------------------------
*/

/**
 * Obtiene el botón visible encargado de abrir
 * la edición del perfil.
 */
function getProfileEditTrigger(
    page: Page,
): Locator {
    return page
        .locator(
            [
                '[data-profile-modal-open]:visible',
                '[data-profile-open]:visible',
                '.profile__edit-button:visible',
                '.profile__edit:visible',
                '.profile-card__edit:visible',
                'button:has-text("Editar perfil"):visible',
                'a:has-text("Editar perfil"):visible',
                'button:has-text("Editar Perfil"):visible',
                'a:has-text("Editar Perfil"):visible',
            ].join(', '),
        )
        .first();
}

/**
 * Obtiene el modal visible del perfil.
 */
function getVisibleProfileModal(
    page: Page,
): Locator {
    return page
        .locator(
            [
                '.profile-modal.profile-modal--visible',
                '.profile-modal[aria-hidden="false"]',
                '.profile-modal:visible',
            ].join(', '),
        )
        .first();
}

/**
 * Cierra el modal de edición del perfil sin enviar cambios.
 *
 * Se priorizan los botones reales del modal y se excluye
 * explícitamente el backdrop, ya que este puede encontrarse
 * cubierto por el contenido del formulario.
 *
 * Si no existe un botón visible, se utiliza Escape, acción
 * admitida por el comportamiento del modal de SkillView.
 */
async function closeProfileModal(
    page: Page,
    modal: Locator,
): Promise<void> {
    const closeControl =
        modal
            .locator(
                [
                    /*
                     * Botones o controles de cierre reales.
                     */
                    'button.profile-modal__close:visible',
                    '.profile-modal__close:visible',

                    /*
                     * Excluir el fondo del modal, aunque posea
                     * el mismo atributo de cierre.
                     */
                    '[data-profile-modal-close]:not(.profile-modal__backdrop):visible',
                    '[data-profile-close]:not(.profile-modal__backdrop):visible',

                    /*
                     * Acciones textuales disponibles.
                     */
                    'button:has-text("Cancelar"):visible',
                    'button:has-text("Cerrar"):visible',
                    'a:has-text("Cancelar"):visible',
                    'a:has-text("Cerrar"):visible',
                ].join(', '),
            )
            .first();

    const hasVisibleCloseControl =
        await closeControl
            .isVisible()
            .catch(
                () => false,
            );

    if (hasVisibleCloseControl) {
        await closeControl.click({
            timeout: 5_000,
        });
    } else {
        /*
         * El uso de Escape evita intentar hacer clic
         * sobre el backdrop cubierto por el formulario.
         */
        await page.keyboard.press(
            'Escape',
        );
    }

    await expect(
        modal,
        'El modal del perfil debe cerrarse sin guardar cambios.',
    ).toBeHidden({
        timeout: 5_000,
    });
}

/*
|--------------------------------------------------------------------------
| Casos de prueba
|--------------------------------------------------------------------------
*/

test.describe(
    'Adaptabilidad de los formularios de autenticación y perfil',
    () => {
        test(
            'adapta el formulario de inicio de sesión',
            async ({
                page,
            }, testInfo) => {
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

                const container =
                    getPublicFormContainer(
                        page,
                        form,
                    );

                await checkAdaptiveForm({
                    name:
                        'Inicio de sesión',

                    form,

                    container,

                    expectedFields: [
                        'correo',
                        'password',
                    ],

                    evidenceName:
                        evidenceName(
                            testInfo,
                            'inicio-sesion',
                        ),

                    page,

                    testInfo,
                });
            },
        );

        test(
            'adapta el formulario de registro',
            async ({
                page,
            }, testInfo) => {
                await page.goto(
                    '/registro',
                    {
                        waitUntil:
                            'domcontentloaded',
                    },
                );

                await expect(
                    page,
                ).toHaveURL(
                    /\/registro$/,
                );

                const form =
                    page
                        .locator(
                            'form:visible',
                        )
                        .first();

                const container =
                    getPublicFormContainer(
                        page,
                        form,
                    );

                await checkAdaptiveForm({
                    name:
                        'Registro de usuarios',

                    form,

                    container,

                    expectedFields: [
                        'nombres',
                        'apellidos',
                        'edad',
                        'sexo',
                        'universidad',
                        'carrera',
                        'correo',
                        'password',
                        'password2',
                        'autoriza_tratamiento_datos',
                    ],

                    evidenceName:
                        evidenceName(
                            testInfo,
                            'registro',
                        ),

                    page,

                    testInfo,
                });
            },
        );

        test(
            'adapta el formulario de recuperación de contraseña',
            async ({
                page,
            }, testInfo) => {
                await page.goto(
                    '/recuperar-password',
                    {
                        waitUntil:
                            'domcontentloaded',
                    },
                );

                await expect(
                    page,
                ).toHaveURL(
                    /\/recuperar-password$/,
                );

                const form =
                    page
                        .locator(
                            'form:visible',
                        )
                        .first();

                const container =
                    getPublicFormContainer(
                        page,
                        form,
                    );

                await checkAdaptiveForm({
                    name:
                        'Recuperación de contraseña',

                    form,

                    container,

                    expectedFields: [
                        'correo',
                    ],

                    evidenceName:
                        evidenceName(
                            testInfo,
                            'recuperar-password',
                        ),

                    page,

                    testInfo,
                });
            },
        );

        test(
            'adapta el formulario de cambio obligatorio de contraseña',
            async ({
                page,
            }, testInfo) => {
                await loginTemporary(
                    page,
                );

                const form =
                    page
                        .locator(
                            'form:visible',
                        )
                        .first();

                const container =
                    getPublicFormContainer(
                        page,
                        form,
                    );

                await checkAdaptiveForm({
                    name:
                        'Cambio obligatorio de contraseña',

                    form,

                    container,

                    expectedFields: [
                        'password',
                        'password2',
                    ],

                    evidenceName:
                        evidenceName(
                            testInfo,
                            'cambiar-password',
                        ),

                    page,

                    testInfo,
                });
            },
        );

        test(
            'adapta el formulario de edición del perfil',
            async ({
                page,
            }, testInfo) => {
                await loginGeneral(
                    page,
                );

                await page.goto(
                    '/perfil',
                    {
                        waitUntil:
                            'domcontentloaded',
                    },
                );

                await expect(
                    page,
                ).toHaveURL(
                    /\/perfil$/,
                );

                const editTrigger =
                    getProfileEditTrigger(
                        page,
                    );

                await expect(
                    editTrigger,
                    'Debe existir un control visible para editar el perfil.',
                ).toBeVisible();

                await editTrigger.click();

                const modal =
                    getVisibleProfileModal(
                        page,
                    );

                await expect(
                    modal,
                    'El modal de edición del perfil debe abrirse.',
                ).toBeVisible();

                await expect(
                    modal,
                ).toHaveClass(
                    /profile-modal--visible/,
                );

                const form =
                    modal
                        .locator(
                            'form:visible',
                        )
                        .first();

                const modalContent =
                    modal
                        .locator(
                            [
                                '.profile-modal__content:visible',
                                '.profile-modal__dialog:visible',
                                '.profile-modal__card:visible',
                                'form:visible',
                            ].join(', '),
                        )
                        .first();

                await checkAdaptiveForm({
                    name:
                        'Edición del perfil',

                    form,

                    container:
                        modalContent,

                    expectedFields: [
                        'nombres',
                        'apellidos',
                        'universidad',
                        'carrera',
                    ],

                    evidenceName:
                        evidenceName(
                            testInfo,
                            'edicion-perfil',
                        ),

                    page,

                    testInfo,

                    /*
                     * En los modales es preferible capturar solo
                     * el viewport actual para mostrar el estado abierto.
                     */
                    fullPageScreenshot:
                        false,
                });

                await closeProfileModal(
                    page,
                    modal,
                );
            },
        );
    },
);