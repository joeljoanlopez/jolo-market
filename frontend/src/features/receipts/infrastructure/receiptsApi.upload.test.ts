// @vitest-environment node
// Multipart bodies need Node's own FormData and File: jsdom's make Node's fetch miscount the length.
import { http, HttpResponse } from 'msw/http';
import { describe, expect, it } from 'vitest';

import { createHttpClient } from '@/shared/http/httpClient';
import { server } from '@/test/server';

import { createReceiptsApi } from './receiptsApi';

const baseUrl = 'http://localhost';
const api = createReceiptsApi(createHttpClient({ baseUrl }));
const receiptsPath = `${baseUrl}/api/lists/l1/receipts`;

describe('receiptsApi uploads', () => {
  it('uploads the photo with the supermarket and resolves with the receipt id', async () => {
    server.use(
      http.post(receiptsPath, async ({ request }) => {
        const form = await request.formData();
        const photo = form.get('photo');
        expect(form.get('supermarketId')).toBe('aldi');
        expect(typeof photo === 'string' || photo === null ? null : photo.name).toBe('ticket.png');
        expect(typeof photo === 'string' || photo === null ? null : await photo.text()).toBe('png');
        return HttpResponse.json({ id: 'r9' }, { status: 202 });
      }),
    );

    await expect(
      api.uploadReceipt('l1', 'aldi', new File(['png'], 'ticket.png', { type: 'image/png' })),
    ).resolves.toBe('r9');
  });
});
