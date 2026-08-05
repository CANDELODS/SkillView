import {
  execFileSync,
} from 'node:child_process';

import {
  existsSync,
} from 'node:fs';

import path from 'node:path';

/**
 * Elimina los usuarios controlados cuando termina
 * la ejecución completa de los cinco proyectos.
 */
export default function globalTeardown(): void {
  const fixturePath =
    path.resolve(
      'tests',
      'E2E',
      'helpers',
      'auth-fixtures.php',
    );

  if (!existsSync(fixturePath)) {
    console.warn(
      'No se ejecutó la limpieza porque no se encontró: '
      + fixturePath,
    );

    return;
  }

  console.log(
    '\nLimpiando usuarios de adaptabilidad...',
  );

  execFileSync(
    'php',
    [
      fixturePath,
      'cleanup',
    ],
    {
      stdio:
        'inherit',
    },
  );
}