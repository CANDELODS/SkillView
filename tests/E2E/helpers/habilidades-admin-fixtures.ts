import { execFileSync } from 'node:child_process';
import path from 'node:path';

const scriptPath = path.resolve(
  process.cwd(),
  'tests',
  'E2E',
  'helpers',
  'habilidades-admin-fixtures.php',
);

export type AdminSkillFixtureIds = {
  admin: number;
  editable: number;
  deletable: number;
};

export const ADMIN_SKILL_DATA = {
  admin: {
    email:
      'e2e_habilidades_admin@skillview.test',

    password:
      'AdminCaja1!',
  },

  editable: {
    name:
      'Habilidad Editable E2E',

    marker:
      'E2E-Control-Habilidades',
  },

  created: {
    name:
      'Habilidad Creada E2E',
  },

  deletable: {
    name:
      'Habilidad Eliminable E2E',
  },
} as const;

/**
 * Restaura las precondiciones antes
 * de ejecutar cada caso.
 */
export function resetAdminSkillFixtures():
AdminSkillFixtureIds {
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
  ) as AdminSkillFixtureIds;
}

/**
 * Elimina únicamente los registros temporales
 * generados por las pruebas automatizadas.
 */
export function cleanupAdminSkillFixtures(): void {
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