import { QueryClientProvider, type QueryClient } from '@tanstack/react-query';
import type { i18n } from 'i18next';
import { I18nextProvider } from 'react-i18next';
import { RouterProvider, type createBrowserRouter } from 'react-router';

interface AppProps {
  router: ReturnType<typeof createBrowserRouter>;
  queryClient: QueryClient;
  i18n: i18n;
}

/** Root component. Dependencies are injected so tests can use a memory router. */
export function App({ router, queryClient, i18n }: AppProps) {
  return (
    <QueryClientProvider client={queryClient}>
      <I18nextProvider i18n={i18n}>
        <RouterProvider router={router} />
      </I18nextProvider>
    </QueryClientProvider>
  );
}
