import { act, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { formatPrice } from '@/features/catalog/domain/format';
import type { Product } from '@/features/catalog/domain/product';
import { aProduct } from '@/features/catalog/ui/components/testProduct';
import { ServerError } from '@/shared/http/errors';
import es from '@/shared/i18n/locales/es.json';
import { FakeAuthGateway, maria } from '@/test/FakeAuthGateway';
import { FakeProductCatalog } from '@/test/FakeProductCatalog';
import { FakeShoppingLists, JOAN, MILK, type StoredList } from '@/test/FakeShoppingLists';
import { LIST_REFRESH_INTERVAL_MS } from '@/features/shopping-list/application/useShoppingList';
import type { ListItem, ListProduct } from '@/features/shopping-list/domain/shoppingList';
import { locationOf, renderApp } from '@/test/renderApp';

const copy = es.lists;

function renderList(
  lists: FakeShoppingLists,
  listId: string,
  catalog: FakeProductCatalog = new FakeProductCatalog(),
) {
  return renderApp(`/lists/${listId}`, {
    auth: FakeAuthGateway.loggedInAs(maria),
    lists,
    catalog,
  });
}

function seedSharedByJoan(lists: FakeShoppingLists, overrides: Partial<StoredList> = {}) {
  return lists.seed({ owner: JOAN, members: [lists.currentUser], ...overrides });
}

async function itemList() {
  return screen.findByRole('list', { name: copy.page.items });
}

/** Testing Library collapses the non-breaking space that Intl writes before "€". */
function asShown(text: string): string {
  return text.replace(/\s/gu, ' ');
}

describe('ListPageContainer', () => {
  it('shows the list with unchecked items first, in their order (PD-45)', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({
      name: 'Compra semanal',
      items: [
        {
          id: 'a',
          position: 0,
          quantity: 1,
          checked: true,
          type: 'text',
          text: 'pan',
          section: 'other',
          sectionOverride: null,
        },
        {
          id: 'b',
          position: 1,
          quantity: 2,
          checked: false,
          type: 'text',
          text: 'huevos',
          section: 'other',
          sectionOverride: null,
        },
        {
          id: 'c',
          position: 2,
          quantity: 1,
          checked: false,
          type: 'text',
          text: 'fruta',
          section: 'other',
          sectionOverride: null,
        },
      ],
    });
    renderList(lists, stored.id);

    expect(await screen.findByRole('heading', { level: 1, name: 'Compra semanal' })).toBeVisible();
    const boxes = within(await itemList()).getAllByRole('checkbox');
    expect(boxes.map((box) => box.getAttribute('aria-label'))).toEqual(['huevos', 'fruta', 'pan']);
    expect(boxes.map((box) => (box as HTMLInputElement).checked)).toEqual([false, false, true]);
    expect(screen.getByText('× 2')).toBeVisible();
  });

  it('adds a free-text item with a quantity', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed();
    renderList(lists, stored.id);
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText(copy.add.text), 'leche');
    await user.clear(screen.getByLabelText(copy.add.quantity));
    await user.type(screen.getByLabelText(copy.add.quantity), '3');
    await user.click(screen.getByRole('button', { name: copy.add.submit }));

    expect(await within(await itemList()).findByRole('checkbox', { name: 'leche' })).toBeVisible();
    expect(screen.getByText('× 3')).toBeVisible();
    expect(screen.getByLabelText(copy.add.text)).toHaveValue('');
  });

  it('checks an item, which then goes after the unchecked ones', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({
      items: [
        {
          id: 'a',
          position: 0,
          quantity: 1,
          checked: false,
          type: 'text',
          text: 'pan',
          section: 'other',
          sectionOverride: null,
        },
        {
          id: 'b',
          position: 1,
          quantity: 1,
          checked: false,
          type: 'text',
          text: 'huevos',
          section: 'other',
          sectionOverride: null,
        },
      ],
    });
    renderList(lists, stored.id);
    const user = userEvent.setup();

    await user.click(await screen.findByRole('checkbox', { name: 'pan' }));

    await waitFor(() => {
      expect(screen.getByRole('checkbox', { name: 'pan' })).toBeChecked();
    });
    const boxes = within(await itemList()).getAllByRole('checkbox');
    expect(boxes.map((box) => box.getAttribute('aria-label'))).toEqual(['huevos', 'pan']);
    expect(stored.items[0]?.checked).toBe(true);
  });

  it('removes an item', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({
      items: [
        {
          id: 'a',
          position: 0,
          quantity: 1,
          checked: false,
          type: 'text',
          text: 'pan',
          section: 'other',
          sectionOverride: null,
        },
      ],
    });
    renderList(lists, stored.id);
    const user = userEvent.setup();

    await user.click(await screen.findByRole('button', { name: 'Quitar pan' }));

    expect(await screen.findByText(copy.page.empty)).toBeVisible();
  });

  it('shows the total per supermarket and flags unavailable products left out of it (PD-22)', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({
      items: [
        {
          id: 'a',
          position: 0,
          quantity: 2,
          checked: false,
          type: 'product',
          product: MILK,
          section: MILK.section,
          sectionOverride: null,
        },
        {
          id: 'b',
          position: 1,
          quantity: 1,
          checked: false,
          type: 'product',
          section: 'other',
          sectionOverride: null,
          product: { ...MILK, id: 'gone', name: 'Yogur natural', available: false },
        },
        {
          id: 'c',
          position: 2,
          quantity: 1,
          checked: false,
          type: 'text',
          text: 'pan',
          section: 'other',
          sectionOverride: null,
        },
      ],
    });
    renderList(lists, stored.id);

    const totals = await screen.findByRole('region', { name: copy.totals.title });
    expect(within(totals).getByText('Mercadona')).toBeVisible();
    expect(totals).toHaveTextContent(asShown(formatPrice(250)));
    expect(totals).toHaveTextContent('1 producto');
    expect(totals).toHaveTextContent('Yogur natural');
    const unavailableRow = within(await itemList())
      .getAllByRole('listitem')
      .find((row) => row.textContent.includes('Yogur natural'));
    expect(unavailableRow).toHaveTextContent(copy.item.unavailable);
  });

  it('links the name of a product item to its page, but not a text item or a product the catalog no longer knows (PD-48)', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({
      items: [
        {
          id: 'a',
          position: 0,
          quantity: 1,
          checked: false,
          type: 'product',
          product: MILK,
          section: MILK.section,
          sectionOverride: null,
        },
        {
          id: 'b',
          position: 1,
          quantity: 1,
          checked: false,
          type: 'product',
          section: 'other',
          sectionOverride: null,
          product: {
            ...MILK,
            id: 'gone',
            name: null,
            brand: null,
            supermarketId: null,
            priceCents: null,
            available: false,
            section: 'other',
          },
        },
        {
          id: 'c',
          position: 2,
          quantity: 1,
          checked: false,
          type: 'text',
          text: 'pan',
          section: 'other',
          sectionOverride: null,
        },
      ],
    });
    renderList(lists, stored.id);

    const items = await itemList();
    expect(within(items).getByRole('link', { name: 'Leche entera' })).toHaveAttribute(
      'href',
      '/product/product-milk',
    );
    expect(within(items).getAllByRole('link')).toHaveLength(1);
    expect(within(items).getByText(copy.item.unknownProduct)).toBeVisible();
  });

  it('gives the owner the full share link to copy', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed();
    renderList(lists, stored.id);
    const user = userEvent.setup();
    const link = `${window.location.origin}/join/${stored.shareToken}`;

    expect(await screen.findByLabelText(copy.share.label)).toHaveValue(link);
    await user.click(screen.getByRole('button', { name: copy.share.copy }));

    expect(await screen.findByText(copy.share.copied)).toBeVisible();
    await expect(navigator.clipboard.readText()).resolves.toBe(link);
  });

  it('lets the owner see and remove members', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({ members: [JOAN] });
    renderList(lists, stored.id);
    const user = userEvent.setup();

    const members = await screen.findByRole('region', { name: copy.members.title });
    expect(members).toHaveTextContent('joan');
    await user.click(within(members).getByRole('button', { name: 'Quitar a joan' }));

    expect(await within(members).findByText(copy.members.none)).toBeVisible();
    expect(stored.members).toEqual([]);
  });

  it('asks before regenerating the link, then shows the new one', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed();
    const oldToken = stored.shareToken;
    renderList(lists, stored.id);
    const user = userEvent.setup();

    await user.click(await screen.findByRole('button', { name: copy.members.regenerate }));
    expect(screen.getByText(copy.members.confirmRegenerate)).toBeVisible();
    await user.click(screen.getByRole('button', { name: copy.members.cancel }));
    expect(stored.shareToken).toBe(oldToken);

    await user.click(screen.getByRole('button', { name: copy.members.regenerate }));
    await user.click(screen.getByRole('button', { name: copy.members.confirm }));

    await waitFor(() => {
      expect(screen.getByLabelText(copy.share.label)).toHaveValue(
        `${window.location.origin}/join/${stored.shareToken}`,
      );
    });
    expect(stored.shareToken).not.toBe(oldToken);
  });

  it('asks before deleting the list, then goes back home (PD-44)', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({ name: 'Cena' });
    const router = renderList(lists, stored.id);
    const user = userEvent.setup();

    await user.click(await screen.findByRole('button', { name: copy.actions.delete }));
    expect(
      screen.getByText(
        '¿Borrar «Cena»? Se borrará para todos sus miembros y no se puede deshacer.',
      ),
    ).toBeVisible();
    await user.click(screen.getByRole('button', { name: copy.actions.confirm }));

    await waitFor(() => {
      expect(locationOf(router)).toBe('/');
    });
    expect(lists.lists.has(stored.id)).toBe(false);
  });

  it('shows a member the list without the owner tools, and lets them leave it (PD-44)', async () => {
    const lists = new FakeShoppingLists();
    const stored = seedSharedByJoan(lists, { name: 'Cena' });
    const router = renderList(lists, stored.id);
    const user = userEvent.setup();

    expect(await screen.findByRole('heading', { level: 1, name: 'Cena' })).toBeVisible();
    expect(screen.getByText('Lista de joan')).toBeVisible();
    expect(screen.queryByLabelText(copy.share.label)).not.toBeInTheDocument();
    expect(screen.queryByRole('region', { name: copy.members.title })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: copy.actions.delete })).not.toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: copy.actions.leave }));

    await waitFor(() => {
      expect(locationOf(router)).toBe('/');
    });
    expect(stored.members).toEqual([]);
  });

  it('shows "list not found" for a list the user cannot access', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({ owner: JOAN });
    renderList(lists, stored.id);

    expect(
      await screen.findByRole('heading', { level: 1, name: copy.notFound.title }),
    ).toBeVisible();
    expect(screen.getByRole('link', { name: copy.notFound.backHome })).toHaveAttribute('href', '/');
  });
});

