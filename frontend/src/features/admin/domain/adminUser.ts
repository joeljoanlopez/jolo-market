import type { Role } from '@/features/auth/domain/currentUser';

export type UserStatus = 'PENDING_ACTIVATION' | 'ACTIVE' | 'DISABLED';

/** A user as the admin users page lists them. */
export interface AdminUser {
  id: string;
  username: string;
  role: Role;
  status: UserStatus;
  createdAt: Date;
  /** Only for a user pending activation: when their current link stops working. */
  activationExpiresAt: Date | null;
}

/** A new activation link. The token is shown once and never again (PD-34). */
export interface ActivationLinkGrant {
  userId: string;
  username: string;
  activationToken: string;
  expiresAt: Date;
}

export function activationPath(grant: ActivationLinkGrant): string {
  return `/activate/${encodeURIComponent(grant.activationToken)}`;
}
