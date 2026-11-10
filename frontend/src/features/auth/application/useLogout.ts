import { useMutation } from '@tanstack/react-query';

import { UnauthorizedError } from '@/shared/http/errors';

import { useAuthGateway } from './authGatewayContext';
import { useEndSessionOnLogout } from './useCurrentUser';

export interface Logout {
  logOut: () => void;
  pending: boolean;
  /** The logout did not reach the server: the session is still open there. */
  failed: boolean;
}

/** Ends the session; the route guard then sends the user to the login page. */
export function useLogout(): Logout {
  const gateway = useAuthGateway();
  const endSession = useEndSessionOnLogout();

  const mutation = useMutation({
    mutationFn: () => gateway.logOut(),
    onSuccess: endSession,
    onError: (error) => {
      // The server had already ended it: the user is logged out either way.
      if (error instanceof UnauthorizedError) {
        endSession();
      }
    },
  });

  return {
    logOut: () => {
      mutation.mutate();
    },
    pending: mutation.isPending,
    failed: mutation.isError && !(mutation.error instanceof UnauthorizedError),
  };
}