const milk = aProduct({ id: MILK.id, name: 'Leche entera', brand: 'Hacendado' });
const eggs = aProduct({
  id: 'product-eggs',
  name: 'Huevos camperos',
  brand: null,
  supermarket: { id: 'bonpreu', name: 'Bonpreu' },
  priceCents: 300,
});

/** A catalog that finds `products` for any text, and lists that know them. */
function withProducts(products: Product[]) {
  const catalog = new FakeProductCatalog();
  catalog.respond = () =>
    Promise.resolve({
      items: products,
      page: 1,
      perPage: 20,
      total: products.length,
      cheapestUnitPriceCents: {},
      approximate: false,
    });
  const lists = new FakeShoppingLists();
  for (const product of products) {
    lists.products.set(product.id, {
      id: product.id,
      name: product.name,
      brand: product.brand,
      supermarketId: product.supermarket.id,
      priceCents: product.priceCents,
      imageUrl: null,
      available: true,
      section: 'other',
    });
  }
  return { catalog, lists };
}

function textField() {
  return screen.getByRole('combobox', { name: copy.add.text });
}

async function suggestion(name: string) {
  return within(await screen.findByRole('listbox')).findByRole('option', {
    name: new RegExp(name, 'u'),
  });
}

describe('ListPageContainer: product suggestions (E12)', () => {
  it('suggests products from 2 typed characters, with every supermarket (PD-80)', async () => {
    const { catalog, lists } = withProducts([milk, eggs]);
    const stored = lists.seed();
    renderList(lists, stored.id, catalog);
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText(copy.add.text), 'l');
    expect(screen.queryByRole('listbox')).toBeNull();
    await user.type(textField(), 'e');

    expect(await suggestion('Huevos camperos')).toBeVisible();
    expect(catalog.searches.at(-1)).toEqual({ q: 'le' });
    expect(screen.getByRole('link', { name: copy.suggestions.seeAll })).toHaveAttribute(
      'href',
      `/search?q=le&list=${stored.id}`,
    );
  });

  it('adds a product with a click, with the quantity of the form, and resets the form (PD-81)', async () => {
    const { catalog, lists } = withProducts([milk, eggs]);
    const stored = lists.seed();
    renderList(lists, stored.id, catalog);
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText(copy.add.text), 'huevos');
    await user.clear(screen.getByLabelText(copy.add.quantity));
    await user.type(screen.getByLabelText(copy.add.quantity), '2');
    await user.click(await suggestion('Huevos camperos'));

    expect(
      await within(await itemList()).findByRole('checkbox', { name: 'Huevos camperos' }),
    ).toBeVisible();
    expect(stored.items).toMatchObject([{ type: 'product', quantity: 2 }]);
    expect(textField()).toHaveValue('');
    expect(screen.getByLabelText(copy.add.quantity)).toHaveValue(1);
    expect(screen.queryByRole('listbox')).toBeNull();
    expect(textField()).toHaveFocus();
  });

  it('adds the highlighted product with the keyboard (PD-81)', async () => {
    const { catalog, lists } = withProducts([milk, eggs]);
    const stored = lists.seed();
    renderList(lists, stored.id, catalog);
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText(copy.add.text), 'leche');
    await suggestion('Leche entera');
    await user.keyboard('{ArrowDown}{ArrowDown}');
    expect(textField()).toHaveAttribute(
      'aria-activedescendant',
      (await suggestion('Leche entera')).id,
    );
    await user.keyboard('{Enter}');

    expect(
      await within(await itemList()).findByRole('checkbox', { name: 'Leche entera' }),
    ).toBeVisible();
    expect(stored.items).toMatchObject([{ type: 'product', product: { id: MILK.id } }]);
    expect(textField()).toHaveValue('');
    expect(textField()).toHaveFocus();
  });

  it('adds the typed text with Enter when nothing is highlighted (PD-81)', async () => {
    const { catalog, lists } = withProducts([milk]);
    const stored = lists.seed();
    renderList(lists, stored.id, catalog);
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText(copy.add.text), 'leche');
    await suggestion('Leche entera');
    await user.keyboard('{Enter}');

    expect(await within(await itemList()).findByRole('checkbox', { name: 'leche' })).toBeVisible();
    expect(stored.items).toMatchObject([
      { type: 'text', text: 'leche', section: 'other', sectionOverride: null },
    ]);
    expect(screen.queryByRole('listbox')).toBeNull();
    expect(textField()).toHaveFocus();
  });

  it('closes the suggestions with Escape', async () => {
    const { catalog, lists } = withProducts([milk]);
    const stored = lists.seed();
    renderList(lists, stored.id, catalog);
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText(copy.add.text), 'leche');
    await suggestion('Leche entera');
    await user.keyboard('{Escape}');

    expect(screen.queryByRole('listbox')).toBeNull();
    expect(textField()).toHaveAttribute('aria-expanded', 'false');
  });

  it('says when no product matches and still adds the text (PD-80)', async () => {
    const { catalog, lists } = withProducts([]);
    const stored = lists.seed();
    renderList(lists, stored.id, catalog);
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText(copy.add.text), 'pan bimbo');
    expect(await screen.findByText('No hay productos para «pan bimbo».')).toBeVisible();
    await user.click(await suggestion('Añadir «pan bimbo» como texto'));

    expect(
      await within(await itemList()).findByRole('checkbox', { name: 'pan bimbo' }),
    ).toBeVisible();
  });

  it('shows a failed search with a retry, and the text can still be added (PD-80)', async () => {
    const { catalog, lists } = withProducts([milk]);
    const working = catalog.respond;
    catalog.respond = () => Promise.reject(new Error('network down'));
    const stored = lists.seed();
    renderList(lists, stored.id, catalog);
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText(copy.add.text), 'leche');
    expect(await screen.findByRole('alert')).toHaveTextContent(copy.suggestions.error);
    catalog.respond = working;
    await user.click(screen.getByRole('button', { name: copy.suggestions.retry }));
    expect(await suggestion('Leche entera')).toBeVisible();

    catalog.respond = () => Promise.reject(new Error('network down'));
    await user.click(screen.getByRole('button', { name: copy.add.submit }));
    expect(await within(await itemList()).findByRole('checkbox', { name: 'leche' })).toBeVisible();
  });

  it('suggests approximate results without a notice (PD-104)', async () => {
    const { catalog, lists } = withProducts([milk]);
    const working = catalog.respond;
    catalog.respond = async (params) => ({ ...(await working(params)), approximate: true });
    const stored = lists.seed();
    renderList(lists, stored.id, catalog);
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText(copy.add.text), 'lece');

    expect(await suggestion('Leche entera')).toBeVisible();
    expect(screen.queryByText(/Mostrando resultados parecidos/)).not.toBeInTheDocument();
  });

  it('shows the same suggestion error with a retry when search is unavailable (503, PD-105)', async () => {
    const { catalog, lists } = withProducts([milk]);
    const working = catalog.respond;
    catalog.respond = () =>
      Promise.reject(
        new ServerError({ type: 'about:blank', title: 'Service Unavailable', status: 503 }),
      );
    const stored = lists.seed();
    renderList(lists, stored.id, catalog);
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText(copy.add.text), 'leche');
    expect(await screen.findByRole('alert')).toHaveTextContent(copy.suggestions.error);
    catalog.respond = working;
    await user.click(screen.getByRole('button', { name: copy.suggestions.retry }));
    expect(await suggestion('Leche entera')).toBeVisible();
  });

  it('adds to the quantity of a product already in the list and unchecked (PD-45)', async () => {
    const { catalog, lists } = withProducts([milk]);
    const stored = lists.seed({
      items: [
        {
          id: 'a',
          position: 0,
          quantity: 2,
          checked: false,
          type: 'product',
          product: MILK,
          section: MILK.section,
          sectionOverride: null,
        },
      ],
    });
    renderList(lists, stored.id, catalog);
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText(copy.add.text), 'leche');
    await user.click(await suggestion('Leche entera'));

    await waitFor(() => {
      expect(
        screen.getByRole('checkbox', { name: 'Leche entera' }).closest('li'),
      ).toHaveTextContent('× 3');
    });
    expect(stored.items).toMatchObject([{ id: 'a', quantity: 3 }]);
  });

  it('says when the product could not be added, and keeps the text', async () => {
    const { catalog, lists } = withProducts([milk]);
    const stored = lists.seed();
    renderList(lists, stored.id, catalog);
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText(copy.add.text), 'leche');
    lists.changeError = new Error('network down');
    await user.click(await suggestion('Leche entera'));

    expect(await screen.findByText(copy.add.failed)).toBeVisible();
    expect(textField()).toHaveValue('leche');
  });
});

