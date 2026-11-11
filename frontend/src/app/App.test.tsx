import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { FakeAuthGateway, maria } from '@/test/FakeAuthGateway';
import { renderApp } from '@/test/renderApp';

function renderLoggedInAt(path: string) {
  return renderApp(path, { auth: FakeAuthGateway.loggedInAs(maria) });
}

describe('App', () => {
  it('shows the app shell with the home page at /', async () => {
    renderLoggedInAt('/');

    expect(
      await screen.findByRole('heading', { level: 1, name: es.home.title }),
    ).toBeInTheDocument();
    expect(screen.getByRole('banner')).toHaveTextContent(es.app.name);
  });

  it('shows the search page at /search, with the catalog wired in', async () => {
    renderLoggedInAt('/search?q=leche');

    expect(
      await screen.findByRole('heading', { level: 1, name: es.catalog.search.title }),
    ).toBeInTheDocument();
    expect(await screen.findByText('No hay resultados para «leche».')).toBeInTheDocument();
  });

  it('shows the not-found page for an unknown route', async () => {
    renderLoggedInAt('/this-page-does-not-exist');

    expect(
      await screen.findByRole('heading', { level: 1, name: es.notFound.title }),
    ).toBeInTheDocument();
  });
});
