import { act, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { FakeShoppingLists, JOAN } from '@/test/FakeShoppingLists';
import { renderHookWithLists } from '@/test/renderHookWithLists';

import { useListManagement } from './useListManagement';
import { useShoppingList } from './useShoppingList';

function renderManagement(lists: FakeShoppingLists, listId: string) {
  return renderHookWithLists(
    () => ({ list: useShoppingList(listId), manage: useListManagement(listId) }),
    lists,
  );
}

describe('useListManagement', () => {
  it('regenerates the link and shows the new token (owner)', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed();
    const oldToken = stored.shareToken;
    const { result } = renderManagement(lists, stored.id);
    await waitFor(() => {
      expect(result.current.list.status).toBe('success');
    });

    act(() => {
      result.current.manage.regenerateLink();
    });

    await waitFor(() => {
      expect(result.current.list).toMatchObject({ list: { shareToken: stored.shareToken } });
    });
    expect(stored.shareToken).not.toBe(oldToken);
  });

  it('removes a member (owner)', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({ members: [JOAN] });
    const { result } = renderManagement(lists, stored.id);
    await waitFor(() => {
      expect(result.current.list).toMatchObject({ list: { members: [JOAN] } });
    });

    act(() => {
      result.current.manage.removeMember(JOAN.id);
    });

    await waitFor(() => {
      expect(result.current.list).toMatchObject({ list: { members: [] } });
    });
  });

  it('deletes the list and then calls back (owner, PD-44)', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed();
    const onDone = vi.fn();
    const { result } = renderHookWithLists(() => useListManagement(stored.id), lists);

    act(() => {
      result.current.deleteList(onDone);
    });

    await waitFor(() => {
      expect(onDone).toHaveBeenCalledOnce();
    });
    expect(lists.lists.has(stored.id)).toBe(false);
  });

  it('leaves the list and then calls back (member, PD-44)', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({ owner: JOAN, members: [lists.currentUser] });
    const onDone = vi.fn();
    const { result } = renderHookWithLists(() => useListManagement(stored.id), lists);

    act(() => {
      result.current.leaveList(onDone);
    });

    await waitFor(() => {
      expect(onDone).toHaveBeenCalledOnce();
    });
    expect(stored.members).toEqual([]);
  });

  it('reports which action failed', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed();
    lists.changeError = new Error('offline');
    const { result } = renderHookWithLists(() => useListManagement(stored.id), lists);

    act(() => {
      result.current.regenerateLink();
    });

    await waitFor(() => {
      expect(result.current.failedAction).toBe('regenerateLink');
    });
  });
});
