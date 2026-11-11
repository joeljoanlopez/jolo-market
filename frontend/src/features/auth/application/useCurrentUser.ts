import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useCallback, useEffect } from 'react';

import { UnauthorizedError } from '@/shared/http/errors';

import type { CurrentUser } from '../domain/currentUser';
import { useAuthGateway } from './authGatewayContext';

export const currentUserQueryKey = ['auth', 'currentUser'] as const;

/** Stored instead of `null` when the user logged out on purpose. */
const LOGGED_OUT = 'loggedOut';
type StoredSession = CurrentUser | null | typeof LOGGED_OUT;

export type CurrentUserState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  /** `loggedOut`: the user ended the session, so there is no page to come back to. */
  | { status: 'anonymous'; loggedOut: boolean }
  | { status: 'authenticated'; user: CurrentUser };

/** The user of the session, loaded once and shared by every component. */
export function useCurrentUser(): CurrentUserState {
  const gateway = useAuthGateway();
  const query = useQuery<StoredSession>({
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
  if (query.data === null || query.data === LOGGED_OUT) {
    return { status: 'anonymous', loggedOut: query.data === LOGGED_OUT };
  }
  return { status: 'authenticated', user: query.data };
}

/**
 * Stores the user that a login or an activation just authenticated, or `null` when the session
 * ended by itself (a 401).
 */
export function useSetCurrentUser(): (user: CurrentUser | null) => void {
  const queryClient = useQueryClient();
  return useCallback(
    (user) => {
      queryClient.setQueryData<StoredSession>(currentUserQueryKey, user);
    },
    [queryClient],
  );
}

/** Marks the session as ended by the user, and drops the cached data of that user. */
export function useEndSessionOnLogout(): () => void {
  const queryClient = useQueryClient();
  return useCallback(() => {
    queryClient.removeQueries({
      predicate: (query) => query.queryKey[0] !== currentUserQueryKey[0],
    });
    queryClient.setQueryData<StoredSession>(currentUserQueryKey, LOGGED_OUT);
  }, [queryClient]);
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
