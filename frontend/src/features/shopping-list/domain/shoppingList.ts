/** Docs: docs/architecture.md#http-api-draft (`/api/lists`) and PD-2, PD-4, PD-22, PD-44, PD-45. */
export type ListRole = 'owner' | 'member';

/** A row of "My lists". */
export interface ListSummary {
  id: string;
  name: string;
  role: ListRole;
  ownerUsername: string;
  itemCount: number;
  updatedAt: Date;
}

export interface ListMember {
  id: string;
  username: string;
}

/**
 * The product of a product item. A product the catalog no longer knows comes back
 * unavailable with `name: null`; its other details may be missing as well.
 */
export interface ListProduct {
  id: string;
  name: string | null;
  brand: string | null;
  supermarketId: string | null;
  priceCents: number | null;
  imageUrl: string | null;
  available: boolean;
}

interface ItemBase {
  id: string;
  /** 0-based order in the list; new items go to the end (PD-45). */
  position: number;
  quantity: number;
  checked: boolean;
}

export type TextItem = ItemBase & { type: 'text'; text: string };
export type ProductItem = ItemBase & { type: 'product'; product: ListProduct };
export type ListItem = TextItem | ProductItem;

/** Estimated cost of the available product items of one supermarket (PD-45). */
export interface SupermarketTotal {
  supermarketId: string;
  supermarketName: string;
  totalCents: number;
  itemCount: number;
}

export interface ShoppingList {
  id: string;
  name: string;
  /** The role of the current user. */
  role: ListRole;
  version: number;
  /** Only the owner gets it; `null` for members. */
  shareToken: string | null;
  owner: ListMember;
  members: ListMember[];
  /** In position order, as the API sends them. */
  items: ListItem[];
  /** One per supermarket with at least one available product item, by supermarket name. */
  totals: SupermarketTotal[];
}

export type NewItem =
  | { type: 'text'; text: string; quantity?: number }
  | { type: 'product'; productId: string; quantity?: number };

export const MAX_LIST_NAME_LENGTH = 80;
export const MAX_ITEM_TEXT_LENGTH = 120;
export const MIN_QUANTITY = 1;
export const MAX_QUANTITY = 99;

export function isOwner(list: ShoppingList): boolean {
  return list.role === 'owner';
}

/** Unchecked items first, then checked ones, each group in position order (PD-45). */
export function itemsForDisplay(items: readonly ListItem[]): ListItem[] {
  return [...items].sort(
    (a, b) => Number(a.checked) - Number(b.checked) || a.position - b.position,
  );
}

/** The list with one item checked or unchecked, e.g. to show the change before the API answers. */
export function withItemChecked(
  list: ShoppingList,
  itemId: string,
  checked: boolean,
): ShoppingList {
  return {
    ...list,
    items: list.items.map((item) => (item.id === itemId ? { ...item, checked } : item)),
  };
}

/** Product items shown flagged and left out of the totals because they are unavailable (PD-22). */
export function unpricedItems(items: readonly ListItem[]): ProductItem[] {
  return items.filter(
    (item): item is ProductItem => item.type === 'product' && !item.product.available,
  );
}

/** The link the owner shares; the SPA route that joins the list. */
export function joinPath(shareToken: string): string {
  return `/join/${shareToken}`;
}

export function isValidListName(name: string): boolean {
  const length = Array.from(name.trim()).length;
  return length >= 1 && length <= MAX_LIST_NAME_LENGTH;
}

export function isValidItemText(text: string): boolean {
  const length = Array.from(text.trim()).length;
  return length >= 1 && length <= MAX_ITEM_TEXT_LENGTH;
}

export function isValidQuantity(quantity: number): boolean {
  return Number.isInteger(quantity) && quantity >= MIN_QUANTITY && quantity <= MAX_QUANTITY;
}
