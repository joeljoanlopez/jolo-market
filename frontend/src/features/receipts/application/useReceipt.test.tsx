import { act, waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { FakeReceiptsApi } from '@/test/FakeReceiptsApi';
import { renderHookWithReceipts } from '@/test/renderHookWithReceipts';

import { useReceipt } from './useReceipt';
import { useReceiptEditing } from './useReceiptEditing';
import { useUploadReceipt } from './useUploadReceipt';

describe('useReceipt', () => {
  it('loads a receipt, and is not found for one of another list', async () => {
    const api = new FakeReceiptsApi();
    const receipt = api.seed({ totalCents: 500 });

    const found = renderHookWithReceipts(() => useReceipt('list-1', receipt.id), api);
    const missing = renderHookWithReceipts(() => useReceipt('list-1', 'receipt-unknown'), api);

    await waitFor(() => {
      expect(found.result.current).toMatchObject({
        status: 'success',
        receipt: { totalCents: 500 },
      });
    });
    await waitFor(() => {
      expect(missing.result.current.status).toBe('notFound');
    });
  });
});

describe('useUploadReceipt', () => {
  it('uploads the photo and resolves with the new receipt id', async () => {
    const api = new FakeReceiptsApi();
    const { result } = renderHookWithReceipts(() => useUploadReceipt('list-1'), api);
    let uploaded: string | null = null;

    act(() => {
      result.current.upload('aldi', new File(['x'], 'ticket.jpg'), (id) => {
        uploaded = id;
      });
    });

    await waitFor(() => {
      expect(uploaded).not.toBeNull();
    });
    expect(api.uploads).toEqual([
      { listId: 'list-1', supermarketId: 'aldi', fileName: 'ticket.jpg' },
    ]);
    expect(result.current.error).toBeNull();
  });

  it('reports a failed upload', async () => {
    const api = new FakeReceiptsApi();
    api.changeError = new Error('offline');
    const { result } = renderHookWithReceipts(() => useUploadReceipt('list-1'), api);

    act(() => {
      result.current.upload('aldi', new File(['x'], 'ticket.jpg'));
    });

    await waitFor(() => {
      expect(result.current.error).toBe('failed');
    });
  });
});

describe('useReceiptEditing', () => {
  it('sends each edit and reloads the receipt', async () => {
    const api = new FakeReceiptsApi();
    const receipt = api.seed();
    const { result } = renderHookWithReceipts(
      () => ({
        editing: useReceiptEditing('list-1', receipt.id),
        view: useReceipt('list-1', receipt.id),
      }),
      api,
    );

    act(() => {
      result.current.editing.update({ date: '2026-11-14', totalCents: 300 });
    });
    await waitFor(() => {
      expect(result.current.view).toMatchObject({
        receipt: { date: '2026-11-14', totalCents: 300 },
      });
    });

    act(() => {
      result.current.editing.addLine({
        kind: 'product',
        text: 'PAN',
        unit: 'units',
        quantity: 1,
        amountCents: 300,
      });
    });
    await waitFor(() => {
      expect(result.current.view).toMatchObject({ receipt: { differenceCents: 0 } });
    });

    act(() => {
      result.current.editing.confirm();
    });
    await waitFor(() => {
      expect(result.current.view).toMatchObject({ receipt: { status: 'confirmed' } });
    });
    expect(api.calls).toEqual([
      'update {"date":"2026-11-14","totalCents":300}',
      'add PAN',
      `confirm ${receipt.id}`,
    ]);
  });

  it('tells a receipt that cannot be confirmed from a failed edit', async () => {
    const api = new FakeReceiptsApi();
    const receipt = api.seed();
    const { result } = renderHookWithReceipts(() => useReceiptEditing('list-1', receipt.id), api);

    act(() => {
      result.current.confirm();
    });
    await waitFor(() => {
      expect(result.current.error).toBe('invalid');
    });

    api.changeError = new Error('offline');
    act(() => {
      result.current.removeLine('line-x');
    });
    await waitFor(() => {
      expect(result.current.error).toBe('failed');
    });
  });

  it('runs onDone once the receipt is deleted', async () => {
    const api = new FakeReceiptsApi();
    const receipt = api.seed();
    const { result } = renderHookWithReceipts(() => useReceiptEditing('list-1', receipt.id), api);
    let done = false;

    act(() => {
      result.current.deleteReceipt(() => {
        done = true;
      });
    });

    await waitFor(() => {
      expect(done).toBe(true);
    });
    expect(api.receipts.has(receipt.id)).toBe(false);
  });
});
