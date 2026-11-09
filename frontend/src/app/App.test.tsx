import { render, screen } from '@testing-library/react';
import { createMemoryRouter } from 'react-router';
import { describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { FakeProductCatalog } from '@/test/FakeProductCatalog';
import { testI18n } from '@/test/i18n';
import { createTestQueryClient } from '@/test/render';

import { App } from './App';
import { routes } from './routes';

function renderAt(path: string) {
  const router = createMemoryRouter(routes, { initialEntries: [path] });
  render(
    <App
      router={router}
      queryClient={createTestQueryClient()}
      i18n={testI18n}
      productCatalog={new FakeProductCatalog()}
    />,
  );
}

describe('App', () => {
  it('shows the app shell with the home page at /', async () => {
    renderAt('/');

    expect(
      await screen.findByRole('heading', { level: 1, name: es.home.title }),
    ).toBeInTheDocument();
    expect(screen.getByRole('banner')).toHaveTextContent(es.app.name);
  });

  it('shows the search page at /search, with the catalog wired in', async () => {
    renderAt('/search?q=leche');

    expect(
      await screen.findByRole('heading', { level: 1, name: es.catalog.search.title }),
    ).toBeInTheDocument();
    expect(await screen.findByText('No hay resultados para «leche».')).toBeInTheDocument();
  });

  it('shows the not-found page for an unknown route', async () => {
    renderAt('/this-page-does-not-exist');

    expect(
      await screen.findByRole('heading', { level: 1, name: es.notFound.title }),
    ).toBeInTheDocument();
  });
});
