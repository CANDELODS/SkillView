import { execFileSync } from 'node:child_process';
import path from 'node:path';

const scriptPath = path.resolve(
  process.cwd(),
  'tests',
  'E2E',
  'helpers',
  'navegacion-filtros-fixtures.php',
);

export type NavigationFixtureIds = {
  general: number;
  admin: number;
  skillCommunication: number;
  skillLeadership: number;
  challengeBasic: number;
  challengeAdvanced: number;
  challengeIntermediate: number;
  challengeDisabled: number;
  blogCommunication: number;
  blogLeadership: number;
  blogShared: number;
  blogDisabled: number;
  paginationUsers: number[];
  paginationSkills: number[];
};

export const NAVIGATION_DATA = {
  general: {
    email:
      'usuario@navtest.skillview.test',

    password:
      'CajaNegra1!',
  },

  admin: {
    email:
      'admin@navtest.skillview.test',

    password:
      'CajaNegra1!',
  },

  searches: {
    users:
      'NavPaginacionUsuario',

    skills:
      'NavPaginacionHab',
  },

  skills: {
    communication:
      'Comunicación Navegación E2E',

    leadership:
      'Liderazgo Navegación E2E',
  },

  challenges: {
    basic:
      'Reto Comunicación Básico E2E',

    advanced:
      'Reto Comunicación Avanzado E2E',

    intermediate:
      'Reto Liderazgo Intermedio E2E',

    disabled:
      'Reto Deshabilitado E2E',
  },

  blogs: {
    communication:
      'E2E Navegación Comunicación',

    leadership:
      'E2E Navegación Liderazgo',

    shared:
      'E2E Navegación Compartido',

    disabled:
      'E2E Navegación Deshabilitado',
  },
} as const;

export function resetNavigationFixtures():
NavigationFixtureIds {
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
  ) as NavigationFixtureIds;
}

export function cleanupNavigationFixtures(): void {
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