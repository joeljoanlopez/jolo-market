import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, type RenderResult } from '@testing-library/react';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';

import { testI18n } from './i18n';

export function createTestQueryClient(): QueryClient {
  return new QueryClient({ defaultOptions: { queries: { retry: false } } });
}

/** Renders a component with the same providers as the app (except the router). */
export function renderWithProviders(ui: ReactElement): RenderResult {
  return render(
    <QueryClientProvider client={createTestQueryClient()}>
      <I18nextProvider i18n={testI18n}>{ui}</I18nextProvider>
    </QueryClientProvider>,
  );
}
