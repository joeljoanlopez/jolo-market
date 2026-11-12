import { waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { FakeProductCatalog } from '@/test/FakeProductCatalog';
import { renderHookWithCatalog } from '@/test/renderHookWithCatalog';
import { testProductDetail } from '@/test/testProductDetail';
import { ServerError } from '@/shared/http/errors';

import type { PriceHistory, PriceHistoryPeriod } from '../domain/priceHistory';
import { usePriceHistory } from './usePriceHistory';

const now = () => new Date('2026-11-12T10:00:00Z');

function catalogWithMilk(): FakeProductCatalog {
  const catalog = new FakeProductCatalog();
  catalog.products.set('milk', testProductDetail());
  return catalog;
}

const history: PriceHistory = {
  productId: 'milk',
  points: [{ capturedAt: new Date('2026-11-08T04:00:00Z'), priceCents: 125, unitPrice: null }],
};

describe('usePriceHistory', () => {
  it('is loading, then returns the history', async () => {
    const catalog = catalogWithMilk();
    catalog.respondHistory = () => Promise.resolve(history);

    const { result } = renderHookWithCatalog(() => usePriceHistory('milk', '6m', now), catalog);

    expect(result.current).toEqual({ status: 'loading' });
    await waitFor(() => {
      expect(result.current).toEqual({ status: 'success', history, isFetching: false });
    });
  });

  it('asks for the days of the period up to today', async () => {
    const catalog = catalogWithMilk();

    renderHookWithCatalog(() => usePriceHistory('milk', '30d', now), catalog);

    await waitFor(() => {
      expect(catalog.historyRequests).toEqual([
        { productId: 'milk', range: { from: '2026-10-13' } },
      ]);
    });
  });

  it('asks for the whole history without a range', async () => {
    const catalog = catalogWithMilk();

    renderHookWithCatalog(() => usePriceHistory('milk', 'all', now), catalog);

    await waitFor(() => {
      expect(catalog.historyRequests).toEqual([{ productId: 'milk', range: {} }]);
    });
  });

  it('keeps the previous history on screen while another period loads', async () => {
    const catalog = catalogWithMilk();
    catalog.respondHistory = () => Promise.resolve(history);
    const { result, rerender } = renderHookWithCatalog(
      (period: PriceHistoryPeriod) => usePriceHistory('milk', period, now),
      catalog,
      { initialProps: '6m' },
    );
    await waitFor(() => {
      expect(result.current.status).toBe('success');
    });
    catalog.respondHistory = () => new Promise(() => undefined);

    rerender('1y');

    await waitFor(() => {
      expect(result.current).toEqual({ status: 'success', history, isFetching: true });
    });
    expect(catalog.historyRequests.at(-1)).toEqual({
      productId: 'milk',
      range: { from: '2025-11-12' },
    });
  });

  it('fails with a retry that asks again', async () => {
    const catalog = catalogWithMilk();
    catalog.respondHistory = () =>
      Promise.reject(new ServerError({ type: 'about:blank', title: 'Error', status: 500 }));

    const { result } = renderHookWithCatalog(() => usePriceHistory('milk', '6m', now), catalog);

    await waitFor(() => {
      expect(result.current.status).toBe('error');
    });
    catalog.respondHistory = () => Promise.resolve(history);
    if (result.current.status === 'error') {
      result.current.retry();
    }
    await waitFor(() => {
      expect(result.current).toEqual({ status: 'success', history, isFetching: false });
    });
  });
});
