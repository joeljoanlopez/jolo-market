import { useId } from 'react';
import { useTranslation } from 'react-i18next';

import { Alert } from '@/shared/ui/atoms/Alert/Alert';
import { Button } from '@/shared/ui/atoms/Button/Button';
import { Text } from '@/shared/ui/atoms/Text/Text';

import type { PriceHistoryState } from '../../application/usePriceHistory';
import { type PriceHistoryPeriod, priceRange } from '../../domain/priceHistory';
import { PriceHistoryChart } from './PriceHistoryChart';
import { PriceHistoryPeriodSelector } from './PriceHistoryPeriodSelector';
import { PriceSummary } from './PriceSummary';

interface PriceHistoryPanelProps {
  state: PriceHistoryState;
  period: PriceHistoryPeriod;
  onPeriodChange: (period: PriceHistoryPeriod) => void;
  currentPriceCents: number;
}

/** "Evolución del precio": the period selector, then the chart and the summary of that period. */
export function PriceHistoryPanel({
  state,
  period,
  onPeriodChange,
  currentPriceCents,
}: PriceHistoryPanelProps) {
  const { t } = useTranslation();
  const titleId = useId();

  return (
    <section
      aria-labelledby={titleId}
      aria-busy={state.status === 'success' && state.isFetching}
      className="flex flex-col gap-3"
    >
      <Text as="h2" size="xl" weight="bold">
        <span id={titleId}>{t('catalog.history.title')}</span>
      </Text>
      <PriceHistoryPeriodSelector value={period} onChange={onPeriodChange} />
      {state.status === 'loading' && <Text tone="muted">{t('catalog.history.loading')}</Text>}
      {state.status === 'error' && (
        <div className="flex flex-col items-start gap-2">
          <Alert>{t('catalog.history.error')}</Alert>
          <Button variant="secondary" size="sm" onClick={state.retry}>
            {t('catalog.history.retry')}
          </Button>
        </div>
      )}
      {state.status === 'success' && (
        <>
          {state.history.points.length === 0 ? (
            <Text tone="muted">{t('catalog.history.empty')}</Text>
          ) : (
            <PriceHistoryChart points={state.history.points} />
          )}
          <PriceSummary
            range={priceRange(state.history.points)}
            currentPriceCents={currentPriceCents}
          />
        </>
      )}
    </section>
  );
}
