import { useTranslation } from 'react-i18next';

import { Button } from '@/shared/ui/atoms/Button/Button';

import { PRICE_HISTORY_PERIODS, type PriceHistoryPeriod } from '../../domain/priceHistory';

interface PriceHistoryPeriodSelectorProps {
  value: PriceHistoryPeriod;
  onChange: (period: PriceHistoryPeriod) => void;
}

/** "30 días", "6 meses", "1 año", "Todo" (PD-48); the selected one is pressed. */
export function PriceHistoryPeriodSelector({ value, onChange }: PriceHistoryPeriodSelectorProps) {
  const { t } = useTranslation();

  return (
    <div role="group" aria-label={t('catalog.history.periods')} className="flex flex-wrap gap-2">
      {PRICE_HISTORY_PERIODS.map((period) => (
        <Button
          key={period}
          size="sm"
          variant={period === value ? 'primary' : 'secondary'}
          aria-pressed={period === value}
          onClick={() => {
            onChange(period);
          }}
        >
          {t(`catalog.history.period.${period}`)}
        </Button>
      ))}
    </div>
  );
}
