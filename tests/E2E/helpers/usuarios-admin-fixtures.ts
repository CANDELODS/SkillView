import { execFileSync } from 'node:child_process';
import path from 'node:path';

const scriptPath = path.resolve(
  process.cwd(),
  'tests',
  'E2E',
  'helpers',
  'usuarios-admin-fixtures.php',
);

export type AdminUserFixtureIds = {
  admin: number;
  editable: number;
  existing: number;
};

export const ADMIN_USER_DATA = {
  admin: {
    email: 'e2e_gestion_admin@skillview.test',
    password: 'AdminCaja1!',
  },

  editable: {
    email: 'e2e_gestion_editable@skillview.test',
    password: 'UsuarioCaja1!',
    temporaryPassword: 'TemporalCaja2!',
  },

  existing: {
    email: 'e2e_gestion_existente@skillview.test',
  },
} as const;

/**
 * Restaura las cuentas controladas antes
 * de ejecutar cada caso.
 */
export function resetAdminUserFixtures():
AdminUserFixtureIds {
  const output = execFileSync(
    'php',
    [
      scriptPath,
      'reset',
    ],
    {
      cwd: process.cwd(),
      encoding: 'utf8',
      stdio: [
        'ignore',
        'pipe',
        'pipe',
      ],
    },
  );

  return JSON.parse(
    output.trim(),
  ) as AdminUserFixtureIds;
}

/**
 * Elimina únicamente los registros temporales
 * utilizados por Playwright.
 */
export function cleanupAdminUserFixtures(): void {
  execFileSync(
    'php',
    [
      scriptPath,
      'cleanup',
    ],
    {
      cwd: process.cwd(),
      encoding: 'utf8',
      stdio: 'pipe',
    },
  );
}