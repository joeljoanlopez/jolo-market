import { expect, test } from '@playwright/test';

import es from '../src/shared/i18n/locales/es.json' with { type: 'json' };

// Runs against the known catalog of backend/tests/Fixtures/e2e/catalog.sql, where "Leche entera" has a price
// history of 0,95 € → 0,99 € → 0,89 € over the last 20 days.
test('a search result opens the product page with its price history', async ({ page }) => {
  await page.goto('/search?q=leche');
  await page.getByRole('link', { name: 'Leche entera', exact: true }).click();

  await expect(page).toHaveURL('/product/01960000-0000-7000-8000-000000000001');
  await expect(page.getByRole('heading', { level: 1, name: 'Leche entera' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Añadir Leche entera a una lista' })).toBeVisible();

  const history = page.getByRole('region', { name: es.catalog.history.title });
  await expect(
    history.getByRole('button', { name: es.catalog.history.period['6m'] }),
  ).toHaveAttribute('aria-pressed', 'true');
  await expect(
    history.getByRole('img', { name: /^Precio del .* entre 0,89\s€ y 0,99\s€\.$/u }),
  ).toBeVisible();

  const summary = history.getByRole('definition');
  await expect(summary).toHaveText([/^0,89\s€$/u, /^0,99\s€$/u, /^0,89\s€$/u]);

  // Another period asks the API again and still covers the 20 days of history.
  await history.getByRole('button', { name: es.catalog.history.period.all }).click();
  await expect(
    history.getByRole('button', { name: es.catalog.history.period.all }),
  ).toHaveAttribute('aria-pressed', 'true');
  await expect(history.getByRole('img', { name: /^Precio del/u })).toBeVisible();
});
