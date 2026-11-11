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
