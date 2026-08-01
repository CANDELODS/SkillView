import { execFileSync } from 'node:child_process';
import path from 'node:path';

const scriptPath = path.resolve(
  process.cwd(),
  'tests',
  'E2E',
  'helpers',
  'registro-fixtures.php',
);

export const REGISTRATION_USERS = {
  newUser: {
    email: 'e2e_registro_nuevo@skillview.test',
  },

  existingUser: {
    email: 'e2e_registro_duplicado@skillview.test',
  },
} as const;

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

export function resetRegistrationFixtures(): void {
  executeFixture('reset');
}

export function cleanupRegistrationFixtures(): void {
  executeFixture('cleanup');
}