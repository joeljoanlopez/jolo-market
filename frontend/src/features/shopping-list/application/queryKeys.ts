/** TanStack Query keys of the shopping lists, shared by every hook that reads or changes them. */
export const shoppingListKeys = {
  all: ['shopping-lists'] as const,
  mine: ['shopping-lists', 'mine'] as const,
  detail: (listId: string) => ['shopping-lists', 'detail', listId] as const,
};
