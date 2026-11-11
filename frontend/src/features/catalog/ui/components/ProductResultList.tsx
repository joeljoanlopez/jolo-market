import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

import { isCheapest } from '../../domain/isCheapest';
import type { CheapestUnitPriceCents, Product } from '../../domain/product';
import { ProductCard } from './ProductCard';

interface ProductResultListProps {
  products: Product[];
  /** Lowest unit price of each unit over the whole query, so every page highlights right. */
  cheapestUnitPriceCents: CheapestUnitPriceCents;
  /** Actions shown on each product, e.g. "Add to list". */
  renderActions?: (product: Product) => ReactNode;
}

export function ProductResultList({
  products,
  cheapestUnitPriceCents,
  renderActions,
}: ProductResultListProps) {
  const { t } = useTranslation();

  return (
    <ul aria-label={t('catalog.search.results')} className="flex flex-col gap-2">
      {products.map((product) => (
        <li key={product.id}>
          <ProductCard
            product={product}
            highlighted={isCheapest(product, cheapestUnitPriceCents)}
            actions={renderActions?.(product)}
          />
        </li>
      ))}
    </ul>
  );
}
