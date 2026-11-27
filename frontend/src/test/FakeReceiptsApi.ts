import type {
  LineChanges,
  NewLine,
  ReceiptChanges,
  ReceiptsApi,
} from '@/features/receipts/application/ReceiptsApi';
import type {
  LineProduct,
  ListReceipts,
  MonthlySpending,
  Receipt,
  ReceiptLine,
  ReceiptSummary,
} from '@/features/receipts/domain/receipt';
import { ConflictError, NotFoundError, ValidationError } from '@/shared/http/errors';

export const MARIA = { id: 'user-maria', username: 'maria' };

export const PRODUCTS: Record<string, LineProduct & { supermarketId: string }> = {
  'product-milk': {
    id: 'product-milk',
    name: 'Leche entera',
    brand: 'Hacendado',
    available: true,
    supermarketId: 'mercadona',
  },
  'product-bread': {
    id: 'product-bread',
    name: 'Pan de molde',
    brand: 'Hacendado',
    available: true,
    supermarketId: 'mercadona',
  },
};

const names: Record<string, string> = { mercadona: 'Mercadona', aldi: 'Aldi' };

function problem(status: number) {
  return { type: 'about:blank', title: 'Error', status };
}

/**
 * In-memory receipts that follow the API rules the SPA relies on: only lists in `lists` exist
 * (404 otherwise), a receipt being read answers 409 to edits, and confirming needs a date and a total (422).
 * `finishReading` plays the worker.
 */
export class FakeReceiptsApi implements ReceiptsApi {
  readonly lists = new Set<string>(['list-1']);
  readonly receipts = new Map<string, Receipt & { listId: string }>();
  readonly uploads: { listId: string; supermarketId: string; fileName: string }[] = [];
  readonly calls: string[] = [];
  /** When set, every change rejects with it (reads still work). */
  changeError: Error | null = null;
  reads = 0;
  private issued = 0;

  seed(receipt: Partial<Receipt> & { listId?: string } = {}): Receipt {
    const id = receipt.id ?? this.nextId('receipt');
    const stored = {
      id,
      listId: receipt.listId ?? 'list-1',
      status: receipt.status ?? 'draft',
      supermarketId: receipt.supermarketId ?? 'mercadona',
      supermarketName: names[receipt.supermarketId ?? 'mercadona'] ?? 'Mercadona',
      date: receipt.date ?? null,
      totalCents: receipt.totalCents ?? null,
      uploader: receipt.uploader ?? MARIA,
      uploadedAt: receipt.uploadedAt ?? new Date('2026-11-14T18:00:00Z'),
      differenceCents: null,
      lines: receipt.lines ?? [],
    };
    this.receipts.set(id, stored);
    this.recompute(stored);
    return stored;
  }

  finishReading(receiptId: string): void {
    const receipt = this.receipts.get(receiptId);
    if (receipt !== undefined) {
      receipt.status = 'draft';
    }
  }

  listReceipts(listId: string): Promise<ListReceipts> {
    this.reads++;
    this.assertList(listId);
    const all = [...this.receipts.values()].filter((receipt) => receipt.listId === listId);
    const summary = (receipt: Receipt): ReceiptSummary => ({
      id: receipt.id,
      status: receipt.status,
      supermarketId: receipt.supermarketId,
      supermarketName: receipt.supermarketName,
      date: receipt.date,
      totalCents: receipt.totalCents,
      uploader: receipt.uploader,
      uploadedAt: receipt.uploadedAt,
    });
    return Promise.resolve({
      pending: all.filter((receipt) => receipt.status !== 'confirmed').map(summary),
      confirmed: all.filter((receipt) => receipt.status === 'confirmed').map(summary),
    });
  }

  listSpending(listId: string): Promise<MonthlySpending[]> {
    this.assertList(listId);
    const months = new Map<string, Map<string, number>>();
    for (const receipt of this.receipts.values()) {
      if (receipt.listId !== listId || receipt.status !== 'confirmed' || receipt.date === null) {
        continue;
      }
      const month = receipt.date.slice(0, 7);
      const bySupermarket = months.get(month) ?? new Map<string, number>();
      bySupermarket.set(
        receipt.supermarketId,
        (bySupermarket.get(receipt.supermarketId) ?? 0) + (receipt.totalCents ?? 0),
      );
      months.set(month, bySupermarket);
    }
    return Promise.resolve(
      [...months.entries()]
        .sort(([a], [b]) => b.localeCompare(a))
        .map(([month, bySupermarket]) => {
          const supermarkets = [...bySupermarket.entries()]
            .map(([supermarketId, totalCents]) => ({
              supermarketId,
              supermarketName: names[supermarketId] ?? supermarketId,
              totalCents,
            }))
            .sort((a, b) => a.supermarketName.localeCompare(b.supermarketName));
          return {
            month,
            totalCents: supermarkets.reduce((sum, s) => sum + s.totalCents, 0),
            supermarkets,
          };
        }),
    );
  }

  getReceipt(listId: string, receiptId: string): Promise<Receipt> {
    const receipt: Receipt = { ...this.find(listId, receiptId) };
    delete (receipt as Partial<Receipt & { listId: string }>).listId;
    return Promise.resolve(structuredClone(receipt));
  }

  photoUrl(listId: string, receiptId: string): string {
    return `/api/lists/${listId}/receipts/${receiptId}/photo`;
  }

