import { execFileSync } from 'node:child_process';
import path from 'node:path';

const scriptPath = path.resolve(
  process.cwd(),
  'tests',
  'E2E',
  'helpers',
  'leccion-flujo-fixtures.php',
);

export type LessonFlowFixtureIds = {
  user: number;
  skill: number;
  lesson: number;
};

export const LESSON_FLOW_DATA = {
  user: {
    email:
      'e2e_leccion_usuario@skillview.test',

    password:
      'LeccionCaja1!',
  },

  skill: {
    name:
      'Comunicación Visual E2E',
  },

  lesson: {
    title:
      'Comunicación clara en una entrevista E2E',
  },

  messages: {
    welcome:
      'Bienvenido a esta lección sobre comunicación clara.',

    explanation:
      'Organiza tu respuesta en una idea principal, un ejemplo y un resultado.',

    microPrompt:
      'Explica cómo presentarías una fortaleza personal durante una entrevista.',

    miniPrompt:
      'Ahora describe una situación en la que hayas comunicado una idea con claridad.',

    microRetry:
      'Tu respuesta todavía necesita un ejemplo más concreto.',

    miniRetry:
      'Amplía la situación y explica cuál fue el resultado.',

    remainingAttempts:
      'Te quedan 2 intentos más para responder correctamente esta parte.',

    finalFeedback:
      'Organizaste la respuesta de manera clara y utilizaste un ejemplo concreto.',

    serviceUnavailable:
      'En este momento no fue posible conectarse con el servicio de inteligencia artificial. Verifica tu conexión a internet e inténtalo nuevamente más tarde. Tu progreso y tus intentos no se verán afectados.',
  },
} as const;

export function resetLessonFlowFixtures():
LessonFlowFixtureIds {
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
  ) as LessonFlowFixtureIds;
}

export function cleanupLessonFlowFixtures(): void {
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