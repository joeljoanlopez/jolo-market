import type {
  PriceHistoryRangeParams,
  ProductCatalog,
  SearchProductsParams,
} from '@/features/catalog/application/ProductCatalog';
import type { PriceHistory } from '@/features/catalog/domain/priceHistory';
import type {
  ProductDetail,
  ProductSearchPage,
  Supermarket,
} from '@/features/catalog/domain/product';
import { NotFoundError } from '@/shared/http/errors';

/** In-memory ProductCatalog: records searches and answers with `respond`. */
export class FakeProductCatalog implements ProductCatalog {
  readonly searches: SearchProductsParams[] = [];
  supermarkets: Supermarket[] = [];
  respond: (params: SearchProductsParams) => Promise<ProductSearchPage> = () =>
    Promise.resolve({ items: [], page: 1, perPage: 20, total: 0, cheapestUnitPriceCents: {} });

  /** Products `getProduct` finds; any other id is a 404. */
  readonly products = new Map<string, ProductDetail>();
  /** Every price history request, in order. */
  readonly historyRequests: { productId: string; range: PriceHistoryRangeParams }[] = [];
  /** Answers price history requests; by default an empty history for a known product, a 404 otherwise. */
  respondHistory: (productId: string, range: PriceHistoryRangeParams) => Promise<PriceHistory> = (
    productId,
  ) =>
    this.products.has(productId)
      ? Promise.resolve({ productId, points: [] })
      : Promise.reject(notFound());

  searchProducts(params: SearchProductsParams): Promise<ProductSearchPage> {
    this.searches.push(params);
    return this.respond(params);
  }

  getProduct(id: string): Promise<ProductDetail> {
    const product = this.products.get(id);
    return product === undefined ? Promise.reject(notFound()) : Promise.resolve(product);
  }

  listSupermarkets(): Promise<Supermarket[]> {
    return Promise.resolve(this.supermarkets);
  }

  getPriceHistory(productId: string, range: PriceHistoryRangeParams): Promise<PriceHistory> {
    this.historyRequests.push({ productId, range });
    return this.respondHistory(productId, range);
  }
}

function notFound(): NotFoundError {
  return new NotFoundError({ type: 'about:blank', title: 'Not Found', status: 404 });
}
