import type { CheapestUnitPriceCents, Product } from './product';

/** True when the product has the lowest unit price of its unit group (PD-33). */
export function isCheapest(product: Product, cheapest: CheapestUnitPriceCents): boolean {
  if (product.unitPrice === null) {
    return false;
  }
  return cheapest[product.unitPrice.unit] === product.unitPrice.cents;
}
