import { expect, test, type APIRequestContext, type Page } from '@playwright/test';

import es from '../src/shared/i18n/locales/es.json' with { type: 'json' };
import { csrfHeaders } from './support/session';
import { totpCode } from './support/totp';

// E4.26. User A is the e2e admin (the saved session); user B is created for this run.
test.setTimeout(120_000);

const lists = es.lists;
// Polling runs every 5 s (PD-7); leave room for one missed tick.
const POLL = { timeout: 12_000 };

/** Creates a USER through the admin API and activates it, which logs `userApi` in with 2FA. */
async function createActiveUser(adminApi: APIRequestContext, userApi: APIRequestContext) {
  const username = `e2e-member-${String(Date.now() % 1_000_000)}`;
  const created = await adminApi.post('/api/admin/users', {
    headers: csrfHeaders,
    data: { username, role: 'USER' },
  });
  expect(created.status(), await created.text()).toBe(201);
  const { activationToken } = (await created.json()) as { activationToken: string };

  const enrolled = await userApi.post('/api/auth/activation/password', {
    headers: csrfHeaders,
    data: { token: activationToken, password: 'Member-Password-123!' },
  });
  expect(enrolled.status(), await enrolled.text()).toBe(200);
  const { secret } = (await enrolled.json()) as { secret: string };

  const activated = await userApi.post('/api/auth/activation/totp', {
    headers: csrfHeaders,
    data: { token: activationToken, code: totpCode(secret) },
  });
  expect(activated.status(), await activated.text()).toBe(200);
  return username;
}

function item(page: Page, name: string) {
  return page
    .getByRole('list', { name: lists.page.items })
    .getByRole('checkbox', { name, exact: true });
}

async function addItem(page: Page, text: string, quantity: string) {
  await page.getByLabel(lists.add.text, { exact: true }).fill(text);
  await page.getByLabel(lists.add.quantity, { exact: true }).fill(quantity);
  await page.getByRole('button', { name: lists.add.submit, exact: true }).click();
  await expect(item(page, text)).toBeVisible();
}

test('two users share a list through its link, see each other’s changes, and a regenerated link revokes the old one', async ({
  page,
  browser,
}) => {
  // 1. A creates a list and adds an item.
  const listName = `Compra e2e ${String(Date.now() % 1_000_000)}`;
  await page.goto('/');
  await page.getByLabel(lists.create.name, { exact: true }).fill(listName);
  await page.getByRole('button', { name: lists.create.submit }).click();
  await expect(page).toHaveURL(/\/lists\/[^/]+$/);
  const listUrl = new URL(page.url()).pathname;
  await expect(page.getByRole('heading', { level: 1, name: listName })).toBeVisible();
  await addItem(page, 'pan', '2');

  // 2. A copies the share link.
  const link = await page.getByLabel(lists.share.label, { exact: true }).inputValue();
  expect(link).toMatch(/\/join\/[A-Za-z0-9_-]{22,}$/);

  // 3. B, in another browser, logged in with 2FA, opens the link and joins.
  // browser.newContext() inherits the project's storageState: start B without A's cookie.
  const memberContext = await browser.newContext({ storageState: { cookies: [], origins: [] } });
  const username = await createActiveUser(page.request, memberContext.request);
  const memberPage = await memberContext.newPage();
  await memberPage.goto(link);
  await expect(memberPage).toHaveURL(listUrl);
  await expect(memberPage.getByRole('heading', { level: 1, name: listName })).toBeVisible();
  await expect(item(memberPage, 'pan')).toBeVisible();
  // Members never see the link (only the owner shares it).
  await expect(memberPage.getByLabel(lists.share.label, { exact: true })).toHaveCount(0);

  // 4. Both see each other's changes while the list is open.
  await expect(
    page.getByRole('region', { name: lists.members.title }).getByText(username, { exact: true }),
  ).toBeVisible(POLL);
  await addItem(memberPage, 'leche', '1');
  await expect(item(page, 'leche')).toBeVisible(POLL);
  // The optimistic check lands after an in-flight poll is cancelled, so assert instead of check().
  await item(page, 'pan').click();
  await expect(item(page, 'pan')).toBeChecked();
  await expect(item(memberPage, 'pan')).toBeChecked(POLL);

  // 5. A regenerates the link: the old one stops working, B stays a member.
  await page.getByRole('button', { name: lists.members.regenerate }).click();
  await page.getByRole('button', { name: lists.members.confirm }).click();
  await expect(page.getByLabel(lists.share.label, { exact: true })).not.toHaveValue(link);

  await memberPage.goto(link);
  await expect(
    memberPage.getByRole('heading', { level: 1, name: lists.join.invalidTitle }),
  ).toBeVisible();
  await memberPage.goto(listUrl);
  await expect(item(memberPage, 'leche')).toBeVisible();

  await memberContext.close();
});

