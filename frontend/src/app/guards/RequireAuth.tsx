import { Navigate, Outlet, useLocation } from 'react-router';

import {
  useCurrentUser,
  useEndSessionOnUnauthorized,
} from '@/features/auth/application/useCurrentUser';

import { SessionStatus } from './SessionStatus';

/**
 * Renders its routes only for a fully authenticated session. Anyone else goes to /login,
 * which brings them back to the page they asked for (a /join/:token link included).
 */
export function RequireAuth() {
  const session = useCurrentUser();
  const location = useLocation();
  useEndSessionOnUnauthorized();

  switch (session.status) {
    case 'loading':
      return <SessionStatus />;
    case 'error':
      return <SessionStatus onRetry={session.retry} />;
    case 'anonymous':
      return <Navigate to={loginPathFor(location.pathname + location.search)} replace />;
    case 'authenticated':
      return <Outlet />;
  }
}

function loginPathFor(target: string): string {
  return target === '/' ? '/login' : `/login?${new URLSearchParams({ next: target }).toString()}`;
}
