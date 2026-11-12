import type { ProductDetail } from '@/features/catalog/domain/product';

export function testProductDetail(overrides: Partial<ProductDetail> = {}): ProductDetail {
  return {
    id: 'milk',
    name: 'Leche entera',
    brand: 'Hacendado',
    supermarket: { id: 'mercadona', name: 'Mercadona' },
    packaging: { quantity: 1.5, unit: 'l' },
    priceCents: 125,
    unitPrice: { cents: 83, unit: 'l' },
    imageUrl: null,
    available: true,
    ...overrides,
  };
}
