import { QueryClientProvider, type QueryClient } from '@tanstack/react-query';
import type { i18n } from 'i18next';
import { useState } from 'react';
import { I18nextProvider } from 'react-i18next';
import { RouterProvider, type createBrowserRouter } from 'react-router';

import type { UserAdministration } from '@/features/admin/application/UserAdministration';
import { UserAdministrationContext } from '@/features/admin/application/userAdministrationContext';
import type { AuthGateway } from '@/features/auth/application/AuthGateway';
import { AuthGatewayContext } from '@/features/auth/application/authGatewayContext';
import type { ProductCatalog } from '@/features/catalog/application/ProductCatalog';
import { ProductCatalogContext } from '@/features/catalog/application/productCatalogContext';
import type { ShoppingLists } from '@/features/shopping-list/application/ShoppingLists';
import { ShoppingListsContext } from '@/features/shopping-list/application/shoppingListsContext';
import { createShoppingListApi } from '@/features/shopping-list/infrastructure/shoppingListApi';
import { createHttpClient } from '@/shared/http/httpClient';

interface AppProps {
  router: ReturnType<typeof createBrowserRouter>;
  queryClient: QueryClient;
  i18n: i18n;
  productCatalog: ProductCatalog;
  authGateway: AuthGateway;
  userAdministration: UserAdministration;
  /** Defaults to the API client on this origin; tests pass a fake. */
  shoppingLists?: ShoppingLists;
}

/** Root component. Dependencies are injected so tests can use a memory router. */
export function App({
  router,
  queryClient,
  i18n,
  productCatalog,
  authGateway,
  userAdministration,
  shoppingLists,
}: AppProps) {
  const [lists] = useState(
    () =>
      shoppingLists ?? createShoppingListApi(createHttpClient({ baseUrl: window.location.origin })),
  );

  return (
    <QueryClientProvider client={queryClient}>
      <I18nextProvider i18n={i18n}>
        <AuthGatewayContext value={authGateway}>
          <UserAdministrationContext value={userAdministration}>
            <ProductCatalogContext value={productCatalog}>
              <ShoppingListsContext value={lists}>
                <RouterProvider router={router} />
              </ShoppingListsContext>
            </ProductCatalogContext>
          </UserAdministrationContext>
        </AuthGatewayContext>
      </I18nextProvider>
    </QueryClientProvider>
  );
}
