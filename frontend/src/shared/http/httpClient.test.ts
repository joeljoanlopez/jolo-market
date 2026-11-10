import { http, HttpResponse } from 'msw/http';
import { describe, expect, it } from 'vitest';

import { server } from '@/test/server';

import {
  ConflictError,
  HttpError,
  NetworkError,
  NotFoundError,
  ServerError,
  TooManyRequestsError,
  UnauthorizedError,
  ValidationError,
} from './errors';
import { createHttpClient } from './httpClient';

const baseUrl = 'http://localhost';
const client = createHttpClient({ baseUrl });

function problem(status: number, title: string, detail?: string) {
  return HttpResponse.json(
    { type: 'about:blank', title, status, ...(detail === undefined ? {} : { detail }) },
    { status, headers: { 'Content-Type': 'application/problem+json' } },
  );
}

async function errorOf(promise: Promise<unknown>): Promise<unknown> {
  try {
    await promise;
  } catch (error) {
    return error;
  }
  throw new Error('Expected the request to fail.');
}

describe('httpClient', () => {
  it('returns the parsed JSON body of a successful GET', async () => {
    server.use(http.get(`${baseUrl}/api/health`, () => HttpResponse.json({ status: 'ok' })));

    await expect(client.get('/api/health')).resolves.toEqual({ status: 'ok' });
  });

  it('sends a JSON body and returns the response', async () => {
    server.use(
      http.post(`${baseUrl}/api/lists`, async ({ request }) => {
        expect(request.headers.get('Content-Type')).toBe('application/json');
        return HttpResponse.json({ received: await request.json() }, { status: 201 });
      }),
    );

    await expect(client.post('/api/lists', { name: 'Semana' })).resolves.toEqual({
      received: { name: 'Semana' },
    });
  });

  it('marks every state-changing request with X-Requested-With, the API CSRF guard', async () => {
    const received: (string | null)[] = [];
    const record = ({ request }: { request: Request }) => {
      received.push(request.headers.get('X-Requested-With'));
      return new HttpResponse(null, { status: 204 });
    };
    server.use(
      http.post(`${baseUrl}/api/auth/logout`, record),
      http.patch(`${baseUrl}/api/lists/1`, record),
      http.delete(`${baseUrl}/api/lists/1`, record),
    );

    await client.post('/api/auth/logout');
    await client.patch('/api/lists/1', { name: 'Semana' });
    await client.delete('/api/lists/1');

    expect(received).toEqual(['XMLHttpRequest', 'XMLHttpRequest', 'XMLHttpRequest']);
  });

  it('supports PATCH and DELETE', async () => {
    server.use(
      http.patch(`${baseUrl}/api/lists/1`, () => HttpResponse.json({ patched: true })),
      http.delete(`${baseUrl}/api/lists/1/items/2`, () => new HttpResponse(null, { status: 204 })),
    );

    await expect(client.patch('/api/lists/1', { name: 'Otra' })).resolves.toEqual({
      patched: true,
    });
    await expect(client.delete('/api/lists/1/items/2')).resolves.toBeUndefined();
  });

  it('returns undefined for a 204 No Content response', async () => {
    server.use(
      http.post(`${baseUrl}/api/auth/logout`, () => new HttpResponse(null, { status: 204 })),
    );

    await expect(client.post('/api/auth/logout')).resolves.toBeUndefined();
  });

  it('sends cookies of the same origin and asks for JSON', async () => {
    server.use(
      http.get(`${baseUrl}/api/auth/me`, ({ request }) => {
        expect(request.credentials).toBe('same-origin');
        expect(request.headers.get('Accept')).toBe('application/json');
        return HttpResponse.json({});
      }),
    );

    await client.get('/api/auth/me');
  });

  it.each([
    [404, 'Not Found', NotFoundError],
    [401, 'Unauthorized', UnauthorizedError],
    [422, 'Unprocessable Content', ValidationError],
    [409, 'Conflict', ConflictError],
    [429, 'Too Many Requests', TooManyRequestsError],
    [500, 'Internal Server Error', ServerError],
    [503, 'Service Unavailable', ServerError],
  ])('maps a %i problem+json to %s', async (status, title, errorClass) => {
    server.use(http.get(`${baseUrl}/api/thing`, () => problem(status, title)));

    const error = await errorOf(client.get('/api/thing'));

    expect(error).toBeInstanceOf(errorClass);
    expect(error).toBeInstanceOf(HttpError);
  });

  it('exposes the problem details on the error', async () => {
    server.use(
      http.get(`${baseUrl}/api/lists/42`, () =>
        problem(404, 'Not Found', 'Shopping list "42" was not found.'),
      ),
    );

    const error = await errorOf(client.get('/api/lists/42'));

    expect(error).toMatchObject({
      status: 404,
      problem: {
        type: 'about:blank',
        title: 'Not Found',
        status: 404,
        detail: 'Shopping list "42" was not found.',
      },
    });
  });

  it('maps any other error status to a generic HttpError', async () => {
    server.use(http.get(`${baseUrl}/api/thing`, () => problem(405, 'Method Not Allowed')));

    const error = await errorOf(client.get('/api/thing'));

    expect(error).toBeInstanceOf(HttpError);
    expect(error).not.toBeInstanceOf(NotFoundError);
    expect(error).toMatchObject({ status: 405 });
  });

  it('still maps the status when the error body is not problem+json', async () => {
    server.use(
      http.get(
        `${baseUrl}/api/thing`,
        () =>
          new HttpResponse('<html>Bad Gateway</html>', { status: 502, statusText: 'Bad Gateway' }),
      ),
    );

    const error = await errorOf(client.get('/api/thing'));

    expect(error).toBeInstanceOf(ServerError);
    expect(error).toMatchObject({
      status: 502,
      problem: { type: 'about:blank', title: 'Bad Gateway', status: 502 },
    });
  });

  it('maps a request that never got a response to a NetworkError', async () => {
    server.use(http.get(`${baseUrl}/api/thing`, () => HttpResponse.error()));

    await expect(client.get('/api/thing')).rejects.toBeInstanceOf(NetworkError);
  });

  it('falls back to the status when a problem+json body is malformed', async () => {
    server.use(
      http.get(
        `${baseUrl}/api/thing`,
        () =>
          new HttpResponse('{"oops": true}', {
            status: 404,
            statusText: 'Not Found',
            headers: { 'Content-Type': 'application/problem+json' },
          }),
      ),
    );

    const error = await errorOf(client.get('/api/thing'));

    expect(error).toBeInstanceOf(NotFoundError);
    expect(error).toMatchObject({
      problem: { type: 'about:blank', title: 'Not Found', status: 404 },
    });
  });

  it('lets an aborted request reject with the abort error, not a NetworkError', async () => {
    server.use(http.get(`${baseUrl}/api/thing`, () => HttpResponse.json({})));
    const controller = new AbortController();
    controller.abort();

    const error = await errorOf(client.get('/api/thing', { signal: controller.signal }));

    expect(error).not.toBeInstanceOf(NetworkError);
    expect(error).toMatchObject({ name: 'AbortError' });
  });
});
