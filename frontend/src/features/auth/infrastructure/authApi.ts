import { NotFoundError, UnauthorizedError, UnexpectedResponseError } from '@/shared/http/errors';
import type { HttpClient } from '@/shared/http/httpClient';

import type { AuthGateway } from '../application/AuthGateway';
import type { TotpEnrollment } from '../domain/activation';
import type { CurrentUser, Role } from '../domain/currentUser';

/** Session API client (docs/architecture.md#4-login). */
export function createAuthApi(http: HttpClient): AuthGateway {
  return {
    async getCurrentUser(options) {
      try {
        return toCurrentUser(await http.get('/api/auth/me', options));
      } catch (error) {
        if (error instanceof UnauthorizedError) {
          return null;
        }
        throw error;
      }
    },

    async logIn({ username, password }) {
      await http.post('/api/auth/login', { username, password });
    },

    async verifyLoginCode(code) {
      return toCurrentUser(await http.post('/api/auth/2fa', { code }));
    },

    async logOut() {
      await http.post('/api/auth/logout');
    },

    async getActivationLink(token) {
      const json = object(await http.post('/api/auth/activation', { token }), 'activation link');
      return {
        username: string(json.username, 'activationLink.username'),
        expiresAt: date(json.expiresAt, 'activationLink.expiresAt'),
      };
    },

    async setInitialPassword(token, password) {
      return toTotpEnrollment(
        await http.post('/api/auth/activation/password', { token, password }),
      );
    },

    async confirmTotpEnrollment(token, code) {
      return toCurrentUser(await http.post('/api/auth/activation/totp', { token, code }));
    },

    async isSetupRequired() {
      try {
        await http.get('/api/setup');
        return true;
      } catch (error) {
        // Once any user exists the endpoint is gone for good.
        if (error instanceof NotFoundError) {
          return false;
        }
        throw error;
      }
    },

    async changePassword({ currentPassword, code, newPassword }) {
      await http.post('/api/me/password', { currentPassword, code, newPassword });
    },

    async startTotpReEnrollment({ currentPassword, code }) {
      return toTotpEnrollment(await http.post('/api/me/totp', { currentPassword, code }));
    },

    async confirmTotpReEnrollment(code) {
      await http.post('/api/me/totp/confirm', { code });
    },

    async completeSetup(code, username) {
      const json = object(await http.post('/api/setup', { code, username }), 'setup');
      return string(json.activationToken, 'setup.activationToken');
    },
  };
}

const roles: readonly string[] = ['ADMIN', 'USER'] satisfies Role[];

function toCurrentUser(body: unknown): CurrentUser {
  const json = object(body, 'user');
  if (typeof json.role !== 'string' || !roles.includes(json.role)) {
    throw unexpected('user.role', json.role);
  }
  return {
    id: string(json.id, 'user.id'),
    username: string(json.username, 'user.username'),
    role: json.role as Role,
  };
}

function toTotpEnrollment(body: unknown): TotpEnrollment {
  const json = object(body, 'TOTP enrollment');
  return {
    secret: string(json.secret, 'totpEnrollment.secret'),
    provisioningUri: string(json.provisioningUri, 'totpEnrollment.provisioningUri'),
  };
}

function object(value: unknown, field: string): Record<string, unknown> {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) {
    throw unexpected(field, value);
  }
  return value as Record<string, unknown>;
}

function string(value: unknown, field: string): string {
  if (typeof value !== 'string') {
    throw unexpected(field, value);
  }
  return value;
}

function date(value: unknown, field: string): Date {
  const parsed = typeof value === 'string' ? new Date(value) : null;
  if (parsed === null || Number.isNaN(parsed.getTime())) {
    throw unexpected(field, value);
  }
  return parsed;
}

function unexpected(field: string, value: unknown): UnexpectedResponseError {
  const shown = value === undefined ? 'missing' : JSON.stringify(value);
  return new UnexpectedResponseError(`Unexpected auth response: ${field} is ${shown}.`);
}
