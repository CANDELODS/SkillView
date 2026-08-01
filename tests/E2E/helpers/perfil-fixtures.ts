import { execFileSync } from 'node:child_process';
import path from 'node:path';

const scriptPath = path.resolve(
  process.cwd(),
  'tests',
  'E2E',
  'helpers',
  'perfil-fixtures.php',
);

export type ProfileFixtureIds = {
  usuario: number;
};

export const PROFILE_DATA = {
  user: {
    email:
      'e2e_perfil_usuario@skillview.test',

    password:
      'PerfilCaja1!',

    names:
      'Usuario Perfil',

    surnames:
      'Prueba E Dos E',

    age:
      '24',

    sex:
      'Prefiero no decirlo',

    university:
      'Universidad del Cauca',

    career:
      'Ingeniería de Sistemas',
  },

  updated: {
    names:
      'María Fernanda',

    surnames:
      'Pérez Gómez',

    university:
      'Universidad Autónoma del Cauca',

    career:
      'Ingeniería de Sistemas',
  },

  injected: {
    email:
      'correo_inyectado@skillview.test',
  },
} as const;

export function resetProfileFixtures():
ProfileFixtureIds {
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
  ) as ProfileFixtureIds;
}

export function cleanupProfileFixtures(): void {
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