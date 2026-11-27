import { http, HttpResponse } from 'msw/http';
import { describe, expect, it } from 'vitest';

import { NotFoundError, UnexpectedResponseError } from '@/shared/http/errors';
import { createHttpClient } from '@/shared/http/httpClient';
import { server } from '@/test/server';

import { createReceiptsApi } from './receiptsApi';

const baseUrl = 'http://localhost';
const api = createReceiptsApi(createHttpClient({ baseUrl }));
const receiptsPath = `${baseUrl}/api/lists/l1/receipts`;
const receiptPath = `${receiptsPath}/r1`;

const summaryJson = {
  id: 'r1',
  status: 'confirmed',
  supermarketId: 'mercadona',
  supermarketName: 'Mercadona',
  date: '2026-11-14',
  totalCents: 2599,
  uploader: { id: 'u1', username: 'maria' },
  uploadedAt: '2026-11-14T18:00:00+00:00',
};

const receiptJson = {
  ...summaryJson,
  status: 'draft',
  differenceCents: -50,
  lines: [
    {
      id: 'line-1',
      kind: 'product',
      text: 'LECHE ENT.',
      unit: 'kg',
      quantity: 1500,
      amountCents: 750,
      linkedProduct: null,
      suggestedProduct: { id: 'p1', name: 'Leche entera', brand: 'Hacendado', available: true },
    },
    {
      id: 'line-2',
      kind: 'discount',
      text: 'DTO',
      unit: null,
      quantity: null,
      amountCents: 50,
      linkedProduct: null,
      suggestedProduct: null,
    },
  ],
};

function problem(status: number) {
  return HttpResponse.json(
    { type: 'about:blank', title: 'Error', status },
    { status, headers: { 'Content-Type': 'application/problem+json' } },
  );
}

