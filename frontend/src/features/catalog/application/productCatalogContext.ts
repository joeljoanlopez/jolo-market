import { createContext, useContext } from 'react';

import type { ProductCatalog } from './ProductCatalog';

/** Injects the ProductCatalog adapter; the app provides the API client, tests a fake. */
export const ProductCatalogContext = createContext<ProductCatalog | null>(null);

export function useProductCatalog(): ProductCatalog {
  const catalog = useContext(ProductCatalogContext);
  if (catalog === null) {
    throw new Error('useProductCatalog() needs a ProductCatalogContext provider.');
  }
  return catalog;
}
