import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { periodStart, type PriceHistory, type PriceHistoryPeriod } from '../domain/priceHistory';
import { useProductCatalog } from './productCatalogContext';

export type PriceHistoryState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  /** `isFetching` while another period loads: the previous history stays on screen meanwhile. */
  | { status: 'success'; history: PriceHistory; isFetching: boolean };

/**
 * The price history of a product over a period ending today (PD-48). `now` is injected so tests do not depend on the
 * date they run.
 */
export function usePriceHistory(
  productId: string,
  period: PriceHistoryPeriod,
  now: () => Date = () => new Date(),
): PriceHistoryState {
  const catalog = useProductCatalog();
  const from = periodStart(period, now());
  const query = useQuery({
    queryKey: ['catalog', 'price-history', productId, from],
    queryFn: ({ signal }) =>
      catalog.getPriceHistory(productId, from === null ? {} : { from }, { signal }),
    placeholderData: keepPreviousData,
  });

  if (query.data !== undefined) {
    return { status: 'success', history: query.data, isFetching: query.isFetching };
  }
  if (query.isError) {
    return { status: 'error', retry: () => void query.refetch() };
  }
  return { status: 'loading' };
}
