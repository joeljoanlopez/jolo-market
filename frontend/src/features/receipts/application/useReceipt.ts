import { useQuery } from '@tanstack/react-query';

import { NotFoundError } from '@/shared/http/errors';

import type { Receipt } from '../domain/receipt';
import { receiptKeys } from './queryKeys';
import { useReceiptsApi } from './receiptsApiContext';
import { RECEIPTS_REFRESH_INTERVAL_MS } from './useListReceipts';

export type ReceiptState =
  | { status: 'loading' }
  /** Unknown, of another list, or the user is not a member (PD-63). */
  | { status: 'notFound' }
  | { status: 'error'; retry: () => void }
  | { status: 'success'; receipt: Receipt };

/** A receipt for its review. While it is being read it polls, so the draft shows up by itself. */
export function useReceipt(listId: string, receiptId: string): ReceiptState {
  const api = useReceiptsApi();
  const query = useQuery({
    queryKey: receiptKeys.detail(listId, receiptId),
    queryFn: ({ signal }) => api.getReceipt(listId, receiptId, { signal }),
    refetchInterval: (current) =>
      current.state.data?.status === 'reading' ? RECEIPTS_REFRESH_INTERVAL_MS : false,
    refetchIntervalInBackground: false,
    retry: (failures, error) => !(error instanceof NotFoundError) && failures < 3,
  });

  if (query.error instanceof NotFoundError) {
    return { status: 'notFound' };
  }
  if (query.data !== undefined) {
    return { status: 'success', receipt: query.data };
  }
  if (query.isError) {
    return { status: 'error', retry: () => void query.refetch() };
  }
  return { status: 'loading' };
}
