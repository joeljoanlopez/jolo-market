import { http, HttpResponse } from 'msw/http';
import { describe, expect, it } from 'vitest';

import {
  ConflictError,
  NotFoundError,
  UnexpectedResponseError,
  ValidationError,
} from '@/shared/http/errors';
import { createHttpClient } from '@/shared/http/httpClient';
import { server } from '@/test/server';

import { createShoppingListApi } from './shoppingListApi';

const baseUrl = 'http://localhost';
const api = createShoppingListApi(createHttpClient({ baseUrl }));
const listId = '0193-list';

function problem(status: number, title: string) {
  return HttpResponse.json(
    { type: 'about:blank', title, status },
    { status, headers: { 'Content-Type': 'application/problem+json' } },
  );
}

/** The `GET /api/lists/{id}` example of docs/architecture.md. */
const listJson = {
  id: listId,
  name: 'Compra semanal',
  role: 'owner',
  version: 7,
  shareToken: 'kq3',
  owner: { id: 'user-maria', username: 'maria' },
  members: [{ id: 'user-joan', username: 'joan' }],
  items: [
    {
      id: 'item-1',
      position: 0,
      quantity: 2,
      checked: false,
      type: 'text',
      text: 'pan',
      product: null,
    },
    {
      id: 'item-2',
      position: 1,
      quantity: 1,
      checked: true,
      type: 'product',
      text: null,
      product: {
        id: 'p1',
        name: 'Leche entera',
        brand: 'Hacendado',
        supermarketId: 'mercadona',
        priceCents: 125,
        imageUrl: null,
        available: true,
      },
    },
  ],
  totals: [
    { supermarketId: 'mercadona', supermarketName: 'Mercadona', totalCents: 125, itemCount: 1 },
  ],
};

const list = {
  id: listId,
  name: 'Compra semanal',
  role: 'owner',
  version: 7,
  shareToken: 'kq3',
  owner: { id: 'user-maria', username: 'maria' },
  members: [{ id: 'user-joan', username: 'joan' }],
  items: [
    { id: 'item-1', position: 0, quantity: 2, checked: false, type: 'text', text: 'pan' },
    {
      id: 'item-2',
      position: 1,
      quantity: 1,
      checked: true,
      type: 'product',
      product: {
        id: 'p1',
        name: 'Leche entera',
        brand: 'Hacendado',
        supermarketId: 'mercadona',
        priceCents: 125,
        imageUrl: null,
        available: true,
      },
    },
  ],
  totals: [
    { supermarketId: 'mercadona', supermarketName: 'Mercadona', totalCents: 125, itemCount: 1 },
  ],
};

/** Records what the API received, so each test can check method, path, body and CSRF header. */
function recordRequests() {
  const received: { method: string; path: string; body: unknown; csrf: string | null }[] = [];
  const record = async (request: Request) => {
    const text = await request.text();
    received.push({
      method: request.method,
      path: new URL(request.url).pathname + new URL(request.url).search,
      body: text === '' ? undefined : (JSON.parse(text) as unknown),
      csrf: request.headers.get('X-Requested-With'),
    });
  };
  return { received, record };
}

