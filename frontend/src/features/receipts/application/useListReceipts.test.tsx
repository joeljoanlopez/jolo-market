import { act, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { FakeReceiptsApi } from '@/test/FakeReceiptsApi';
import { renderHookWithReceipts } from '@/test/renderHookWithReceipts';

import { RECEIPTS_REFRESH_INTERVAL_MS, useListReceipts, useListSpending } from './useListReceipts';

describe('useListReceipts', () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  it('loads the pending and confirmed receipts of the list', async () => {
    const api = new FakeReceiptsApi();
    api.seed({ status: 'confirmed', date: '2026-11-14', totalCents: 100 });
    api.seed({ status: 'draft' });

    const { result } = renderHookWithReceipts(() => useListReceipts('list-1'), api);

    await waitFor(() => {
      expect(result.current.status).toBe('success');
    });
    expect(result.current).toMatchObject({
      receipts: { pending: [{ status: 'draft' }], confirmed: [{ status: 'confirmed' }] },
    });
  });

  it('is not found when the user cannot see the list', async () => {
    const api = new FakeReceiptsApi();

    const { result } = renderHookWithReceipts(() => useListReceipts('list-9'), api);

    await waitFor(() => {
      expect(result.current.status).toBe('notFound');
    });
  });

  it('polls every 5 s while a receipt is being read, and stops when none is (PD-7)', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const api = new FakeReceiptsApi();
    const reading = api.seed({ status: 'reading' });
    const { result } = renderHookWithReceipts(() => useListReceipts('list-1'), api);
    await waitFor(() => {
      expect(result.current.status).toBe('success');
    });

    api.finishReading(reading.id);
    await act(() => vi.advanceTimersByTimeAsync(RECEIPTS_REFRESH_INTERVAL_MS));

    await waitFor(() => {
      expect(result.current).toMatchObject({ receipts: { pending: [{ status: 'draft' }] } });
    });
    const reads = api.reads;
    await act(() => vi.advanceTimersByTimeAsync(RECEIPTS_REFRESH_INTERVAL_MS * 3));
    expect(api.reads).toBe(reads);
  });
});

describe('useListSpending', () => {
  it('loads the spending per month', async () => {
    const api = new FakeReceiptsApi();
    api.seed({ status: 'confirmed', date: '2026-11-14', totalCents: 2599 });

    const { result } = renderHookWithReceipts(() => useListSpending('list-1', false), api);

    await waitFor(() => {
      expect(result.current).toMatchObject({
        status: 'success',
        months: [{ month: '2026-11', totalCents: 2599 }],
      });
    });
  });
});
