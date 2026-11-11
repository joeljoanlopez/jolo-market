import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { I18nextProvider } from 'react-i18next';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { describe, expect, it } from 'vitest';

import { ShoppingListsContext } from '@/features/shopping-list/application/shoppingListsContext';
import es from '@/shared/i18n/locales/es.json';
import { FakeShoppingLists } from '@/test/FakeShoppingLists';
import { testI18n } from '@/test/i18n';
import { createTestQueryClient } from '@/test/render';

import { HomePage } from './HomePage';

function renderHome() {
  const router = createMemoryRouter(
    [
      { path: '/', element: <HomePage /> },
      { path: '/search', element: null },
    ],
    { initialEntries: ['/'] },
  );
  render(
    <QueryClientProvider client={createTestQueryClient()}>
      <I18nextProvider i18n={testI18n}>
        <ShoppingListsContext value={new FakeShoppingLists()}>
          <RouterProvider router={router} />
        </ShoppingListsContext>
      </I18nextProvider>
    </QueryClientProvider>,
  );
  return router;
}

describe('HomePage', () => {
  it('shows the title and description in Spanish', () => {
    renderHome();

    expect(screen.getByRole('heading', { level: 1, name: es.home.title })).toBeInTheDocument();
    expect(screen.getByText(es.home.description)).toBeInTheDocument();
  });

  it('shows "My lists"', async () => {
    renderHome();

    expect(
      await screen.findByRole('heading', { level: 2, name: es.lists.mine.title }),
    ).toBeInTheDocument();
  });

  it('opens the search page with the typed text', async () => {
    const router = renderHome();

    await userEvent.type(
      screen.getByRole('searchbox', { name: es.home.searchLabel }),
      'leche entera',
    );
    await userEvent.click(screen.getByRole('button', { name: es.home.searchSubmit }));

    expect(router.state.location.pathname).toBe('/search');
    expect(router.state.location.search).toBe('?q=leche+entera');
  });
});