describe('shoppingListApi', () => {
  it('lists my lists, with the role in each', async () => {
    server.use(
      http.get(`${baseUrl}/api/lists`, () =>
        HttpResponse.json([
          {
            id: 'list-1',
            name: 'Compra semanal',
            role: 'owner',
            ownerUsername: 'maria',
            itemCount: 3,
            updatedAt: '2026-11-11T10:00:00+00:00',
          },
          {
            id: 'list-2',
            name: 'Cena',
            role: 'member',
            ownerUsername: 'joan',
            itemCount: 0,
            updatedAt: '2026-11-10T10:00:00+00:00',
          },
        ]),
      ),
    );

    await expect(api.listMyLists()).resolves.toEqual([
      {
        id: 'list-1',
        name: 'Compra semanal',
        role: 'owner',
        ownerUsername: 'maria',
        itemCount: 3,
        updatedAt: new Date('2026-11-11T10:00:00Z'),
      },
      {
        id: 'list-2',
        name: 'Cena',
        role: 'member',
        ownerUsername: 'joan',
        itemCount: 0,
        updatedAt: new Date('2026-11-10T10:00:00Z'),
      },
    ]);
  });

  it('rejects a summary with an unknown role', async () => {
    server.use(
      http.get(`${baseUrl}/api/lists`, () =>
        HttpResponse.json([
          {
            id: 'list-1',
            name: 'Compra',
            role: 'admin',
            ownerUsername: 'maria',
            itemCount: 0,
            updatedAt: '2026-11-11T10:00:00+00:00',
          },
        ]),
      ),
    );

    await expect(api.listMyLists()).rejects.toBeInstanceOf(UnexpectedResponseError);
  });

  it('creates a list and resolves with its id', async () => {
    const { received, record } = recordRequests();
    server.use(
      http.post(`${baseUrl}/api/lists`, async ({ request }) => {
        await record(request);
        return HttpResponse.json({ id: 'list-new' }, { status: 201 });
      }),
    );

    await expect(api.createList('Semana')).resolves.toBe('list-new');
    expect(received).toEqual([
      { method: 'POST', path: '/api/lists', body: { name: 'Semana' }, csrf: 'XMLHttpRequest' },
    ]);
  });

  it('rejects an invalid name with a ValidationError', async () => {
    server.use(http.post(`${baseUrl}/api/lists`, () => problem(422, 'Unprocessable Content')));

    await expect(api.createList(' ')).rejects.toBeInstanceOf(ValidationError);
  });

  it('joins through a share token and resolves with the list id', async () => {
    const { received, record } = recordRequests();
    server.use(
      http.post(`${baseUrl}/api/lists/join/:token`, async ({ request }) => {
        await record(request);
        return HttpResponse.json({ id: listId });
      }),
    );

    await expect(api.joinList('kq3-_x')).resolves.toBe(listId);
    expect(received[0]).toMatchObject({ method: 'POST', path: '/api/lists/join/kq3-_x' });
  });

  it('rejects an unknown or revoked token with a NotFoundError', async () => {
    server.use(http.post(`${baseUrl}/api/lists/join/:token`, () => problem(404, 'Not Found')));

    await expect(api.joinList('revoked')).rejects.toBeInstanceOf(NotFoundError);
  });

  it('reads a list with its ETag', async () => {
    server.use(
      http.get(`${baseUrl}/api/lists/${listId}`, () =>
        HttpResponse.json(listJson, { headers: { ETag: '"7"' } }),
      ),
    );

    await expect(api.getList(listId)).resolves.toEqual({ status: 'changed', list, etag: '"7"' });
  });

  it('reports an unchanged list when the API answers 304 to the ETag', async () => {
    let ifNoneMatch: string | null = null;
    server.use(
      http.get(`${baseUrl}/api/lists/${listId}`, ({ request }) => {
        ifNoneMatch = request.headers.get('If-None-Match');
        return new HttpResponse(null, { status: 304 });
      }),
    );

    await expect(api.getList(listId, { etag: '"7"' })).resolves.toEqual({
      status: 'notModified',
    });
    expect(ifNoneMatch).toBe('"7"');
  });

  it('maps a member view and a product the catalog no longer knows', async () => {
    server.use(
      http.get(`${baseUrl}/api/lists/${listId}`, () =>
        HttpResponse.json({
          ...listJson,
          role: 'member',
          shareToken: null,
          items: [
            {
              id: 'item-3',
              position: 0,
              quantity: 1,
              checked: false,
              type: 'product',
              text: null,
              product: {
                id: 'p9',
                name: null,
                brand: null,
                supermarketId: null,
                priceCents: null,
                imageUrl: null,
                available: false,
              },
            },
          ],
          totals: [],
        }),
      ),
    );

    await expect(api.getList(listId)).resolves.toEqual({
      status: 'changed',
      etag: null,
      list: {
        ...list,
        role: 'member',
        shareToken: null,
        items: [
          {
            id: 'item-3',
            position: 0,
            quantity: 1,
            checked: false,
            type: 'product',
            product: {
              id: 'p9',
              name: null,
              brand: null,
              supermarketId: null,
              priceCents: null,
              imageUrl: null,
              available: false,
            },
          },
        ],
        totals: [],
      },
    });
  });

  it('rejects an item with an unknown type', async () => {
    server.use(
      http.get(`${baseUrl}/api/lists/${listId}`, () =>
        HttpResponse.json({
          ...listJson,
          items: [{ ...listJson.items[0], type: 'recipe' }],
        }),
      ),
    );

    await expect(api.getList(listId)).rejects.toBeInstanceOf(UnexpectedResponseError);
  });

  it('rejects a list the user cannot access with a NotFoundError', async () => {
    server.use(http.get(`${baseUrl}/api/lists/${listId}`, () => problem(404, 'Not Found')));

    await expect(api.getList(listId)).rejects.toBeInstanceOf(NotFoundError);
  });

  it('regenerates the share token and resolves with the new one', async () => {
    const { received, record } = recordRequests();
    server.use(
      http.post(`${baseUrl}/api/lists/${listId}/share-token`, async ({ request }) => {
        await record(request);
        return HttpResponse.json({ shareToken: 'new-token' });
      }),
    );

    await expect(api.regenerateShareToken(listId)).resolves.toBe('new-token');
    expect(received[0]).toMatchObject({ method: 'POST', csrf: 'XMLHttpRequest' });
  });

  it('adds a free-text item and a product item, and resolves with the item id', async () => {
    const { received, record } = recordRequests();
    server.use(
      http.post(`${baseUrl}/api/lists/${listId}/items`, async ({ request }) => {
        await record(request);
        return HttpResponse.json({ id: `item-${String(received.length)}` }, { status: 201 });
      }),
    );

    await expect(api.addItem(listId, { type: 'text', text: 'pan', quantity: 2 })).resolves.toBe(
      'item-1',
    );
    await expect(api.addItem(listId, { type: 'product', productId: 'p1' })).resolves.toBe('item-2');
    expect(received.map((request) => request.body)).toEqual([
      { type: 'text', text: 'pan', quantity: 2 },
      { type: 'product', productId: 'p1' },
    ]);
  });

  it('sends every change of the list to its route', async () => {
    const { received, record } = recordRequests();
    const noContent = async ({ request }: { request: Request }) => {
      await record(request);
      return new HttpResponse(null, { status: 204 });
    };
    server.use(
      http.patch(`${baseUrl}/api/lists/${listId}`, noContent),
      http.delete(`${baseUrl}/api/lists/${listId}`, noContent),
      http.post(`${baseUrl}/api/lists/${listId}/leave`, noContent),
      http.delete(`${baseUrl}/api/lists/${listId}/members/:userId`, noContent),
      http.patch(`${baseUrl}/api/lists/${listId}/items/:itemId`, noContent),
      http.delete(`${baseUrl}/api/lists/${listId}/items/:itemId`, noContent),
      http.delete(`${baseUrl}/api/lists/${listId}/items`, noContent),
    );

    await api.renameList(listId, 'Cena');
    await api.removeMember(listId, 'user-joan');
    await api.updateItem(listId, 'item-1', { checked: true });
    await api.removeItem(listId, 'item-1');
    await api.clearCheckedItems(listId);
    await api.leaveList(listId);
    await api.deleteList(listId);

    expect(received).toEqual([
      {
        method: 'PATCH',
        path: `/api/lists/${listId}`,
        body: { name: 'Cena' },
        csrf: 'XMLHttpRequest',
      },
      {
        method: 'DELETE',
        path: `/api/lists/${listId}/members/user-joan`,
        body: undefined,
        csrf: 'XMLHttpRequest',
      },
      {
        method: 'PATCH',
        path: `/api/lists/${listId}/items/item-1`,
        body: { checked: true },
        csrf: 'XMLHttpRequest',
      },
      {
        method: 'DELETE',
        path: `/api/lists/${listId}/items/item-1`,
        body: undefined,
        csrf: 'XMLHttpRequest',
      },
      {
        method: 'DELETE',
        path: `/api/lists/${listId}/items?checked=true`,
        body: undefined,
        csrf: 'XMLHttpRequest',
      },
      {
        method: 'POST',
        path: `/api/lists/${listId}/leave`,
        body: undefined,
        csrf: 'XMLHttpRequest',
      },
      { method: 'DELETE', path: `/api/lists/${listId}`, body: undefined, csrf: 'XMLHttpRequest' },
    ]);
  });

  it('rejects the owner leaving their own list with a ConflictError (PD-44)', async () => {
    server.use(http.post(`${baseUrl}/api/lists/${listId}/leave`, () => problem(409, 'Conflict')));

    await expect(api.leaveList(listId)).rejects.toBeInstanceOf(ConflictError);
  });
});
