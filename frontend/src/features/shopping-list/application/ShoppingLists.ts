import type { ListSummary, NewItem, ShoppingList } from '../domain/shoppingList';

export interface CallOptions {
  signal?: AbortSignal;
}

/** The answer to a conditional read of a list (polling with ETag, PD-7). */
export type ListSnapshot =
  { status: 'notModified' } | { status: 'changed'; list: ShoppingList; etag: string | null };

export interface ItemChanges {
  quantity?: number;
  checked?: boolean;
  /** 0-based index to move the item to. */
  position?: number;
}

/**
 * Port to the shopping lists API, implemented in infrastructure/. A list the user cannot access,
 * an owner-only action by a member and an unknown or revoked share token reject with
 * `NotFoundError`; invalid input with `ValidationError`.
 */
export interface ShoppingLists {
  /** Lists the user owns or is a member of, most recently updated first. */
  listMyLists(options?: CallOptions): Promise<ListSummary[]>;
  /** Resolves with the id of the new list, owned by the current user. */
  createList(name: string): Promise<string>;
  /** Joins through a secret link (idempotent) and resolves with the list id. */
  joinList(shareToken: string): Promise<string>;
  /** Sends `etag` as `If-None-Match`, so an unchanged list costs a `304`. */
  getList(listId: string, options?: CallOptions & { etag?: string | null }): Promise<ListSnapshot>;
  renameList(listId: string, name: string): Promise<void>;
  /** Owner only: removes the list for everyone (PD-44). */
  deleteList(listId: string): Promise<void>;
  /** Member only: the owner gets a `ConflictError` (PD-44). */
  leaveList(listId: string): Promise<void>;
  /** Owner only: the old link stops working, members stay. Resolves with the new token. */
  regenerateShareToken(listId: string): Promise<string>;
  removeMember(listId: string, userId: string): Promise<void>;
  /** Resolves with the item id (an unchecked product already in the list gets the quantity added). */
  addItem(listId: string, item: NewItem): Promise<string>;
  updateItem(listId: string, itemId: string, changes: ItemChanges): Promise<void>;
  removeItem(listId: string, itemId: string): Promise<void>;
  clearCheckedItems(listId: string): Promise<void>;
}
