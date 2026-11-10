import type { Role } from '@/features/auth/domain/currentUser';

import type { ActivationLinkGrant, AdminUser } from '../domain/adminUser';

/**
 * Port to the admin users API, implemented in infrastructure/. Every call is ADMIN only; the API
 * answers 404 to anyone else (PD-15). Rule violations (PD-34 to PD-36) reject with
 * `ConflictError`, an invalid username with `ValidationError`.
 */
export interface UserAdministration {
  listUsers(options?: { signal?: AbortSignal }): Promise<AdminUser[]>;
  /** Rejects with `ConflictError` when the username is taken. */
  createUser(user: { username: string; role: Role }): Promise<ActivationLinkGrant>;
  /** A new link for a pending user; the old one stops working (PD-34). */
  regenerateActivationLink(userId: string): Promise<ActivationLinkGrant>;
  /** Back to pending activation, with a new link (PD-35). */
  resetUser(userId: string): Promise<ActivationLinkGrant>;
  disableUser(userId: string): Promise<void>;
  enableUser(userId: string): Promise<void>;
  changeRole(userId: string, role: Role): Promise<void>;
}
