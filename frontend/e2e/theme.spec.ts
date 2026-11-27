import { expect, test, type Page } from '@playwright/test';

import es from '../src/shared/i18n/locales/es.json' with { type: 'json' };

function themeSelect(page: Page) {
  return page.getByRole('banner').getByRole('combobox', { name: es.theme.label });
}

function html(page: Page) {
  return page.locator('html');
}

test('a user picks the dark theme, which survives a reload (PD-75, PD-76)', async ({ page }) => {
  await page.emulateMedia({ colorScheme: 'light' });
  await page.goto('/');
  await expect(html(page)).toHaveAttribute('data-theme', 'light');

  await themeSelect(page).selectOption({ label: es.theme.options.dark });

  await expect(html(page)).toHaveAttribute('data-theme', 'dark');
  await page.reload();
  await expect(html(page)).toHaveAttribute('data-theme', 'dark');
  await expect(themeSelect(page)).toHaveValue('dark');
});

test('the stored theme also applies to the login page (PD-75)', async ({ page, context }) => {
  await page.emulateMedia({ colorScheme: 'light' });
  await page.goto('/');
  await themeSelect(page).selectOption({ label: es.theme.options.dark });

  // Drops this browser's session without logging out, so other tests keep theirs.
  await context.clearCookies();
  await page.goto('/login');

  await expect(page.getByRole('heading', { level: 1, name: es.auth.login.title })).toBeVisible();
  await expect(html(page)).toHaveAttribute('data-theme', 'dark');
});

test('with Sistema the app follows the system theme live (PD-74)', async ({ page }) => {
  await page.emulateMedia({ colorScheme: 'light' });
  await page.goto('/');
  await themeSelect(page).selectOption({ label: es.theme.options.light });

  await page.emulateMedia({ colorScheme: 'dark' });
  await expect(html(page)).toHaveAttribute('data-theme', 'light');

  await themeSelect(page).selectOption({ label: es.theme.options.system });
  await expect(html(page)).toHaveAttribute('data-theme', 'dark');

  await page.emulateMedia({ colorScheme: 'light' });
  await expect(html(page)).toHaveAttribute('data-theme', 'light');
});