const OIL: ListProduct = {
  id: 'product-oil',
  name: 'Aceite de oliva',
  brand: 'Bonpreu',
  supermarketId: 'bonpreu',
  priceCents: 800,
  imageUrl: null,
  available: true,
  section: 'other',
};

function product(id: string, position: number, item: ListProduct, quantity = 1): ListItem {
  return {
    id,
    position,
    quantity,
    checked: false,
    type: 'product',
    product: item,
    section: item.section,
    sectionOverride: null,
  };
}

function text(id: string, position: number, value: string): ListItem {
  return {
    id,
    position,
    quantity: 1,
    checked: false,
    type: 'text',
    text: value,
    section: 'other',
    sectionOverride: null,
  };
}

/** Milk of Mercadona, oil of Bonpreu, a free-text item and a product removed from the catalog. */
function seedMixedList(lists: FakeShoppingLists) {
  return lists.seed({
    items: [
      product('milk', 0, MILK, 2),
      product('oil', 1, OIL),
      text('bread', 2, 'pan'),
      product('removed', 3, {
        id: 'product-removed',
        name: null,
        brand: null,
        supermarketId: null,
        priceCents: null,
        imageUrl: null,
        available: false,
        section: 'other',
      }),
    ],
  });
}

function renderAt(lists: FakeShoppingLists, path: string) {
  return renderApp(path, { auth: FakeAuthGateway.loggedInAs(maria), lists });
}

