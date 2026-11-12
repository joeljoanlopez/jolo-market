import { describe, expect, it } from 'vitest';

import { collapseEqualPrices, periodStart, type PricePoint, priceRange } from './priceHistory';

function point(capturedAt: string, priceCents: number): PricePoint {
  return { capturedAt: new Date(capturedAt), priceCents, unitPrice: null };
}

describe('collapseEqualPrices', () => {
  it('keeps the first point of each run of equal prices and the last point', () => {
    const points = [
      point('2026-11-01T05:00:00Z', 125),
      point('2026-11-02T05:00:00Z', 125),
      point('2026-11-03T05:00:00Z', 130),
      point('2026-11-04T05:00:00Z', 130),
      point('2026-11-05T05:00:00Z', 130),
      point('2026-11-06T05:00:00Z', 120),
    ];

    expect(collapseEqualPrices(points)).toEqual([points[0], points[2], points[5]]);
  });

  it('keeps the last point of a final flat run so the segment reaches it', () => {
    const points = [
      point('2026-11-01T05:00:00Z', 125),
      point('2026-11-02T05:00:00Z', 130),
      point('2026-11-03T05:00:00Z', 130),
      point('2026-11-04T05:00:00Z', 130),
    ];

    expect(collapseEqualPrices(points)).toEqual([points[0], points[1], points[3]]);
  });

  it('turns a price that never changed into one flat segment', () => {
    const points = [
      point('2026-11-01T05:00:00Z', 125),
      point('2026-11-02T05:00:00Z', 125),
      point('2026-11-03T05:00:00Z', 125),
    ];

    expect(collapseEqualPrices(points)).toEqual([points[0], points[2]]);
  });

  it('keeps a price that comes back after a change', () => {
    const points = [
      point('2026-11-01T05:00:00Z', 125),
      point('2026-11-02T05:00:00Z', 130),
      point('2026-11-03T05:00:00Z', 125),
    ];

    expect(collapseEqualPrices(points)).toEqual(points);
  });

  it('keeps zero or one point as they are', () => {
    const single = [point('2026-11-01T05:00:00Z', 125)];

    expect(collapseEqualPrices([])).toEqual([]);
    expect(collapseEqualPrices(single)).toEqual(single);
  });
});

describe('priceRange', () => {
  it('returns the lowest and the highest price', () => {
    const points = [
      point('2026-11-01T05:00:00Z', 125),
      point('2026-11-02T05:00:00Z', 99),
      point('2026-11-03T05:00:00Z', 140),
      point('2026-11-04T05:00:00Z', 130),
    ];

    expect(priceRange(points)).toEqual({ minCents: 99, maxCents: 140 });
  });

  it('returns the same price for both ends with one point', () => {
    expect(priceRange([point('2026-11-01T05:00:00Z', 125)])).toEqual({
      minCents: 125,
      maxCents: 125,
    });
  });

  it('returns null without points', () => {
    expect(priceRange([])).toBeNull();
  });
});

describe('periodStart', () => {
  const now = new Date('2026-11-12T10:00:00Z');

  it('starts 30 days before today', () => {
    expect(periodStart('30d', now)).toBe('2026-10-13');
  });

  it('starts 6 months before today', () => {
    expect(periodStart('6m', now)).toBe('2026-05-12');
  });

  it('starts one year before today', () => {
    expect(periodStart('1y', now)).toBe('2025-11-12');
  });

  it('has no start for the whole history', () => {
    expect(periodStart('all', now)).toBeNull();
  });

  it('counts days in Madrid, where a new day starts before midnight UTC', () => {
    // 23:30 UTC on 12 November is 00:30 on 13 November in Madrid (UTC+1).
    expect(periodStart('1y', new Date('2026-11-12T23:30:00Z'))).toBe('2025-11-13');
  });

  it('moves a start past the end of a shorter month to its last day', () => {
    expect(periodStart('6m', new Date('2026-08-31T10:00:00Z'))).toBe('2026-02-28');
  });

  it('moves 29 February to 28 February one year before', () => {
    expect(periodStart('1y', new Date('2028-02-29T10:00:00Z'))).toBe('2027-02-28');
  });
});
