import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { FakeAuthGateway, maria } from '@/test/FakeAuthGateway';
import { FakeShoppingLists, JOAN } from '@/test/FakeShoppingLists';
import { locationOf, renderApp } from '@/test/renderApp';

const copy = es.lists;

function renderHome(lists: FakeShoppingLists) {
  return renderApp('/', { auth: FakeAuthGateway.loggedInAs(maria), lists });
}

describe('MyListsContainer (home page)', () => {
  it('shows the lists I own and the ones shared with me, with my role and their size', async () => {
    const lists = new FakeShoppingLists();
    lists.seed({ name: 'Compra semanal' });
    lists.seed({ name: 'Cena del sábado', owner: JOAN, members: [lists.currentUser] });
    renderHome(lists);

    const list = await screen.findByRole('list', { name: copy.mine.title });
    const [first, second] = within(list).getAllByRole('listitem');
    expect(within(first ?? list).getByRole('link', { name: 'Compra semanal' })).toHaveAttribute(
      'href',
      '/lists/list-1',
    );
    expect(first).toHaveTextContent(copy.mine.owned);
    expect(first).toHaveTextContent('0 elementos');
    expect(second).toHaveTextContent('Cena del sábado');
    expect(second).toHaveTextContent('Compartida por joan');
  });

  it('says there are no lists yet', async () => {
    renderHome(new FakeShoppingLists());

    expect(await screen.findByText(copy.mine.empty)).toBeVisible();
  });

  it('creates a list and opens it', async () => {
    const lists = new FakeShoppingLists();
    const router = renderHome(lists);
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText(copy.create.name), 'Barbacoa');
    await user.click(screen.getByRole('button', { name: copy.create.submit }));

    await screen.findByRole('heading', { level: 1, name: 'Barbacoa' });
    expect(locationOf(router)).toBe('/lists/list-1');
  });

  it('rejects an empty name without creating anything (PD-45)', async () => {
    const lists = new FakeShoppingLists();
    renderHome(lists);
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText(copy.create.name), '   ');
    await user.click(screen.getByRole('button', { name: copy.create.submit }));

    expect(await screen.findByText(copy.create.invalid)).toBeVisible();
    expect(lists.lists.size).toBe(0);
  });

  it('keeps the product search of the home page', async () => {
    renderHome(new FakeShoppingLists());

    expect(await screen.findByRole('searchbox', { name: es.home.searchLabel })).toBeVisible();
  });
});