  uploadReceipt(listId: string, supermarketId: string, photo: File): Promise<string> {
    return this.change(`upload ${supermarketId}`, () => {
      this.assertList(listId);
      this.uploads.push({ listId, supermarketId, fileName: photo.name });
      return this.seed({ listId, supermarketId, status: 'reading' }).id;
    });
  }

  updateReceipt(listId: string, receiptId: string, changes: ReceiptChanges): Promise<void> {
    return this.edit(listId, receiptId, `update ${JSON.stringify(changes)}`, (receipt) => {
      receipt.date = changes.date ?? receipt.date;
      receipt.totalCents = changes.totalCents ?? receipt.totalCents;
    });
  }

  addLine(listId: string, receiptId: string, line: NewLine): Promise<string> {
    const id = this.nextId('line');
    return this.edit(listId, receiptId, `add ${line.text}`, (receipt) => {
      receipt.lines.push({
        id,
        kind: line.kind,
        text: line.text,
        quantity:
          line.unit === undefined || line.quantity === undefined
            ? null
            : { unit: line.unit, value: line.quantity },
        amountCents: line.amountCents,
        linkedProduct: null,
        suggestedProduct: null,
      });
    }).then(() => id);
  }

  updateLine(
    listId: string,
    receiptId: string,
    lineId: string,
    changes: LineChanges,
  ): Promise<void> {
    return this.edit(
      listId,
      receiptId,
      `updateLine ${lineId} ${JSON.stringify(changes)}`,
      (receipt) => {
        const line = this.line(receipt, lineId);
        line.text = changes.text ?? line.text;
        line.amountCents = changes.amountCents ?? line.amountCents;
        if (changes.unit !== undefined && changes.quantity !== undefined) {
          line.quantity = { unit: changes.unit, value: changes.quantity };
        }
      },
    );
  }

  removeLine(listId: string, receiptId: string, lineId: string): Promise<void> {
    return this.edit(listId, receiptId, `removeLine ${lineId}`, (receipt) => {
      receipt.lines = receipt.lines.filter((line) => line.id !== lineId);
    });
  }

  linkLine(listId: string, receiptId: string, lineId: string, productId: string): Promise<void> {
    return this.edit(listId, receiptId, `link ${lineId} ${productId}`, (receipt) => {
      const product = PRODUCTS[productId];
      if (product === undefined || product.supermarketId !== receipt.supermarketId) {
        throw new ValidationError(problem(422));
      }
      const line = this.line(receipt, lineId);
      line.linkedProduct = {
        id: product.id,
        name: product.name,
        brand: product.brand,
        available: product.available,
      };
      line.suggestedProduct = null;
    });
  }

  unlinkLine(listId: string, receiptId: string, lineId: string): Promise<void> {
    return this.edit(listId, receiptId, `unlink ${lineId}`, (receipt) => {
      const line = this.line(receipt, lineId);
      line.linkedProduct = null;
      line.suggestedProduct = null;
    });
  }

  confirmReceipt(listId: string, receiptId: string): Promise<void> {
    return this.edit(listId, receiptId, `confirm ${receiptId}`, (receipt) => {
      if (receipt.date === null || receipt.totalCents === null) {
        throw new ValidationError(problem(422));
      }
      receipt.status = 'confirmed';
    });
  }

  deleteReceipt(listId: string, receiptId: string): Promise<void> {
    return this.change(`delete ${receiptId}`, () => {
      this.find(listId, receiptId);
      this.receipts.delete(receiptId);
    });
  }

  private edit(
    listId: string,
    receiptId: string,
    call: string,
    apply: (receipt: Receipt) => void,
  ): Promise<void> {
    return this.change(call, () => {
      const receipt = this.find(listId, receiptId);
      if (receipt.status === 'reading') {
        throw new ConflictError(problem(409));
      }
      apply(receipt);
      this.recompute(receipt);
    });
  }

  private change<T>(call: string, apply: () => T): Promise<T> {
    this.calls.push(call);
    if (this.changeError !== null) {
      return Promise.reject(this.changeError);
    }
    try {
      return Promise.resolve(apply());
    } catch (error) {
      return Promise.reject(error instanceof Error ? error : new Error(String(error)));
    }
  }

  private recompute(receipt: Receipt): void {
    if (receipt.totalCents === null) {
      receipt.differenceCents = null;
      return;
    }
    const lines = receipt.lines.reduce(
      (sum, line) => sum + (line.kind === 'product' ? line.amountCents : -line.amountCents),
      0,
    );
    receipt.differenceCents = lines - receipt.totalCents;
  }

  private line(receipt: Receipt, lineId: string): ReceiptLine {
    const line = receipt.lines.find((candidate) => candidate.id === lineId);
    if (line === undefined) {
      throw new NotFoundError(problem(404));
    }
    return line;
  }

  private find(listId: string, receiptId: string): Receipt & { listId: string } {
    this.assertList(listId);
    const receipt = this.receipts.get(receiptId);
    if (receipt?.listId !== listId) {
      throw new NotFoundError(problem(404));
    }
    return receipt;
  }

  private assertList(listId: string): void {
    if (!this.lists.has(listId)) {
      throw new NotFoundError(problem(404));
    }
  }

  private nextId(prefix: string): string {
    this.issued++;
    return `${prefix}-${String(this.issued)}`;
  }
}
