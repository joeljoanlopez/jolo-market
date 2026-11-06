import { QueryClient } from '@tanstack/react-query';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { createBrowserRouter } from 'react-router';

import { App } from '@/app/App';
import { routes } from '@/app/routes';
import { createI18n } from '@/shared/i18n/i18n';

import './index.css';

const root = document.getElementById('root');
if (root === null) {
  throw new Error('The #root element is missing from index.html.');
}

createRoot(root).render(
  <StrictMode>
    <App router={createBrowserRouter(routes)} queryClient={new QueryClient()} i18n={createI18n()} />
  </StrictMode>,
);
