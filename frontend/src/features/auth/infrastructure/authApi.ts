import { UnauthorizedError, UnexpectedResponseError } from '@/shared/http/errors';
import type { HttpClient } from '@/shared/http/httpClient';

import type { AuthGateway } from '../application/AuthGateway';
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
  };
}

const roles: readonly string[] = ['ADMIN', 'USER'] satisfies Role[];

/** Also used by the activation and setup clients, which answer with the same user shape. */
export function toCurrentUser(body: unknown): CurrentUser {
  if (typeof body !== 'object' || body === null || Array.isArray(body)) {
    throw unexpected('user', body);
  }
  const json = body as Record<string, unknown>;
  if (typeof json.id !== 'string') {
    throw unexpected('user.id', json.id);
  }
  if (typeof json.username !== 'string') {
    throw unexpected('user.username', json.username);
  }
  if (typeof json.role !== 'string' || !roles.includes(json.role)) {
    throw unexpected('user.role', json.role);
  }
  return { id: json.id, username: json.username, role: json.role as Role };
}

function unexpected(field: string, value: unknown): UnexpectedResponseError {
  const shown = value === undefined ? 'missing' : JSON.stringify(value);
  return new UnexpectedResponseError(`Unexpected auth response: ${field} is ${shown}.`);
}
