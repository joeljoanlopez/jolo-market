import type { RouteObject } from 'react-router';

import { LoginPageContainer } from '@/features/auth/ui/containers/LoginPageContainer';
import { SearchPageContainer } from '@/features/catalog/ui/containers/SearchPageContainer';
import { HomePage } from '@/features/home/ui/components/HomePage';

import { AppShell } from './AppShell';
import { RequireAuth } from './guards/RequireAuth';
import { NotFoundPage } from './NotFoundPage';

export const routes: RouteObject[] = [
  {
    element: <AppShell />,
    children: [
      { path: 'login', element: <LoginPageContainer /> },
      {
        element: <RequireAuth />,
        children: [
          { index: true, element: <HomePage /> },
          { path: 'search', element: <SearchPageContainer /> },
          { path: '*', element: <NotFoundPage /> },
        ],
      },
    ],
  },
];
