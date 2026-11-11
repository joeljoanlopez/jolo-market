import { QueryClient } from '@tanstack/react-query';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { createBrowserRouter } from 'react-router';

import { App } from '@/app/App';
import { routes } from '@/app/routes';
import { createAdminApi } from '@/features/admin/infrastructure/adminApi';
import { createAuthApi } from '@/features/auth/infrastructure/authApi';
import { createCatalogApi } from '@/features/catalog/infrastructure/catalogApi';
import { createShoppingListApi } from '@/features/shopping-list/infrastructure/shoppingListApi';
import { createHttpClient } from '@/shared/http/httpClient';
import { createI18n } from '@/shared/i18n/i18n';

import './index.css';

const root = document.getElementById('root');
if (root === null) {
  throw new Error('The #root element is missing from index.html.');
}

// The SPA and the API share the origin in every environment.
const http = createHttpClient({ baseUrl: window.location.origin });

createRoot(root).render(
  <StrictMode>
    <App
      router={createBrowserRouter(routes)}
      queryClient={new QueryClient()}
      i18n={createI18n()}
      productCatalog={createCatalogApi(http)}
      authGateway={createAuthApi(http)}
      userAdministration={createAdminApi(http)}
      shoppingLists={createShoppingListApi(http)}
    />
  </StrictMode>,
);
