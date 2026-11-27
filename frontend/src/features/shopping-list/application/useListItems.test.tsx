import { act, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { FakeShoppingLists, MILK } from '@/test/FakeShoppingLists';
import { renderHookWithLists } from '@/test/renderHookWithLists';

import { useAddProductToList, useListItems } from './useListItems';
import { useShoppingList } from './useShoppingList';

function seedWithBread(lists: FakeShoppingLists) {
  return lists.seed({
    items: [
      {
        id: 'bread',
        position: 0,
        quantity: 1,
        checked: false,
        type: 'text',
        text: 'pan',
        section: 'other',
        sectionOverride: null,
      },
    ],
  });
}

function renderListWithItems(lists: FakeShoppingLists, listId: string) {
  return renderHookWithLists(
    () => ({ list: useShoppingList(listId), items: useListItems(listId) }),
    lists,
  );
}

async function loaded(result: { current: { list: { status: string } } }) {
  await waitFor(() => {
    expect(result.current.list.status).toBe('success');
  });
}

function itemsOf(state: ReturnType<typeof useShoppingList>) {
  return state.status === 'success' ? state.list.items : [];
}

describe('useListItems', () => {
  it('adds a free-text item with its quantity and shows it', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed();
    const { result } = renderListWithItems(lists, stored.id);
    await loaded(result);
    const onAdded = vi.fn();

    act(() => {
      result.current.items.add({ type: 'text', text: 'huevos', quantity: 12 }, onAdded);
    });

    await waitFor(() => {
      expect(itemsOf(result.current.list)).toMatchObject([{ text: 'huevos', quantity: 12 }]);
    });
    expect(onAdded).toHaveBeenCalledOnce();
  });

  it('says an invalid item is invalid without calling the API (PD-45)', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed();
    const addItem = vi.spyOn(lists, 'addItem');
    const { result } = renderListWithItems(lists, stored.id);
    await loaded(result);

    act(() => {
      result.current.items.add({ type: 'text', text: '  ', quantity: 100 }, vi.fn());
    });

    await waitFor(() => {
      expect(result.current.items.addError).toBe('invalid');
    });
    expect(addItem).not.toHaveBeenCalled();
  });

  it('shows a check at once, before the API answers (optimistic)', async () => {
    const lists = new FakeShoppingLists();
    const stored = seedWithBread(lists);
    let answer: () => void = () => undefined;
    const updateItem = vi.spyOn(lists, 'updateItem').mockImplementation(
      () =>
        new Promise<void>((resolve) => {
          answer = resolve;
        }),
    );
    const { result } = renderListWithItems(lists, stored.id);
    await loaded(result);

    act(() => {
      result.current.items.setChecked('bread', true);
    });

    await waitFor(() => {
      expect(itemsOf(result.current.list)[0]?.checked).toBe(true);
    });
    expect(updateItem).toHaveBeenCalledWith(stored.id, 'bread', { checked: true });
    answer();
  });

  it('undoes the check and reports it when the API fails', async () => {
    const lists = new FakeShoppingLists();
    const stored = seedWithBread(lists);
    const { result } = renderListWithItems(lists, stored.id);
    await loaded(result);
    lists.changeError = new Error('offline');

    act(() => {
      result.current.items.setChecked('bread', true);
    });

    await waitFor(() => {
      expect(result.current.items.failed).toBe(true);
    });
    expect(itemsOf(result.current.list)[0]?.checked).toBe(false);
  });

  it('changes the quantity of an item and removes an item', async () => {
    const lists = new FakeShoppingLists();
    const stored = seedWithBread(lists);
    const { result } = renderListWithItems(lists, stored.id);
    await loaded(result);

    act(() => {
      result.current.items.changeQuantity('bread', 3);
    });
    await waitFor(() => {
      expect(itemsOf(result.current.list)[0]?.quantity).toBe(3);
    });

    act(() => {
      result.current.items.remove('bread');
    });
    await waitFor(() => {
      expect(itemsOf(result.current.list)).toEqual([]);
    });
  });

  it('chooses the section of an item and removes the choice (PD-92)', async () => {
    const lists = new FakeShoppingLists();
    const stored = seedWithBread(lists);
    const { result } = renderListWithItems(lists, stored.id);
    await loaded(result);

    act(() => {
      result.current.items.changeSection('bread', 'bakery');
    });
    await waitFor(() => {
      expect(itemsOf(result.current.list)[0]).toMatchObject({
        section: 'bakery',
        sectionOverride: 'bakery',
      });
    });

    act(() => {
      result.current.items.changeSection('bread', null);
    });
    await waitFor(() => {
      expect(itemsOf(result.current.list)[0]).toMatchObject({
        section: 'other',
        sectionOverride: null,
      });
    });
  });
});

describe('useAddProductToList', () => {
  it('adds a product to the chosen list and says which one', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed();
    const { result } = renderHookWithLists(() => useAddProductToList(MILK.id), lists);

    act(() => {
      result.current.add(stored.id);
    });

    await waitFor(() => {
      expect(result.current.addedTo).toBe(stored.id);
    });
    expect(stored.items).toMatchObject([
      { type: 'product', product: { id: MILK.id }, quantity: 1 },
    ]);
  });

  it('reports a failure', async () => {
    const lists = new FakeShoppingLists();
    const stored = lists.seed();
    lists.changeError = new Error('offline');
    const { result } = renderHookWithLists(() => useAddProductToList(MILK.id), lists);

    act(() => {
      result.current.add(stored.id);
    });

    await waitFor(() => {
      expect(result.current.failed).toBe(true);
    });
    expect(result.current.addedTo).toBeNull();
  });
});
