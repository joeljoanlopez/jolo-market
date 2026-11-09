import { describe, expect, it } from 'vitest';

import { isCheapest } from './isCheapest';
import type { Product } from './product';

function product(unitPrice: Product['unitPrice']): Product {
  return {
    id: 'p1',
    name: 'Leche entera',
    brand: null,
    supermarket: { id: 'mercadona', name: 'Mercadona' },
    packaging: null,
    priceCents: 125,
    unitPrice,
    imageUrl: null,
  };
}

describe('isCheapest', () => {
  it('is true when the unit price is the cheapest of its unit', () => {
    expect(isCheapest(product({ cents: 70, unit: 'l' }), { l: 70, kg: 900 })).toBe(true);
  });

  it('is false when the unit price is above the cheapest of its unit', () => {
    expect(isCheapest(product({ cents: 83, unit: 'l' }), { l: 70 })).toBe(false);
  });

  it('compares only with the cheapest of the same unit', () => {
    expect(isCheapest(product({ cents: 70, unit: 'kg' }), { l: 70, kg: 900 })).toBe(false);
  });

  it('is false when the product has no unit price', () => {
    expect(isCheapest(product(null), { l: 70 })).toBe(false);
  });
});