function filterGroup() {
  return screen.findByRole('group', { name: copy.filter.label });
}

async function chip(name: string) {
  return within(await filterGroup()).getByRole('button', { name: new RegExp(name, 'u') });
}

async function shownItemNames() {
  return within(await itemList())
    .getAllByRole('checkbox')
    .map((box) => box.getAttribute('aria-label'));
}

function hiddenNotice(count: number) {
  return (count === 1 ? copy.filter.hidden_one : copy.filter.hidden_other).replace(
    '{{count}}',
    String(count),
  );
}

describe('ListPageContainer: filter by supermarket (E13)', () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  it('offers "Todos" and one chip per supermarket with its total (PD-84)', async () => {
    const lists = new FakeShoppingLists();
    const stored = seedMixedList(lists);
    renderAt(lists, `/lists/${stored.id}`);

    const group = await filterGroup();
    expect(within(group).getByRole('button', { name: copy.filter.all })).toHaveAttribute(
      'aria-pressed',
      'true',
    );
    expect(await chip('Bonpreu')).toHaveTextContent(asShown(formatPrice(800)));
    expect(await chip('Mercadona')).toHaveTextContent(asShown(formatPrice(250)));
    expect(screen.queryByText(hiddenNotice(2))).not.toBeInTheDocument();
  });

  it('gives no chip to a supermarket whose products are all unavailable, and shows them under "Todos" (PD-84)', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({
      items: [product('milk', 0, MILK), product('oil', 1, { ...OIL, available: false })],
    });
    renderAt(lists, `/lists/${stored.id}`);

    const group = await filterGroup();
    expect(within(group).queryByRole('button', { name: /Bonpreu/u })).not.toBeInTheDocument();
    expect(await shownItemNames()).toEqual(['Leche entera', 'Aceite de oliva']);
  });

  it('shows only the products of the chosen supermarket and how many items without one are hidden (PD-85, PD-86)', async () => {
    const lists = new FakeShoppingLists();
    const stored = seedMixedList(lists);
    const router = renderAt(lists, `/lists/${stored.id}`);
    const user = userEvent.setup();

    await user.click(await chip('Mercadona'));

    expect(await shownItemNames()).toEqual(['Leche entera']);
    expect(await chip('Mercadona')).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByText(hiddenNotice(2))).toBeVisible();
    expect(locationOf(router)).toBe(`/lists/${stored.id}?supermarket=mercadona`);
  });

  it('shows no notice when the chosen supermarket hides no item without a supermarket (PD-85)', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({ items: [product('milk', 0, MILK), product('oil', 1, OIL)] });
    renderAt(lists, `/lists/${stored.id}?supermarket=bonpreu`);

    expect(await shownItemNames()).toEqual(['Aceite de oliva']);
    expect(screen.queryByText(/sin supermercado/u)).not.toBeInTheDocument();
  });

  it('goes back to "Todos" with every item still in the list (PD-83, PD-86)', async () => {
    const lists = new FakeShoppingLists();
    const stored = seedMixedList(lists);
    const router = renderAt(lists, `/lists/${stored.id}?supermarket=bonpreu`);
    const user = userEvent.setup();

    expect(await shownItemNames()).toEqual(['Aceite de oliva']);
    await user.click(await chip(copy.filter.all));

    expect(await shownItemNames()).toEqual([
      'Leche entera',
      'Aceite de oliva',
      'pan',
      copy.item.unknownProduct,
    ]);
    expect(locationOf(router)).toBe(`/lists/${stored.id}`);
    expect(stored.items).toHaveLength(4);
  });

  it('follows back and forward through the chosen supermarkets (PD-86)', async () => {
    const lists = new FakeShoppingLists();
    const stored = seedMixedList(lists);
    const router = renderAt(lists, `/lists/${stored.id}`);
    const user = userEvent.setup();

    await user.click(await chip('Bonpreu'));
    expect(await shownItemNames()).toEqual(['Aceite de oliva']);
    await act(() => router.navigate(-1));

    expect(await shownItemNames()).toHaveLength(4);
    await act(() => router.navigate(1));
    expect(await shownItemNames()).toEqual(['Aceite de oliva']);
  });

  it('shows "Todos" and drops a supermarket parameter that names no chip (PD-86)', async () => {
    const lists = new FakeShoppingLists();
    const stored = seedMixedList(lists);
    const router = renderAt(lists, `/lists/${stored.id}?supermarket=aldi`);

    expect(await shownItemNames()).toHaveLength(4);
    await waitFor(() => {
      expect(locationOf(router)).toBe(`/lists/${stored.id}`);
    });
  });

  it('updates the filtered view and the chip totals with changes of other members (PD-7, PD-85)', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const lists = new FakeShoppingLists();
    const stored = seedMixedList(lists);
    renderAt(lists, `/lists/${stored.id}?supermarket=bonpreu`);
    expect(await shownItemNames()).toEqual(['Aceite de oliva']);

    lists.addItemAsSomeoneElse(
      stored.id,
      product('oil-2', 0, {
        ...OIL,
        id: 'product-oil-2',
        name: 'Aceite de girasol',
        priceCents: 300,
      }),
    );
    await act(() => vi.advanceTimersByTimeAsync(LIST_REFRESH_INTERVAL_MS));

    await waitFor(async () => {
      expect(await shownItemNames()).toEqual(['Aceite de oliva', 'Aceite de girasol']);
    });
    expect(await chip('Bonpreu')).toHaveTextContent(asShown(formatPrice(1100)));
  });

  it('goes back to "Todos" when another member removes the last available product of the chosen supermarket (PD-86)', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const lists = new FakeShoppingLists();
    const stored = seedMixedList(lists);
    const router = renderAt(lists, `/lists/${stored.id}?supermarket=bonpreu`);
    expect(await shownItemNames()).toEqual(['Aceite de oliva']);

    stored.items = stored.items.filter((item) => item.id !== 'oil');
    stored.version++;
    await act(() => vi.advanceTimersByTimeAsync(LIST_REFRESH_INTERVAL_MS));

    await waitFor(() => {
      expect(locationOf(router)).toBe(`/lists/${stored.id}`);
    });
    expect(await shownItemNames()).toHaveLength(3);
  });

  it('saves a free-text item added while filtered and shows it under "Todos" (PD-87)', async () => {
    const lists = new FakeShoppingLists();
    const stored = seedMixedList(lists);
    renderAt(lists, `/lists/${stored.id}?supermarket=mercadona`);
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText(copy.add.text), 'fruta');
    await user.click(screen.getByRole('button', { name: copy.add.submit }));

    expect(await screen.findByText(hiddenNotice(3))).toBeVisible();
    expect(await shownItemNames()).toEqual(['Leche entera']);
    expect(stored.items.map((item) => item.id)).toHaveLength(5);
    await user.click(await chip(copy.filter.all));
    expect(await shownItemNames()).toContain('fruta');
  });
});

