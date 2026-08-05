import {
  execFileSync,
} from 'node:child_process';

import {
  existsSync,
} from 'node:fs';

import path from 'node:path';

function ejecutarFixture(
  ruta: string,
  comando: string,
): void {
  if (!existsSync(ruta)) {
    console.warn(
      `No se encontró el fixture para limpiar: ${ruta}`,
    );

    return;
  }

  execFileSync(
    'php',
    [
      ruta,
      comando,
    ],
    {
      cwd:
        process.cwd(),

      stdio:
        'inherit',
    },
  );
}

export default function globalTeardown(): void {
  const authFixture =
    path.resolve(
      process.cwd(),
      'tests',
      'E2E',
      'helpers',
      'auth-fixtures.php',
    );

  const groupFixture =
    path.resolve(
      process.cwd(),
      'tests',
      'Adaptability',
      'helpers',
      'grupo5-fixtures.php',
    );

  ejecutarFixture(
    groupFixture,
    'cleanup',
  );

  ejecutarFixture(
    authFixture,
    'cleanup',
  );
}