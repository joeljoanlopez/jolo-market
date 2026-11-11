import { Outlet } from 'react-router';

import { useCurrentUser } from '@/features/auth/application/useCurrentUser';
import { isAdmin } from '@/features/auth/domain/currentUser';

import { NotFoundPage } from '../NotFoundPage';

/**
 * Renders its routes only for an ADMIN. Anyone else sees the not-found page, never a
 * "forbidden" message, so the admin area does not reveal it exists (PD-15).
 * It goes inside RequireAuth, which already handles loading and anonymous sessions.
 */
export function RequireAdmin() {
  const session = useCurrentUser();

  return session.status === 'authenticated' && isAdmin(session.user) ? (
    <Outlet />
  ) : (
    <NotFoundPage />
  );
}
