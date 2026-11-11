import { http, HttpResponse } from 'msw/http';
import { describe, expect, it } from 'vitest';

import { ConflictError, UnexpectedResponseError } from '@/shared/http/errors';
import { createHttpClient } from '@/shared/http/httpClient';
import { server } from '@/test/server';

import { createAdminApi } from './adminApi';

const baseUrl = 'http://localhost';
const api = createAdminApi(createHttpClient({ baseUrl }));
const id = '0192f3a4-7b1c-7d2e-8f30-000000000002';

const linkJson = {
  id,
  username: 'pedro',
  activationToken: 'token-shown-once',
  activationExpiresAt: '2026-11-13T10:00:00+00:00',
};
const grant = {
  userId: id,
  username: 'pedro',
  activationToken: 'token-shown-once',
  expiresAt: new Date('2026-11-13T10:00:00Z'),
};

function problem(status: number, title: string) {
  return HttpResponse.json(
    { type: 'about:blank', title, status },
    { status, headers: { 'Content-Type': 'application/problem+json' } },
  );
}

describe('adminApi', () => {
  it('lists the users', async () => {
    server.use(
      http.get(`${baseUrl}/api/admin/users`, () =>
        HttpResponse.json([
          {
            id: 'user-1',
            username: 'admin',
            role: 'ADMIN',
            status: 'ACTIVE',
            createdAt: '2026-11-09T10:00:00+00:00',
            activationExpiresAt: null,
          },
          {
            id,
            username: 'pedro',
            role: 'USER',
            status: 'PENDING_ACTIVATION',
            createdAt: '2026-11-10T10:00:00+00:00',
            activationExpiresAt: '2026-11-13T10:00:00+00:00',
          },
        ]),
      ),
    );

    await expect(api.listUsers()).resolves.toEqual([
      {
        id: 'user-1',
        username: 'admin',
        role: 'ADMIN',
        status: 'ACTIVE',
        createdAt: new Date('2026-11-09T10:00:00Z'),
        activationExpiresAt: null,
      },
      {
        id,
        username: 'pedro',
        role: 'USER',
        status: 'PENDING_ACTIVATION',
        createdAt: new Date('2026-11-10T10:00:00Z'),
        activationExpiresAt: new Date('2026-11-13T10:00:00Z'),
      },
    ]);
  });

  it('rejects a user with an unknown status', async () => {
    server.use(
      http.get(`${baseUrl}/api/admin/users`, () =>
        HttpResponse.json([
          {
            id,
            username: 'pedro',
            role: 'USER',
            status: 'LOCKED',
            createdAt: '2026-11-10T10:00:00+00:00',
            activationExpiresAt: null,
          },
        ]),
      ),
    );

    await expect(api.listUsers()).rejects.toBeInstanceOf(UnexpectedResponseError);
  });

  it('creates a user and returns the activation link shown once', async () => {
    let body: unknown;
    server.use(
      http.post(`${baseUrl}/api/admin/users`, async ({ request }) => {
        body = await request.json();
        return HttpResponse.json(linkJson, { status: 201 });
      }),
    );

    await expect(api.createUser({ username: 'pedro', role: 'USER' })).resolves.toEqual(grant);
    expect(body).toEqual({ username: 'pedro', role: 'USER' });
  });

  it('rejects a taken username with ConflictError', async () => {
    server.use(http.post(`${baseUrl}/api/admin/users`, () => problem(409, 'Conflict')));

    await expect(api.createUser({ username: 'pedro', role: 'USER' })).rejects.toBeInstanceOf(
      ConflictError,
    );
  });

  it.each([
    ['regenerateActivationLink', 'activation-link'],
    ['resetUser', 'reset'],
  ] as const)('%s posts to /%s and returns the new link', async (method, path) => {
    server.use(
      http.post(`${baseUrl}/api/admin/users/${id}/${path}`, () => HttpResponse.json(linkJson)),
    );

    await expect(api[method](id)).resolves.toEqual(grant);
  });

  it.each([
    ['disableUser', 'disable'],
    ['enableUser', 'enable'],
  ] as const)('%s posts to /%s', async (method, path) => {
    let called = false;
    server.use(
      http.post(`${baseUrl}/api/admin/users/${id}/${path}`, () => {
        called = true;
        return new HttpResponse(null, { status: 204 });
      }),
    );

    await api[method](id);

    expect(called).toBe(true);
  });

  it('changes the role with a PUT', async () => {
    let body: unknown;
    server.use(
      http.put(`${baseUrl}/api/admin/users/${id}/role`, async ({ request }) => {
        body = await request.json();
        return new HttpResponse(null, { status: 204 });
      }),
    );

    await api.changeRole(id, 'ADMIN');

    expect(body).toEqual({ role: 'ADMIN' });
  });

  it('rejects demoting the last active admin with ConflictError', async () => {
    server.use(http.put(`${baseUrl}/api/admin/users/${id}/role`, () => problem(409, 'Conflict')));

    await expect(api.changeRole(id, 'USER')).rejects.toBeInstanceOf(ConflictError);
  });
});
