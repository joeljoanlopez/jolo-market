import type { PriceHistory } from '../domain/priceHistory';
import type { ProductDetail, ProductSearchPage, Supermarket } from '../domain/product';

export interface SearchProductsParams {
  /** At least 2 characters after trimming; the API answers 422 otherwise. */
  q: string;
  /** Supermarket slug. Leave it out to search every supermarket. */
  supermarket?: string;
  /** 1-based. Leave it out for the first page. */
  page?: number;
}

/** Days as YYYY-MM-DD in Europe/Madrid, both inclusive (PD-47). Leave one out for an open end. */
export interface PriceHistoryRangeParams {
  from?: string;
  to?: string;
}

export interface CallOptions {
  signal?: AbortSignal;
}

/** Port to the catalog API, implemented in infrastructure/. */
export interface ProductCatalog {
  searchProducts(params: SearchProductsParams, options?: CallOptions): Promise<ProductSearchPage>;
  getProduct(id: string, options?: CallOptions): Promise<ProductDetail>;
  listSupermarkets(options?: CallOptions): Promise<Supermarket[]>;
  getPriceHistory(
    productId: string,
    range: PriceHistoryRangeParams,
    options?: CallOptions,
  ): Promise<PriceHistory>;
}
