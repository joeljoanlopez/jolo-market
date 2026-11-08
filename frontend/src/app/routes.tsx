import type { RouteObject } from 'react-router';

import { HomePage } from '@/features/home/ui/components/HomePage';

import { AppShell } from './AppShell';
import { NotFoundPage } from './NotFoundPage';

export const routes: RouteObject[] = [
  {
    element: <AppShell />,
    children: [
      { index: true, element: <HomePage /> },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
];
