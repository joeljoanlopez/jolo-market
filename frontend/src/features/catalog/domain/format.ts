import type { Packaging, Unit, UnitPrice } from './product';

const currency = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' });
const quantity = new Intl.NumberFormat('es-ES', { maximumFractionDigits: 3 });

// Unit symbols, like "€", are part of the number format rather than UI copy.
const unitSymbols: Record<Unit, string> = { kg: 'kg', l: 'L', unit: 'ud' };

/** 125 → "1,25 €" (with a non-breaking space before the symbol, as Intl writes it). */
export function formatPrice(cents: number): string {
  return currency.format(cents / 100);
}

/** { cents: 83, unit: 'l' } → "0,83 €/L". */
export function formatUnitPrice(unitPrice: UnitPrice): string {
  return `${formatPrice(unitPrice.cents)}/${unitSymbols[unitPrice.unit]}`;
}

/** { quantity: 1.5, unit: 'l' } → "1,5 L". */
export function formatPackaging(packaging: Packaging): string {
  return `${quantity.format(packaging.quantity)} ${unitSymbols[packaging.unit]}`;
}
