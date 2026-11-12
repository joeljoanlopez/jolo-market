import type { RouteObject } from 'react-router';

import { UsersPageContainer } from '@/features/admin/ui/containers/UsersPageContainer';
import { ActivationPageContainer } from '@/features/auth/ui/containers/ActivationPageContainer';
import { LoginPageContainer } from '@/features/auth/ui/containers/LoginPageContainer';
import { ProfilePageContainer } from '@/features/auth/ui/containers/ProfilePageContainer';
import { SetupPageContainer } from '@/features/auth/ui/containers/SetupPageContainer';
import type { Product } from '@/features/catalog/domain/product';
import { ProductPageContainer } from '@/features/catalog/ui/containers/ProductPageContainer';
import { SearchPageContainer } from '@/features/catalog/ui/containers/SearchPageContainer';
import { HomePage } from '@/features/home/ui/components/HomePage';
import { AddToListContainer } from '@/features/shopping-list/ui/containers/AddToListContainer';
import { JoinPageContainer } from '@/features/shopping-list/ui/containers/JoinPageContainer';
import { ListPageContainer } from '@/features/shopping-list/ui/containers/ListPageContainer';

import { AppShell } from './AppShell';
import { RequireAdmin } from './guards/RequireAdmin';
import { RequireAuth } from './guards/RequireAuth';
import { RequireSetupDone } from './guards/RequireSetupDone';
import { NotFoundPage } from './NotFoundPage';

// The catalog never imports the shopping-list feature: its pages get this action from here.
function addToList(product: Product) {
  return <AddToListContainer productId={product.id} productName={product.name} />;
}

export const routes: RouteObject[] = [
  {
    element: <AppShell />,
    children: [
      { path: 'activate/:token', element: <ActivationPageContainer /> },
      { path: 'setup', element: <SetupPageContainer /> },
      {
        element: <RequireSetupDone />,
        children: [
          { path: 'login', element: <LoginPageContainer /> },
          {
            element: <RequireAuth />,
            children: [
              { index: true, element: <HomePage /> },
              {
                path: 'search',
                element: <SearchPageContainer renderProductActions={addToList} />,
              },
              {
                path: 'product/:productId',
                element: <ProductPageContainer renderProductActions={addToList} />,
              },
              { path: 'profile', element: <ProfilePageContainer /> },
              { path: 'lists/:listId', element: <ListPageContainer /> },
              // Inside RequireAuth: a visitor without a session logs in first and comes back.
              { path: 'join/:token', element: <JoinPageContainer /> },
              {
                element: <RequireAdmin />,
                children: [{ path: 'admin/users', element: <UsersPageContainer /> }],
              },
              { path: '*', element: <NotFoundPage /> },
            ],
          },
        ],
      },
    ],
  },
];
