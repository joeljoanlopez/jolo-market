import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import type { ProductSearchPage } from '../domain/product';
import type { SearchProductsParams } from './ProductCatalog';
import { useProductCatalog } from './productCatalogContext';

/** The API rejects shorter texts (after trimming) with a 422. */
export const MIN_SEARCH_LENGTH = 2;
export const SEARCH_DEBOUNCE_MS = 300;

export interface ProductSearchInput {
  /** Raw text of the search box; it is debounced and trimmed here. */
  text: string;
  /** Supermarket slug; leave it out for every supermarket. */
  supermarket?: string;
  /** 1-based page. The caller resets it when the text or the filter changes. */
  page?: number;
}

export type ProductSearchState =
  /** The text is too short to search. */
  | { status: 'idle' }
  /** The first search is in flight, or a typed text waits for the debounce delay. */
  | { status: 'loading' }
  | { status: 'error'; error: Error; retry: () => void }
  /** `isFetching` is true while a newer search runs and `data` still shows the previous one. */
  | { status: 'success'; data: ProductSearchPage; isFetching: boolean };

/**
 * Searches the catalog as the user types: the text waits SEARCH_DEBOUNCE_MS without
 * changes before it is sent, while the filter and the page apply at once. A text that
 * gets too short (e.g. the box is cleared) goes idle at once, without the delay.
 */
export function useProductSearch({
  text,
  supermarket,
  page,
}: ProductSearchInput): ProductSearchState {
  const catalog = useProductCatalog();
  const q = useDebouncedValue(text, SEARCH_DEBOUNCE_MS).trim();
  const typedEnough = text.trim().length >= MIN_SEARCH_LENGTH;
  const enabled = typedEnough && q.length >= MIN_SEARCH_LENGTH;

  const params: SearchProductsParams = { q };
  if (supermarket !== undefined) {
    params.supermarket = supermarket;
  }
  if (page !== undefined) {
    params.page = page;
  }

  const query = useQuery({
    queryKey: ['catalog', 'products', params],
    queryFn: ({ signal }) => catalog.searchProducts(params, { signal }),
    enabled,
    placeholderData: keepPreviousData,
  });

  if (!typedEnough) {
    return { status: 'idle' };
  }
  if (query.isError) {
    return {
      status: 'error',
      error: query.error,
      retry: () => {
        void query.refetch();
      },
    };
  }
  if (!enabled || query.data === undefined) {
    return { status: 'loading' };
  }
  return { status: 'success', data: query.data, isFetching: query.isFetching };
}

function useDebouncedValue<T>(value: T, delayMs: number): T {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const timeout = setTimeout(() => {
      setDebounced(value);
    }, delayMs);
    return () => {
      clearTimeout(timeout);
    };
  }, [value, delayMs]);

  return debounced;
}
