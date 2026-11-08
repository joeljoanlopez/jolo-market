import { expect, test } from '@playwright/test';

import es from '../src/shared/i18n/locales/es.json' with { type: 'json' };

test('the home page loads with its Spanish copy', async ({ page }) => {
  await page.goto('/');

  await expect(page.getByRole('heading', { level: 1, name: es.home.title })).toBeVisible();
  await expect(page.getByRole('banner')).toContainText(es.app.name);
});
