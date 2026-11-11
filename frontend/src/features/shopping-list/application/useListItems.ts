import { useMutation, useQueryClient } from '@tanstack/react-query';

import { ValidationError } from '@/shared/http/errors';

import {
  isValidItemText,
  isValidQuantity,
  withItemChecked,
  type NewItem,
} from '../domain/shoppingList';
import { shoppingListKeys } from './queryKeys';
import { useShoppingLists } from './shoppingListsContext';
import type { CachedList } from './useShoppingList';

export type AddItemError = 'invalid' | 'failed';

type ItemChange =
  | { kind: 'check'; itemId: string; checked: boolean }
  | { kind: 'quantity'; itemId: string; quantity: number }
  | { kind: 'remove'; itemId: string };

export interface ListItems {
  /** Adds an item at the end of the list; `onAdded` runs once the API accepted it. */
  add: (item: NewItem, onAdded: () => void) => void;
  adding: boolean;
  addError: AddItemError | null;
  /** Shown at once; undone if the API refuses it (PD-43: it states the intent, never toggles). */
  setChecked: (itemId: string, checked: boolean) => void;
  changeQuantity: (itemId: string, quantity: number) => void;
  remove: (itemId: string) => void;
  /** The last change of an item failed. */
  failed: boolean;
}

/** Item changes of an open list, by the owner or a member. The list reloads after each one. */
export function useListItems(listId: string): ListItems {
  const lists = useShoppingLists();
  const queryClient = useQueryClient();
  const key = shoppingListKeys.detail(listId);
  const refresh = () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: key }),
      queryClient.invalidateQueries({ queryKey: shoppingListKeys.mine }),
    ]);

  const addition = useMutation({
    mutationFn: async (item: NewItem) => {
      if (!isValidNewItem(item)) {
        throw new InvalidItem();
      }
      return lists.addItem(
        listId,
        item.type === 'text' ? { ...item, text: item.text.trim() } : item,
      );
    },
    onSettled: refresh,
  });

  const change = useMutation({
    mutationFn: (itemChange: ItemChange): Promise<void> => {
      switch (itemChange.kind) {
        case 'check':
          return lists.updateItem(listId, itemChange.itemId, { checked: itemChange.checked });
        case 'quantity':
          return lists.updateItem(listId, itemChange.itemId, { quantity: itemChange.quantity });
        case 'remove':
          return lists.removeItem(listId, itemChange.itemId);
      }
    },
    onMutate: async (itemChange) => {
      if (itemChange.kind !== 'check') {
        return { previous: undefined };
      }
      // A poll in flight would overwrite the optimistic check with the old list.
      await queryClient.cancelQueries({ queryKey: key });
      const previous = queryClient.getQueryData<CachedList>(key);
      if (previous !== undefined) {
        queryClient.setQueryData<CachedList>(key, {
          ...previous,
          list: withItemChecked(previous.list, itemChange.itemId, itemChange.checked),
        });
      }
      return { previous };
    },
    onError: (_error, _change, context) => {
      if (context?.previous !== undefined) {
        queryClient.setQueryData(key, context.previous);
      }
    },
    onSettled: refresh,
  });

  return {
    add: (item, onAdded) => {
      addition.mutate(item, {
        onSuccess: () => {
          onAdded();
        },
      });
    },
    adding: addition.isPending,
    addError: addition.isError
      ? addition.error instanceof InvalidItem || addition.error instanceof ValidationError
        ? 'invalid'
        : 'failed'
      : null,
    setChecked: (itemId, checked) => {
      change.mutate({ kind: 'check', itemId, checked });
    },
    changeQuantity: (itemId, quantity) => {
      change.mutate({ kind: 'quantity', itemId, quantity });
    },
    remove: (itemId) => {
      change.mutate({ kind: 'remove', itemId });
    },
    failed: change.isError,
  };
}

export interface AddProductToList {
  add: (listId: string) => void;
  pending: boolean;
  /** The list the product was last added to. */
  addedTo: string | null;
  failed: boolean;
}

/** "Add to list" from the search results: one product, any of "My lists" (quantity 1, PD-45). */
export function useAddProductToList(productId: string): AddProductToList {
  const lists = useShoppingLists();
  const queryClient = useQueryClient();
  const mutation = useMutation({
    mutationFn: (listId: string) => lists.addItem(listId, { type: 'product', productId }),
    onSettled: (_itemId, _error, listId) =>
      Promise.all([
        queryClient.invalidateQueries({ queryKey: shoppingListKeys.detail(listId) }),
        queryClient.invalidateQueries({ queryKey: shoppingListKeys.mine }),
      ]),
  });

  return {
    add: (listId) => {
      mutation.mutate(listId);
    },
    pending: mutation.isPending,
    addedTo: mutation.isSuccess ? mutation.variables : null,
    failed: mutation.isError,
  };
}

class InvalidItem extends Error {}

function isValidNewItem(item: NewItem): boolean {
  if (item.quantity !== undefined && !isValidQuantity(item.quantity)) {
    return false;
  }
  return item.type === 'product' || isValidItemText(item.text);
}
