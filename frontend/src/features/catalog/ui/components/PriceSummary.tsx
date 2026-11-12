import { useTranslation } from 'react-i18next';

import { formatPrice } from '../../domain/format';
import type { PriceRange } from '../../domain/priceHistory';

interface PriceSummaryProps {
  /** Lowest and highest price of the period shown; `null` when it has no snapshot. */
  range: PriceRange | null;
  currentPriceCents: number;
}

/** Minimum and maximum of the period and the current price of the product (PD-48). */
export function PriceSummary({ range, currentPriceCents }: PriceSummaryProps) {
  const { t } = useTranslation();
  const entries = [
    ...(range === null
      ? []
      : [
          { term: t('catalog.history.min'), cents: range.minCents },
          { term: t('catalog.history.max'), cents: range.maxCents },
        ]),
    { term: t('catalog.history.current'), cents: currentPriceCents },
  ];

  return (
    <dl aria-label={t('catalog.history.summary')} className="flex flex-wrap gap-6 font-sans">
      {entries.map(({ term, cents }) => (
        <div key={term} className="flex flex-col">
          <dt className="text-sm text-muted">{term}</dt>
          <dd className="text-lg font-bold text-foreground">{formatPrice(cents)}</dd>
        </div>
      ))}
    </dl>
  );
}
