import { expect, test, type Page } from '@playwright/test';

import es from '../src/shared/i18n/locales/es.json' with { type: 'json' };
import { ADMIN, saveCredentials, savedCredentials } from './support/session';
import { setupCode } from './support/setupCode';
import { nextUnusedCode, totpCode } from './support/totp';

// E3.29. On a fresh instance it starts at /setup; on one an earlier run already set up, it logs
// in as that admin instead, and the rest of the journey is the same.
test.use({ storageState: { cookies: [], origins: [] } });
// Codes are single-use per 30 seconds, so logging in again right after an activation waits.
test.setTimeout(120_000);

const activation = es.auth.activation;
const login = es.auth.login;

/** Sets the password and enrolls TOTP on an /activate page. Returns the secret and when its code was used. */
async function activate(page: Page, password: string) {
  await page.getByLabel(activation.password, { exact: true }).fill(password);
  await page.getByRole('button', { name: activation.continue }).click();

  await expect(page.getByRole('img', { name: activation.qrLabel })).toBeVisible();
  const secret = (await page.locator('code').textContent())?.trim() ?? '';
  expect(secret).toMatch(/^[A-Z2-7]+=*$/);

  await page.getByLabel(activation.code, { exact: true }).fill(totpCode(secret));
  const usedAt = new Date();
  await page.getByRole('button', { name: activation.activate }).click();
  await expect(page.getByRole('heading', { level: 1, name: es.home.title })).toBeVisible();

  return { secret, usedAt };
}

async function logIn(page: Page, username: string, password: string, code: string) {
  await page.getByLabel(login.username, { exact: true }).fill(username);
  await page.getByLabel(login.password, { exact: true }).fill(password);
  await page.getByRole('button', { name: login.continue }).click();
  await page.getByLabel(login.code, { exact: true }).fill(code);
  await page.getByRole('button', { name: login.submit }).click();
}

test('an admin sets up the instance, creates a user who activates their account and logs in with 2FA', async ({
  page,
  browser,
}) => {
  // 1. The admin: /setup on a fresh instance, or a login with the saved credentials.
  await page.goto('/');
  const fresh = await page
    .waitForURL('/setup', { timeout: 5_000 })
    .then(() => true)
    .catch(() => false);

  if (fresh) {
    await page.getByLabel(es.auth.setup.code, { exact: true }).fill(setupCode());
    await page.getByLabel(es.auth.setup.username, { exact: true }).fill(ADMIN.username);
    await page.getByRole('button', { name: es.auth.setup.submit }).click();
    await expect(page).toHaveURL(/\/activate\//);
    const { secret } = await activate(page, ADMIN.password);
    saveCredentials({ ...ADMIN, totpSecret: secret });
  } else {
    const credentials = savedCredentials();
    if (credentials === null) {
      throw new Error(
        'This instance already has users but no saved e2e credentials: use an empty database.',
      );
    }
    await expect(page).toHaveURL('/login');
    await logIn(
      page,
      credentials.username,
      credentials.password,
      await nextUnusedCode(credentials.totpSecret, new Date()),
    );
    await expect(page.getByRole('heading', { level: 1, name: es.home.title })).toBeVisible();
  }

  // 2. The admin creates a user and copies the activation link, shown once.
  const username = `e2e-user-${String(Date.now() % 1_000_000)}`;
  await page.getByRole('link', { name: es.admin.users.nav }).click();
  await page.getByLabel(es.admin.users.create.username, { exact: true }).fill(username);
  await page.getByRole('button', { name: es.admin.users.create.submit }).click();
  const link = await page.getByLabel(es.admin.users.link.label, { exact: true }).inputValue();
  expect(link).toContain('/activate/');

  // 3. The user, in their own browser, activates the account.
  const userContext = await browser.newContext();
  const userPage = await userContext.newPage();
  await userPage.goto(link);
  await expect(
    userPage.getByText(`Hola, ${username}. Elige una contraseña para tu cuenta.`),
  ).toBeVisible();
  const password = 'User-Password-123!';
  const { secret, usedAt } = await activate(userPage, password);

  // 4. They log out, then log in again with the password and a TOTP code.
  await userPage.getByRole('button', { name: es.auth.session.logOut }).click();
  await expect(userPage).toHaveURL('/login');
  await logIn(userPage, username, password, await nextUnusedCode(secret, usedAt));

  await expect(userPage.getByRole('heading', { level: 1, name: es.home.title })).toBeVisible();
  await expect(userPage.getByRole('banner')).toContainText(username);
  // A USER sees no admin entry point (PD-15).
  await expect(userPage.getByRole('link', { name: es.admin.users.nav })).toHaveCount(0);
  await userContext.close();
});
