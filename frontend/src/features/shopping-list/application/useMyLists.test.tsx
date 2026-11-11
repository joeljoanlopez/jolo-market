import { act, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { FakeShoppingLists, JOAN } from '@/test/FakeShoppingLists';
import { renderHookWithLists } from '@/test/renderHookWithLists';

import { useCreateList, useJoinList, useMyLists } from './useMyLists';

describe('useMyLists', () => {
  it('loads the lists the user owns or is a member of, with the role in each', async () => {
    const lists = new FakeShoppingLists();
    lists.seed({ name: 'Mía' });
    lists.seed({ name: 'De Joan', owner: JOAN, members: [lists.currentUser] });
    lists.seed({ name: 'Ajena', owner: JOAN });

    const { result } = renderHookWithLists(() => useMyLists(), lists);

    await waitFor(() => {
      expect(result.current.status).toBe('success');
    });
    expect(result.current).toMatchObject({
      lists: [
        { name: 'Mía', role: 'owner' },
        { name: 'De Joan', role: 'member', ownerUsername: 'joan' },
      ],
    });
  });
});

describe('useCreateList', () => {
  it('creates a list with the trimmed name, reports its id and refreshes "My lists"', async () => {
    const lists = new FakeShoppingLists();
    const onCreated = vi.fn();
    const { result } = renderHookWithLists(
      () => ({ create: useCreateList(), mine: useMyLists() }),
      lists,
    );
    await waitFor(() => {
      expect(result.current.mine.status).toBe('success');
    });

    act(() => {
      result.current.create.create('  Cena  ', onCreated);
    });

    await waitFor(() => {
      expect(onCreated).toHaveBeenCalledWith('list-1');
    });
    expect(lists.lists.get('list-1')?.name).toBe('Cena');
    await waitFor(() => {
      expect(result.current.mine).toMatchObject({ lists: [{ name: 'Cena' }] });
    });
  });

  it('says the name is invalid without calling the API (PD-45)', async () => {
    const lists = new FakeShoppingLists();
    const createList = vi.spyOn(lists, 'createList');
    const { result } = renderHookWithLists(() => useCreateList(), lists);

    act(() => {
      result.current.create('   ', vi.fn());
    });

    await waitFor(() => {
      expect(result.current.error).toBe('invalid');
    });
    expect(createList).not.toHaveBeenCalled();
  });

  it('says the creation failed when the API fails', async () => {
    const lists = new FakeShoppingLists();
    lists.changeError = new Error('offline');
    const { result } = renderHookWithLists(() => useCreateList(), lists);

    act(() => {
      result.current.create('Cena', vi.fn());
    });

    await waitFor(() => {
      expect(result.current.error).toBe('failed');
    });
  });
});

describe('useJoinList', () => {
  it('joins the list of the token and gives its id', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({ owner: JOAN });

    const { result } = renderHookWithLists(() => useJoinList(stored.shareToken), lists);

    expect(result.current.status).toBe('joining');
    await waitFor(() => {
      expect(result.current).toEqual({ status: 'joined', listId: stored.id });
    });
    expect(stored.members).toEqual([lists.currentUser]);
  });

  it('is invalid for an unknown or revoked token', async () => {
    const lists = new FakeShoppingLists();

    const { result } = renderHookWithLists(() => useJoinList('revoked'), lists);

    await waitFor(() => {
      expect(result.current.status).toBe('invalid');
    });
  });

  it('offers a retry when joining fails for another reason', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed({ owner: JOAN });
    lists.changeError = new Error('offline');
    const { result } = renderHookWithLists(() => useJoinList(stored.shareToken), lists);
    await waitFor(() => {
      expect(result.current.status).toBe('error');
    });

    lists.changeError = null;
    act(() => {
      if (result.current.status === 'error') {
        result.current.retry();
      }
    });

    await waitFor(() => {
      expect(result.current).toEqual({ status: 'joined', listId: stored.id });
    });
  });
});
