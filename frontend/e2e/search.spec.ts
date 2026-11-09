import { expect, test } from '@playwright/test';

import es from '../src/shared/i18n/locales/es.json' with { type: 'json' };

// Runs against the known catalog of backend/tests/Fixtures/e2e/catalog.sql.
test('searching "leche" lists the results grouped by unit and ranked by unit price', async ({
  page,
}) => {
  await page.goto('/');
  await page.getByRole('searchbox', { name: es.home.searchLabel }).fill('leche');
  await page.getByRole('button', { name: es.home.searchSubmit }).click();

  await expect(page).toHaveURL('/search?q=leche');
  const results = page.getByRole('list', { name: es.catalog.search.results }).getByRole('listitem');

  // PD-33: the unit with most results first (L: 4, kg: 2, unit: 1), ascending unit price
  // within each group, and the product without a unit price last.
  await expect(results.getByRole('heading', { level: 2 })).toHaveText([
    'Leche entera',
    'Leche semidesnatada',
    'Leche desnatada',
    'Leche sin lactosa',
    'Leche condensada',
    'Leche en polvo',
    'Batido de cacao con leche',
    'Leche fresca',
  ]);

  const litreCards = await results.filter({ hasText: '€/L' }).allTextContents();
  const litreCents = litreCards.map((text) => {
    const price = /([\d,]+)\s*€\/L/.exec(text)?.[1] ?? '';
    return Math.round(Number(price.replace(',', '.')) * 100);
  });
  expect(litreCents).toEqual([89, 90, 105, 129]);

  await expect(
    results.filter({ hasText: es.catalog.product.cheapest }).getByRole('heading', { level: 2 }),
  ).toHaveText(['Leche entera', 'Leche condensada', 'Batido de cacao con leche']);

  await expect(results.last()).toContainText(es.catalog.product.noUnitPrice);

  // The unavailable product is the cheapest litre, so its absence also proves the highlight.
  await expect(
    page.getByRole('heading', { name: 'Leche entera retirada', exact: true }),
  ).toHaveCount(0);
});
