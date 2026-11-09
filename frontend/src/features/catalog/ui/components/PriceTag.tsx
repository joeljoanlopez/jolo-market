import { cva } from 'class-variance-authority';
import { useTranslation } from 'react-i18next';

import { Text } from '@/shared/ui/atoms/Text/Text';

import { formatPrice, formatUnitPrice } from '../../domain/format';
import type { UnitPrice } from '../../domain/product';
import { useUnitSymbols } from '../useUnitSymbols';

const unitPriceVariants = cva('rounded-sm px-1 font-sans text-sm', {
  variants: {
    highlighted: {
      true: 'bg-primary font-bold text-primary-foreground',
      false: 'text-muted',
    },
  },
});

interface PriceTagProps {
  priceCents: number;
  unitPrice: UnitPrice | null;
  /** The cheapest unit price of its unit group (PD-33). */
  highlighted?: boolean;
}

export function PriceTag({ priceCents, unitPrice, highlighted = false }: PriceTagProps) {
  const { t } = useTranslation();
  const unitSymbols = useUnitSymbols();

  return (
    <div className="flex flex-col items-end gap-1">
      <Text as="span" size="lg" weight="bold">
        {formatPrice(priceCents)}
      </Text>
      {unitPrice === null ? (
        <Text as="span" size="sm" tone="muted">
          {t('catalog.product.noUnitPrice')}
        </Text>
      ) : (
        <span className={unitPriceVariants({ highlighted })}>
          {formatUnitPrice(unitPrice, unitSymbols)}
        </span>
      )}
      {highlighted && (
        <Text as="span" size="xs" weight="medium">
          {t('catalog.product.cheapest')}
        </Text>
      )}
    </div>
  );
}
