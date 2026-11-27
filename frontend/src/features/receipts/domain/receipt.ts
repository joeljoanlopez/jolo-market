/** Receipt lifecycle (PD-65): read by the worker, reviewed as a draft, then confirmed. */
export type ReceiptStatus = 'reading' | 'draft' | 'confirmed';

export type LineKind = 'product' | 'discount';

export type QuantityUnit = 'units' | 'kg';

/** Whole units, or a weight in thousandths of a kg (PD-66). */
export interface LineQuantity {
  unit: QuantityUnit;
  value: number;
}

export interface ReceiptUser {
  id: string;
  username: string;
}

/** A linked or suggested product. `name` is null when the catalog no longer knows it. */
export interface LineProduct {
  id: string;
  name: string | null;
  brand: string | null;
  available: boolean;
}

export interface ReceiptLine {
  id: string;
  kind: LineKind;
  /** The text as printed. */
  text: string;
  /** Null for a discount line. */
  quantity: LineQuantity | null;
  amountCents: number;
  linkedProduct: LineProduct | null;
  /** Shown, but not linked until a member accepts it (PD-67). */
  suggestedProduct: LineProduct | null;
}

/** A receipt in the lists of a shopping list (PD-70). */
export interface ReceiptSummary {
  id: string;
  status: ReceiptStatus;
  supermarketId: string;
  supermarketName: string;
  /** "YYYY-MM-DD", a day in Madrid; null until it is read or typed. */
  date: string | null;
  totalCents: number | null;
  uploader: ReceiptUser;
  uploadedAt: Date;
}

export interface Receipt extends ReceiptSummary {
  /** Products − discounts − total; null without a total (PD-66). */
  differenceCents: number | null;
  lines: ReceiptLine[];
}

export interface ListReceipts {
  /** Still being read, or drafts waiting for review: newest upload first. */
  pending: ReceiptSummary[];
  /** Newest receipt first. */
  confirmed: ReceiptSummary[];
}

export interface SupermarketSpending {
  supermarketId: string;
  supermarketName: string;
  totalCents: number;
}

/** What a list spent in one month, by receipt date (PD-70). */
export interface MonthlySpending {
  /** "YYYY-MM". */
  month: string;
  totalCents: number;
  supermarkets: SupermarketSpending[];
}

/** The symbol shown for each unit ("ud", "kg"). It is UI copy, so callers read it from the translation files. */
export type QuantitySymbols = Record<QuantityUnit, string>;

const kilograms = new Intl.NumberFormat('es-ES', { maximumFractionDigits: 3 });
const month = new Intl.DateTimeFormat('es-ES', { month: 'long', timeZone: 'UTC' });

/** { units, 2 } → "2 ud"; { kg, 532 } → "0,532 kg". */
export function formatLineQuantity(quantity: LineQuantity, symbols: QuantitySymbols): string {
  const amount = quantity.unit === 'kg' ? kilograms.format(quantity.value / 1000) : quantity.value;
  return `${String(amount)} ${symbols[quantity.unit]}`;
}

/** "2026-11" → "noviembre 2026". */
export function formatMonth(yearMonth: string): string {
  const [year = '', monthNumber = ''] = yearMonth.split('-');
  const date = new Date(Date.UTC(Number(year), Number(monthNumber) - 1, 1));
  return `${month.format(date)} ${year}`;
}

/** The review warns when products minus discounts are not the total; it still confirms (PD-66). */
export function hasDifference(receipt: Receipt): boolean {
  return receipt.differenceCents !== null && receipt.differenceCents !== 0;
}

/** While one is still being read, the list polls for it to become a draft (PD-7, PD-65). */
export function isReadingAny(receipts: readonly ReceiptSummary[]): boolean {
  return receipts.some((receipt) => receipt.status === 'reading');
}
