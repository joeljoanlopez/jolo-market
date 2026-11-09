import { UnexpectedResponseError } from '@/shared/http/errors';
import type { HttpClient } from '@/shared/http/httpClient';

import type { ProductCatalog } from '../application/ProductCatalog';
import type {
  CheapestUnitPriceCents,
  Packaging,
  Product,
  ProductDetail,
  ProductSearchPage,
  Supermarket,
  Unit,
  UnitPrice,
} from '../domain/product';

/** Catalog API client (docs/architecture.md#2-search-and-compare). */
export function createCatalogApi(http: HttpClient): ProductCatalog {
  return {
    async searchProducts({ q, supermarket, page }, options) {
      const params = new URLSearchParams({ q });
      if (supermarket !== undefined) {
        params.set('supermarket', supermarket);
      }
      if (page !== undefined) {
        params.set('page', String(page));
      }
      return toSearchPage(await http.get(`/api/products?${params.toString()}`, options));
    },

    async getProduct(id, options) {
      return toProductDetail(await http.get(`/api/products/${encodeURIComponent(id)}`, options));
    },

    async listSupermarkets(options) {
      return list(await http.get('/api/supermarkets', options), 'supermarkets').map(toSupermarket);
    },
  };
}

type Json = Record<string, unknown>;

function toSearchPage(body: unknown): ProductSearchPage {
  const json = object(body, 'search page');
  return {
    items: list(json.items, 'items').map(toProduct),
    page: integer(json.page, 'page'),
    perPage: integer(json.perPage, 'perPage'),
    total: integer(json.total, 'total'),
    cheapestUnitPriceCents: toCheapest(json.cheapestUnitPriceCents),
  };
}

function toProductDetail(body: unknown): ProductDetail {
  const json = object(body, 'product');
  if (typeof json.available !== 'boolean') {
    throw unexpected('product.available', json.available);
  }
  return { ...toProduct(json), available: json.available };
}

function toProduct(value: unknown): Product {
  const json = object(value, 'product');
  return {
    id: string(json.id, 'product.id'),
    name: string(json.name, 'product.name'),
    brand: nullable(json.brand, (brand) => string(brand, 'product.brand')),
    supermarket: toSupermarket(json.supermarket),
    packaging: nullable(json.packaging, toPackaging),
    priceCents: integer(json.priceCents, 'product.priceCents'),
    unitPrice: nullable(json.unitPrice, toUnitPrice),
    imageUrl: nullable(json.imageUrl, (url) => string(url, 'product.imageUrl')),
  };
}

function toSupermarket(value: unknown): Supermarket {
  const json = object(value, 'supermarket');
  return { id: string(json.id, 'supermarket.id'), name: string(json.name, 'supermarket.name') };
}

function toPackaging(value: unknown): Packaging {
  const json = object(value, 'packaging');
  if (typeof json.quantity !== 'number' || !Number.isFinite(json.quantity)) {
    throw unexpected('packaging.quantity', json.quantity);
  }
  return { quantity: json.quantity, unit: unit(json.unit, 'packaging.unit') };
}

function toUnitPrice(value: unknown): UnitPrice {
  const json = object(value, 'unitPrice');
  return { cents: integer(json.cents, 'unitPrice.cents'), unit: unit(json.unit, 'unitPrice.unit') };
}

function toCheapest(value: unknown): CheapestUnitPriceCents {
  const cheapest: CheapestUnitPriceCents = {};
  for (const [key, cents] of Object.entries(object(value, 'cheapestUnitPriceCents'))) {
    cheapest[unit(key, 'cheapestUnitPriceCents key')] = integer(
      cents,
      `cheapestUnitPriceCents.${key}`,
    );
  }
  return cheapest;
}

function object(value: unknown, field: string): Json {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) {
    throw unexpected(field, value);
  }
  return value as Json;
}

function list(value: unknown, field: string): unknown[] {
  if (!Array.isArray(value)) {
    throw unexpected(field, value);
  }
  return value;
}

function string(value: unknown, field: string): string {
  if (typeof value !== 'string') {
    throw unexpected(field, value);
  }
  return value;
}

function integer(value: unknown, field: string): number {
  if (!Number.isInteger(value)) {
    throw unexpected(field, value);
  }
  return value as number;
}

const units: readonly string[] = ['kg', 'l', 'unit'] satisfies Unit[];

function unit(value: unknown, field: string): Unit {
  if (typeof value !== 'string' || !units.includes(value)) {
    throw unexpected(field, value);
  }
  return value as Unit;
}

function nullable<T>(value: unknown, map: (value: unknown) => T): T | null {
  return value === null ? null : map(value);
}

function unexpected(field: string, value: unknown): UnexpectedResponseError {
  const shown = value === undefined ? 'missing' : JSON.stringify(value);
  return new UnexpectedResponseError(`Unexpected catalog response: ${field} is ${shown}.`);
}
