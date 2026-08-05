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
    throw new Error(
      `No se encontró el fixture: ${ruta}`,
    );
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

export default function globalSetup(): void {
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
    authFixture,
    'reset',
  );

  ejecutarFixture(
    groupFixture,
    'setup',
  );
}