import { useQuery, useQueryClient, type QueryClient } from '@tanstack/react-query';

import { NotFoundError, UnexpectedResponseError } from '@/shared/http/errors';

import type { ShoppingList } from '../domain/shoppingList';
import { shoppingListKeys } from './queryKeys';
import type { ShoppingLists } from './ShoppingLists';
import { useShoppingLists } from './shoppingListsContext';

/** While a list is open, changes made by others show up within this delay (PD-7). */
export const LIST_REFRESH_INTERVAL_MS = 5000;

/** What the cache holds for an open list: the list and the ETag to revalidate it with. */
export interface CachedList {
  list: ShoppingList;
  etag: string | null;
}

export type ShoppingListState =
  | { status: 'loading' }
  /** Deleted, left, removed from it, or never accessible: the API never says which (PD-44). */
  | { status: 'notFound' }
  | { status: 'error'; retry: () => void }
  | { status: 'success'; list: ShoppingList };

/**
 * An open list. It polls every LIST_REFRESH_INTERVAL_MS while the page is visible, sending the
 * ETag it holds, so an unchanged list costs a `304` and keeps the same data.
 */
export function useShoppingList(listId: string): ShoppingListState {
  const lists = useShoppingLists();
  const queryClient = useQueryClient();
  const query = useQuery({
    queryKey: shoppingListKeys.detail(listId),
    queryFn: ({ signal }) => fetchList(lists, queryClient, listId, signal),
    refetchInterval: LIST_REFRESH_INTERVAL_MS,
    refetchIntervalInBackground: false,
    retry: (failures, error) => !(error instanceof NotFoundError) && failures < 3,
  });

  if (query.error instanceof NotFoundError) {
    return { status: 'notFound' };
  }
  if (query.data !== undefined) {
    // A failed poll keeps the list on screen; the next one tries again.
    return { status: 'success', list: query.data.list };
  }
  if (query.isError) {
    return { status: 'error', retry: () => void query.refetch() };
  }
  return { status: 'loading' };
}

async function fetchList(
  lists: ShoppingLists,
  queryClient: QueryClient,
  listId: string,
  signal: AbortSignal,
): Promise<CachedList> {
  const held = queryClient.getQueryData<CachedList>(shoppingListKeys.detail(listId));
  const snapshot = await lists.getList(listId, { etag: held?.etag ?? null, signal });
  if (snapshot.status === 'changed') {
    return { list: snapshot.list, etag: snapshot.etag };
  }
  if (held === undefined) {
    throw new UnexpectedResponseError(`List ${listId} answered 304 to a request without an ETag.`);
  }
  return held;
}
