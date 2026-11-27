import { useMutation, useQueryClient } from '@tanstack/react-query';

import { ValidationError } from '@/shared/http/errors';

import { receiptKeys } from './queryKeys';
import { useReceiptsApi } from './receiptsApiContext';

/** `invalid`: the API refused the file or the supermarket (PD-64, PD-68); `failed`: anything else. */
export type UploadError = 'invalid' | 'failed';

export interface ReceiptUpload {
  /** `onDone` gets the id of the new receipt, which is then being read. */
  upload: (supermarketId: string, photo: File, onDone?: (receiptId: string) => void) => void;
  pending: boolean;
  error: UploadError | null;
}

export function useUploadReceipt(listId: string): ReceiptUpload {
  const api = useReceiptsApi();
  const queryClient = useQueryClient();
  const mutation = useMutation({
    mutationFn: ({ supermarketId, photo }: { supermarketId: string; photo: File }) =>
      api.uploadReceipt(listId, supermarketId, photo),
    onSettled: () => queryClient.invalidateQueries({ queryKey: receiptKeys.list(listId) }),
  });

  return {
    upload: (supermarketId, photo, onDone) => {
      mutation.mutate(
        { supermarketId, photo },
        {
          onSuccess: (receiptId) => {
            onDone?.(receiptId);
          },
        },
      );
    },
    pending: mutation.isPending,
    error:
      mutation.error === null
        ? null
        : mutation.error instanceof ValidationError
          ? 'invalid'
          : 'failed',
  };
}
