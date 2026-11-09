import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { I18nextProvider } from 'react-i18next';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { testI18n } from '@/test/i18n';

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
    <I18nextProvider i18n={testI18n}>
      <RouterProvider router={router} />
    </I18nextProvider>,
  );
  return router;
}

describe('HomePage', () => {
  it('shows the title and description in Spanish', () => {
    renderHome();

    expect(screen.getByRole('heading', { level: 1, name: es.home.title })).toBeInTheDocument();
    expect(screen.getByText(es.home.description)).toBeInTheDocument();
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
