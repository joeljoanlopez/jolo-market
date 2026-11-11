import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { NotFoundError, ValidationError } from '@/shared/http/errors';

import { isValidListName, type ListSummary } from '../domain/shoppingList';
import { shoppingListKeys } from './queryKeys';
import { useShoppingLists } from './shoppingListsContext';

export type MyListsState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'success'; lists: ListSummary[] };

/** "My lists": the lists the user owns or is a member of, most recently updated first. */
export function useMyLists(): MyListsState {
  const lists = useShoppingLists();
  const query = useQuery({
    queryKey: shoppingListKeys.mine,
    queryFn: ({ signal }) => lists.listMyLists({ signal }),
  });

  if (query.isPending) {
    return { status: 'loading' };
  }
  if (query.isError) {
    return { status: 'error', retry: () => void query.refetch() };
  }
  return { status: 'success', lists: query.data };
}

export type CreateListError = 'invalid' | 'failed';

export interface CreateList {
  /** Creates a list owned by the current user; `onCreated` gets its id. */
  create: (name: string, onCreated: (listId: string) => void) => void;
  pending: boolean;
  error: CreateListError | null;
}

export function useCreateList(): CreateList {
  const lists = useShoppingLists();
  const queryClient = useQueryClient();
  const mutation = useMutation({
    mutationFn: async (name: string) => {
      // Checked here too (PD-45), so an obviously wrong name never reaches the API.
      if (!isValidListName(name)) {
        throw new InvalidListName();
      }
      return lists.createList(name.trim());
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: shoppingListKeys.mine }),
  });

  return {
    create: (name, onCreated) => {
      mutation.mutate(name, {
        onSuccess: (listId) => {
          onCreated(listId);
        },
      });
    },
    pending: mutation.isPending,
    error: mutation.isError
      ? mutation.error instanceof InvalidListName || mutation.error instanceof ValidationError
        ? 'invalid'
        : 'failed'
      : null,
  };
}

class InvalidListName extends Error {}

export type JoinListState =
  | { status: 'joining' }
  /** Unknown, malformed or revoked link. */
  | { status: 'invalid' }
  | { status: 'error'; retry: () => void }
  | { status: 'joined'; listId: string };

/**
 * Joins the list of a secret link as soon as it renders. Joining is idempotent, so it is a
 * query: it runs once per token, even when the page renders twice.
 */
export function useJoinList(shareToken: string): JoinListState {
  const lists = useShoppingLists();
  const queryClient = useQueryClient();
  const query = useQuery({
    queryKey: [...shoppingListKeys.all, 'join', shareToken],
    queryFn: async () => {
      const listId = await lists.joinList(shareToken);
      await queryClient.invalidateQueries({ queryKey: shoppingListKeys.mine });
      return listId;
    },
    staleTime: Infinity,
    gcTime: 0,
    retry: false,
  });

  if (query.isSuccess) {
    return { status: 'joined', listId: query.data };
  }
  if (query.error instanceof NotFoundError) {
    return { status: 'invalid' };
  }
  if (query.isError) {
    return { status: 'error', retry: () => void query.refetch() };
  }
  return { status: 'joining' };
}
