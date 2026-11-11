import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';

export const baseURL = process.env.E2E_BASE_URL ?? 'http://localhost:8080';

/**
 * Outside the repository, one folder per instance: it survives between local runs, so an
 * instance that already has its e2e user still works.
 */
const stateDirectory = join(tmpdir(), 'jolo-market-e2e', encodeURIComponent(baseURL));

export const storageStatePath = join(stateDirectory, 'session.json');
export const credentialsPath = join(stateDirectory, 'credentials.json');

export interface E2eCredentials {
  username: string;
  password: string;
  totpSecret: string;
}

/** The e2e admin, the first user of the instance. */
export const ADMIN = { username: 'e2e-admin', password: 'E2e-Password-123!' };

/** The admin's credentials saved by an earlier run against this instance, if any. */
export function savedCredentials(): E2eCredentials | null {
  return existsSync(credentialsPath)
    ? (JSON.parse(readFileSync(credentialsPath, 'utf8')) as E2eCredentials)
    : null;
}

export function saveCredentials(credentials: E2eCredentials): void {
  mkdirSync(dirname(credentialsPath), { recursive: true });
  writeFileSync(credentialsPath, JSON.stringify(credentials), { mode: 0o600 });
}

/** The API refuses state-changing requests without it (CSRF, E3.18). */
export const csrfHeaders = { 'X-Requested-With': 'XMLHttpRequest' };
