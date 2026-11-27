import { UnexpectedResponseError } from '@/shared/http/errors';
import type { HttpClient } from '@/shared/http/httpClient';

import type { ReceiptsApi } from '../application/ReceiptsApi';
import type {
  LineKind,
  LineProduct,
  LineQuantity,
  ListReceipts,
  MonthlySpending,
  QuantityUnit,
  Receipt,
  ReceiptLine,
  ReceiptStatus,
  ReceiptSummary,
  ReceiptUser,
  SupermarketSpending,
} from '../domain/receipt';

/** Receipts API client (docs/architecture.md#http-api-draft, `/api/lists/{id}/receipts` rows). */
export function createReceiptsApi(http: HttpClient): ReceiptsApi {
  const listPath = (listId: string, rest: string) =>
    `/api/lists/${encodeURIComponent(listId)}${rest}`;
  const receiptPath = (listId: string, receiptId: string, rest = '') =>
    listPath(listId, `/receipts/${encodeURIComponent(receiptId)}${rest}`);
  const linePath = (listId: string, receiptId: string, lineId: string, rest = '') =>
    receiptPath(listId, receiptId, `/lines/${encodeURIComponent(lineId)}${rest}`);

  return {
    async listReceipts(listId, options) {
      return toListReceipts(await http.get(listPath(listId, '/receipts'), options));
    },

    async listSpending(listId, options) {
      const json = object(await http.get(listPath(listId, '/spending'), options), 'spending');
      return list(json.months, 'spending.months').map(toMonth);
    },

    async getReceipt(listId, receiptId, options) {
      return toReceipt(await http.get(receiptPath(listId, receiptId), options));
    },

    photoUrl(listId, receiptId) {
      return receiptPath(listId, receiptId, '/photo');
    },

    async uploadReceipt(listId, supermarketId, photo) {
      const form = new FormData();
      form.append('supermarketId', supermarketId);
      form.append('photo', photo, photo.name);
      return idOf(await http.postForm(listPath(listId, '/receipts'), form));
    },

    async updateReceipt(listId, receiptId, changes) {
      await http.patch(receiptPath(listId, receiptId), changes);
    },

    async addLine(listId, receiptId, line) {
      return idOf(await http.post(receiptPath(listId, receiptId, '/lines'), line));
    },

    async updateLine(listId, receiptId, lineId, changes) {
      await http.patch(linePath(listId, receiptId, lineId), changes);
    },

    async removeLine(listId, receiptId, lineId) {
      await http.delete(linePath(listId, receiptId, lineId));
    },

    async linkLine(listId, receiptId, lineId, productId) {
      await http.put(linePath(listId, receiptId, lineId, '/product'), { productId });
    },

    async unlinkLine(listId, receiptId, lineId) {
      await http.delete(linePath(listId, receiptId, lineId, '/product'));
    },

    async confirmReceipt(listId, receiptId) {
      await http.post(receiptPath(listId, receiptId, '/confirm'));
    },

    async deleteReceipt(listId, receiptId) {
      await http.delete(receiptPath(listId, receiptId));
    },
  };
}

type Json = Record<string, unknown>;

const statuses: readonly ReceiptStatus[] = ['reading', 'draft', 'confirmed'];
const kinds: readonly LineKind[] = ['product', 'discount'];
const units: readonly QuantityUnit[] = ['units', 'kg'];

function toListReceipts(body: unknown): ListReceipts {
  const json = object(body, 'receipts');
  return {
    pending: list(json.pending, 'receipts.pending').map(toSummary),
    confirmed: list(json.confirmed, 'receipts.confirmed').map(toSummary),
  };
}

function toSummary(value: unknown): ReceiptSummary {
  const json = object(value, 'receipt');
  return {
    id: string(json.id, 'receipt.id'),
    status: oneOf(json.status, statuses, 'receipt.status'),
    supermarketId: string(json.supermarketId, 'receipt.supermarketId'),
    supermarketName: string(json.supermarketName, 'receipt.supermarketName'),
    date: nullable(json.date, (date) => string(date, 'receipt.date')),
    totalCents: nullable(json.totalCents, (cents) => integer(cents, 'receipt.totalCents')),
    uploader: toUser(json.uploader),
    uploadedAt: dateTime(json.uploadedAt, 'receipt.uploadedAt'),
  };
}

