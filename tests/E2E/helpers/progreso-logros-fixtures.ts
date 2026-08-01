import { execFileSync } from 'node:child_process';
import path from 'node:path';

const scriptPath = path.resolve(
    process.cwd(),
    'tests',
    'E2E',
    'helpers',
    'progreso-logros-fixtures.php',
);

export type ProgressAchievementFixture = {
    user: number;

    skills: {
        basic: number;
        intermediate: number;
        advanced: number;
    };

    challenges: {
        first: number;
        second: number;
    };

    achievements: {
        skillUnlocked: number;
        performanceUnlocked: number;
        scoreLocked: number;
        disabled: number;
    };

    profileAchievements: {
        firstUnlocked: {
            id: number;
            name: string;
        };

        secondUnlocked: {
            id: number;
            name: string;
        };

        locked: {
            id: number;
            name: string;
        };

        allFeatured: Array<{
            id: number;
            name: string;
        }>;
    };

    expected: {
        generalProgress: number;
        totalPoints: number;
        achievementDate: string;
    };
};

export const PROGRESS_ACHIEVEMENT_DATA = {
    user: {
        email:
            'e2e_progreso_logros@skillview.test',

        password:
            'ProgresoCaja1!',
    },

    skills: {
        basic: {
            name:
                'E2E Progreso Básico',

            progress:
                33,

            level:
                'Básico',
        },

        intermediate: {
            name:
                'E2E Progreso Intermedio',

            progress:
                50,

            level:
                'Intermedio',
        },

        advanced: {
            name:
                'E2E Progreso Avanzado',

            progress:
                100,

            level:
                'Avanzado',
        },
    },

    achievements: {
        skillUnlocked: {
            name:
                'E2E Progreso Habilidad Desbloqueada',

            description:
                'Logro obtenido por completar '
                + 'el objetivo de una habilidad.',

            type:
                'Habilidad',
        },

        performanceUnlocked: {
            name:
                'E2E Progreso Desempeño Desbloqueado',

            description:
                'Logro obtenido por alcanzar '
                + 'un desempeño destacado.',

            type:
                'Desempeño',
        },

        scoreLocked: {
            name:
                'E2E Progreso Puntaje Bloqueado',

            description:
                'Logro que todavía no fue '
                + 'alcanzado por el usuario.',

            type:
                'Puntaje',
        },

        disabled: {
            name:
                'E2E Progreso Oculto',
        },
    },
} as const;

/**
 * Restaura todos los datos antes
 * de cada caso.
 */
export function resetProgressAchievementFixtures():
    ProgressAchievementFixture {
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
    ) as ProgressAchievementFixture;
}

/**
 * Elimina los registros controlados.
 */
export function cleanupProgressAchievementFixtures():
    void {
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