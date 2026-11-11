import type {
  CallOptions,
  ItemChanges,
  ListSnapshot,
  ShoppingLists,
} from '@/features/shopping-list/application/ShoppingLists';
import type {
  ListItem,
  ListMember,
  ListProduct,
  ListSummary,
  NewItem,
  ShoppingList,
} from '@/features/shopping-list/domain/shoppingList';
import { ConflictError, NotFoundError, ValidationError } from '@/shared/http/errors';

export const MARIA: ListMember = { id: 'user-maria', username: 'maria' };
export const JOAN: ListMember = { id: 'user-joan', username: 'joan' };

export const MILK: ListProduct = {
  id: 'product-milk',
  name: 'Leche entera',
  brand: 'Hacendado',
  supermarketId: 'mercadona',
  priceCents: 125,
  imageUrl: null,
  available: true,
};

/** A stored list, as the API keeps it: the role and the token are worked out per reader. */
export interface StoredList {
  id: string;
  name: string;
  version: number;
  shareToken: string;
  owner: ListMember;
  members: ListMember[];
  items: ListItem[];
  updatedAt: Date;
}

const supermarketNames: Record<string, string> = { mercadona: 'Mercadona', bonpreu: 'Bonpreu' };

/**
 * In-memory lists that follow the API rules the SPA relies on: only owner and members see a
 * list (404 otherwise), owner-only actions answer 404 to members, the owner cannot leave (409),
 * every change bumps `version`, and the ETag is the version.
 */
export class FakeShoppingLists implements ShoppingLists {
  /** The user the calls are made as. */
  currentUser: ListMember = MARIA;
  readonly lists = new Map<string, StoredList>();
  products = new Map<string, ListProduct>([[MILK.id, MILK]]);
  /** Every `getList` call, with the ETag it sent. */
  readonly reads: { listId: string; etag: string | null }[] = [];
  /** When set, every change rejects with it (reads still work). */
  changeError: Error | null = null;
  private issued = 0;

  /** Adds a list owned by `owner` (MARIA by default) and returns it. */
  seed(overrides: Partial<StoredList> = {}): StoredList {
    this.issued++;
    const list: StoredList = {
      id: `list-${String(this.issued)}`,
      name: 'Compra semanal',
      version: 1,
      shareToken: `token-${String(this.issued)}`,
      owner: MARIA,
      members: [],
      items: [],
      updatedAt: new Date('2026-11-11T10:00:00Z'),
      ...overrides,
    };
    this.lists.set(list.id, list);
    return list;
  }

  /** Adds an item to a stored list as another user would (bumps the version). */
  addItemAsSomeoneElse(listId: string, item: ListItem): void {
    const list = this.require(listId);
    list.items.push({ ...item, position: list.items.length });
    list.version++;
  }

  listMyLists(): Promise<ListSummary[]> {
    const mine = [...this.lists.values()]
      .filter((list) => this.canAccess(list))
      .sort((a, b) => b.updatedAt.getTime() - a.updatedAt.getTime())
      .map((list) => ({
        id: list.id,
        name: list.name,
        role: this.isOwner(list) ? ('owner' as const) : ('member' as const),
        ownerUsername: list.owner.username,
        itemCount: list.items.length,
        updatedAt: list.updatedAt,
      }));
    return Promise.resolve(mine);
  }

  createList(name: string): Promise<string> {
    return this.change(() => {
      if (name.trim() === '' || name.trim().length > 80) {
        throw invalid();
      }
      return this.seed({ name: name.trim(), owner: this.currentUser }).id;
    });
  }

  joinList(shareToken: string): Promise<string> {
    return this.change(() => {
      const list = [...this.lists.values()].find(
        (candidate) => candidate.shareToken === shareToken,
      );
      if (list === undefined) {
        throw notFound();
      }
      if (!this.canAccess(list)) {
        list.members.push(this.currentUser);
        list.version++;
      }
      return list.id;
    });
  }

  getList(
    listId: string,
    options: CallOptions & { etag?: string | null } = {},
  ): Promise<ListSnapshot> {
    const etag = options.etag ?? null;
    this.reads.push({ listId, etag });
    const list = this.lists.get(listId);
    if (list === undefined || !this.canAccess(list)) {
      return Promise.reject(notFound());
    }
    const current = `"${String(list.version)}"`;
    if (etag === current) {
      return Promise.resolve({ status: 'notModified' });
    }
    return Promise.resolve({ status: 'changed', list: this.view(list), etag: current });
  }

  renameList(listId: string, name: string): Promise<void> {
    return this.ownerChange(listId, (list) => {
      list.name = name;
    });
  }

  deleteList(listId: string): Promise<void> {
    return this.ownerChange(listId, (list) => {
      this.lists.delete(list.id);
    });
  }