function toReceipt(body: unknown): Receipt {
  const json = object(body, 'receipt');
  return {
    ...toSummary(json),
    differenceCents: nullable(json.differenceCents, (cents) =>
      integer(cents, 'receipt.differenceCents'),
    ),
    lines: list(json.lines, 'receipt.lines').map(toLine),
  };
}

function toLine(value: unknown): ReceiptLine {
  const json = object(value, 'line');
  return {
    id: string(json.id, 'line.id'),
    kind: oneOf(json.kind, kinds, 'line.kind'),
    text: string(json.text, 'line.text'),
    quantity: toQuantity(json.unit, json.quantity),
    amountCents: integer(json.amountCents, 'line.amountCents'),
    linkedProduct: nullable(json.linkedProduct, toProduct),
    suggestedProduct: nullable(json.suggestedProduct, toProduct),
  };
}

function toQuantity(unit: unknown, quantity: unknown): LineQuantity | null {
  if (unit === null && quantity === null) {
    return null;
  }
  return { unit: oneOf(unit, units, 'line.unit'), value: integer(quantity, 'line.quantity') };
}

function toProduct(value: unknown): LineProduct {
  const json = object(value, 'product');
  return {
    id: string(json.id, 'product.id'),
    name: nullable(json.name, (name) => string(name, 'product.name')),
    brand: nullable(json.brand, (brand) => string(brand, 'product.brand')),
    available: boolean(json.available, 'product.available'),
  };
}

function toUser(value: unknown): ReceiptUser {
  const json = object(value, 'uploader');
  return {
    id: string(json.id, 'uploader.id'),
    username: string(json.username, 'uploader.username'),
  };
}

function toMonth(value: unknown): MonthlySpending {
  const json = object(value, 'month');
  return {
    month: string(json.month, 'month.month'),
    totalCents: integer(json.totalCents, 'month.totalCents'),
    supermarkets: list(json.supermarkets, 'month.supermarkets').map(toSupermarketSpending),
  };
}

function toSupermarketSpending(value: unknown): SupermarketSpending {
  const json = object(value, 'supermarket');
  return {
    supermarketId: string(json.supermarketId, 'supermarket.supermarketId'),
    supermarketName: string(json.supermarketName, 'supermarket.supermarketName'),
    totalCents: integer(json.totalCents, 'supermarket.totalCents'),
  };
}

function idOf(body: unknown): string {
  return string(object(body, 'created').id, 'id');
}

function object(value: unknown, field: string): Json {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) {
    throw unexpected(field, value);
  }
  return value as Json;
}

function list(value: unknown, field: string): unknown[] {
  if (!Array.isArray(value)) {
    throw unexpected(field, value);
  }
  return value as unknown[];
}

function string(value: unknown, field: string): string {
  if (typeof value !== 'string') {
    throw unexpected(field, value);
  }
  return value;
}

function integer(value: unknown, field: string): number {
  if (typeof value !== 'number' || !Number.isInteger(value)) {
    throw unexpected(field, value);
  }
  return value;
}

function boolean(value: unknown, field: string): boolean {
  if (typeof value !== 'boolean') {
    throw unexpected(field, value);
  }
  return value;
}

function dateTime(value: unknown, field: string): Date {
  const date = new Date(string(value, field));
  if (Number.isNaN(date.getTime())) {
    throw unexpected(field, value);
  }
  return date;
}

function oneOf<T extends string>(value: unknown, allowed: readonly T[], field: string): T {
  const found = allowed.find((candidate) => candidate === value);
  if (found === undefined) {
    throw unexpected(field, value);
  }
  return found;
}

function nullable<T>(value: unknown, map: (value: unknown) => T): T | null {
  return value === null ? null : map(value);
}

function unexpected(field: string, value: unknown): UnexpectedResponseError {
  return new UnexpectedResponseError(
    `Unexpected ${field} in a receipts response: ${JSON.stringify(value)}`,
  );
}
