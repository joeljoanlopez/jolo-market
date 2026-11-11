import type { UserAdministration } from '@/features/admin/application/UserAdministration';
import type { ActivationLinkGrant, AdminUser } from '@/features/admin/domain/adminUser';
import type { Role } from '@/features/auth/domain/currentUser';
import { ConflictError, ValidationError } from '@/shared/http/errors';

export const LINK_EXPIRY = new Date('2026-11-13T10:00:00Z');

export function anAdminUser(overrides: Partial<AdminUser> = {}): AdminUser {
  return {
    id: 'user-admin',
    username: 'admin',
    role: 'ADMIN',
    status: 'ACTIVE',
    createdAt: new Date('2026-11-09T10:00:00Z'),
    activationExpiresAt: null,
    ...overrides,
  };
}

/**
 * In-memory users. It applies the rules the page relies on: a taken username, a username under
 * 3 characters, and the last active admin (PD-36); anything listed in `conflicts` answers 409.
 */
export class FakeUserAdministration implements UserAdministration {
  users: AdminUser[] = [
    anAdminUser(),
    anAdminUser({ id: 'user-maria', username: 'maria', role: 'USER' }),
  ];
  /** User ids whose next action answers 409. */
  conflicts = new Set<string>();
  private issued = 0;

  listUsers(): Promise<AdminUser[]> {
    return Promise.resolve([...this.users].sort((a, b) => a.username.localeCompare(b.username)));
  }

  createUser({ username, role }: { username: string; role: Role }): Promise<ActivationLinkGrant> {
    if (username.length < 3) {
      return Promise.reject(invalid());
    }
    if (this.users.some((user) => user.username === username)) {
      return Promise.reject(conflict());
    }
    const user = anAdminUser({
      id: `user-${username}`,
      username,
      role,
      status: 'PENDING_ACTIVATION',
      activationExpiresAt: LINK_EXPIRY,
    });
    this.users.push(user);
    return Promise.resolve(this.grantFor(user));
  }

  regenerateActivationLink(userId: string): Promise<ActivationLinkGrant> {
    return this.act(userId, (user) => {
      if (user.status !== 'PENDING_ACTIVATION') {
        throw conflict();
      }
      return this.grantFor(user);
    });
  }

  resetUser(userId: string): Promise<ActivationLinkGrant> {
    return this.act(userId, (user) => {
      user.status = 'PENDING_ACTIVATION';
      user.activationExpiresAt = LINK_EXPIRY;
      return this.grantFor(user);
    });
  }

  disableUser(userId: string): Promise<void> {
    return this.act(userId, (user) => {
      user.status = 'DISABLED';
    });
  }

  enableUser(userId: string): Promise<void> {
    return this.act(userId, (user) => {
      user.status = 'ACTIVE';
    });
  }

  changeRole(userId: string, role: Role): Promise<void> {
    return this.act(userId, (user) => {
      user.role = role;
    });
  }

  private act<T>(userId: string, change: (user: AdminUser) => T): Promise<T> {
    const user = this.users.find((candidate) => candidate.id === userId);
    if (user === undefined || this.conflicts.has(userId)) {
      return Promise.reject(conflict());
    }
    try {
      return Promise.resolve(change(user));
    } catch (error) {
      return Promise.reject(error instanceof Error ? error : new Error(String(error)));
    }
  }

  private grantFor(user: AdminUser): ActivationLinkGrant {
    this.issued++;
    return {
      userId: user.id,
      username: user.username,
      activationToken: `token-${String(this.issued)}-of-${user.username}`,
      expiresAt: LINK_EXPIRY,
    };
  }
}

function conflict(): ConflictError {
  return new ConflictError({ type: 'about:blank', title: 'Conflict', status: 409 });
}

function invalid(): ValidationError {
  return new ValidationError({ type: 'about:blank', title: 'Unprocessable Content', status: 422 });
}
