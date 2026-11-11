import type { RouteObject } from 'react-router';

import { UsersPageContainer } from '@/features/admin/ui/containers/UsersPageContainer';
import { ActivationPageContainer } from '@/features/auth/ui/containers/ActivationPageContainer';
import { LoginPageContainer } from '@/features/auth/ui/containers/LoginPageContainer';
import { ProfilePageContainer } from '@/features/auth/ui/containers/ProfilePageContainer';
import { SetupPageContainer } from '@/features/auth/ui/containers/SetupPageContainer';
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
                element: (
                  <SearchPageContainer
                    renderProductActions={(product) => (
                      <AddToListContainer productId={product.id} productName={product.name} />
                    )}
                  />
                ),
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
