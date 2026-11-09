/** Unit of a unit price or a package size, as the API sends it. */
export type Unit = 'kg' | 'l' | 'unit';

export interface Supermarket {
  /** Slug, e.g. "mercadona". */
  id: string;
  name: string;
}

export interface Packaging {
  /** Decimal quantity in `unit`, e.g. 1.5 for 1.5 L. */
  quantity: number;
  unit: Unit;
}

/** Price per kg, per litre or per unit, in integer cents. */
export interface UnitPrice {
  cents: number;
  unit: Unit;
}

export interface Product {
  id: string;
  name: string;
  brand: string | null;
  supermarket: Supermarket;
  packaging: Packaging | null;
  /** Shelf price in integer cents. */
  priceCents: number;
  unitPrice: UnitPrice | null;
  imageUrl: string | null;
}

/** A product looked up by id. It may be unavailable: lists still show it (PD-22). */
export interface ProductDetail extends Product {
  available: boolean;
}

/** Lowest unit price of each unit over the whole query, not only the page (PD-33). */
export type CheapestUnitPriceCents = Partial<Record<Unit, number>>;

export interface ProductSearchPage {
  items: Product[];
  page: number;
  perPage: number;
  total: number;
  cheapestUnitPriceCents: CheapestUnitPriceCents;
}
