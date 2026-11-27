import { createContext, useContext } from 'react';

import type { ReceiptsApi } from './ReceiptsApi';

/** Injects the ReceiptsApi adapter; the app provides the API client, tests a fake. */
export const ReceiptsApiContext = createContext<ReceiptsApi | null>(null);

export function useReceiptsApi(): ReceiptsApi {
  const receipts = useContext(ReceiptsApiContext);
  if (receipts === null) {
    throw new Error('useReceiptsApi() needs a ReceiptsApiContext provider.');
  }
  return receipts;
}
