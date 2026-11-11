import { describe, expect, it } from 'vitest';

import {
  isOwner,
  isValidItemText,
  isValidListName,
  isValidQuantity,
  itemsForDisplay,
  joinPath,
  unpricedItems,
  withItemChecked,
  type ListItem,
  type ShoppingList,
  type TextItem,
} from './shoppingList';

function aTextItem(overrides: Partial<TextItem> = {}): TextItem {
  return {
    id: 'item-1',
    position: 0,
    quantity: 1,
    checked: false,
    type: 'text',
    text: 'pan',
    ...overrides,
  };
}

function aProductItem(available: boolean, id = 'item-p'): ListItem {
  return {
    id,
    position: 0,
    quantity: 1,
    checked: false,
    type: 'product',
    product: {
      id: 'p1',
      name: 'Leche entera',
      brand: 'Hacendado',
      supermarketId: 'mercadona',
      priceCents: 125,
      imageUrl: null,
      available,
    },
  };
}

function aList(overrides: Partial<ShoppingList> = {}): ShoppingList {
  return {
    id: 'list-1',
    name: 'Compra semanal',
    role: 'owner',
    version: 1,
    shareToken: 'token',
    owner: { id: 'user-maria', username: 'maria' },
    members: [],
    items: [],
    totals: [],
    ...overrides,
  };
}

describe('shoppingList domain', () => {
  it('shows unchecked items first, then checked ones, each group in position order (PD-45)', () => {
    const items = [
      aTextItem({ id: 'a', position: 0, checked: true }),
      aTextItem({ id: 'b', position: 1 }),
      aTextItem({ id: 'c', position: 2, checked: true }),
      aTextItem({ id: 'd', position: 3 }),
    ];

    expect(itemsForDisplay(items).map((item) => item.id)).toEqual(['b', 'd', 'a', 'c']);
  });

  it('sorts by position even when the items arrive out of order', () => {
    const items = [aTextItem({ id: 'b', position: 1 }), aTextItem({ id: 'a', position: 0 })];

    expect(itemsForDisplay(items).map((item) => item.id)).toEqual(['a', 'b']);
  });

  it('knows whether the current user owns the list', () => {
    expect(isOwner(aList({ role: 'owner' }))).toBe(true);
    expect(isOwner(aList({ role: 'member', shareToken: null }))).toBe(false);
  });

  it('checks or unchecks one item and leaves the rest untouched', () => {
    const list = aList({ items: [aTextItem({ id: 'a' }), aTextItem({ id: 'b', position: 1 })] });

    const changed = withItemChecked(list, 'b', true);

    expect(changed.items.map((item) => item.checked)).toEqual([false, true]);
    expect(list.items[1]?.checked).toBe(false);
  });

  it('lists the product items left out of the totals because they are unavailable (PD-22)', () => {
    const unavailable = aProductItem(false, 'gone');
    const items = [aTextItem(), aProductItem(true), unavailable];

    expect(unpricedItems(items)).toEqual([unavailable]);
  });

  it('builds the join path of a share token', () => {
    expect(joinPath('kq3-_x')).toBe('/join/kq3-_x');
  });

  it('accepts a list name of 1 to 80 characters after trimming (PD-45)', () => {
    expect(isValidListName('  Semana  ')).toBe(true);
    expect(isValidListName('   ')).toBe(false);
    expect(isValidListName('a'.repeat(80))).toBe(true);
    expect(isValidListName('a'.repeat(81))).toBe(false);
  });

  it('accepts a free-text item of 1 to 120 characters after trimming (PD-45)', () => {
    expect(isValidItemText(' pan ')).toBe(true);
    expect(isValidItemText('')).toBe(false);
    expect(isValidItemText('a'.repeat(120))).toBe(true);
    expect(isValidItemText('a'.repeat(121))).toBe(false);
  });

  it('accepts a whole quantity from 1 to 99 (PD-45)', () => {
    expect(isValidQuantity(1)).toBe(true);
    expect(isValidQuantity(99)).toBe(true);
    expect(isValidQuantity(0)).toBe(false);
    expect(isValidQuantity(100)).toBe(false);
    expect(isValidQuantity(1.5)).toBe(false);
  });
});