function suggestion(page: Page, name: string) {
  return page
    .getByRole('listbox', { name: lists.suggestions.label })
    .getByRole('option')
    .filter({ has: page.getByText(name, { exact: true }) });
}

// E12.7. Runs against the known catalog of backend/tests/Fixtures/e2e/catalog.sql.
test('a user adds catalog products from inside a list, by click, by keyboard and from every result', async ({
  page,
}) => {
  const listName = `Productos e2e ${String(Date.now() % 1_000_000)}`;
  await page.goto('/');
  await page.getByLabel(lists.create.name, { exact: true }).fill(listName);
  await page.getByRole('button', { name: lists.create.submit }).click();
  await expect(page).toHaveURL(/\/lists\/[^/]+$/);
  const listUrl = new URL(page.url()).pathname;
  const field = page.getByRole('combobox', { name: lists.add.text });

  // 1. A product with a click (PD-80, PD-81).
  await field.fill('leche');
  await suggestion(page, 'Leche entera').click();
  await expect(item(page, 'Leche entera')).toBeVisible();
  await expect(field).toHaveValue('');
  await expect(field).toBeFocused();

  // 2. Another one with the keyboard: the text option, then the first and second products.
  await field.fill('leche');
  await expect(suggestion(page, 'Leche semidesnatada')).toBeVisible();
  await field.press('ArrowDown');
  await field.press('ArrowDown');
  await field.press('ArrowDown');
  await expect(suggestion(page, 'Leche semidesnatada')).toHaveAttribute('aria-selected', 'true');
  await field.press('Enter');
  await expect(item(page, 'Leche semidesnatada')).toBeVisible();

  // 3. Free text with Enter, as before.
  await field.fill('pan');
  await field.press('Enter');
  await expect(item(page, 'pan')).toBeVisible();

  const totals = page.getByRole('region', { name: lists.totals.title });
  await expect(totals.getByText('Mercadona', { exact: true })).toBeVisible();
  await expect(totals.getByText('Aldi', { exact: true })).toBeVisible();

  // 4. Every result of the search, which adds straight to this list (PD-82).
  await field.fill('yogur');
  await page.getByRole('link', { name: lists.suggestions.seeAll }).click();
  await expect(page).toHaveURL(`/search?q=yogur&list=${listUrl.split('/').at(-1) ?? ''}`);
  await page.getByRole('button', { name: `Añadir Yogur natural a ${listName}` }).click();
  await expect(page.getByText(`Añadido a «${listName}».`)).toBeVisible();

  await page.getByRole('link', { name: `Volver a ${listName}` }).click();
  await expect(page).toHaveURL(listUrl);
  await expect(item(page, 'Yogur natural')).toBeVisible();
});

function chip(page: Page, name: RegExp) {
  return page.getByRole('group', { name: lists.filter.label }).getByRole('button', { name });
}

