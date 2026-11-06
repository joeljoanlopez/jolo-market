# E4 — Shared shopping lists

**Goal:** logged-in users create shopping lists and share them through a secret link. Opening the link adds the user as a member. Owner and members edit the list collaboratively. Items can be free text or concrete products, and the list shows estimated totals per supermarket.

Related decisions: PD-2, PD-4, PD-7. Open questions: OQ-3, OQ-4.

## Acceptance criteria

- A logged-in user creates a list and becomes its owner. "My lists" shows the lists they own or are a member of.
- Each list has an unguessable share token (≥128 bits of randomness, URL-safe). The link `/join/{token}` requires login. Opening it makes the user a member (idempotent) and redirects to the list.
- Owner and members can read and edit items. Only the owner can rename the list, remove members and regenerate the link. Regenerating invalidates the old link but keeps current members.
- A list the user cannot access, and an unknown or revoked token, return `404`.
- Items can be added as free text or as a product from search results. Each item has a quantity and can be checked or unchecked.
- The list shows the estimated total per supermarket (product items only, using current prices). Free-text items are shown but not priced.
- While the list is open, changes made by others appear within ~5 s (polling with ETag/304).

## Tasks

### Domain (ShoppingList)

- [ ] E4.1 Add the `ShareToken` value object (format validation) and a `ShareTokenGenerator` port.
- [ ] E4.2 Add the `ShoppingList` aggregate: `create(ownerId)`, `join(userId)`, `removeMember` (owner only), `regenerateShareToken` (owner only), `rename` (owner only), `canBeAccessedBy(userId)`. Each change increments `version`.
- [ ] E4.3 Add item behavior to the aggregate: `addFreeTextItem`, `addProductItem`, `changeQuantity`, `check`/`uncheck`, `removeItem`, `reorder`, `clearChecked` (owner and members).
- [ ] E4.4 Add the `ListItem` entity with its `FreeTextItem` / `ProductItem` content and the positive-quantity invariant.
- [ ] E4.5 Add the `ShoppingListRepository` port (find by id, find by share token, find by member/owner, save).
- [ ] E4.6 ❓ OQ-3 Agree on the concurrent edit policy (last-write-wins or an optimistic version check).
- [ ] E4.7 ❓ OQ-4 Agree on list deletion, leaving a list and ownership transfer.

### Application

- [ ] E4.8 Add use cases, each receiving an explicit `actorId`: `CreateList`, `JoinList`, `RenameList`, `RegenerateShareToken`, `RemoveMember`, `AddItem`, `UpdateItem`, `RemoveItem`, `ClearCheckedItems`. Unit tests use in-memory fakes and cover authorization (non-member → not found).
- [ ] E4.9 Add a `ProductCatalog` port (read product name, price and supermarket by id) and a `UserDirectory` port (member usernames), both owned by ShoppingList.
- [ ] E4.10 Add the `GetList` query: items, members and per-supermarket totals, including handling of products that are no longer available.
- [ ] E4.11 Add the `GetMyLists` query: lists owned or joined by the actor, with the role in each.

### Infrastructure

- [ ] E4.12 Add migrations for the `shopping_list`, `list_member` and `list_item` tables.
- [ ] E4.13 Add the DBAL `ShoppingListRepository` (integration tests: save and reload the full aggregate with members and items).
- [ ] E4.14 Add the `ProductCatalog` and `UserDirectory` adapters on top of the Catalog and Identity query services (Deptrac allows only these paths).
- [ ] E4.15 Add a secure random `ShareTokenGenerator` (uses `random_bytes` and is URL-safe).
- [ ] E4.16 Add the list endpoints (see the architecture doc API table), with functional tests for each one: owner, member, outsider (→ 404) and revoked token (→ 404).
- [ ] E4.17 Add ETag support on `GET /api/lists/{id}` based on `version`, returning `304` when it has not changed.

### Frontend

- [ ] E4.18 Add shopping-list domain types and pure functions (totals display, item sorting: unchecked first, `isOwner`).
- [ ] E4.19 Add the list API client (MSW tests).
- [ ] E4.20 Add a `useShoppingList(id)` hook with a 5 s `refetchInterval` while visible, plus optimistic updates for check/uncheck.
- [ ] E4.21 Add a home page showing "My lists" (owned and shared with me) and a create-list action.
- [ ] E4.22 Add the list page container and presentational components: `ListItemRow`, `AddItemForm` (free text), `SupermarketTotals`, `ShareLinkButton` (copies `/join/{token}`, owner only).
- [ ] E4.23 Add a members panel (owner): see members, remove a member, regenerate the link with a confirmation.
- [ ] E4.24 Add a `/join/:token` page: join, then redirect to the list. It has an invalid/revoked link state. It goes through login first when there is no session.
- [ ] E4.25 Add an "Add to list" action on search results (product item), with a picker drawn from "My lists".
- [ ] E4.26 Add a Playwright journey: user A creates a list → adds items → copies the link → user B (another browser context, logged in with 2FA) opens it and joins → both see each other's changes → A regenerates the link → the old link returns not found.
