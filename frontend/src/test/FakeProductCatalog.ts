import type {
  ProductCatalog,
  SearchProductsParams,
} from '@/features/catalog/application/ProductCatalog';
import type { ProductSearchPage, Supermarket } from '@/features/catalog/domain/product';

/** In-memory ProductCatalog: records searches and answers with `respond`. */
export class FakeProductCatalog implements ProductCatalog {
  readonly searches: SearchProductsParams[] = [];
  supermarkets: Supermarket[] = [];
  respond: (params: SearchProductsParams) => Promise<ProductSearchPage> = () =>
    Promise.resolve({ items: [], page: 1, perPage: 20, total: 0, cheapestUnitPriceCents: {} });

  searchProducts(params: SearchProductsParams): Promise<ProductSearchPage> {
    this.searches.push(params);
    return this.respond(params);
  }

  getProduct(): never {
    throw new Error('getProduct is not faked yet.');
  }

  listSupermarkets(): Promise<Supermarket[]> {
    return Promise.resolve(this.supermarkets);
  }
}
