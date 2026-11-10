import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useCallback, useEffect } from 'react';

import { UnauthorizedError } from '@/shared/http/errors';

import type { CurrentUser } from '../domain/currentUser';
import { useAuthGateway } from './authGatewayContext';

export const currentUserQueryKey = ['auth', 'currentUser'] as const;

export type CurrentUserState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'anonymous' }
  | { status: 'authenticated'; user: CurrentUser };

/** The user of the session, loaded once and shared by every component. */
export function useCurrentUser(): CurrentUserState {
  const gateway = useAuthGateway();
  const query = useQuery({
    queryKey: currentUserQueryKey,
    queryFn: ({ signal }) => gateway.getCurrentUser({ signal }),
    // It only changes on login, logout or a 401, which update it explicitly.
    staleTime: Infinity,
  });

  if (query.isPending) {
    return { status: 'loading' };
  }
  if (query.isError) {
    return { status: 'error', retry: () => void query.refetch() };
  }
  return query.data === null
    ? { status: 'anonymous' }
    : { status: 'authenticated', user: query.data };
}

/** Stores the user that a login or an activation just authenticated (or `null` after logout). */
export function useSetCurrentUser(): (user: CurrentUser | null) => void {
  const queryClient = useQueryClient();
  return useCallback(
    (user) => {
      queryClient.setQueryData(currentUserQueryKey, user);
    },
    [queryClient],
  );
}

/**
 * Any request answered with 401 means the session is over (expired, ended by a password
 * change, the account was disabled...). The current user becomes anonymous, so the route
 * guard sends the user to the login page.
 */
export function useEndSessionOnUnauthorized(): void {
  const queryClient = useQueryClient();
  const setCurrentUser = useSetCurrentUser();

  useEffect(() => {
    const endIfUnauthorized = (error: unknown) => {
      if (error instanceof UnauthorizedError) {
        setCurrentUser(null);
      }
    };
    const unsubscribeQueries = queryClient.getQueryCache().subscribe((event) => {
      if (event.type === 'updated' && event.action.type === 'error') {
        endIfUnauthorized(event.action.error);
      }
    });
    const unsubscribeMutations = queryClient.getMutationCache().subscribe((event) => {
      if (event.type === 'updated' && event.action.type === 'error') {
        endIfUnauthorized(event.action.error);
      }
    });
    return () => {
      unsubscribeQueries();
      unsubscribeMutations();
    };
  }, [queryClient, setCurrentUser]);
}
