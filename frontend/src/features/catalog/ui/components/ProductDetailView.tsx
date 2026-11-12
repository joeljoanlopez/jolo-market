import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

import { Text } from '@/shared/ui/atoms/Text/Text';

import { formatPackaging } from '../../domain/format';
import type { ProductDetail } from '../../domain/product';
import { useUnitSymbols } from '../useUnitSymbols';
import { PriceTag } from './PriceTag';
import { SupermarketBadge } from './SupermarketBadge';

interface ProductDetailViewProps {
  product: ProductDetail;
  /** Actions on the product from other features, e.g. "Add to list". */
  actions?: ReactNode;
  /** The price history. */
  children: ReactNode;
}

/** The product page (PD-48): the product, an "unavailable" flag, its actions and its price history. */
export function ProductDetailView({ product, actions, children }: ProductDetailViewProps) {
  const { t } = useTranslation();
  const unitSymbols = useUnitSymbols();

  return (
    <article className="flex flex-col gap-6">
      <div className="flex gap-4">
        {product.imageUrl === null ? (
          <div
            role="img"
            aria-label={t('catalog.product.noImage')}
            className="size-24 shrink-0 rounded-md bg-surface"
          />
        ) : (
          <img
            src={product.imageUrl}
            alt={t('catalog.product.imageAlt', { name: product.name })}
            className="size-24 shrink-0 rounded-md object-contain"
          />
        )}
        <div className="flex min-w-0 flex-1 flex-col items-start gap-1">
          <Text as="h1" size="2xl" weight="bold">
            {product.name}
          </Text>
          {product.brand !== null && (
            <Text as="span" tone="muted">
              {product.brand}
            </Text>
          )}
          <SupermarketBadge supermarket={product.supermarket} />
          <Text as="span" size="sm" tone="muted">
            {product.packaging === null
              ? t('catalog.product.noPackaging')
              : formatPackaging(product.packaging, unitSymbols)}
          </Text>
          {!product.available && (
            <Text as="span" size="sm" tone="danger" weight="medium">
              {t('catalog.detail.unavailable')}
            </Text>
          )}
          {actions}
        </div>
        <PriceTag priceCents={product.priceCents} unitPrice={product.unitPrice} />
      </div>
      {children}
    </article>
  );
}
