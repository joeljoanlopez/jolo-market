import { QueryClientProvider, type QueryClient } from '@tanstack/react-query';
import { renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';

import { ProductCatalogContext } from '@/features/catalog/application/productCatalogContext';

import type { FakeProductCatalog } from './FakeProductCatalog';
import { createTestQueryClient } from './render';

/** Renders a catalog hook with a fresh query client and the given fake. */
export function renderHookWithCatalog<Props, Result>(
  hook: (props: Props) => Result,
  catalog: FakeProductCatalog,
  options: { initialProps?: Props; queryClient?: QueryClient } = {},
) {
  const queryClient = options.queryClient ?? createTestQueryClient();
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={queryClient}>
      <ProductCatalogContext value={catalog}>{children}</ProductCatalogContext>
    </QueryClientProvider>
  );
  return renderHook(hook, {
    wrapper,
    ...(options.initialProps === undefined ? {} : { initialProps: options.initialProps }),
  });
}
