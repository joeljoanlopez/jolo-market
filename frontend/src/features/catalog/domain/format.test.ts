import { describe, expect, it } from 'vitest';

import {
  formatDay,
  formatPackaging,
  formatPrice,
  formatUnitPrice,
  type UnitSymbols,
} from './format';

// Intl separates the amount and the symbol with a non-breaking space.
const nbsp = ' ';

// The symbols come from the translation files; any set works here.
const symbols: UnitSymbols = { kg: 'kg', l: 'L', unit: 'ud' };

describe('formatPrice', () => {
  it.each([
    [125, `1,25${nbsp}€`],
    [5, `0,05${nbsp}€`],
    [900, `9,00${nbsp}€`],
  ])('formats %i cents as %s', (cents, expected) => {
    expect(formatPrice(cents)).toBe(expected);
  });
});

describe('formatUnitPrice', () => {
  it.each([
    [{ cents: 83, unit: 'l' as const }, `0,83${nbsp}€/L`],
    [{ cents: 900, unit: 'kg' as const }, `9,00${nbsp}€/kg`],
    [{ cents: 30, unit: 'unit' as const }, `0,30${nbsp}€/ud`],
  ])('formats %o as %s', (unitPrice, expected) => {
    expect(formatUnitPrice(unitPrice, symbols)).toBe(expected);
  });
});

describe('formatPackaging', () => {
  it.each([
    [{ quantity: 1.5, unit: 'l' as const }, '1,5 L'],
    [{ quantity: 0.5, unit: 'kg' as const }, '0,5 kg'],
    [{ quantity: 6, unit: 'unit' as const }, '6 ud'],
    [{ quantity: 0.125, unit: 'kg' as const }, '0,125 kg'],
  ])('formats %o as %s', (packaging, expected) => {
    expect(formatPackaging(packaging, symbols)).toBe(expected);
  });
});

describe('unit symbols', () => {
  it('uses the symbols it is given', () => {
    const english: UnitSymbols = { kg: 'kg', l: 'l', unit: 'pc' };

    expect(formatUnitPrice({ cents: 30, unit: 'unit' }, english)).toBe(`0,30${nbsp}€/pc`);
    expect(formatPackaging({ quantity: 6, unit: 'unit' }, english)).toBe('6 pc');
  });
});

describe('formatDay', () => {
  it('writes the day in Madrid as dd/mm/yyyy', () => {
    expect(formatDay(new Date('2026-11-08T05:00:00Z'))).toBe('08/11/2026');
  });

  it('uses the Madrid day, which starts before midnight UTC', () => {
    // 23:30 UTC on 31 October is 00:30 on 1 November in Madrid (UTC+1).
    expect(formatDay(new Date('2026-10-31T23:30:00Z'))).toBe('01/11/2026');
  });
});
