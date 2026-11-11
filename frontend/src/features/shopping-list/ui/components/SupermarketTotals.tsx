import { useId } from 'react';
import { useTranslation } from 'react-i18next';

import { formatPrice } from '@/features/catalog/domain/format';
import { SupermarketBadge } from '@/features/catalog/ui/components/SupermarketBadge';
import { Text } from '@/shared/ui/atoms/Text/Text';

import type { SupermarketTotal } from '../../domain/shoppingList';

interface SupermarketTotalsProps {
  totals: SupermarketTotal[];
  /** Names of the unavailable products left out of the totals (PD-22). */
  excluded: string[];
}

/** Estimated cost of the list per supermarket: price × quantity of its available products (PD-45). */
export function SupermarketTotals({ totals, excluded }: SupermarketTotalsProps) {
  const { t } = useTranslation();
  const titleId = useId();

  return (
    <section aria-labelledby={titleId} className="flex flex-col gap-3">
      <span id={titleId}>
        <Text as="h2" size="lg" weight="bold">
          {t('lists.totals.title')}
        </Text>
      </span>
      {totals.length === 0 ? (
        <Text size="sm" tone="muted">
          {t('lists.totals.empty')}
        </Text>
      ) : (
        <ul className="flex flex-col gap-2">
          {totals.map((total) => (
            <li
              key={total.supermarketId}
              className="flex items-center justify-between gap-3 rounded-lg border border-border bg-background p-3"
            >
              <div className="flex flex-col items-start gap-1">
                <SupermarketBadge
                  supermarket={{ id: total.supermarketId, name: total.supermarketName }}
                />
                <Text as="span" size="sm" tone="muted">
                  {t('lists.totals.itemCount', { count: total.itemCount })}
                </Text>
              </div>
              <Text as="span" size="lg" weight="bold">
                {formatPrice(total.totalCents)}
              </Text>
            </li>
          ))}
        </ul>
      )}
      {excluded.length === 0 ? null : (
        <Text size="sm" tone="muted">
          {t('lists.totals.excluded', { names: excluded.join(', ') })}
        </Text>
      )}
    </section>
  );
}
