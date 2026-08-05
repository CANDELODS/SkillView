import { defineConfig } from '@playwright/test';
import baseConfig from './playwright.adaptability.config';

export default defineConfig({
  ...baseConfig,

  testDir:
    './tests/Adaptability',

  testMatch:
    'flujos-conversacionales.spec.ts',

  timeout:
    120_000,

  expect: {
    ...baseConfig.expect,

    timeout:
      20_000,
  },

  globalSetup:
    './tests/Adaptability/setup/grupo4-global-setup.ts',

  globalTeardown:
    './tests/Adaptability/setup/grupo4-global-teardown.ts',

  outputDir:
    'test-results/adaptabilidad/grupo-4',

  reporter: [
    [
      'html',
      {
        outputFolder:
          'reports/adaptabilidad/grupo-4',

        open:
          'never',
      },
    ],

    [
      'list',
    ],
  ],
});