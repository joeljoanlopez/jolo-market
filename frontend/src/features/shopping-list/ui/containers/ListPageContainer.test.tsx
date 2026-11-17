import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';

import { formatPrice } from '@/features/catalog/domain/format';
import type { Product } from '@/features/catalog/domain/product';
import { aProduct } from '@/features/catalog/ui/components/testProduct';
import es from '@/shared/i18n/locales/es.json';
import { FakeAuthGateway, maria } from '@/test/FakeAuthGateway';
import { FakeProductCatalog } from '@/test/FakeProductCatalog';
import { FakeShoppingLists, JOAN, MILK, type StoredList } from '@/test/FakeShoppingLists';
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
        { id: 'a', position: 0, quantity: 1, checked: true, type: 'text', text: 'pan' },
        { id: 'b', position: 1, quantity: 2, checked: false, type: 'text', text: 'huevos' },
        { id: 'c', position: 2, quantity: 1, checked: false, type: 'text', text: 'fruta' },
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
        { id: 'a', position: 0, quantity: 1, checked: false, type: 'text', text: 'pan' },
        { id: 'b', position: 1, quantity: 1, checked: false, type: 'text', text: 'huevos' },
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
      items: [{ id: 'a', position: 0, quantity: 1, checked: false, type: 'text', text: 'pan' }],
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
        { id: 'a', position: 0, quantity: 2, checked: false, type: 'product', product: MILK },
        {
          id: 'b',
          position: 1,
          quantity: 1,
          checked: false,
          type: 'product',
          product: { ...MILK, id: 'gone', name: 'Yogur natural', available: false },
        },
        { id: 'c', position: 2, quantity: 1, checked: false, type: 'text', text: 'pan' },
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
        { id: 'a', position: 0, quantity: 1, checked: false, type: 'product', product: MILK },
        {
          id: 'b',
          position: 1,
          quantity: 1,
          checked: false,
          type: 'product',
          product: {
            ...MILK,
            id: 'gone',
            name: null,
            brand: null,
            supermarketId: null,
            priceCents: null,
            available: false,
          },
        },
        { id: 'c', position: 2, quantity: 1, checked: false, type: 'text', text: 'pan' },
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
    expect(stored.items).toMatchObject([{ type: 'text', text: 'leche' }]);
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

  it('adds to the quantity of a product already in the list and unchecked (PD-45)', async () => {
    const { catalog, lists } = withProducts([milk]);
    const stored = lists.seed({
      items: [
        { id: 'a', position: 0, quantity: 2, checked: false, type: 'product', product: MILK },
      ],
    });
    renderList(lists, stored.id, catalog);
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText(copy.add.text), 'leche');
    await user.click(await suggestion('Leche entera'));

    await waitFor(() => {
      expect(
        within(screen.getByRole('list', { name: copy.page.items })).getByRole('listitem'),
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
