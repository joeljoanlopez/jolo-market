import { createContext, useContext } from 'react';

import type { ShoppingLists } from './ShoppingLists';

/** Injects the ShoppingLists adapter; the app provides the API client, tests a fake. */
export const ShoppingListsContext = createContext<ShoppingLists | null>(null);

export function useShoppingLists(): ShoppingLists {
  const lists = useContext(ShoppingListsContext);
  if (lists === null) {
    throw new Error('useShoppingLists() needs a ShoppingListsContext provider.');
  }
  return lists;
}
