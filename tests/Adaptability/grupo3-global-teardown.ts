import {
  execFileSync,
} from 'node:child_process';

import path from 'node:path';

function ejecutarFixture(
  ruta: string,
  comando: 'reset' | 'cleanup',
): void {
  execFileSync(
    'php',
    [
      ruta,
      comando,
    ],
    {
      stdio: 'inherit',
    },
  );
}

export default function globalTeardown(): void {
  const authFixture =
    path.resolve(
      'tests',
      'E2E',
      'helpers',
      'auth-fixtures.php',
    );

  const grupo3Fixture =
    path.resolve(
      'tests',
      'Adaptability',
      'helpers',
      'grupo3-fixtures.php',
    );

  ejecutarFixture(
    grupo3Fixture,
    'cleanup',
  );

  ejecutarFixture(
    authFixture,
    'cleanup',
  );
}