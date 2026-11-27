import { useMutation, useQueryClient } from '@tanstack/react-query';

import { ValidationError } from '@/shared/http/errors';

import { receiptKeys } from './queryKeys';
import type { LineChanges, NewLine, ReceiptChanges } from './ReceiptsApi';
import { useReceiptsApi } from './receiptsApiContext';

/** `invalid`: the API refused the data (422), such as confirming without date or total; `failed`: anything else. */
export type EditError = 'invalid' | 'failed';

type Edit =
  | { type: 'update'; changes: ReceiptChanges }
  | { type: 'addLine'; line: NewLine }
  | { type: 'updateLine'; lineId: string; changes: LineChanges }
  | { type: 'removeLine'; lineId: string }
  | { type: 'link'; lineId: string; productId: string }
  | { type: 'unlink'; lineId: string }
  | { type: 'confirm' }
  | { type: 'delete' };

export interface ReceiptEditing {
  update: (changes: ReceiptChanges) => void;
  addLine: (line: NewLine, onDone?: () => void) => void;
  updateLine: (lineId: string, changes: LineChanges) => void;
  removeLine: (lineId: string) => void;
  /** Accepts the suggestion, or links the product picked in the search (PD-67). */
  link: (lineId: string, productId: string) => void;
  unlink: (lineId: string) => void;
  confirm: (onDone?: () => void) => void;
  /** `onDone` runs once the receipt is gone. */
  deleteReceipt: (onDone: () => void) => void;
  pending: boolean;
  error: EditError | null;
}

/** The review of a receipt: every edit reloads it, and the list's receipts and spending. */
export function useReceiptEditing(listId: string, receiptId: string): ReceiptEditing {
  const api = useReceiptsApi();
  const queryClient = useQueryClient();
  const mutation = useMutation({
    mutationFn: async (edit: Edit): Promise<void> => {
      switch (edit.type) {
        case 'update':
          return api.updateReceipt(listId, receiptId, edit.changes);
        case 'addLine':
          await api.addLine(listId, receiptId, edit.line);
          return;
        case 'updateLine':
          return api.updateLine(listId, receiptId, edit.lineId, edit.changes);
        case 'removeLine':
          return api.removeLine(listId, receiptId, edit.lineId);
        case 'link':
          return api.linkLine(listId, receiptId, edit.lineId, edit.productId);
        case 'unlink':
          return api.unlinkLine(listId, receiptId, edit.lineId);
        case 'confirm':
          return api.confirmReceipt(listId, receiptId);
        case 'delete':
          return api.deleteReceipt(listId, receiptId);
      }
    },
    onSettled: (_result, _error, edit) => {
      if (edit.type === 'delete') {
        return queryClient.invalidateQueries({
          queryKey: receiptKeys.ofList(listId),
          refetchType: 'none',
        });
      }
      return queryClient.invalidateQueries({ queryKey: receiptKeys.ofList(listId) });
    },
  });

  const run = (edit: Edit, onDone?: () => void) => {
    mutation.mutate(edit, {
      onSuccess: () => {
        if (edit.type === 'delete') {
          queryClient.removeQueries({ queryKey: receiptKeys.detail(listId, receiptId) });
        }
        onDone?.();
      },
    });
  };

  return {
    update: (changes) => {
      run({ type: 'update', changes });
    },
    addLine: (line, onDone) => {
      run({ type: 'addLine', line }, onDone);
    },
    updateLine: (lineId, changes) => {
      run({ type: 'updateLine', lineId, changes });
    },
    removeLine: (lineId) => {
      run({ type: 'removeLine', lineId });
    },
    link: (lineId, productId) => {
      run({ type: 'link', lineId, productId });
    },
    unlink: (lineId) => {
      run({ type: 'unlink', lineId });
    },
    confirm: (onDone) => {
      run({ type: 'confirm' }, onDone);
    },
    deleteReceipt: (onDone) => {
      run({ type: 'delete' }, onDone);
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
