# E14 — Group a list by section

**Goal:** the owner and the members of a shopping list see its pending items grouped by store section (Fruta y verdura, Lácteos y huevos, Despensa…), in a fixed order that follows a typical walk through the store, so they can shop section by section. A product's section comes from its catalog category, and any member can change the section of any item. Requested in [issue #65](https://github.com/joeljoanlopez/jolo-market/issues/65).

Related decisions: PD-3, PD-7, PD-8, PD-22, PD-43, PD-45, PD-58, PD-60, PD-61, PD-85, PD-90, PD-91, PD-92, PD-93, PD-94, PD-95, PD-96, PD-115, PD-116, PD-117.

## Acceptance criteria

- On a list page, unchecked items are shown under a header per section, in the fixed order of PD-90 ("Otros" last), and inside each section in their list order. A section without unchecked items is not shown. Headers show only the section name (PD-93).
- Checked items are shown in one block at the end, in their list order, not grouped (PD-93).
- A product item is in the section its catalog category maps to (longest matching prefix). A product with no category or an unmapped one, and a product removed from the catalog, is in "Otros" (PD-91).
- A free-text item is in "Otros" until someone changes it (PD-92).
- On a pending item, any member can choose another section with a selector; the item moves to that section, and other members see the change through polling (PD-7, PD-43, PD-92).
- On a product item, choosing "Automática (<catalog section>)" removes the choice, and the item goes back to its catalog section (PD-92).
- Adding a product that is already in the list adds the quantity and keeps the section chosen by hand (PD-94).
- Existing items need no migration: free-text items show in "Otros" and product items in their catalog section (PD-94).
- With a supermarket filter chosen (E13), the filtered items are grouped the same way (PD-93).
- Extending the mapping moves items already in lists to their new section, without touching stored data (PD-91).
- `GET /api/lists/{id}` returns `section` and `sectionOverride` on each item; `PATCH /api/lists/{id}/items/{itemId}` accepts `section` (a section id or `null`), and an unknown section id is a `422` (PD-96).
- `app:catalog:unmapped-categories` (and `make unmapped-categories`) lists, per supermarket, the stored categories that map to "Otros" with their product count, most products first, and changes nothing (PD-95).
- All new copy (section names, the selector, "Automática (…)") is Spanish in `es.json` (PD-8).

## Tasks

### Domain (Catalog)

- [x] E14.1 Add the `Section` enum in `Catalog\Domain` with the thirteen sections of PD-90, their English ids and their display order, and a `SectionMapping` domain service that turns a supermarket id and a `CategoryPath` into a `Section` by the longest matching prefix, `other` for a missing or unknown path (PD-91). Start with the mapping of the dummy catalog categories (`DummyCatalog`), so every dummy product gets a section other than `other` ("Lácteos y huevos > Quesos" and "Carne y pescado > Embutidos" go to `deli-and-cheese`, PD-115); unit tests cover the longest-prefix rule, a missing path and an unknown path.

### Application (Catalog → ShoppingList)

- [ ] E14.2 Expose the section of each product through the Catalog query API that `ShoppingList\Application\ProductCatalog` wraps, and add `section` to `ShoppingList\Application\CatalogProduct`. The section is worked out when the product is read, not stored (PD-91). The ShoppingList side keeps its own section id type and does not import Catalog's `Section` (cross-context rule); Deptrac must pass.

### Domain and persistence (ShoppingList)

- [ ] E14.3 Add an optional section choice to list items (`FreeTextItem` and `ProductItem`): `ShoppingList::changeItemSection(itemId, ?section)` sets or removes it (removing it on a free-text item leaves it in "Otros"), an unknown item is `ListItemNotFound`, and adding an already listed product keeps the choice (PD-92, PD-94). Add a nullable `section` column to `list_item` with a migration (existing rows stay `NULL`, PD-94), and map it in the DBAL repository.
- [ ] E14.4 Accept `section` in `UpdateItemCommand`/`UpdateItemHandler`, validated against the section ids (unknown → a validation exception mapped to `422`), saved with the retry of PD-43. A checked item accepts it too (PD-117).

