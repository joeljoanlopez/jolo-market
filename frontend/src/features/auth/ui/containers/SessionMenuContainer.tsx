import { useCurrentUser } from '../../application/useCurrentUser';
import { isAdmin } from '../../domain/currentUser';
import { useLogout } from '../../application/useLogout';
import { SessionMenu } from '../components/SessionMenu';

/** The session part of the app header: who is logged in and the logout button. */
export function SessionMenuContainer() {
  const session = useCurrentUser();
  const logout = useLogout();

  if (session.status !== 'authenticated') {
    return null;
  }

  return (
    <SessionMenu
      username={session.user.username}
      showAdminLinks={isAdmin(session.user)}
      pending={logout.pending}
      failed={logout.failed}
      onLogOut={logout.logOut}
    />
  );
}
