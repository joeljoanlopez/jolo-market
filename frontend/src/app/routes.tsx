import type { RouteObject } from 'react-router';

import { UsersPageContainer } from '@/features/admin/ui/containers/UsersPageContainer';
import { ActivationPageContainer } from '@/features/auth/ui/containers/ActivationPageContainer';
import { LoginPageContainer } from '@/features/auth/ui/containers/LoginPageContainer';
import { ProfilePageContainer } from '@/features/auth/ui/containers/ProfilePageContainer';
import { SetupPageContainer } from '@/features/auth/ui/containers/SetupPageContainer';
import { SearchPageContainer } from '@/features/catalog/ui/containers/SearchPageContainer';
import { HomePage } from '@/features/home/ui/components/HomePage';

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
              { path: 'search', element: <SearchPageContainer /> },
              { path: 'profile', element: <ProfilePageContainer /> },
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
