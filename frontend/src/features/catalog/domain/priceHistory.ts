import type { UnitPrice } from './product';

/** The price of a product at one ingestion run. */
export interface PricePoint {
  capturedAt: Date;
  /** Shelf price in integer cents. */
  priceCents: number;
  unitPrice: UnitPrice | null;
}

export interface PriceHistory {
  productId: string;
  /** Oldest first. */
  points: PricePoint[];
}

/** The ranges the product page offers (PD-48). */
export const PRICE_HISTORY_PERIODS = ['30d', '6m', '1y', 'all'] as const;
export type PriceHistoryPeriod = (typeof PRICE_HISTORY_PERIODS)[number];
export const DEFAULT_PRICE_HISTORY_PERIOD: PriceHistoryPeriod = '6m';

export interface PriceRange {
  minCents: number;
  maxCents: number;
}

/**
 * Drops the points that repeat the previous price, so a run of equal prices is one flat segment. The last point is
 * always kept: the segment then reaches the latest snapshot instead of stopping at the first one of the run.
 */
export function collapseEqualPrices(points: PricePoint[]): PricePoint[] {
  return points.filter(
    (point, index) =>
      index === 0 ||
      index === points.length - 1 ||
      point.priceCents !== points[index - 1]?.priceCents,
  );
}

/** Lowest and highest shelf price; `null` without points. */
export function priceRange(points: PricePoint[]): PriceRange | null {
  if (points.length === 0) {
    return null;
  }
  const prices = points.map((point) => point.priceCents);
  return { minCents: Math.min(...prices), maxCents: Math.max(...prices) };
}

const madridDate = new Intl.DateTimeFormat('en-CA', {
  timeZone: 'Europe/Madrid',
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
});

/**
 * First day (YYYY-MM-DD, Europe/Madrid, as the API reads it: PD-47) of a period ending today; `null` for the whole
 * history. A day past the end of a shorter month becomes its last day (31 August − 6 months → 28 February).
 */
export function periodStart(period: PriceHistoryPeriod, now: Date): string | null {
  const [year, month, day] = madridDate.format(now).split('-').map(Number) as [
    number,
    number,
    number,
  ];

  switch (period) {
    case '30d':
      // Plain calendar arithmetic: a UTC date has no summer time to skip.
      return isoDate(new Date(Date.UTC(year, month - 1, day - 30)));
    case '6m':
      return monthsBefore(year, month, day, 6);
    case '1y':
      return monthsBefore(year, month, day, 12);
    case 'all':
      return null;
  }
}

function monthsBefore(year: number, month: number, day: number, months: number): string {
  const firstOfTarget = new Date(Date.UTC(year, month - 1 - months, 1));
  const daysInTarget = new Date(
    Date.UTC(firstOfTarget.getUTCFullYear(), firstOfTarget.getUTCMonth() + 1, 0),
  ).getUTCDate();
  return isoDate(
    new Date(
      Date.UTC(
        firstOfTarget.getUTCFullYear(),
        firstOfTarget.getUTCMonth(),
        Math.min(day, daysInTarget),
      ),
    ),
  );
}

function isoDate(date: Date): string {
  return date.toISOString().slice(0, 10);
}