const sectionCopy = copy.sections;

/** The section headers shown, in order. */
async function sectionHeaders() {
  return within(await itemList())
    .queryAllByRole('heading', { level: 2 })
    .map((heading) => heading.textContent);
}

/** The item names under one section header. */
async function namesIn(section: string) {
  return within(within(await itemList()).getByRole('list', { name: section }))
    .getAllByRole('checkbox')
    .map((box) => box.getAttribute('aria-label'));
}

function sectionSelect(name: string) {
  return screen.findByRole('combobox', { name: copy.item.section.replace('{{name}}', name) });
}

const BREAD: ListProduct = {
  ...MILK,
  id: 'product-bread',
  name: 'Pan de molde',
  section: 'bakery',
};

describe('ListPageContainer: sections (E14)', () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  it('groups pending items under their section in the store order, "Otros" last (PD-90, PD-93)', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({
      items: [
        text('napkins', 0, 'servilletas'),
        product('milk', 1, MILK),
        product('bread', 2, BREAD),
        product('yogurt', 3, { ...MILK, id: 'product-yogurt', name: 'Yogur' }),
      ],
    });
    renderAt(lists, `/lists/${stored.id}`);

    expect(await sectionHeaders()).toEqual([
      sectionCopy.bakery,
      sectionCopy['dairy-and-eggs'],
      sectionCopy.other,
    ]);
    expect(await namesIn(sectionCopy['dairy-and-eggs'])).toEqual(['Leche entera', 'Yogur']);
    expect(await namesIn(sectionCopy.other)).toEqual(['servilletas']);
  });

  it('shows the checked items in one block at the end, in list order (PD-93)', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({
      items: [
        { ...product('bread', 0, BREAD), checked: true },
        product('milk', 1, MILK),
        { ...text('napkins', 2, 'servilletas'), checked: true },
      ],
    });
    renderAt(lists, `/lists/${stored.id}`);

    expect(await sectionHeaders()).toEqual([sectionCopy['dairy-and-eggs']]);
    expect(await shownItemNames()).toEqual(['Leche entera', 'Pan de molde', 'servilletas']);
  });

  it('moves a free-text item to the section a member chooses (PD-92)', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({ items: [text('bread', 0, 'pan'), product('milk', 1, MILK)] });
    renderAt(lists, `/lists/${stored.id}`);
    const user = userEvent.setup();

    await user.selectOptions(await sectionSelect('pan'), 'bakery');

    await waitFor(async () => {
      expect(await sectionHeaders()).toEqual([sectionCopy.bakery, sectionCopy['dairy-and-eggs']]);
    });
    expect(await namesIn(sectionCopy.bakery)).toEqual(['pan']);
    expect(stored.items[0]?.sectionOverride).toBe('bakery');
  });

  it('sends a product back to its catalog section with "Automática" (PD-92)', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({
      items: [{ ...product('milk', 0, MILK), section: 'drinks', sectionOverride: 'drinks' }],
    });
    renderAt(lists, `/lists/${stored.id}`);
    const user = userEvent.setup();
    expect(await sectionHeaders()).toEqual([sectionCopy.drinks]);

    await user.selectOptions(await sectionSelect('Leche entera'), '');

    await waitFor(async () => {
      expect(await sectionHeaders()).toEqual([sectionCopy['dairy-and-eggs']]);
    });
    expect(stored.items[0]?.sectionOverride).toBeNull();
  });

  it('drops a section once its last pending item is checked (PD-93)', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({ items: [product('bread', 0, BREAD), product('milk', 1, MILK)] });
    renderAt(lists, `/lists/${stored.id}`);
    const user = userEvent.setup();

    await user.click(await screen.findByRole('checkbox', { name: 'Pan de molde' }));

    await waitFor(async () => {
      expect(await sectionHeaders()).toEqual([sectionCopy['dairy-and-eggs']]);
    });
  });

  it('shows a section change of another member after the next poll (PD-7, PD-43)', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const lists = new FakeShoppingLists();
    const stored = lists.seed({ items: [text('bread', 0, 'pan')] });
    renderAt(lists, `/lists/${stored.id}`);
    expect(await sectionHeaders()).toEqual([sectionCopy.other]);

    lists.currentUser = JOAN;
    stored.members = [JOAN];
    await lists.updateItemSection(stored.id, 'bread', 'bakery');
    await act(() => vi.advanceTimersByTimeAsync(LIST_REFRESH_INTERVAL_MS));

    await waitFor(async () => {
      expect(await sectionHeaders()).toEqual([sectionCopy.bakery]);
    });
  });

  it('groups the products of the chosen supermarket the same way (PD-85, PD-93)', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({
      items: [
        product('bread', 0, BREAD),
        product('oil', 1, OIL),
        product('milk', 2, MILK),
        text('napkins', 3, 'servilletas'),
      ],
    });
    renderAt(lists, `/lists/${stored.id}?supermarket=mercadona`);

    expect(await sectionHeaders()).toEqual([sectionCopy.bakery, sectionCopy['dairy-and-eggs']]);
  });
});

