import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

import { Text } from '@/shared/ui/atoms/Text/Text';

import { formatPackaging } from '../../domain/format';
import type { Product } from '../../domain/product';
import { useUnitSymbols } from '../useUnitSymbols';
import { PriceTag } from './PriceTag';
import { SupermarketBadge } from './SupermarketBadge';

interface ProductCardProps {
  product: Product;
  /** The cheapest unit price of its unit group (PD-33). */
  highlighted?: boolean;
  /** Actions on the product from other features, e.g. "Add to list". */
  actions?: ReactNode;
}

export function ProductCard({ product, highlighted = false, actions }: ProductCardProps) {
  const { t } = useTranslation();
  const unitSymbols = useUnitSymbols();

  return (
    <article className="flex gap-3 rounded-lg border border-border bg-background p-3">
      {product.imageUrl === null ? (
        <div
          role="img"
          aria-label={t('catalog.product.noImage')}
          className="size-16 shrink-0 rounded-md bg-surface"
        />
      ) : (
        <img
          src={product.imageUrl}
          alt={t('catalog.product.imageAlt', { name: product.name })}
          loading="lazy"
          className="size-16 shrink-0 rounded-md object-contain"
        />
      )}
      <div className="flex min-w-0 flex-1 flex-col items-start gap-1">
        <Text as="h2" weight="medium">
          {product.name}
        </Text>
        {product.brand !== null && (
          <Text as="span" size="sm" tone="muted">
            {product.brand}
          </Text>
        )}
        <SupermarketBadge supermarket={product.supermarket} />
        <Text as="span" size="sm" tone="muted">
          {product.packaging === null
            ? t('catalog.product.noPackaging')
            : formatPackaging(product.packaging, unitSymbols)}
        </Text>
        {actions}
      </div>
      <PriceTag
        priceCents={product.priceCents}
        unitPrice={product.unitPrice}
        highlighted={highlighted}
      />
    </article>
  );
}
