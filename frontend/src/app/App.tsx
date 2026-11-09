import { QueryClientProvider, type QueryClient } from '@tanstack/react-query';
import type { i18n } from 'i18next';
import { I18nextProvider } from 'react-i18next';
import { RouterProvider, type createBrowserRouter } from 'react-router';

import type { ProductCatalog } from '@/features/catalog/application/ProductCatalog';
import { ProductCatalogContext } from '@/features/catalog/application/productCatalogContext';

interface AppProps {
  router: ReturnType<typeof createBrowserRouter>;
  queryClient: QueryClient;
  i18n: i18n;
  productCatalog: ProductCatalog;
}

/** Root component. Dependencies are injected so tests can use a memory router. */
export function App({ router, queryClient, i18n, productCatalog }: AppProps) {
  return (
    <QueryClientProvider client={queryClient}>
      <I18nextProvider i18n={i18n}>
        <ProductCatalogContext value={productCatalog}>
          <RouterProvider router={router} />
        </ProductCatalogContext>
      </I18nextProvider>
    </QueryClientProvider>
  );
}
