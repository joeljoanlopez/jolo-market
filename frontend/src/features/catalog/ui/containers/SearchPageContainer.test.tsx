import { QueryClientProvider } from '@tanstack/react-query';
import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { I18nextProvider } from 'react-i18next';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { beforeEach, describe, expect, it } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { FakeProductCatalog } from '@/test/FakeProductCatalog';
import { testI18n } from '@/test/i18n';
import { createTestQueryClient } from '@/test/render';

import { ProductCatalogContext } from '../../application/productCatalogContext';
import type { ProductSearchPage } from '../../domain/product';
import { aProduct } from '../components/testProduct';
import { SearchPageContainer } from './SearchPageContainer';

function resultsPage(page: number, overrides: Partial<ProductSearchPage> = {}): ProductSearchPage {
  return {
    items: [
      aProduct({
        id: `${String(page)}a`,
        name: `Leche ${String(page)}A`,
        unitPrice: { cents: 70, unit: 'l' },
      }),
      aProduct({
        id: `${String(page)}b`,
        name: `Leche ${String(page)}B`,
        unitPrice: { cents: 83, unit: 'l' },
      }),
    ],
    page,
    perPage: 2,
    total: 5,
    cheapestUnitPriceCents: { l: 70 },
    ...overrides,
  };
}

let catalog: FakeProductCatalog;

beforeEach(() => {
  catalog = new FakeProductCatalog();
  catalog.supermarkets = [
    { id: 'mercadona', name: 'Mercadona' },
    { id: 'aldi', name: 'Aldi' },
  ];
  catalog.respond = (params) => Promise.resolve(resultsPage(params.page ?? 1));
});

function renderAt(url: string) {
  const router = createMemoryRouter([{ path: '/search', element: <SearchPageContainer /> }], {
    initialEntries: [url],
  });
  render(
    <QueryClientProvider client={createTestQueryClient()}>
      <I18nextProvider i18n={testI18n}>
        <ProductCatalogContext value={catalog}>
          <RouterProvider router={router} />
        </ProductCatalogContext>
      </I18nextProvider>
    </QueryClientProvider>,
  );
  return router;
}

function searchBox(): HTMLElement {
  return screen.getByRole('searchbox', { name: es.catalog.search.label });
}

function filter(): HTMLElement {
  return screen.getByRole('combobox', { name: es.catalog.search.supermarket });
}

async function results(): Promise<string[]> {
  const list = await screen.findByRole('list', { name: es.catalog.search.results });
  return within(list)
    .getAllByRole('heading')
    .map((heading) => heading.textContent);
}

function urlOf(router: ReturnType<typeof renderAt>): string {
  return router.state.location.search;
}

describe('SearchPageContainer', () => {
  it('asks for at least 2 characters before searching', () => {
    renderAt('/search');

    expect(
      screen.getByRole('heading', { level: 1, name: es.catalog.search.title }),
    ).toBeInTheDocument();
    expect(screen.getByText(es.catalog.search.idle)).toBeInTheDocument();
    expect(catalog.searches).toEqual([]);
  });

  it('searches the text of the URL and shows the total and the results', async () => {
    renderAt('/search?q=leche');

    expect(screen.getByText(es.catalog.search.loading)).toBeInTheDocument();
    expect(await results()).toEqual(['Leche 1A', 'Leche 1B']);
    expect(screen.getByText('5 resultados')).toBeInTheDocument();
    expect(searchBox()).toHaveValue('leche');
    expect(catalog.searches).toEqual([{ q: 'leche' }]);
  });

  it('highlights the cheapest unit price of the query', async () => {
    renderAt('/search?q=leche');

    const items = within(
      await screen.findByRole('list', { name: es.catalog.search.results }),
    ).getAllByRole('listitem');
    expect(
      items.map((item) => within(item).queryByText(es.catalog.product.cheapest) !== null),
    ).toEqual([true, false]);
  });

  it('says when nothing matches', async () => {
    catalog.respond = () => Promise.resolve(resultsPage(1, { items: [], total: 0 }));
    renderAt('/search?q=xyz');

    expect(await screen.findByText('No hay resultados para «xyz».')).toBeInTheDocument();
  });

  it('shows an error that can be retried', async () => {
    catalog.respond = () => Promise.reject(new Error('boom'));
    renderAt('/search?q=leche');

    expect(await screen.findByText(es.catalog.search.error)).toBeInTheDocument();
    catalog.respond = (params) => Promise.resolve(resultsPage(params.page ?? 1));
    await userEvent.click(screen.getByRole('button', { name: es.catalog.search.retry }));

    expect(await results()).toEqual(['Leche 1A', 'Leche 1B']);
  });

  it('puts the typed text in the URL and goes back to the first page', async () => {
    const router = renderAt('/search?q=leche&page=2');
    await results();

    await userEvent.type(searchBox(), 's');

    expect(urlOf(router)).toBe('?q=leches');
    await waitFor(() => {
      expect(catalog.searches.at(-1)).toEqual({ q: 'leches' });
    });
  });

  it('goes idle as soon as the box is cleared', async () => {
    const router = renderAt('/search?q=leche');
    await results();

    await userEvent.clear(searchBox());

    expect(screen.getByText(es.catalog.search.idle)).toBeInTheDocument();
    expect(urlOf(router)).toBe('');
  });

  it('filters by supermarket from the first page, and "all" removes the filter', async () => {
    const router = renderAt('/search?q=leche&page=2');
    await results();

    await userEvent.selectOptions(filter(), 'Aldi');
    expect(urlOf(router)).toBe('?q=leche&supermarket=aldi');
    expect(await results()).toEqual(['Leche 1A', 'Leche 1B']);
    expect(catalog.searches.at(-1)).toEqual({ q: 'leche', supermarket: 'aldi' });

    await userEvent.selectOptions(filter(), es.catalog.search.allSupermarkets);
    expect(urlOf(router)).toBe('?q=leche');
  });

  it('reads the filter from the URL', async () => {
    renderAt('/search?q=leche&supermarket=aldi');

    await results();
    expect(filter()).toHaveValue('aldi');
    expect(catalog.searches).toEqual([{ q: 'leche', supermarket: 'aldi' }]);
  });

  it('moves between pages and keeps the current page on screen while the next loads', async () => {
    const router = renderAt('/search?q=leche');
    await results();
    let resolveNext: (page: ProductSearchPage) => void = () => undefined;
    catalog.respond = () =>
      new Promise((resolve) => {
        resolveNext = resolve;
      });

    await userEvent.click(screen.getByRole('button', { name: es.catalog.search.next }));

    expect(urlOf(router)).toBe('?q=leche&page=2');
    expect(await results()).toEqual(['Leche 1A', 'Leche 1B']);
    expect(screen.getByText('Página 1 de 3')).toBeInTheDocument();
    act(() => {
      resolveNext(resultsPage(2));
    });
    expect(await screen.findByText('Leche 2A')).toBeInTheDocument();
    expect(screen.getByText('Página 2 de 3')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: es.catalog.search.previous }));
    expect(urlOf(router)).toBe('?q=leche');
  });

  it('restores the text of the URL when going back', async () => {
    const router = renderAt('/search?q=leche');
    await results();
    await userEvent.selectOptions(filter(), 'Aldi');
    await userEvent.type(searchBox(), 's');

    await act(() => router.navigate(-1));

    expect(urlOf(router)).toBe('?q=leche');
    expect(searchBox()).toHaveValue('leche');
    expect(filter()).toHaveValue('');
  });
});
