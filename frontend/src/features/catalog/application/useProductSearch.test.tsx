import { QueryClientProvider } from '@tanstack/react-query';
import { act, renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { createTestQueryClient } from '@/test/render';

import type { ProductSearchPage } from '../domain/product';
import type { ProductCatalog, SearchProductsParams } from './ProductCatalog';
import { ProductCatalogContext } from './productCatalogContext';
import { type ProductSearchInput, useProductSearch } from './useProductSearch';

function page(number: number): ProductSearchPage {
  return {
    items: [
      {
        id: `p${String(number)}`,
        name: 'Leche entera',
        brand: null,
        supermarket: { id: 'mercadona', name: 'Mercadona' },
        packaging: null,
        priceCents: 125,
        unitPrice: { cents: 83, unit: 'l' },
        imageUrl: null,
      },
    ],
    page: number,
    perPage: 20,
    total: 40,
    cheapestUnitPriceCents: { l: 83 },
  };
}

/** In-memory ProductCatalog: records searches and answers with `respond`. */
class FakeProductCatalog implements ProductCatalog {
  readonly searches: SearchProductsParams[] = [];
  respond: (params: SearchProductsParams) => Promise<ProductSearchPage> = (params) =>
    Promise.resolve(page(params.page ?? 1));

  searchProducts(params: SearchProductsParams): Promise<ProductSearchPage> {
    this.searches.push(params);
    return this.respond(params);
  }

  getProduct(): never {
    throw new Error('Not used by useProductSearch.');
  }

  listSupermarkets(): never {
    throw new Error('Not used by useProductSearch.');
  }

  getPriceHistory(): never {
    throw new Error('Not used by useProductSearch.');
  }
}

let catalog: FakeProductCatalog;

function renderSearch(initial: ProductSearchInput) {
  const queryClient = createTestQueryClient();
  return renderHook((input: ProductSearchInput) => useProductSearch(input), {
    initialProps: initial,
    wrapper: ({ children }: { children: ReactNode }) => (
      <QueryClientProvider client={queryClient}>
        <ProductCatalogContext value={catalog}>{children}</ProductCatalogContext>
      </QueryClientProvider>
    ),
  });
}

async function elapse(ms: number): Promise<void> {
  await act(() => vi.advanceTimersByTimeAsync(ms));
}

beforeEach(() => {
  vi.useFakeTimers();
  catalog = new FakeProductCatalog();
});

afterEach(() => {
  vi.useRealTimers();
});

describe('useProductSearch', () => {
  it('searches the trimmed initial text at once, e.g. when landing on a search URL', async () => {
    const { result } = renderSearch({ text: '  leche ' });

    await elapse(0);

    expect(catalog.searches).toEqual([{ q: 'leche' }]);
    expect(result.current).toEqual({ status: 'success', data: page(1), isFetching: false });
  });

  it('searches a typed text once the user stops typing for 300 ms', async () => {
    const { rerender } = renderSearch({ text: '' });

    rerender({ text: 'leche' });
    await elapse(299);
    expect(catalog.searches).toEqual([]);

    await elapse(1);
    expect(catalog.searches).toEqual([{ q: 'leche' }]);
  });

  it('searches only the last text typed within the debounce delay', async () => {
    const { rerender } = renderSearch({ text: '' });

    rerender({ text: 'le' });
    await elapse(200);
    rerender({ text: 'lec' });
    await elapse(200);
    rerender({ text: 'leche' });
    await elapse(300);

    expect(catalog.searches).toEqual([{ q: 'leche' }]);
  });

  it.each(['', 'l', '  l  '])('stays idle without searching for %j', async (text) => {
    const { result } = renderSearch({ text });

    await elapse(1000);

    expect(catalog.searches).toEqual([]);
    expect(result.current).toEqual({ status: 'idle' });
  });

  it('passes the supermarket filter and the page', async () => {
    renderSearch({ text: 'leche', supermarket: 'mercadona', page: 2 });

    await elapse(300);

    expect(catalog.searches).toEqual([{ q: 'leche', supermarket: 'mercadona', page: 2 }]);
  });

  it('is loading while the first search is in flight', async () => {
    catalog.respond = () => new Promise(() => undefined);
    const { result } = renderSearch({ text: 'leche' });

    await elapse(300);

    expect(result.current).toEqual({ status: 'loading' });
  });

  it('exposes the error of a failed search', async () => {
    const failure = new Error('boom');
    catalog.respond = () => Promise.reject(failure);
    const { result } = renderSearch({ text: 'leche' });

    await elapse(300);
    await elapse(0);

    expect(result.current).toMatchObject({ status: 'error', error: failure });
    expect(result.current).toHaveProperty('retry', expect.any(Function) as unknown);
  });

  it('retries a failed search', async () => {
    catalog.respond = () => Promise.reject(new Error('boom'));
    const { result } = renderSearch({ text: 'leche' });
    await elapse(0);
    catalog.respond = (params) => Promise.resolve(page(params.page ?? 1));

    act(() => {
      if (result.current.status === 'error') {
        result.current.retry();
      }
    });
    await elapse(0);

    expect(catalog.searches).toHaveLength(2);
    expect(result.current).toEqual({ status: 'success', data: page(1), isFetching: false });
  });

  it('goes idle as soon as the text gets too short, without waiting for the debounce delay', async () => {
    const { result, rerender } = renderSearch({ text: 'leche' });
    await elapse(0);

    rerender({ text: 'l' });

    expect(result.current).toEqual({ status: 'idle' });
  });

  it('is loading while a typed text waits for the debounce delay', () => {
    const { result, rerender } = renderSearch({ text: '' });

    rerender({ text: 'leche' });

    expect(result.current).toEqual({ status: 'loading' });
  });

  it('keeps the previous results while the next page loads', async () => {
    const { result, rerender } = renderSearch({ text: 'leche', page: 1 });
    await elapse(300);
    await elapse(0);
    catalog.respond = () => new Promise(() => undefined);

    rerender({ text: 'leche', page: 2 });
    await elapse(0);

    expect(catalog.searches).toEqual([
      { q: 'leche', page: 1 },
      { q: 'leche', page: 2 },
    ]);
    expect(result.current).toEqual({ status: 'success', data: page(1), isFetching: true });
  });

  it('changes the filter without waiting for the debounce delay', async () => {
    const { rerender } = renderSearch({ text: 'leche' });
    await elapse(300);

    rerender({ text: 'leche', supermarket: 'aldi' });
    await elapse(0);

    expect(catalog.searches).toEqual([{ q: 'leche' }, { q: 'leche', supermarket: 'aldi' }]);
  });
});
