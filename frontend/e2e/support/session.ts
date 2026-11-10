import { tmpdir } from 'node:os';
import { join } from 'node:path';

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

/** The API refuses state-changing requests without it (CSRF, E3.18). */
export const csrfHeaders = { 'X-Requested-With': 'XMLHttpRequest' };
