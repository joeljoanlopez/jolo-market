import { describe, expect, it } from 'vitest';

import {
  MAX_PHOTO_BYTES,
  centsFromEuros,
  eurosFromCents,
  formatLineQuantity,
  formatReceiptDate,
  inputFromQuantity,
  photoProblem,
  quantityFromInput,
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

describe('photoProblem', () => {
  it('accepts a JPEG, PNG or WebP photo of up to 10 MB', () => {
    expect(photoProblem({ type: 'image/jpeg', size: 1 })).toBeNull();
    expect(photoProblem({ type: 'image/png', size: MAX_PHOTO_BYTES })).toBeNull();
    expect(photoProblem({ type: 'image/webp', size: 2048 })).toBeNull();
  });

  it('refuses another type or a bigger file (PD-68)', () => {
    expect(photoProblem({ type: 'application/pdf', size: 10 })).toBe('type');
    expect(photoProblem({ type: 'image/gif', size: 10 })).toBe('type');
    expect(photoProblem({ type: 'image/jpeg', size: MAX_PHOTO_BYTES + 1 })).toBe('size');
  });
});

describe('formatReceiptDate', () => {
  it('writes the day as dd/mm/yyyy', () => {
    expect(formatReceiptDate('2026-11-04')).toBe('04/11/2026');
  });
});

describe('centsFromEuros', () => {
  it('reads an amount typed with a comma or a dot', () => {
    expect(centsFromEuros('12,34')).toBe(1234);
    expect(centsFromEuros('0.5')).toBe(50);
    expect(centsFromEuros(' 3 ')).toBe(300);
  });

  it('is null for anything else', () => {
    expect(centsFromEuros('')).toBeNull();
    expect(centsFromEuros('1,234')).toBeNull();
    expect(centsFromEuros('-1')).toBeNull();
    expect(centsFromEuros('abc')).toBeNull();
  });

  it('writes cents back in the same form', () => {
    expect(eurosFromCents(1234)).toBe('12,34');
    expect(eurosFromCents(50)).toBe('0,50');
  });
});

describe('quantityFromInput', () => {
  it('reads whole units, or kg with up to three decimals as thousandths', () => {
    expect(quantityFromInput('units', '3')).toEqual({ unit: 'units', value: 3 });
    expect(quantityFromInput('kg', '0,532')).toEqual({ unit: 'kg', value: 532 });
    expect(quantityFromInput('kg', '1.5')).toEqual({ unit: 'kg', value: 1500 });
  });

  it('is null for anything else', () => {
    expect(quantityFromInput('units', '1,5')).toBeNull();
    expect(quantityFromInput('units', '0')).toBeNull();
    expect(quantityFromInput('kg', '0,0001')).toBeNull();
    expect(quantityFromInput('kg', '')).toBeNull();
  });

  it('writes a quantity back in the same form', () => {
    expect(inputFromQuantity({ unit: 'kg', value: 532 })).toBe('0,532');
    expect(inputFromQuantity({ unit: 'units', value: 2 })).toBe('2');
  });
});
