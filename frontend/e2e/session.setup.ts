import { existsSync, mkdirSync } from 'node:fs';
import { dirname } from 'node:path';

import { expect, request, test as setup, type APIRequestContext } from '@playwright/test';

import {
  ADMIN,
  csrfHeaders,
  saveCredentials,
  savedCredentials,
  storageStatePath,
  type E2eCredentials,
} from './support/session';
import { setupCode } from './support/setupCode';
import { msUntilNextCode, totpCode } from './support/totp';

/**
 * Leaves a fully authenticated session in `storageStatePath` for the other tests.
 *
 * On a fresh instance (CI) it creates the first admin through /api/setup with the code that
 * `app:setup:ensure-code` prints, and activates it. Later runs against the same instance reuse
 * the saved session, or log in again with the saved credentials.
 */
setup('a logged-in session', async ({ baseURL }) => {
  if (baseURL === undefined) {
    throw new Error('playwright.config.ts sets use.baseURL.');
  }
  const api = await request.newContext({ baseURL });

  if (await hasSavedSession(baseURL)) {
    return;
  }

  const setupResponse = await api.get('/api/setup');
  const credentials = savedCredentials();
  if (setupResponse.ok()) {
    await createFirstAdmin(api);
  } else if (credentials !== null) {
    await logIn(api, credentials);
  } else {
    throw new Error(
      'This instance already has users, but no saved e2e credentials. Run the end-to-end tests ' +
        'against an empty database (docker compose down -v, then start the stack again).',
    );
  }

  mkdirSync(dirname(storageStatePath), { recursive: true });
  await api.storageState({ path: storageStatePath });
  await api.dispose();
});

async function hasSavedSession(baseURL: string): Promise<boolean> {
  if (!existsSync(storageStatePath)) {
    return false;
  }
  const saved = await request.newContext({ baseURL, storageState: storageStatePath });
  const me = await saved.get('/api/auth/me');
  if (me.ok()) {
    // The API may have re-sent the cookie with 7 more days (PD-37): keep the fresher copy.
    await saved.storageState({ path: storageStatePath });
  }
  await saved.dispose();
  return me.ok();
}

async function createFirstAdmin(api: APIRequestContext): Promise<void> {
  const credentials = ADMIN;

  const created = await api.post('/api/setup', {
    headers: csrfHeaders,
    data: { code: setupCode(), username: credentials.username },
  });
  expect(created.status(), await created.text()).toBe(201);
  const { activationToken } = (await created.json()) as { activationToken: string };

  const enrolled = await api.post('/api/auth/activation/password', {
    headers: csrfHeaders,
    data: { token: activationToken, password: credentials.password },
  });
  expect(enrolled.status(), await enrolled.text()).toBe(200);
  const { secret } = (await enrolled.json()) as { secret: string };
  saveCredentials({ ...credentials, totpSecret: secret });

  // Confirming the code activates the account and logs this context in.
  const activated = await api.post('/api/auth/activation/totp', {
    headers: csrfHeaders,
    data: { token: activationToken, code: totpCode(secret) },
  });
  expect(activated.status(), await activated.text()).toBe(200);
}

async function logIn(api: APIRequestContext, credentials: E2eCredentials): Promise<void> {
  const passwordStep = await api.post('/api/auth/login', {
    headers: csrfHeaders,
    data: { username: credentials.username, password: credentials.password },
  });
  expect(passwordStep.status(), await passwordStep.text()).toBe(202);

  let codeStep = await api.post('/api/auth/2fa', {
    headers: csrfHeaders,
    data: { code: totpCode(credentials.totpSecret) },
  });
  if (codeStep.status() === 401) {
    // The current code was already used (codes are single-use): wait for the next one.
    await new Promise((resolve) => setTimeout(resolve, msUntilNextCode() + 1000));
    codeStep = await api.post('/api/auth/2fa', {
      headers: csrfHeaders,
      data: { code: totpCode(credentials.totpSecret) },
    });
  }
  expect(codeStep.status(), await codeStep.text()).toBe(200);
}
