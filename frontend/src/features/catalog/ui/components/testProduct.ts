import type { Product } from '../../domain/product';

/** A product for UI tests; override what the test is about. */
export function aProduct(overrides: Partial<Product> = {}): Product {
  return {
    id: 'p1',
    name: 'Leche entera',
    brand: 'Hacendado',
    supermarket: { id: 'mercadona', name: 'Mercadona' },
    packaging: { quantity: 1.5, unit: 'l' },
    priceCents: 125,
    unitPrice: { cents: 83, unit: 'l' },
    imageUrl: 'https://img.example/leche.jpg',
    ...overrides,
  };
}

/** Testing Library collapses the non-breaking space that Intl writes before "€". */
export function asShown(text: string): string {
  return text.replace(/\s/gu, ' ');
}