describe('receiptsApi', () => {
  it('reads the pending and confirmed receipts of a list', async () => {
    server.use(
      http.get(receiptsPath, () =>
        HttpResponse.json({
          pending: [{ ...summaryJson, status: 'reading', date: null, totalCents: null }],
          confirmed: [summaryJson],
        }),
      ),
    );

    const receipts = await api.listReceipts('l1');

    expect(receipts.pending[0]).toMatchObject({ status: 'reading', date: null, totalCents: null });
    expect(receipts.confirmed).toEqual([
      { ...summaryJson, uploadedAt: new Date('2026-11-14T18:00:00Z') },
    ]);
  });

  it('reads the spending per month and supermarket', async () => {
    const months = [
      {
        month: '2026-11',
        totalCents: 1500,
        supermarkets: [{ supermarketId: 'aldi', supermarketName: 'Aldi', totalCents: 1500 }],
      },
    ];
    server.use(http.get(`${baseUrl}/api/lists/l1/spending`, () => HttpResponse.json({ months })));

    await expect(api.listSpending('l1')).resolves.toEqual(months);
  });

  it('reads a receipt with its lines', async () => {
    server.use(http.get(receiptPath, () => HttpResponse.json(receiptJson)));

    const receipt = await api.getReceipt('l1', 'r1');

    expect(receipt.differenceCents).toBe(-50);
    expect(receipt.lines).toEqual([
      {
        id: 'line-1',
        kind: 'product',
        text: 'LECHE ENT.',
        quantity: { unit: 'kg', value: 1500 },
        amountCents: 750,
        linkedProduct: null,
        suggestedProduct: { id: 'p1', name: 'Leche entera', brand: 'Hacendado', available: true },
      },
      {
        id: 'line-2',
        kind: 'discount',
        text: 'DTO',
        quantity: null,
        amountCents: 50,
        linkedProduct: null,
        suggestedProduct: null,
      },
    ]);
  });

  it('rejects a body of the wrong shape', async () => {
    server.use(
      http.get(receiptPath, () => HttpResponse.json({ ...receiptJson, status: 'lost' })),
      http.get(receiptsPath, () => HttpResponse.json({ pending: [] })),
      http.get(`${baseUrl}/api/lists/l1/spending`, () =>
        HttpResponse.json({ months: [{ month: 11 }] }),
      ),
    );

    await expect(api.getReceipt('l1', 'r1')).rejects.toBeInstanceOf(UnexpectedResponseError);
    await expect(api.listReceipts('l1')).rejects.toBeInstanceOf(UnexpectedResponseError);
    await expect(api.listSpending('l1')).rejects.toBeInstanceOf(UnexpectedResponseError);
  });

  it('uploads the photo with the supermarket and resolves with the receipt id', async () => {
    server.use(
      http.post(receiptsPath, async ({ request }) => {
        const form = await request.formData();
        const photo = form.get('photo');
        expect(form.get('supermarketId')).toBe('aldi');
        expect(typeof photo === 'string' || photo === null ? null : await photo.text()).toBe('png');
        return HttpResponse.json({ id: 'r9' }, { status: 202 });
      }),
    );

    await expect(
      api.uploadReceipt('l1', 'aldi', new File(['png'], 'ticket.png', { type: 'image/png' })),
    ).resolves.toBe('r9');
  });

  it('sends every edit to its endpoint', async () => {
    const calls: string[] = [];
    const record = async ({ request }: { request: Request }) => {
      const body = await request.text();
      calls.push(`${request.method} ${new URL(request.url).pathname} ${body}`);
      return request.method === 'POST' && request.url.endsWith('/lines')
        ? HttpResponse.json({ id: 'line-9' }, { status: 201 })
        : new HttpResponse(null, { status: 204 });
    };
    server.use(
      http.patch(receiptPath, record),
      http.post(`${receiptPath}/lines`, record),
      http.patch(`${receiptPath}/lines/line-1`, record),
      http.delete(`${receiptPath}/lines/line-1`, record),
      http.put(`${receiptPath}/lines/line-1/product`, record),
      http.delete(`${receiptPath}/lines/line-1/product`, record),
      http.post(`${receiptPath}/confirm`, record),
      http.delete(receiptPath, record),
    );

    await api.updateReceipt('l1', 'r1', { date: '2026-11-14', totalCents: 100 });
    await expect(
      api.addLine('l1', 'r1', { kind: 'discount', text: 'DTO', amountCents: 10 }),
    ).resolves.toBe('line-9');
    await api.updateLine('l1', 'r1', 'line-1', { unit: 'units', quantity: 2 });
    await api.linkLine('l1', 'r1', 'line-1', 'p1');
    await api.unlinkLine('l1', 'r1', 'line-1');
    await api.removeLine('l1', 'r1', 'line-1');
    await api.confirmReceipt('l1', 'r1');
    await api.deleteReceipt('l1', 'r1');

    expect(calls).toEqual([
      'PATCH /api/lists/l1/receipts/r1 {"date":"2026-11-14","totalCents":100}',
      'POST /api/lists/l1/receipts/r1/lines {"kind":"discount","text":"DTO","amountCents":10}',
      'PATCH /api/lists/l1/receipts/r1/lines/line-1 {"unit":"units","quantity":2}',
      'PUT /api/lists/l1/receipts/r1/lines/line-1/product {"productId":"p1"}',
      'DELETE /api/lists/l1/receipts/r1/lines/line-1/product ',
      'DELETE /api/lists/l1/receipts/r1/lines/line-1 ',
      'POST /api/lists/l1/receipts/r1/confirm ',
      'DELETE /api/lists/l1/receipts/r1 ',
    ]);
  });

  it('builds the photo URL and maps a 404', async () => {
    server.use(http.get(receiptPath, () => problem(404)));

    expect(api.photoUrl('l1', 'r1')).toBe('/api/lists/l1/receipts/r1/photo');
    await expect(api.getReceipt('l1', 'r1')).rejects.toBeInstanceOf(NotFoundError);
  });
});
