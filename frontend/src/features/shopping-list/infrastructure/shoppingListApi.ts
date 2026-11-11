import { UnexpectedResponseError } from '@/shared/http/errors';
import type { HttpClient } from '@/shared/http/httpClient';

import type { ShoppingLists } from '../application/ShoppingLists';
import type {
  ListItem,
  ListMember,
  ListProduct,
  ListRole,
  ListSummary,
  ShoppingList,
  SupermarketTotal,
} from '../domain/shoppingList';

/** Shopping lists API client (docs/architecture.md#http-api-draft, `/api/lists` rows). */
export function createShoppingListApi(http: HttpClient): ShoppingLists {
  const listPath = (listId: string, rest = '') => `/api/lists/${encodeURIComponent(listId)}${rest}`;
  const itemPath = (listId: string, itemId: string) =>
    listPath(listId, `/items/${encodeURIComponent(itemId)}`);

  return {
    async listMyLists(options) {
      return list(await http.get('/api/lists', options), 'lists').map(toSummary);
    },

    async createList(name) {
      return idOf(await http.post('/api/lists', { name }));
    },

    async joinList(shareToken) {
      return idOf(await http.post(`/api/lists/join/${encodeURIComponent(shareToken)}`));
    },

    async getList(listId, { etag, signal } = {}) {
      const response = await http.getConditional(listPath(listId), {
        etag: etag ?? null,
        ...(signal === undefined ? {} : { signal }),
      });
      return response.notModified
        ? { status: 'notModified' }
        : { status: 'changed', list: toShoppingList(response.body), etag: response.etag };
    },

    async renameList(listId, name) {
      await http.patch(listPath(listId), { name });
    },

    async deleteList(listId) {
      await http.delete(listPath(listId));
    },

    async leaveList(listId) {
      await http.post(listPath(listId, '/leave'));
    },

    async regenerateShareToken(listId) {
      const json = object(await http.post(listPath(listId, '/share-token')), 'share token');
      return string(json.shareToken, 'shareToken');
    },

    async removeMember(listId, userId) {
      await http.delete(listPath(listId, `/members/${encodeURIComponent(userId)}`));
    },

    async addItem(listId, item) {
      return idOf(await http.post(listPath(listId, '/items'), item));
    },

    async updateItem(listId, itemId, changes) {
      await http.patch(itemPath(listId, itemId), changes);
    },

    async removeItem(listId, itemId) {
      await http.delete(itemPath(listId, itemId));
    },

    async clearCheckedItems(listId) {
      await http.delete(listPath(listId, '/items?checked=true'));
    },
  };
}

type Json = Record<string, unknown>;

const roles: readonly ListRole[] = ['owner', 'member'];

function toSummary(value: unknown): ListSummary {
  const json = object(value, 'list summary');
  return {
    id: string(json.id, 'list.id'),
    name: string(json.name, 'list.name'),
    role: role(json.role, 'list.role'),
    ownerUsername: string(json.ownerUsername, 'list.ownerUsername'),
    itemCount: integer(json.itemCount, 'list.itemCount'),
    updatedAt: date(json.updatedAt, 'list.updatedAt'),
  };
}

function toShoppingList(body: unknown): ShoppingList {
  const json = object(body, 'list');
  return {
    id: string(json.id, 'list.id'),
    name: string(json.name, 'list.name'),
    role: role(json.role, 'list.role'),
    version: integer(json.version, 'list.version'),
    shareToken: nullable(json.shareToken, (token) => string(token, 'list.shareToken')),
    owner: toMember(json.owner),
    members: list(json.members, 'list.members').map(toMember),
    items: list(json.items, 'list.items').map(toItem),
    totals: list(json.totals, 'list.totals').map(toTotal),
  };
}

function toMember(value: unknown): ListMember {
  const json = object(value, 'member');
  return { id: string(json.id, 'member.id'), username: string(json.username, 'member.username') };
}

function toItem(value: unknown): ListItem {
  const json = object(value, 'item');
  const base = {
    id: string(json.id, 'item.id'),
    position: integer(json.position, 'item.position'),
    quantity: integer(json.quantity, 'item.quantity'),
    checked: boolean(json.checked, 'item.checked'),
  };
  switch (json.type) {
    case 'text':
      return { ...base, type: 'text', text: string(json.text, 'item.text') };
    case 'product':
      return { ...base, type: 'product', product: toProduct(json.product) };
    default:
      throw unexpected('item.type', json.type);
  }
}

function toProduct(value: unknown): ListProduct {
  const json = object(value, 'item.product');
  const optionalString = (field: string) =>
    nullable(json[field], (text) => string(text, `product.${field}`));
  return {
    id: string(json.id, 'product.id'),
    name: optionalString('name'),
    brand: optionalString('brand'),
    supermarketId: optionalString('supermarketId'),
    priceCents: nullable(json.priceCents, (cents) => integer(cents, 'product.priceCents')),
    imageUrl: optionalString('imageUrl'),
    available: boolean(json.available, 'product.available'),
  };
}

function toTotal(value: unknown): SupermarketTotal {
  const json = object(value, 'total');
  return {
    supermarketId: string(json.supermarketId, 'total.supermarketId'),
    supermarketName: string(json.supermarketName, 'total.supermarketName'),
    totalCents: integer(json.totalCents, 'total.totalCents'),
    itemCount: integer(json.itemCount, 'total.itemCount'),
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
  return value;
}

function string(value: unknown, field: string): string {
  if (typeof value !== 'string') {
    throw unexpected(field, value);
  }
  return value;
}

function integer(value: unknown, field: string): number {
  if (!Number.isInteger(value)) {
    throw unexpected(field, value);
  }
  return value as number;
}

function boolean(value: unknown, field: string): boolean {
  if (typeof value !== 'boolean') {
    throw unexpected(field, value);
  }
  return value;
}

function role(value: unknown, field: string): ListRole {
  if (typeof value !== 'string' || !(roles as readonly string[]).includes(value)) {
    throw unexpected(field, value);
  }
  return value as ListRole;
}

function date(value: unknown, field: string): Date {
  const parsed = new Date(string(value, field));
  if (Number.isNaN(parsed.getTime())) {
    throw unexpected(field, value);
  }
  return parsed;
}

function nullable<T>(value: unknown, map: (value: unknown) => T): T | null {
  return value === null ? null : map(value);
}

function unexpected(field: string, value: unknown): UnexpectedResponseError {
  const shown = value === undefined ? 'missing' : JSON.stringify(value);
  return new UnexpectedResponseError(`Unexpected shopping list response: ${field} is ${shown}.`);
}
