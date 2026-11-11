import { expect, test } from '@playwright/test';

import es from '../src/shared/i18n/locales/es.json' with { type: 'json' };

test('the home page loads with its Spanish copy', async ({ page }) => {
  await page.goto('/');

  await expect(page.getByRole('heading', { level: 1, name: es.home.title })).toBeVisible();
  await expect(page.getByRole('banner')).toContainText(es.app.name);
});

test.describe('without a session', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('a visitor is sent to the login page, which keeps the page they asked for', async ({
    page,
  }) => {
    await page.goto('/search?q=leche');

    await expect(page).toHaveURL('/login?next=%2Fsearch%3Fq%3Dleche');
    await expect(page.getByRole('heading', { level: 1, name: es.auth.login.title })).toBeVisible();
  });
});