### API

- [ ] E14.5 Return `section` (the choice, else the catalog section, else `other`) and `sectionOverride` on each item of `GET /api/lists/{id}` (`ListJson`), and accept `section` (id or `null`) in `PATCH /api/lists/{id}/items/{itemId}` (`ListItemsController`): `204`, unknown section id `422`, unknown item `404` (PD-96). Functional tests cover a free-text item, a mapped product, an unmapped product, a removed product, setting and removing a choice, and the `version` change that polling picks up.

### Console

- [ ] E14.6 Add `app:catalog:unmapped-categories` in `Catalog\Infrastructure`: it lists, per supermarket, every stored category path that `SectionMapping` sends to `other`, with its number of products, most products first, and prints a message when there is none (PD-95). Integration test against a seeded catalog. Add the `make unmapped-categories` target (with `dev-stack-only`, PD-60) and its bats test.

### Mapping of the real chains

- [ ] E14.7 Extend `SectionMapping` for Mercadona, Bonpreu i Esclat and Aldi. Input: the output of `app:catalog:unmapped-categories` on the product owner's real catalog, which the product owner provides (tests never call the real stores). Map by the most specific prefix where a top category mixes sections (Bonpreu "Frescos"). Bonpreu promotion paths ("Ofertes > …") stay in "Otros" (PD-92 lets members fix those items). Unit tests cover a sample of each chain, including the recorded fixture categories in `backend/tests/Fixtures/`. The product owner reviews the mapping in the pull request.
  - Note: this container cannot reach the product owner's instance, so the rest of the epic is built first; the product owner then runs `make unmapped-categories` on their real catalog and pastes the output in the pull request, and the mapping is written in the same pull request. Until then this task stays unticked (confirmed by the product owner on 2026-11-27).

### Frontend (domain and infrastructure)

- [ ] E14.8 Add `section` and `sectionOverride` to the list item type in `features/shopping-list/domain` and map them in `shoppingListApi` (PD-96). Add an `updateItemSection(listId, itemId, section | null)` call to the `ShoppingLists` port, `shoppingListApi` and `FakeShoppingLists`.
- [ ] E14.9 Add pure functions in `features/shopping-list/domain` that group the unchecked items of a list (or of the E13 filtered items) by section, in the order of PD-90, keeping list order inside each section and leaving out empty sections, and return the checked items apart in list order (PD-93).

### Frontend (presentational)

- [ ] E14.10 Add a `SectionHeader` component and a section selector on `ListItemRow` for pending items (a compact native select under the quantity line, labelled with the item's name, PD-116): the thirteen sections by their Spanish names, and for a product item also "Automática (<catalog section>)" (PD-92). It only gets props and emits `onSectionChange(section | null)`. Theme tokens only, `cva` variants if needed.

### Frontend (container)

- [ ] E14.11 Wire it into `ListPageContainer` with `useListItems`: render the grouped unchecked items with their headers, then the checked block (PD-93), also when a supermarket is chosen (E13). Changing the selector calls `updateItemSection`, and the item moves to its new section. Container tests with `FakeShoppingLists` cover the grouping, a free-text item in "Otros", moving an item, going back to "Automática", checking the last item of a section (the section disappears) and a section change by another member arriving through polling.
- [ ] E14.12 Add the Spanish copy to `es.json`: the thirteen section names, the selector label and "Automática ({{section}})" (PD-8).

### End to end

- [ ] E14.13 Extend `frontend/e2e/lists.spec.ts`: a user adds a dairy product and a free-text item to a list, sees each under its section header in the PD-90 order ("Otros" last), moves the free-text item to another section and sees it there after a reload, and checks an item and sees it in the checked block at the end.

### Docs

- [ ] E14.14 Update `docs/architecture.md` (`Section` and `SectionMapping` in the Catalog domain model, `section` and `sectionOverride` in `GET /api/lists/{id}`, `section` in the `PATCH` row, the `list_item.section` column, the new console command, the grouping functions and components in Frontend structure) and the README ("What it does", the shopping list API, the new command and the `make unmapped-categories` row in "Make targets").
