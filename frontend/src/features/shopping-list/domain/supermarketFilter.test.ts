import { describe, expect, it } from 'vitest';

import type { ListItem, ProductItem, ShoppingList, TextItem } from './shoppingList';
import {
  hiddenWithoutSupermarket,
  isSupermarketChip,
  itemsOfSupermarket,
  supermarketChips,
} from './supermarketFilter';

function aText(id: string, position: number): TextItem {
  return { id, position, quantity: 1, checked: false, type: 'text', text: id };
}

function aProduct(
  id: string,
  position: number,
  supermarketId: string | null,
  overrides: Partial<ProductItem> = {},
): ProductItem {
  return {
    id,
    position,
    quantity: 1,
    checked: false,
    type: 'product',
    product: {
      id: `product-${id}`,
      name: id,
      brand: null,
      supermarketId,
      priceCents: 100,
      imageUrl: null,
      available: true,
    },
    ...overrides,
  };
}

function aList(items: ListItem[], totals: ShoppingList['totals'] = []): ShoppingList {
  return {
    id: 'list-1',
    name: 'Compra',
    role: 'owner',
    version: 1,
    shareToken: 'token',
    owner: { id: 'u', username: 'maria' },
    members: [],
    items,
    totals,
  };
}

const bonpreuTotal = {
  supermarketId: 'bonpreu',
  supermarketName: 'Bonpreu',
  totalCents: 300,
  itemCount: 2,
};
const mercadonaTotal = {
  supermarketId: 'mercadona',
  supermarketName: 'Mercadona',
  totalCents: 125,
  itemCount: 1,
};

describe('supermarket filter', () => {
  it('offers one chip per supermarket of the totals, in their order (PD-84)', () => {
    const list = aList([], [bonpreuTotal, mercadonaTotal]);

    expect(supermarketChips(list)).toEqual([
      { supermarketId: 'bonpreu', supermarketName: 'Bonpreu', totalCents: 300 },
      { supermarketId: 'mercadona', supermarketName: 'Mercadona', totalCents: 125 },
    ]);
  });

  it('accepts only a supermarket that has a chip (PD-86)', () => {
    const list = aList([], [mercadonaTotal]);

    expect(isSupermarketChip(list, 'mercadona')).toBe(true);
    expect(isSupermarketChip(list, 'aldi')).toBe(false);
  });

  it('shows the products of one supermarket, available or not, in the list order (PD-85)', () => {
    const gone = aProduct('gone', 3, 'mercadona');
    gone.product.available = false;
    const items = [
      aProduct('milk', 0, 'mercadona', { checked: true }),
      aText('bread', 1),
      aProduct('oil', 2, 'bonpreu'),
      gone,
      aProduct('eggs', 4, 'mercadona'),
    ];

    expect(itemsOfSupermarket(items, 'mercadona').map((item) => item.id)).toEqual([
      'milk',
      'gone',
      'eggs',
    ]);
  });

  it('counts the free-text items and the products without a supermarket it hides (PD-85)', () => {
    const items = [
      aText('bread', 0),
      aProduct('removed', 1, null),
      aProduct('oil', 2, 'bonpreu'),
      aProduct('milk', 3, 'mercadona'),
    ];

    expect(hiddenWithoutSupermarket(items)).toBe(2);
  });
});
