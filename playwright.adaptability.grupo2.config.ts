import {
  defineConfig,
} from '@playwright/test';

import baseConfig
  from './playwright.adaptability.config';

/**
 * Reutiliza los cinco proyectos, el servidor PHP,
 * el global setup y el global teardown definidos
 * para las pruebas de adaptabilidad.
 */
export default defineConfig({
  ...baseConfig,

  testDir:
    './tests/Adaptability',

  testMatch:
    'formularios-autenticacion-perfil.spec.ts',

  timeout:
    90_000,

  reporter: [
    [
      'list',
    ],
    [
      'html',
      {
        outputFolder:
          'reports/adaptabilidad/grupo-2',

        open:
          'never',
      },
    ],
  ],

  outputDir:
    'test-results/adaptabilidad/grupo-2',
});