describe('ListPageContainer: totals count pending items only (E17)', () => {
  function totalsRow(name: string) {
    return screen.findByRole('region', { name: copy.totals.title }).then((region) => {
      const row = within(region).getByText(name).closest('li');
      if (row === null) {
        throw new Error(`No totals row for ${name}.`);
      }
      return row;
    });
  }

  it('lowers the row total and the chip total when an item is checked, and restores them when it is unchecked (PD-120)', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({ items: [product('milk', 0, MILK, 2), product('oil', 1, OIL)] });
    renderAt(lists, `/lists/${stored.id}`);
    const user = userEvent.setup();
    expect(await totalsRow('Mercadona')).toHaveTextContent(asShown(formatPrice(250)));

    await user.click(await screen.findByRole('checkbox', { name: 'Leche entera' }));

    await waitFor(async () => {
      expect(await chip('Mercadona')).toHaveTextContent(asShown(formatPrice(0)));
    });
    expect(await totalsRow('Mercadona')).toHaveTextContent(asShown(formatPrice(0)));
    expect(await chip('Bonpreu')).toHaveTextContent(asShown(formatPrice(800)));

    await user.click(await screen.findByRole('checkbox', { name: 'Leche entera' }));

    await waitFor(async () => {
      expect(await chip('Mercadona')).toHaveTextContent(asShown(formatPrice(250)));
    });
    expect(await totalsRow('Mercadona')).toHaveTextContent(asShown(formatPrice(250)));
  });

  it('keeps a fully bought supermarket at 0 with its checked items under its chip (PD-84, PD-85, PD-121)', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({ items: [product('milk', 0, MILK), product('oil', 1, OIL)] });
    renderAt(lists, `/lists/${stored.id}`);
    const user = userEvent.setup();

    await user.click(await screen.findByRole('checkbox', { name: 'Leche entera' }));
    await waitFor(async () => {
      expect(await chip('Mercadona')).toHaveTextContent(asShown(formatPrice(0)));
    });
    const row = await totalsRow('Mercadona');
    expect(row).toHaveTextContent(asShown(formatPrice(0)));
    expect(row).toHaveTextContent('0 productos');

    await user.click(await chip('Mercadona'));

    expect(await shownItemNames()).toEqual(['Leche entera']);
    expect(screen.getByRole('checkbox', { name: 'Leche entera' })).toBeChecked();
  });
});
