import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';

export default async function globalTeardown(): Promise<void> {
  const phpBinary =
    process.env.PHP_BINARY
    ?? 'php';

  const fixtureScript =
    resolve(
      process.cwd(),
      'tests/Adaptability/helpers/grupo4-fixtures.php',
    );

  try {
    execFileSync(
      phpBinary,
      [
        fixtureScript,
        'cleanup',
      ],
      {
        cwd:
          process.cwd(),

        env: {
          ...process.env,

          ADAPT_DB_NAME:
            process.env.ADAPT_DB_NAME
            ?? process.env.TEST_DB_NAME
            ?? 'skillview_test',
        },

        stdio:
          'inherit',
      },
    );
  } catch (error) {
    console.error(
      'No fue posible limpiar automáticamente el fixture del grupo 4.',
      error,
    );
  }
}