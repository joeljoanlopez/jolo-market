import { act, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { FakeShoppingLists, JOAN } from '@/test/FakeShoppingLists';
import { renderHookWithLists } from '@/test/renderHookWithLists';

import { LIST_REFRESH_INTERVAL_MS, useShoppingList } from './useShoppingList';

function renderUseShoppingList(lists: FakeShoppingLists, listId: string) {
  return renderHookWithLists(() => useShoppingList(listId), lists);
}

describe('useShoppingList', () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  it('loads the list', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({ name: 'Cena' });

    const { result } = renderUseShoppingList(lists, stored.id);

    expect(result.current.status).toBe('loading');
    await waitFor(() => {
      expect(result.current).toMatchObject({ status: 'success', list: { name: 'Cena' } });
    });
  });

  it('is not found when the user cannot access the list', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({ owner: JOAN });

    const { result } = renderUseShoppingList(lists, stored.id);

    await waitFor(() => {
      expect(result.current.status).toBe('notFound');
    });
  });

  it('polls every 5 s with the ETag it holds and shows changes made by others (PD-7)', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const lists = new FakeShoppingLists();
    const stored = lists.seed();
    const { result } = renderUseShoppingList(lists, stored.id);
    await waitFor(() => {
      expect(result.current.status).toBe('success');
    });

    lists.addItemAsSomeoneElse(stored.id, {
      id: 'item-joan',
      position: 0,
      quantity: 1,
      checked: false,
      type: 'text',
      text: 'pan',
    });
    await act(() => vi.advanceTimersByTimeAsync(LIST_REFRESH_INTERVAL_MS));

    expect(LIST_REFRESH_INTERVAL_MS).toBe(5000);
    expect(lists.reads.map((read) => read.etag)).toEqual([null, '"1"']);
    await waitFor(() => {
      expect(result.current).toMatchObject({
        status: 'success',
        list: { items: [{ text: 'pan' }] },
      });
    });
  });

  it('keeps the list it holds when the API answers 304', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const lists = new FakeShoppingLists();
    const stored = lists.seed();
    const { result } = renderUseShoppingList(lists, stored.id);
    await waitFor(() => {
      expect(result.current.status).toBe('success');
    });
    const before = result.current;

    await act(() => vi.advanceTimersByTimeAsync(LIST_REFRESH_INTERVAL_MS));

    expect(lists.reads).toHaveLength(2);
    expect(result.current).toEqual(before);
  });

  it('does not poll while the page is hidden', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const lists = new FakeShoppingLists();
    const stored = lists.seed();
    const { result } = renderUseShoppingList(lists, stored.id);
    await waitFor(() => {
      expect(result.current.status).toBe('success');
    });

    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('hidden');
    await act(() => vi.advanceTimersByTimeAsync(LIST_REFRESH_INTERVAL_MS * 3));

    expect(lists.reads).toHaveLength(1);
  });

  it('turns into not found when the list is deleted while it is open (PD-44)', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const lists = new FakeShoppingLists();
    const stored = lists.seed({ owner: JOAN, members: [] });
    stored.members.push(lists.currentUser);
    const { result } = renderUseShoppingList(lists, stored.id);
    await waitFor(() => {
      expect(result.current.status).toBe('success');
    });

    lists.lists.delete(stored.id);
    await act(() => vi.advanceTimersByTimeAsync(LIST_REFRESH_INTERVAL_MS));

    await waitFor(() => {
      expect(result.current.status).toBe('notFound');
    });
  });
});
