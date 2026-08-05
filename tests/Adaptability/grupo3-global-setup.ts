import {
  execFileSync,
} from 'node:child_process';

import {
  existsSync,
} from 'node:fs';

import path from 'node:path';

/**
 * Ejecuta uno de los fixtures PHP utilizados
 * por las pruebas del grupo 3.
 */
function ejecutarFixture(
  ruta: string,
  comando: 'reset' | 'cleanup',
): void {
  if (!existsSync(ruta)) {
    throw new Error(
      `No se encontró el fixture requerido: ${ruta}`,
    );
  }

  execFileSync(
    'php',
    [
      ruta,
      comando,
    ],
    {
      stdio:
        'inherit',
    },
  );
}

/**
 * Prepara los usuarios y registros controlados
 * antes de ejecutar las pruebas del grupo 3.
 */
export default function globalSetup(): void {
  const authFixture =
    path.resolve(
      process.cwd(),
      'tests',
      'E2E',
      'helpers',
      'auth-fixtures.php',
    );

  const grupo3Fixture =
    path.resolve(
      process.cwd(),
      'tests',
      'Adaptability',
      'helpers',
      'grupo3-fixtures.php',
    );

  console.log(
    '\nPreparando usuarios de autenticación...',
  );

  ejecutarFixture(
    authFixture,
    'reset',
  );

  console.log(
    'Preparando tarjetas, filtros y registros paginados...',
  );

  ejecutarFixture(
    grupo3Fixture,
    'reset',
  );

  console.log(
    'Datos del grupo 3 preparados correctamente.\n',
  );
}