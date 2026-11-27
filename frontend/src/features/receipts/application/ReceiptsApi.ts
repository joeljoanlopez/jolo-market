import type {
  LineKind,
  ListReceipts,
  MonthlySpending,
  QuantityUnit,
  Receipt,
} from '../domain/receipt';

export interface CallOptions {
  signal?: AbortSignal;
}

export interface NewLine {
  kind: LineKind;
  text: string;
  /** Required for a product line, left out for a discount line. */
  unit?: QuantityUnit;
  /** Units, or thousandths of a kg. */
  quantity?: number;
  amountCents: number;
}

/** Only the fields sent change; unit and quantity go together. */
export interface LineChanges {
  text?: string;
  unit?: QuantityUnit;
  quantity?: number;
  amountCents?: number;
}

export interface ReceiptChanges {
  /** "YYYY-MM-DD". */
  date?: string;
  totalCents?: number;
}

/**
 * Port to the receipts API, implemented in infrastructure/. A list or receipt the user cannot
 * access rejects with `NotFoundError` (PD-63); a receipt still being read with `ConflictError`;
 * invalid input with `ValidationError`.
 */
export interface ReceiptsApi {
  listReceipts(listId: string, options?: CallOptions): Promise<ListReceipts>;
  listSpending(listId: string, options?: CallOptions): Promise<MonthlySpending[]>;
  getReceipt(listId: string, receiptId: string, options?: CallOptions): Promise<Receipt>;
  /** Where the browser loads the photo from (members only). */
  photoUrl(listId: string, receiptId: string): string;
  /** Resolves with the id of the new receipt, which is then being read (PD-65). */
  uploadReceipt(listId: string, supermarketId: string, photo: File): Promise<string>;
  updateReceipt(listId: string, receiptId: string, changes: ReceiptChanges): Promise<void>;
  /** Resolves with the line id. */
  addLine(listId: string, receiptId: string, line: NewLine): Promise<string>;
  updateLine(
    listId: string,
    receiptId: string,
    lineId: string,
    changes: LineChanges,
  ): Promise<void>;
  removeLine(listId: string, receiptId: string, lineId: string): Promise<void>;
  linkLine(listId: string, receiptId: string, lineId: string, productId: string): Promise<void>;
  unlinkLine(listId: string, receiptId: string, lineId: string): Promise<void>;
  confirmReceipt(listId: string, receiptId: string): Promise<void>;
  deleteReceipt(listId: string, receiptId: string): Promise<void>;
}
