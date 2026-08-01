import { execFileSync } from 'node:child_process';
import path from 'node:path';

const scriptPath = path.resolve(
  process.cwd(),
  'tests',
  'E2E',
  'helpers',
  'auth-fixtures.php',
);

export const AUTH_USERS = {
  general: {
    email: 'e2e_auth_general@skillview.test',
    password: 'CajaNegra1!',
  },

  admin: {
    email: 'e2e_auth_admin@skillview.test',
    password: 'CajaNegra1!',
  },

  disabled: {
    email: 'e2e_auth_deshabilitado@skillview.test',
    password: 'CajaNegra1!',
  },

  temporary: {
    email: 'e2e_auth_temporal@skillview.test',
    password: 'Temporal1!',
    newPassword: 'NuevaClave2!',
  },

  disabledTemporary: {
    email: 'e2e_auth_bloqueado@skillview.test',
    password: 'Temporal1!',
  },

  nonexistent: {
    email: 'e2e_auth_inexistente@skillview.test',
    password: 'CajaNegra1!',
  },
} as const;

/**
 * Ejecuta el script PHP que prepara o elimina
 * las cuentas utilizadas por Playwright.
 */
function executeFixture(
  command: 'reset' | 'cleanup',
): void {
  execFileSync(
    'php',
    [scriptPath, command],
    {
      cwd: process.cwd(),
      encoding: 'utf8',
      stdio: 'pipe',
    },
  );
}

export function resetAuthFixtures(): void {
  executeFixture('reset');
}

export function cleanupAuthFixtures(): void {
  executeFixture('cleanup');
}