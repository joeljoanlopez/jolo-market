export type Role = 'ADMIN' | 'USER';

/** The user of a fully authenticated session (login + 2FA). */
export interface CurrentUser {
  id: string;
  username: string;
  role: Role;
}

export function isAdmin(user: CurrentUser): boolean {
  return user.role === 'ADMIN';
}
