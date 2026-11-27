import { useQuery } from '@tanstack/react-query';

import { NotFoundError } from '@/shared/http/errors';

import { isReadingAny, type ListReceipts, type MonthlySpending } from '../domain/receipt';
import { receiptKeys } from './queryKeys';
import { useReceiptsApi } from './receiptsApiContext';

/** While a receipt is being read, the list asks again within this delay (PD-7, PD-65). */
export const RECEIPTS_REFRESH_INTERVAL_MS = 5000;

export type ListReceiptsState =
  | { status: 'loading' }
  | { status: 'notFound' }
  | { status: 'error'; retry: () => void }
  | { status: 'success'; receipts: ListReceipts };

export type ListSpendingState =
  | { status: 'loading' }
  | { status: 'notFound' }
  | { status: 'error'; retry: () => void }
  | { status: 'success'; months: MonthlySpending[] };

/** The receipts of a list. It polls only while one of them is still being read. */
export function useListReceipts(listId: string): ListReceiptsState {
  const api = useReceiptsApi();
  const query = useQuery({
    queryKey: receiptKeys.list(listId),
    queryFn: ({ signal }) => api.listReceipts(listId, { signal }),
    refetchInterval: (current) =>
      current.state.data !== undefined && isReadingAny(current.state.data.pending)
        ? RECEIPTS_REFRESH_INTERVAL_MS
        : false,
    refetchIntervalInBackground: false,
    retry: (failures, error) => !(error instanceof NotFoundError) && failures < 3,
  });

  if (query.error instanceof NotFoundError) {
    return { status: 'notFound' };
  }
  if (query.data !== undefined) {
    return { status: 'success', receipts: query.data };
  }
  if (query.isError) {
    return { status: 'error', retry: () => void query.refetch() };
  }
  return { status: 'loading' };
}

/** What the list spent per month (PD-70). It polls while `polling`, like the receipts. */
export function useListSpending(listId: string, polling: boolean): ListSpendingState {
  const api = useReceiptsApi();
  const query = useQuery({
    queryKey: receiptKeys.spending(listId),
    queryFn: ({ signal }) => api.listSpending(listId, { signal }),
    refetchInterval: polling ? RECEIPTS_REFRESH_INTERVAL_MS : false,
    refetchIntervalInBackground: false,
    retry: (failures, error) => !(error instanceof NotFoundError) && failures < 3,
  });

  if (query.error instanceof NotFoundError) {
    return { status: 'notFound' };
  }
  if (query.data !== undefined) {
    return { status: 'success', months: query.data };
  }
  if (query.isError) {
    return { status: 'error', retry: () => void query.refetch() };
  }
  return { status: 'loading' };
}
