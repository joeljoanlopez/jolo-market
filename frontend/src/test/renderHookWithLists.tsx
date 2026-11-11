import { QueryClientProvider, type QueryClient } from '@tanstack/react-query';
import { renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';

import { ShoppingListsContext } from '@/features/shopping-list/application/shoppingListsContext';

import type { FakeShoppingLists } from './FakeShoppingLists';
import { createTestQueryClient } from './render';

/** Renders a shopping-list hook with a fresh query client and the given fake. */
export function renderHookWithLists<T>(
  hook: () => T,
  lists: FakeShoppingLists,
  queryClient: QueryClient = createTestQueryClient(),
) {
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={queryClient}>
      <ShoppingListsContext value={lists}>{children}</ShoppingListsContext>
    </QueryClientProvider>
  );
  return { ...renderHook(hook, { wrapper }), queryClient };
}
