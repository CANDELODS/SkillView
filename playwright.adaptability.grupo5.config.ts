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
    'modales-tablas-progreso-logros.spec.ts',

  timeout:
    120_000,

  expect: {
    ...baseConfig.expect,

    timeout:
      20_000,
  },

  globalSetup:
    './tests/Adaptability/setup/grupo5-global-setup.ts',

  globalTeardown:
    './tests/Adaptability/setup/grupo5-global-teardown.ts',

  outputDir:
    'test-results/adaptabilidad/grupo-5',

  reporter: [
    [
      'list',
    ],

    [
      'html',
      {
        outputFolder:
          'reports/adaptabilidad/grupo-5',

        open:
          'never',
      },
    ],
  ],
});