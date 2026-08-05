import {
  execFileSync,
} from 'node:child_process';

import {
  existsSync,
} from 'node:fs';

import path from 'node:path';

/**
 * Prepara los usuarios controlados antes de ejecutar
 * las pruebas de adaptabilidad.
 */
export default function globalSetup(): void {
  const fixturePath =
    path.resolve(
      'tests',
      'E2E',
      'helpers',
      'auth-fixtures.php',
    );

  if (!existsSync(fixturePath)) {
    throw new Error(
      'No se encontró el helper de autenticación en: '
      + fixturePath,
    );
  }

  console.log(
    '\nPreparando usuarios para adaptabilidad...',
  );

  execFileSync(
    'php',
    [
      fixturePath,
      'reset',
    ],
    {
      stdio:
        'inherit',
    },
  );
}