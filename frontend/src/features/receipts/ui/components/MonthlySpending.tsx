import { useTranslation } from 'react-i18next';

import { formatPrice } from '@/features/catalog/domain/format';
import { SupermarketBadge } from '@/features/catalog/ui/components/SupermarketBadge';
import { Text } from '@/shared/ui/atoms/Text/Text';

import { formatMonth, type MonthlySpending as Month } from '../../domain/receipt';

/** PD-70: what the list spent each month, newest first, with the supermarket colors (PD-20). */
export function MonthlySpending({ months }: { months: Month[] }) {
  const { t } = useTranslation();

  return (
    <div className="flex flex-col gap-2">
      <Text as="h3" weight="bold">
        {t('receipts.spending.title')}
      </Text>
      {months.length === 0 ? (
        <Text size="sm" tone="muted">
          {t('receipts.spending.empty')}
        </Text>
      ) : (
        months.map((month) => {
          const name = formatMonth(month.month);
          return (
            <div
              key={month.month}
              className="flex flex-col gap-2 rounded-lg border border-border bg-background p-3"
            >
              <h4 className="flex items-center justify-between gap-2 font-sans">
                <Text as="span" weight="medium">
                  {name}
                </Text>
                <Text as="span" size="lg" weight="bold">
                  {formatPrice(month.totalCents)}
                </Text>
              </h4>
              <ul
                aria-label={t('receipts.spending.month', { month: name })}
                className="flex flex-col gap-1"
              >
                {month.supermarkets.map((supermarket) => (
                  <li
                    key={supermarket.supermarketId}
                    className="flex items-center justify-between gap-2"
                  >
                    <SupermarketBadge
                      supermarket={{
                        id: supermarket.supermarketId,
                        name: supermarket.supermarketName,
                      }}
                    />
                    <Text as="span" size="sm">
                      {formatPrice(supermarket.totalCents)}
                    </Text>
                  </li>
                ))}
              </ul>
            </div>
          );
        })
      )}
    </div>
  );
}
