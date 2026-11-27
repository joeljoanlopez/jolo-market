import { QueryClientProvider, type QueryClient } from '@tanstack/react-query';
import { renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';

import { ReceiptsApiContext } from '@/features/receipts/application/receiptsApiContext';

import type { FakeReceiptsApi } from './FakeReceiptsApi';
import { createTestQueryClient } from './render';

/** Renders a receipts hook with a fresh query client and the given fake. */
export function renderHookWithReceipts<T>(
  hook: () => T,
  receipts: FakeReceiptsApi,
  queryClient: QueryClient = createTestQueryClient(),
) {
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={queryClient}>
      <ReceiptsApiContext value={receipts}>{children}</ReceiptsApiContext>
    </QueryClientProvider>
  );
  return { ...renderHook(hook, { wrapper }), queryClient };
}