// E13.5. Runs against the known catalog of backend/tests/Fixtures/e2e/catalog.sql.
test('a user filters a list by supermarket, keeps the filter on reload and goes back to every item', async ({
  page,
}) => {
  const listName = `Filtro e2e ${String(Date.now() % 1_000_000)}`;
  await page.goto('/');
  await page.getByLabel(lists.create.name, { exact: true }).fill(listName);
  await page.getByRole('button', { name: lists.create.submit }).click();
  await expect(page).toHaveURL(/\/lists\/[^/]+$/);
  const listUrl = new URL(page.url()).pathname;
  const field = page.getByRole('combobox', { name: lists.add.text });

  // 1. Products of two supermarkets (Mercadona 0,89 €, Aldi 1,35 €) and a free-text item.
  await field.fill('leche');
  await suggestion(page, 'Leche entera').click();
  await expect(item(page, 'Leche entera')).toBeVisible();
  await field.fill('leche');
  await suggestion(page, 'Leche semidesnatada').click();
  await expect(item(page, 'Leche semidesnatada')).toBeVisible();
  await addItem(page, 'pan', '1');

  // 2. The chips with their totals (PD-84).
  await expect(chip(page, new RegExp(lists.filter.all))).toHaveAttribute('aria-pressed', 'true');
  await expect(chip(page, /Mercadona/u)).toHaveText(/0,89\s€/u);
  await expect(chip(page, /Aldi/u)).toHaveText(/1,35\s€/u);

  // 3. One supermarket: only its products, and the notice of the hidden free-text item (PD-85).
  await chip(page, /Mercadona/u).click();
  await expect(page).toHaveURL(`${listUrl}?supermarket=mercadona`);
  await expect(item(page, 'Leche entera')).toBeVisible();
  await expect(item(page, 'Leche semidesnatada')).toBeHidden();
  await expect(item(page, 'pan')).toBeHidden();
  await expect(page.getByText('1 elemento sin supermercado no se muestra')).toBeVisible();

  // 4. A reload keeps the filter (PD-86).
  await page.reload();
  await expect(item(page, 'Leche entera')).toBeVisible();
  await expect(item(page, 'Leche semidesnatada')).toBeHidden();
  await expect(chip(page, /Mercadona/u)).toHaveAttribute('aria-pressed', 'true');

  // 5. A new quantity changes the chip total: the same product adds to its quantity (PD-45).
  await page.getByLabel(lists.add.quantity, { exact: true }).fill('2');
  await field.fill('leche');
  await suggestion(page, 'Leche entera').click();
  await expect(chip(page, /Mercadona/u)).toHaveText(/2,67\s€/u);

  // 6. Back to "Todos": every item is still in the list (PD-83).
  await chip(page, new RegExp(lists.filter.all)).click();
  await expect(page).toHaveURL(listUrl);
  await expect(item(page, 'Leche entera')).toBeVisible();
  await expect(item(page, 'Leche semidesnatada')).toBeVisible();
  await expect(item(page, 'pan')).toBeVisible();
});

function row(page: Page, name: string) {
  return (
    page
      .getByRole('list', { name: lists.page.items })
      .getByRole('listitem')
      // `has` resolves inside each list item, so it names the checkbox alone, not through `item()`.
      .filter({ has: page.getByRole('checkbox', { name, exact: true }) })
  );
}

// E15.5. The e2e catalog has no images (PD-99), so every product shows the placeholder.
test('a user sees product images in the suggestions and the list, and opens a product from its image', async ({
  page,
}) => {
  const noImage = es.catalog.product.noImage;
  const listName = `Imágenes e2e ${String(Date.now() % 1_000_000)}`;
  await page.goto('/');
  await page.getByLabel(lists.create.name, { exact: true }).fill(listName);
  await page.getByRole('button', { name: lists.create.submit }).click();
  await expect(page).toHaveURL(/\/lists\/[^/]+$/);
  const listUrl = new URL(page.url()).pathname;
  const field = page.getByRole('combobox', { name: lists.add.text });

  // 1. The suggested products show the placeholder (PD-97). Images are hidden from assistive
  // technology, which the product names already serve.
  await field.fill('leche');
  await expect(
    suggestion(page, 'Leche entera').getByRole('img', { name: noImage, includeHidden: true }),
  ).toBeVisible();

  // 2. Added to the list, its row shows the placeholder too, which opens the product page (PD-48).
  await suggestion(page, 'Leche entera').click();
  const image = row(page, 'Leche entera').getByRole('img', { name: noImage, includeHidden: true });
  await expect(image).toBeVisible();
  await image.click();
  await expect(page).toHaveURL(/\/product\/[^/]+$/);
  await expect(page.getByRole('heading', { level: 1, name: 'Leche entera' })).toBeVisible();

  // 3. Back in the list, a free-text item shows no image or placeholder.
  await page.goBack();
  await expect(page).toHaveURL(listUrl);
  await addItem(page, 'pan', '1');
  await expect(row(page, 'pan').getByRole('img', { includeHidden: true })).toHaveCount(0);
});
