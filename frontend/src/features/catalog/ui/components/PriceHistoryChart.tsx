import { useTranslation } from 'react-i18next';

import { Text } from '@/shared/ui/atoms/Text/Text';

import { formatDay, formatPrice } from '../../domain/format';
import { collapseEqualPrices, type PricePoint, priceRange } from '../../domain/priceHistory';

const WIDTH = 600;
const HEIGHT = 200;
const MARGIN = 8;

interface PriceHistoryChartProps {
  /** At least one point, oldest first. */
  points: PricePoint[];
}

/**
 * The shelf price over time as a step line (PD-49): a price holds until the next snapshot changes it. Equal
 * consecutive prices are one flat segment. The time axis goes from the first to the last snapshot (PD-48).
 */
export function PriceHistoryChart({ points }: PriceHistoryChartProps) {
  const { t } = useTranslation();
  const steps = collapseEqualPrices(points);
  const first = steps[0];
  const last = steps.at(-1);
  const range = priceRange(steps);
  if (first === undefined || last === undefined || range === null) {
    return null;
  }

  const single = steps.length === 1;
  const label = single
    ? t('catalog.history.chartSingle', {
        date: formatDay(first.capturedAt),
        price: formatPrice(first.priceCents),
      })
    : t('catalog.history.chart', {
        from: formatDay(first.capturedAt),
        to: formatDay(last.capturedAt),
        min: formatPrice(range.minCents),
        max: formatPrice(range.maxCents),
      });

  const start = first.capturedAt.getTime();
  const span = last.capturedAt.getTime() - start;
  const x = (point: PricePoint) =>
    span === 0
      ? WIDTH / 2
      : round(MARGIN + ((point.capturedAt.getTime() - start) / span) * (WIDTH - 2 * MARGIN));
  const y = (point: PricePoint) =>
    range.maxCents === range.minCents
      ? HEIGHT / 2
      : round(
          MARGIN +
            ((range.maxCents - point.priceCents) / (range.maxCents - range.minCents)) *
              (HEIGHT - 2 * MARGIN),
        );

  return (
    <figure className="flex flex-col gap-1">
      <svg
        viewBox={`0 0 ${String(WIDTH)} ${String(HEIGHT)}`}
        role="img"
        aria-label={label}
        className="h-auto w-full"
      >
        {single ? (
          <circle cx={x(first)} cy={y(first)} r={4} className="fill-primary" />
        ) : (
          <path d={stepPath(steps, x, y)} className="fill-none stroke-primary stroke-2" />
        )}
      </svg>
      <figcaption className="flex justify-between" aria-hidden="true">
        <Text as="span" size="xs" tone="muted">
          {formatDay(first.capturedAt)}
        </Text>
        {!single && (
          <Text as="span" size="xs" tone="muted">
            {formatDay(last.capturedAt)}
          </Text>
        )}
      </figcaption>
    </figure>
  );
}

/** Horizontal to the next snapshot, then vertical to its price; no vertical move when the price is the same. */
function stepPath(
  points: PricePoint[],
  x: (point: PricePoint) => number,
  y: (point: PricePoint) => number,
): string {
  return points
    .map((point, index) => {
      if (index === 0) {
        return `M${String(x(point))} ${String(y(point))}`;
      }
      const previous = points[index - 1];
      const horizontal = `H${String(x(point))}`;
      return previous !== undefined && y(previous) === y(point)
        ? horizontal
        : `${horizontal} V${String(y(point))}`;
    })
    .join(' ');
}

function round(value: number): number {
  return Math.round(value * 100) / 100;
}
