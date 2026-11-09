import type { RouteObject } from 'react-router';

import { SearchPageContainer } from '@/features/catalog/ui/containers/SearchPageContainer';
import { HomePage } from '@/features/home/ui/components/HomePage';

import { AppShell } from './AppShell';
import { NotFoundPage } from './NotFoundPage';

export const routes: RouteObject[] = [
  {
    element: <AppShell />,
    children: [
      { index: true, element: <HomePage /> },
      { path: 'search', element: <SearchPageContainer /> },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
];
