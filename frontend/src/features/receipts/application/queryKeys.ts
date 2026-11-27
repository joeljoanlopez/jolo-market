/** TanStack Query keys of receipts, shared by every hook that reads or changes them. */
export const receiptKeys = {
  all: ['receipts'] as const,
  ofList: (listId: string) => ['receipts', 'list', listId] as const,
  list: (listId: string) => ['receipts', 'list', listId, 'receipts'] as const,
  spending: (listId: string) => ['receipts', 'list', listId, 'spending'] as const,
  detail: (listId: string, receiptId: string) =>
    ['receipts', 'list', listId, 'receipt', receiptId] as const,
};
