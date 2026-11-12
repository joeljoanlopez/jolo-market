import { waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { FakeProductCatalog } from '@/test/FakeProductCatalog';
import { renderHookWithCatalog } from '@/test/renderHookWithCatalog';
import { testProductDetail } from '@/test/testProductDetail';
import { ServerError } from '@/shared/http/errors';

import { useProduct } from './useProduct';

describe('useProduct', () => {
  it('is loading, then returns the product', async () => {
    const catalog = new FakeProductCatalog();
    const milk = testProductDetail();
    catalog.products.set(milk.id, milk);

    const { result } = renderHookWithCatalog(() => useProduct('milk'), catalog);

    expect(result.current).toEqual({ status: 'loading' });
    await waitFor(() => {
      expect(result.current).toEqual({ status: 'success', product: milk });
    });
  });

  it('is not found for an unknown product', async () => {
    const { result } = renderHookWithCatalog(() => useProduct('unknown'), new FakeProductCatalog());

    await waitFor(() => {
      expect(result.current).toEqual({ status: 'notFound' });
    });
  });

  it('fails after its retries, with a retry that loads the product again', async () => {
    const catalog = new FakeProductCatalog();
    const milk = testProductDetail();
    let calls = 0;
    catalog.getProduct = () => {
      calls += 1;
      // The first request and its three automatic retries fail.
      return calls <= 4
        ? Promise.reject(new ServerError({ type: 'about:blank', title: 'Error', status: 500 }))
        : Promise.resolve(milk);
    };

    const { result } = renderHookWithCatalog(() => useProduct('milk'), catalog);

    await waitFor(() => {
      expect(result.current.status).toBe('error');
    });
    if (result.current.status === 'error') {
      result.current.retry();
    }
    await waitFor(() => {
      expect(result.current).toEqual({ status: 'success', product: milk });
    });
  });
});
