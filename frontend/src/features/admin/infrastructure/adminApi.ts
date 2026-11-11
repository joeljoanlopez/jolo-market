import type { Role } from '@/features/auth/domain/currentUser';
import { UnexpectedResponseError } from '@/shared/http/errors';
import type { HttpClient } from '@/shared/http/httpClient';

import type { UserAdministration } from '../application/UserAdministration';
import type { ActivationLinkGrant, AdminUser, UserStatus } from '../domain/adminUser';

/** Admin users API client (docs/architecture.md#http-api-draft). */
export function createAdminApi(http: HttpClient): UserAdministration {
  const userPath = (userId: string, action: string) =>
    `/api/admin/users/${encodeURIComponent(userId)}/${action}`;

  return {
    async listUsers(options) {
      return list(await http.get('/api/admin/users', options)).map(toAdminUser);
    },

    async createUser({ username, role }) {
      return toGrant(await http.post('/api/admin/users', { username, role }));
    },

    async regenerateActivationLink(userId) {
      return toGrant(await http.post(userPath(userId, 'activation-link')));
    },

    async resetUser(userId) {
      return toGrant(await http.post(userPath(userId, 'reset')));
    },

    async disableUser(userId) {
      await http.post(userPath(userId, 'disable'));
    },

    async enableUser(userId) {
      await http.post(userPath(userId, 'enable'));
    },

    async changeRole(userId, role) {
      await http.put(userPath(userId, 'role'), { role });
    },
  };
}

type Json = Record<string, unknown>;

function toAdminUser(value: unknown): AdminUser {
  const json = object(value, 'user');
  return {
    id: string(json.id, 'user.id'),
    username: string(json.username, 'user.username'),
    role: oneOf(json.role, roles, 'user.role'),
    status: oneOf(json.status, statuses, 'user.status'),
    createdAt: date(json.createdAt, 'user.createdAt'),
    activationExpiresAt:
      json.activationExpiresAt === null
        ? null
        : date(json.activationExpiresAt, 'user.activationExpiresAt'),
  };
}

function toGrant(body: unknown): ActivationLinkGrant {
  const json = object(body, 'activation link');
  return {
    userId: string(json.id, 'activationLink.id'),
    username: string(json.username, 'activationLink.username'),
    activationToken: string(json.activationToken, 'activationLink.activationToken'),
    expiresAt: date(json.activationExpiresAt, 'activationLink.activationExpiresAt'),
  };
}

const roles = ['ADMIN', 'USER'] as const satisfies readonly Role[];
const statuses = [
  'PENDING_ACTIVATION',
  'ACTIVE',
  'DISABLED',
] as const satisfies readonly UserStatus[];

function oneOf<T extends string>(value: unknown, allowed: readonly T[], field: string): T {
  if (typeof value !== 'string' || !(allowed as readonly string[]).includes(value)) {
    throw unexpected(field, value);
  }
  return value as T;
}

function object(value: unknown, field: string): Json {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) {
    throw unexpected(field, value);
  }
  return value as Json;
}

function list(value: unknown): unknown[] {
  if (!Array.isArray(value)) {
    throw unexpected('users', value);
  }
  return value;
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
  return new UnexpectedResponseError(`Unexpected admin response: ${field} is ${shown}.`);
}
