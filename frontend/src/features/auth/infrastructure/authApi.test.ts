import { http, HttpResponse } from 'msw/http';
import { describe, expect, it } from 'vitest';

import {
  NotFoundError,
  TooManyRequestsError,
  UnauthorizedError,
  UnexpectedResponseError,
  ValidationError,
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

  describe('activation', () => {
    const token = 'a'.repeat(43);

    it('reads whose account a link activates and until when', async () => {
      let body: unknown;
      server.use(
        http.post(`${baseUrl}/api/auth/activation`, async ({ request }) => {
          body = await request.json();
          return HttpResponse.json({ username: 'pedro', expiresAt: '2026-11-13T10:00:00+00:00' });
        }),
      );

      await expect(api.getActivationLink(token)).resolves.toEqual({
        username: 'pedro',
        expiresAt: new Date('2026-11-13T10:00:00Z'),
      });
      // The token goes in the body, never in a URL, so it stays out of logs.
      expect(body).toEqual({ token });
    });

    it('rejects an unknown, expired or used link with NotFoundError', async () => {
      server.use(http.post(`${baseUrl}/api/auth/activation`, () => problem(404, 'Not Found')));

      await expect(api.getActivationLink(token)).rejects.toBeInstanceOf(NotFoundError);
    });

    it('rejects a link with an unreadable expiry', async () => {
      server.use(
        http.post(`${baseUrl}/api/auth/activation`, () =>
          HttpResponse.json({ username: 'pedro', expiresAt: 'soon' }),
        ),
      );

      await expect(api.getActivationLink(token)).rejects.toBeInstanceOf(UnexpectedResponseError);
    });

    it('sets the password and returns the TOTP device to enroll', async () => {
      let body: unknown;
      server.use(
        http.post(`${baseUrl}/api/auth/activation/password`, async ({ request }) => {
          body = await request.json();
          return HttpResponse.json({ secret: 'JBSWY3DP', provisioningUri: 'otpauth://totp/x' });
        }),
      );

      await expect(api.setInitialPassword(token, 'New-password-1!')).resolves.toEqual({
        secret: 'JBSWY3DP',
        provisioningUri: 'otpauth://totp/x',
      });
      expect(body).toEqual({ token, password: 'New-password-1!' });
    });

    it('rejects a password that breaks the policy with ValidationError', async () => {
      server.use(
        http.post(`${baseUrl}/api/auth/activation/password`, () =>
          problem(422, 'Unprocessable Content'),
        ),
      );

      await expect(api.setInitialPassword(token, 'short')).rejects.toBeInstanceOf(ValidationError);
    });

    it('confirms the code and returns the activated, logged-in user', async () => {
      let body: unknown;
      server.use(
        http.post(`${baseUrl}/api/auth/activation/totp`, async ({ request }) => {
          body = await request.json();
          return HttpResponse.json(mariaJson);
        }),
      );

      await expect(api.confirmTotpEnrollment(token, '123456')).resolves.toEqual(mariaJson);
      expect(body).toEqual({ token, code: '123456' });
    });
  });

  describe('setup', () => {
    it('is required while the API answers it', async () => {
      server.use(http.get(`${baseUrl}/api/setup`, () => HttpResponse.json({ required: true })));

      await expect(api.isSetupRequired()).resolves.toBe(true);
    });

    it('is not required once the API answers 404', async () => {
      server.use(http.get(`${baseUrl}/api/setup`, () => problem(404, 'Not Found')));

      await expect(api.isSetupRequired()).resolves.toBe(false);
    });

    it('creates the first admin and returns the activation token', async () => {
      let body: unknown;
      server.use(
        http.post(`${baseUrl}/api/setup`, async ({ request }) => {
          body = await request.json();
          return HttpResponse.json(
            {
              id: 'user-1',
              username: 'admin',
              activationToken: 'token-of-the-first-admin',
              activationExpiresAt: '2026-11-13T10:00:00+00:00',
            },
            { status: 201 },
          );
        }),
      );

      await expect(api.completeSetup('ABCD-1234', 'admin')).resolves.toBe(
        'token-of-the-first-admin',
      );
      expect(body).toEqual({ code: 'ABCD-1234', username: 'admin' });
    });

    it('rejects a wrong code with ValidationError', async () => {
      server.use(http.post(`${baseUrl}/api/setup`, () => problem(422, 'Unprocessable Content')));

      await expect(api.completeSetup('wrong', 'admin')).rejects.toBeInstanceOf(ValidationError);
    });
  });

  describe('profile', () => {
    function recordPost(path: string, response: () => Response) {
      const bodies: unknown[] = [];
      server.use(
        http.post(`${baseUrl}${path}`, async ({ request }) => {
          bodies.push(await request.json());
          return response();
        }),
      );
      return bodies;
    }

    it('changes the password', async () => {
      const bodies = recordPost('/api/me/password', () => new HttpResponse(null, { status: 204 }));

      await api.changePassword({
        currentPassword: 'Old-password-1!',
        code: '123456',
        newPassword: 'New-password-1!',
      });

      expect(bodies).toEqual([
        { currentPassword: 'Old-password-1!', code: '123456', newPassword: 'New-password-1!' },
      ]);
    });

    it('rejects a wrong current password or code with ValidationError', async () => {
      recordPost('/api/me/password', () => problem(422, 'Unprocessable Content'));

      await expect(
        api.changePassword({ currentPassword: 'x', code: '000000', newPassword: 'y' }),
      ).rejects.toBeInstanceOf(ValidationError);
    });

    it('starts a re-enrollment and returns the new device', async () => {
      const bodies = recordPost('/api/me/totp', () =>
        HttpResponse.json({ secret: 'NEWSECRET', provisioningUri: 'otpauth://totp/new' }),
      );

      await expect(
        api.startTotpReEnrollment({ currentPassword: 'Old-password-1!', code: '123456' }),
      ).resolves.toEqual({ secret: 'NEWSECRET', provisioningUri: 'otpauth://totp/new' });
      expect(bodies).toEqual([{ currentPassword: 'Old-password-1!', code: '123456' }]);
    });

    it('confirms the new device with one of its codes', async () => {
      const bodies = recordPost(
        '/api/me/totp/confirm',
        () => new HttpResponse(null, { status: 204 }),
      );

      await api.confirmTotpReEnrollment('654321');

      expect(bodies).toEqual([{ code: '654321' }]);
    });
  });
});
