import { Navigate, Outlet } from 'react-router';

import { useCurrentUser } from '@/features/auth/application/useCurrentUser';
import { useSetupStatus } from '@/features/auth/application/useSetup';

import { SessionStatus } from './SessionStatus';

/** While a fresh instance has no users, every page leads to /setup (PD-14). */
export function RequireSetupDone() {
  const session = useCurrentUser();
  const setup = useSetupStatus(session.status === 'anonymous');

  if (session.status === 'loading' || setup === 'checking') {
    return <SessionStatus />;
  }
  return setup === 'required' ? <Navigate to="/setup" replace /> : <Outlet />;
}
