import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';

export default async function globalSetup(): Promise<void> {
  const phpBinary =
    process.env.PHP_BINARY
    ?? 'php';

  const fixtureScript =
    resolve(
      process.cwd(),
      'tests/Adaptability/helpers/grupo4-fixtures.php',
    );

  execFileSync(
    phpBinary,
    [
      fixtureScript,
      'setup',
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
}