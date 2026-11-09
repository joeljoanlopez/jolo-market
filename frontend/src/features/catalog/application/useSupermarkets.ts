import { useQuery } from '@tanstack/react-query';

import type { Supermarket } from '../domain/product';
import { useProductCatalog } from './productCatalogContext';

/**
 * The enabled supermarkets, for the search filter. While they load, or if they fail,
 * it is an empty list: the filter then offers only "all supermarkets".
 */
export function useSupermarkets(): Supermarket[] {
  const catalog = useProductCatalog();
  const query = useQuery({
    queryKey: ['catalog', 'supermarkets'],
    queryFn: ({ signal }) => catalog.listSupermarkets({ signal }),
    // ENABLED_SUPERMARKETS only changes with a deploy.
    staleTime: Infinity,
  });

  return query.data ?? [];
}
