import { http, HttpResponse } from 'msw/http';
import { describe, expect, it } from 'vitest';

import {
  TooManyRequestsError,
  UnauthorizedError,
  UnexpectedResponseError,
} from '@/shared/http/errors';
import { createHttpClient } from '@/shared/http/httpClient';
import { server } from '@/test/server';

import { createAuthApi } from './authApi';

const baseUrl = 'http://localhost';
const api = createAuthApi(createHttpClient({ baseUrl }));

const mariaJson = { id: '0192f3a4-7b1c-7d2e-8f30-000000000001', username: 'maria', role: 'USER' };

function problem(status: number, title: string) {
  return HttpResponse.json(
    { type: 'about:blank', title, status, detail: 'Invalid credentials.' },
    { status, headers: { 'Content-Type': 'application/problem+json' } },
  );
}

describe('authApi', () => {
  describe('getCurrentUser', () => {
    it('returns the user of the session', async () => {
      server.use(http.get(`${baseUrl}/api/auth/me`, () => HttpResponse.json(mariaJson)));

      await expect(api.getCurrentUser()).resolves.toEqual(mariaJson);
    });

    it('returns null when the session is not fully authenticated', async () => {
      server.use(http.get(`${baseUrl}/api/auth/me`, () => problem(401, 'Unauthorized')));

      await expect(api.getCurrentUser()).resolves.toBeNull();
    });

    it('rejects a user with an unknown role', async () => {
      server.use(
        http.get(`${baseUrl}/api/auth/me`, () => HttpResponse.json({ ...mariaJson, role: 'ROOT' })),
      );

      await expect(api.getCurrentUser()).rejects.toBeInstanceOf(UnexpectedResponseError);
    });
  });

  describe('logIn', () => {
    it('sends the credentials and resolves once the password is accepted', async () => {
      let body: unknown;
      server.use(
        http.post(`${baseUrl}/api/auth/login`, async ({ request }) => {
          body = await request.json();
          return HttpResponse.json({ twoFactorRequired: true }, { status: 202 });
        }),
      );

      await api.logIn({ username: 'maria', password: 'Correct-horse-1' });

      expect(body).toEqual({ username: 'maria', password: 'Correct-horse-1' });
    });

    it('rejects wrong credentials with UnauthorizedError', async () => {
      server.use(http.post(`${baseUrl}/api/auth/login`, () => problem(401, 'Unauthorized')));

      await expect(api.logIn({ username: 'maria', password: 'x' })).rejects.toBeInstanceOf(
        UnauthorizedError,
      );
    });

    it('rejects too many attempts with TooManyRequestsError', async () => {
      server.use(http.post(`${baseUrl}/api/auth/login`, () => problem(429, 'Too Many Requests')));

      await expect(api.logIn({ username: 'maria', password: 'x' })).rejects.toBeInstanceOf(
        TooManyRequestsError,
      );
    });
  });

  describe('verifyLoginCode', () => {
    it('sends the code and returns the logged-in user', async () => {
      let body: unknown;
      server.use(
        http.post(`${baseUrl}/api/auth/2fa`, async ({ request }) => {
          body = await request.json();
          return HttpResponse.json({ ...mariaJson, role: 'ADMIN' });
        }),
      );

      await expect(api.verifyLoginCode('123456')).resolves.toEqual({ ...mariaJson, role: 'ADMIN' });
      expect(body).toEqual({ code: '123456' });
    });

    it('rejects a wrong code with UnauthorizedError', async () => {
      server.use(http.post(`${baseUrl}/api/auth/2fa`, () => problem(401, 'Unauthorized')));

      await expect(api.verifyLoginCode('000000')).rejects.toBeInstanceOf(UnauthorizedError);
    });
  });

  describe('logOut', () => {
    it('ends the session', async () => {
      let called = false;
      server.use(
        http.post(`${baseUrl}/api/auth/logout`, () => {
          called = true;
          return new HttpResponse(null, { status: 204 });
        }),
      );

      await api.logOut();

      expect(called).toBe(true);
    });
  });
});
