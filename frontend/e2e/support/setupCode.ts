import { execFileSync } from 'node:child_process';

/**
 * The setup code of a fresh instance. It runs the same command as the php container at startup
 * (`app:setup:ensure-code`, which prints the code to its logs), so it works even when the
 * container started before the migrations, as in CI. A new code replaces the previous one.
 */
export function setupCode(): string {
  const output = execFileSync(
    'docker',
    ['compose', 'exec', '-T', 'php', 'bin/console', 'app:setup:ensure-code', '--no-interaction'],
    { encoding: 'utf8' },
  );
  const code = /Setup code: (\S+)/.exec(output)?.[1];
  if (code === undefined) {
    throw new Error(`app:setup:ensure-code printed no setup code:\n${output}`);
  }
  return code;
}
