import type { Packaging, Unit, UnitPrice } from './product';

const currency = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' });
const quantity = new Intl.NumberFormat('es-ES', { maximumFractionDigits: 3 });

/** The symbol shown for each unit ("kg", "L", "ud"). It is UI copy, so callers read it from the translation files. */
export type UnitSymbols = Record<Unit, string>;

/** 125 → "1,25 €" (with a non-breaking space before the symbol, as Intl writes it). */
export function formatPrice(cents: number): string {
  return currency.format(cents / 100);
}

/** { cents: 83, unit: 'l' } → "0,83 €/L". */
export function formatUnitPrice(unitPrice: UnitPrice, unitSymbols: UnitSymbols): string {
  return `${formatPrice(unitPrice.cents)}/${unitSymbols[unitPrice.unit]}`;
}

/** { quantity: 1.5, unit: 'l' } → "1,5 L". */
export function formatPackaging(packaging: Packaging, unitSymbols: UnitSymbols): string {
  return `${quantity.format(packaging.quantity)} ${unitSymbols[packaging.unit]}`;
}
