import { expect, test } from '@playwright/test';

test.describe('Disponibilidad inicial de SkillView', () => {
  test('carga la página de inicio del sistema', async ({ page }) => {
    const response = await page.goto('/');

    /*
     * Comprueba que el servidor respondió
     * satisfactoriamente.
     */
    expect(response).not.toBeNull();
    expect(response?.ok()).toBeTruthy();

    /*
     * Verifica que el navegador recibió y presentó
     * contenido HTML visible.
     */
    await expect(page.locator('body')).toBeVisible();

    await expect(page.locator('body')).not.toBeEmpty();
  });
});