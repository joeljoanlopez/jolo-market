import { useTranslation } from 'react-i18next';

import { isCheapest } from '../../domain/isCheapest';
import type { CheapestUnitPriceCents, Product } from '../../domain/product';
import { ProductCard } from './ProductCard';

interface ProductResultListProps {
  products: Product[];
  /** Lowest unit price of each unit over the whole query, so every page highlights right. */
  cheapestUnitPriceCents: CheapestUnitPriceCents;
}

export function ProductResultList({ products, cheapestUnitPriceCents }: ProductResultListProps) {
  const { t } = useTranslation();

  return (
    <ul aria-label={t('catalog.search.results')} className="flex flex-col gap-2">
      {products.map((product) => (
        <li key={product.id}>
          <ProductCard
            product={product}
            highlighted={isCheapest(product, cheapestUnitPriceCents)}
          />
        </li>
      ))}
    </ul>
  );
}
