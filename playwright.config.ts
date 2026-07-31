import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  /*
   * Carpeta donde se almacenarán las pruebas
   * funcionales de caja negra.
   */
  testDir: './tests/E2E',

  /*
   * Las pruebas no se ejecutarán en paralelo porque
   * compartirán la base de datos skillview_test.
   */
  fullyParallel: false,
  workers: 1,

  /*
   * Tiempo máximo permitido para cada caso.
   */
  timeout: 30_000,

  expect: {
    timeout: 5_000,
  },

  /*
   * Impide dejar accidentalmente test.only()
   * cuando las pruebas se ejecuten en CI.
   */
  forbidOnly: !!process.env.CI,

  /*
   * No se repetirán los casos localmente.
   * En integración continua se permitirán dos reintentos.
   */
  retries: process.env.CI ? 2 : 0,

  /*
   * Se mostrará el resultado en la terminal y también
   * se generará un informe HTML.
   */
  reporter: [
    ['list'],
    ['html', {
      outputFolder: 'playwright-report',
      open: 'never',
    }],
  ],

  use: {
    /*
     * Permite usar rutas relativas:
     * page.goto('/');
     * page.goto('/registro');
     */
    baseURL: 'http://localhost:3000',

    /*
     * Evidencias conservadas cuando una prueba falla.
     */
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'retain-on-failure',

    /*
     * Tiempo máximo para operaciones como page.goto().
     */
    navigationTimeout: 15_000,
    actionTimeout: 10_000,
  },

  /*
   * Inicialmente se utilizará solo Chromium.
   * Firefox y WebKit se reservarán para las pruebas
   * posteriores de adaptabilidad y compatibilidad.
   */
  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
      },
    },
  ],

  /*
   * Inicia el servidor PHP desde la raíz de SkillView.
   *
   * "-t public" equivale a:
   * cd public
   * php -S localhost:3000
   */
  webServer: {
    command: 'php -S localhost:3000 -t public',
    url: 'http://localhost:3000',
    reuseExistingServer: true,
    timeout: 120_000,
  },
});