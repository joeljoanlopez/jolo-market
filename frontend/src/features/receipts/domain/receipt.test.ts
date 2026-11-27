import { describe, expect, it } from 'vitest';

import {
  formatLineQuantity,
  formatMonth,
  hasDifference,
  isReadingAny,
  type Receipt,
  type ReceiptSummary,
} from './receipt';

const symbols = { units: 'ud', kg: 'kg' };

function receipt(differenceCents: number | null): Receipt {
  return {
    id: 'r1',
    status: 'draft',
    supermarketId: 'mercadona',
    supermarketName: 'Mercadona',
    date: '2026-11-14',
    totalCents: 1000,
    uploader: { id: 'u1', username: 'maria' },
    uploadedAt: new Date('2026-11-14T18:00:00Z'),
    differenceCents,
    lines: [],
  };
}

describe('formatLineQuantity', () => {
  it('writes whole units with their symbol', () => {
    expect(formatLineQuantity({ unit: 'units', value: 2 }, symbols)).toBe('2 ud');
  });

  it('writes a weight in kg with up to three decimals and a comma', () => {
    expect(formatLineQuantity({ unit: 'kg', value: 532 }, symbols)).toBe('0,532 kg');
    expect(formatLineQuantity({ unit: 'kg', value: 1500 }, symbols)).toBe('1,5 kg');
  });
});

describe('formatMonth', () => {
  it('writes the month and the year in Spanish', () => {
    expect(formatMonth('2026-11')).toBe('noviembre 2026');
    expect(formatMonth('2027-01')).toBe('enero 2027');
  });
});

describe('hasDifference', () => {
  it('is true only when the lines do not add up to the total', () => {
    expect(hasDifference(receipt(-50))).toBe(true);
    expect(hasDifference(receipt(25))).toBe(true);
    expect(hasDifference(receipt(0))).toBe(false);
    expect(hasDifference(receipt(null))).toBe(false);
  });
});

describe('isReadingAny', () => {
  it('is true while a pending receipt is still being read', () => {
    const summary = (status: ReceiptSummary['status']): ReceiptSummary => ({
      ...receipt(null),
      status,
    });

    expect(isReadingAny([summary('draft'), summary('reading')])).toBe(true);
    expect(isReadingAny([summary('draft')])).toBe(false);
    expect(isReadingAny([])).toBe(false);
  });
});
