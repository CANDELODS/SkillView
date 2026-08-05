import {
  defineConfig,
} from '@playwright/test';

/**
 * Configuración exclusiva para las pruebas
 * no funcionales de adaptabilidad de SkillView.
 */
export default defineConfig({
  testDir:
    './tests/Adaptability',

  /*
   * Los proyectos se ejecutan secuencialmente para:
   *
   * - evitar sobrecargar el servidor PHP;
   * - mantener ordenadas las capturas;
   * - reutilizar de manera segura el usuario E2E.
   */
  fullyParallel:
    false,

  workers:
    1,

  timeout:
    90_000,

  expect: {
    timeout:
      10_000,
  },

  globalSetup:
    './tests/Adaptability/global-setup.ts',

  globalTeardown:
    './tests/Adaptability/global-teardown.ts',

  reporter: [
    [
      'list',
    ],
    [
      'html',
      {
        outputFolder:
          'reports/adaptabilidad/grupo-1',

        open:
          'never',
      },
    ],
  ],

  outputDir:
    'test-results/adaptabilidad/grupo-1',

  use: {
    baseURL:
      'http://127.0.0.1:3000',

    /*
     * Estas evidencias automáticas se conservarán
     * cuando ocurra un fallo.
     */
    trace:
      'retain-on-failure',

    screenshot:
      'only-on-failure',

    video:
      'retain-on-failure',

    locale:
      'es-CO',

    timezoneId:
      'America/Bogota',

    deviceScaleFactor:
      1,
  },

  projects: [
    {
      name:
        'chromium-movil',

      metadata: {
        layout:
          'compacto',

        evidencia:
          'movil-chromium',
      },

      use: {
        browserName:
          'chromium',

        viewport: {
          width:
            390,

          height:
            844,
        },
      },
    },
    {
      name:
        'chromium-tableta',

      metadata: {
        layout:
          'compacto',

        evidencia:
          'tableta-chromium',
      },

      use: {
        browserName:
          'chromium',

        viewport: {
          width:
            768,

          height:
            1024,
        },
      },
    },
    {
      name:
        'chromium-escritorio',

      metadata: {
        layout:
          'escritorio',

        evidencia:
          'escritorio-chromium',
      },

      use: {
        browserName:
          'chromium',

        viewport: {
          width:
            1366,

          height:
            768,
        },
      },
    },
    {
      name:
        'firefox-escritorio',

      metadata: {
        layout:
          'escritorio',

        evidencia:
          'escritorio-firefox',
      },

      use: {
        browserName:
          'firefox',

        viewport: {
          width:
            1366,

          height:
            768,
        },
      },
    },
    {
      name:
        'webkit-escritorio',

      metadata: {
        layout:
          'escritorio',

        evidencia:
          'escritorio-webkit',
      },

      use: {
        browserName:
          'webkit',

        viewport: {
          width:
            1366,

          height:
            768,
        },
      },
    },
  ],

  webServer: {
    command:
      'php -S 127.0.0.1:3000 -t public',

    url:
      'http://127.0.0.1:3000',

    reuseExistingServer:
      true,

    timeout:
      120_000,
  },
});