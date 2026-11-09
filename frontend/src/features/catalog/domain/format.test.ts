import { describe, expect, it } from 'vitest';

import { formatPackaging, formatPrice, formatUnitPrice } from './format';

// Intl separates the amount and the symbol with a non-breaking space.
const nbsp = ' ';

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
    expect(formatUnitPrice(unitPrice)).toBe(expected);
  });
});

describe('formatPackaging', () => {
  it.each([
    [{ quantity: 1.5, unit: 'l' as const }, '1,5 L'],
    [{ quantity: 0.5, unit: 'kg' as const }, '0,5 kg'],
    [{ quantity: 6, unit: 'unit' as const }, '6 ud'],
    [{ quantity: 0.125, unit: 'kg' as const }, '0,125 kg'],
  ])('formats %o as %s', (packaging, expected) => {
    expect(formatPackaging(packaging)).toBe(expected);
  });
});
