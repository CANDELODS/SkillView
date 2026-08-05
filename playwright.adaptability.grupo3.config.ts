import {
  defineConfig,
} from '@playwright/test';

import baseConfig
  from './playwright.adaptability.config';

export default defineConfig({
  ...baseConfig,

  testDir:
    './tests/Adaptability',

  testMatch:
    'tarjetas-filtros-paginacion.spec.ts',

  timeout:
    120_000,

  globalSetup:
    './tests/Adaptability/grupo3-global-setup.ts',

  globalTeardown:
    './tests/Adaptability/grupo3-global-teardown.ts',

  reporter: [
    [
      'list',
    ],
    [
      'html',
      {
        outputFolder:
          'reports/adaptabilidad/grupo-3',

        open:
          'never',
      },
    ],
  ],

  outputDir:
    'test-results/adaptabilidad/grupo-3',
});