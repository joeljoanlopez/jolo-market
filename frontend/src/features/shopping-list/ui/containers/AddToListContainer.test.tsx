import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';

import { aProduct } from '@/features/catalog/ui/components/testProduct';
import es from '@/shared/i18n/locales/es.json';
import { FakeAuthGateway, maria } from '@/test/FakeAuthGateway';
import { FakeProductCatalog } from '@/test/FakeProductCatalog';
import { FakeShoppingLists, JOAN, MILK } from '@/test/FakeShoppingLists';
import { renderApp } from '@/test/renderApp';

const copy = es.lists.addToList;

function renderSearch(lists: FakeShoppingLists) {
  const catalog = new FakeProductCatalog();
  catalog.respond = () =>
    Promise.resolve({
      items: [aProduct({ id: MILK.id, name: 'Leche entera' })],
      page: 1,
      perPage: 20,
      total: 1,
      cheapestUnitPriceCents: {},
    });
  renderApp('/search?q=leche', { auth: FakeAuthGateway.loggedInAs(maria), catalog, lists });
}

describe('AddToListContainer (search results)', () => {
  it('adds the product to one of my lists, owned or shared with me', async () => {
    const lists = new FakeShoppingLists();
    lists.seed({ name: 'Compra semanal' });
    const shared = lists.seed({ name: 'Cena', owner: JOAN, members: [lists.currentUser] });
    renderSearch(lists);
    const user = userEvent.setup();

    await user.click(
      await screen.findByRole('button', { name: 'Añadir Leche entera a una lista' }),
    );
    const picker = screen.getByRole('group', { name: 'Añadir Leche entera a una lista' });
    await user.selectOptions(await within(picker).findByLabelText(copy.list), 'Cena');
    await user.click(within(picker).getByRole('button', { name: copy.submit }));

    expect(await screen.findByText('Añadido a «Cena».')).toBeVisible();
    expect(shared.items).toMatchObject([
      { type: 'product', product: { id: MILK.id }, quantity: 1 },
    ]);
  });

  it('offers to create a list when there is none yet', async () => {
    renderSearch(new FakeShoppingLists());
    const user = userEvent.setup();

    await user.click(
      await screen.findByRole('button', { name: 'Añadir Leche entera a una lista' }),
    );

    expect(await screen.findByText(copy.noLists)).toBeVisible();
    expect(screen.getByRole('link', { name: copy.createList })).toHaveAttribute('href', '/');
  });

  it('can be closed without adding anything', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({ name: 'Cena' });
    renderSearch(lists);
    const user = userEvent.setup();

    await user.click(
      await screen.findByRole('button', { name: 'Añadir Leche entera a una lista' }),
    );
    await user.click(await screen.findByRole('button', { name: copy.cancel }));

    expect(screen.getByRole('button', { name: 'Añadir Leche entera a una lista' })).toBeVisible();
    expect(stored.items).toEqual([]);
  });
});
