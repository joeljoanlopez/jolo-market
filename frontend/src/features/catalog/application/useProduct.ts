import { useQuery } from '@tanstack/react-query';

import { NotFoundError } from '@/shared/http/errors';

import type { ProductDetail } from '../domain/product';
import { useProductCatalog } from './productCatalogContext';

export type ProductState =
  | { status: 'loading' }
  /** Unknown or malformed id: the API answers 404 for both. */
  | { status: 'notFound' }
  | { status: 'error'; retry: () => void }
  | { status: 'success'; product: ProductDetail };

/** One product by id, available or not (PD-22). */
export function useProduct(productId: string): ProductState {
  const catalog = useProductCatalog();
  const query = useQuery({
    queryKey: ['catalog', 'product', productId],
    queryFn: ({ signal }) => catalog.getProduct(productId, { signal }),
    retry: (failures, error) => !(error instanceof NotFoundError) && failures < 3,
  });

  if (query.error instanceof NotFoundError) {
    return { status: 'notFound' };
  }
  if (query.data !== undefined) {
    return { status: 'success', product: query.data };
  }
  if (query.isError) {
    return { status: 'error', retry: () => void query.refetch() };
  }
  return { status: 'loading' };
}
