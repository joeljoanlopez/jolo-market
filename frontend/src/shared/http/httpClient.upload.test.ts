// @vitest-environment node
// Multipart bodies need Node's own FormData and File: jsdom's make Node's fetch miscount the length.
import { http, HttpResponse } from 'msw/http';
import { describe, expect, it } from 'vitest';

import { server } from '@/test/server';

import { ValidationError } from './errors';
import { createHttpClient } from './httpClient';

const baseUrl = 'http://localhost';
const client = createHttpClient({ baseUrl });

function problem(status: number, title: string) {
  return HttpResponse.json(
    { type: 'about:blank', title, status },
    { status, headers: { 'Content-Type': 'application/problem+json' } },
  );
}

describe('httpClient uploads', () => {
  it('uploads FormData as multipart, with the CSRF header and without a JSON content type', async () => {
    server.use(
      http.post(`${baseUrl}/api/lists/l1/receipts`, async ({ request }) => {
        expect(request.headers.get('X-Requested-With')).toBe('XMLHttpRequest');
        expect(request.headers.get('Content-Type')).toMatch(/^multipart\/form-data; boundary=/);
        const form = await request.formData();
        const photo = form.get('photo');
        expect(form.get('supermarketId')).toBe('mercadona');
        expect(typeof photo === 'string' || photo === null ? null : await photo.text()).toBe(
          'bytes',
        );
        return HttpResponse.json({ id: 'r1' }, { status: 202 });
      }),
    );
    const form = new FormData();
    form.append('supermarketId', 'mercadona');
    form.append('photo', new File(['bytes'], 'ticket.jpg', { type: 'image/jpeg' }));

    await expect(client.postForm('/api/lists/l1/receipts', form)).resolves.toEqual({ id: 'r1' });
  });

  it('rejects a failed upload with the typed error', async () => {
    server.use(http.post(`${baseUrl}/api/upload`, () => problem(422, 'Unprocessable Content')));

    await expect(client.postForm('/api/upload', new FormData())).rejects.toBeInstanceOf(
      ValidationError,
    );
  });
});
