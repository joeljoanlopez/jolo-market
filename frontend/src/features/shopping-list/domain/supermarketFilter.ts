/** Docs: docs/architecture.md#product-decisions (PD-83 to PD-86). */
import type { ListItem, ProductItem, ShoppingList } from './shoppingList';

/** A supermarket the list can be filtered by, with its estimated total (PD-84). */
export interface SupermarketChip {
  supermarketId: string;
  supermarketName: string;
  totalCents: number;
}

/**
 * One chip per supermarket with at least one available product, in the order of the totals
 * (PD-84). "Todos" is not a chip of its own: it is the view without a supermarket.
 */
export function supermarketChips(list: ShoppingList): SupermarketChip[] {
  return list.totals.map(({ supermarketId, supermarketName, totalCents }) => ({
    supermarketId,
    supermarketName,
    totalCents,
  }));
}

/** Whether `supermarketId` names a chip; any other value falls back to "Todos" (PD-86). */
export function isSupermarketChip(list: ShoppingList, supermarketId: string): boolean {
  return list.totals.some((total) => total.supermarketId === supermarketId);
}

/** The products of one supermarket, available or not, keeping the order of `items` (PD-85). */
export function itemsOfSupermarket(
  items: readonly ListItem[],
  supermarketId: string,
): ProductItem[] {
  return items.filter(
    (item): item is ProductItem =>
      item.type === 'product' && item.product.supermarketId === supermarketId,
  );
}

/** Free-text items and products removed from the catalog: a filtered view hides them (PD-85). */
export function hiddenWithoutSupermarket(items: readonly ListItem[]): number {
  return items.filter((item) => item.type === 'text' || item.product.supermarketId === null).length;
}