  leaveList(listId: string): Promise<void> {
    return this.memberChange(listId, (list) => {
      if (this.isOwner(list)) {
        throw new ConflictError({ type: 'about:blank', title: 'Conflict', status: 409 });
      }
      list.members = list.members.filter((member) => member.id !== this.currentUser.id);
    });
  }

  regenerateShareToken(listId: string): Promise<string> {
    return this.ownerChange(listId, (list) => {
      this.issued++;
      list.shareToken = `token-${String(this.issued)}`;
      return list.shareToken;
    });
  }

  removeMember(listId: string, userId: string): Promise<void> {
    return this.ownerChange(listId, (list) => {
      if (!list.members.some((member) => member.id === userId)) {
        throw notFound();
      }
      list.members = list.members.filter((member) => member.id !== userId);
    });
  }

  addItem(listId: string, item: NewItem): Promise<string> {
    return this.memberChange(listId, (list) => {
      this.issued++;
      const base = {
        id: `item-${String(this.issued)}`,
        position: list.items.length,
        quantity: item.quantity ?? 1,
        checked: false,
      };
      if (item.type === 'text') {
        if (item.text.trim() === '') {
          throw invalid();
        }
        list.items.push({ ...base, type: 'text', text: item.text.trim() });
        return base.id;
      }
      const product = this.products.get(item.productId);
      if (product === undefined || !product.available) {
        throw invalid();
      }
      list.items.push({ ...base, type: 'product', product });
      return base.id;
    });
  }

  updateItem(listId: string, itemId: string, changes: ItemChanges): Promise<void> {
    return this.memberChange(listId, (list) => {
      const item = list.items.find((candidate) => candidate.id === itemId);
      if (item === undefined) {
        throw notFound();
      }
      Object.assign(item, changes);
    });
  }

  removeItem(listId: string, itemId: string): Promise<void> {
    return this.memberChange(listId, (list) => {
      list.items = list.items.filter((item) => item.id !== itemId);
    });
  }

  clearCheckedItems(listId: string): Promise<void> {
    return this.memberChange(listId, (list) => {
      list.items = list.items.filter((item) => !item.checked);
    });
  }

  private view(list: StoredList): ShoppingList {
    const owner = this.isOwner(list);
    return {
      id: list.id,
      name: list.name,
      role: owner ? 'owner' : 'member',
      version: list.version,
      shareToken: owner ? list.shareToken : null,
      owner: list.owner,
      members: [...list.members],
      items: list.items.map((item) => ({ ...item })),
      totals: totalsOf(list.items),
    };
  }

  private isOwner(list: StoredList): boolean {
    return list.owner.id === this.currentUser.id;
  }

  private canAccess(list: StoredList): boolean {
    return this.isOwner(list) || list.members.some((member) => member.id === this.currentUser.id);
  }

  private require(listId: string): StoredList {
    const list = this.lists.get(listId);
    if (list === undefined) {
      throw new Error(`No list ${listId} in the fake.`);
    }
    return list;
  }

  private memberChange<T>(listId: string, change: (list: StoredList) => T): Promise<T> {
    return this.change(() => {
      const list = this.lists.get(listId);
      if (list === undefined || !this.canAccess(list)) {
        throw notFound();
      }
      const result = change(list);
      list.version++;
      return result;
    });
  }

  private ownerChange<T>(listId: string, change: (list: StoredList) => T): Promise<T> {
    return this.memberChange(listId, (list) => {
      if (!this.isOwner(list)) {
        throw notFound();
      }
      return change(list);
    });
  }

  private change<T>(change: () => T): Promise<T> {
    if (this.changeError !== null) {
      return Promise.reject(this.changeError);
    }
    try {
      return Promise.resolve(change());
    } catch (error) {
      return Promise.reject(error instanceof Error ? error : new Error(String(error)));
    }
  }
}

function totalsOf(items: ListItem[]): ShoppingList['totals'] {
  const totals = new Map<string, ShoppingList['totals'][number]>();
  for (const item of items) {
    if (item.type !== 'product' || !item.product.available) {
      continue;
    }
    const { supermarketId, priceCents } = item.product;
    if (supermarketId === null || priceCents === null) {
      continue;
    }
    const total = totals.get(supermarketId) ?? {
      supermarketId,
      supermarketName: supermarketNames[supermarketId] ?? supermarketId,
      totalCents: 0,
      itemCount: 0,
    };
    total.totalCents += priceCents * item.quantity;
    total.itemCount++;
    totals.set(supermarketId, total);
  }
  return [...totals.values()].sort((a, b) => a.supermarketName.localeCompare(b.supermarketName));
}

function notFound(): NotFoundError {
  return new NotFoundError({ type: 'about:blank', title: 'Not Found', status: 404 });
}

function invalid(): ValidationError {
  return new ValidationError({ type: 'about:blank', title: 'Unprocessable Content', status: 422 });
}
