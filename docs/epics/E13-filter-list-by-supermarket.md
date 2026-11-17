# E13 — Filter a list by supermarket

**Goal:** the owner and the members of a shopping list choose a supermarket and see only the list's products of that chain, with its estimated total, so they know what to buy in each store and how much they will spend there. It is a view of whoever is looking: the list does not change and the other members do not see the filter. Requested in [issue #59](https://github.com/joeljoanlopez/jolo-market/issues/59).

Related decisions: PD-3, PD-7, PD-8, PD-22, PD-45, PD-80, PD-81, PD-83, PD-84, PD-85, PD-86, PD-87.

## Acceptance criteria

- On a list page, a row of chips above the items shows "Todos", selected by default, and one chip per supermarket with at least one available product in the list, in the order of the totals, each with its estimated total (PD-84).
- A supermarket whose products in the list are all unavailable gets no chip; its products show under "Todos" (PD-84).
- Choosing a supermarket chip shows only that chain's products, checked or not, in the order of PD-45, with its unavailable products flagged (PD-22, PD-85).
- With a supermarket chosen, free-text items and products removed from the catalog are hidden, and a notice says how many: "N elementos sin supermercado no se muestran". With none hidden there is no notice (PD-85).
- Changing a quantity, checking an item or another member's edit arriving through polling updates the filtered view and the chip totals (PD-7, PD-85).
- Switching between chips, or going back to "Todos", never changes the list: every item is still there under "Todos" (PD-83).
- The chosen supermarket is in the URL (`/lists/:listId?supermarket=<supermarketId>`): it survives a reload and works with back and forward (PD-86).
- A `supermarket` parameter without a chip (unknown, or its last available product removed, also by another member) shows "Todos" and drops the parameter (PD-86).
- The "Qué necesitas" field works as before with a supermarket chosen; an item of another chain or a free-text item is saved and shows under "Todos" (PD-87).
- The "Total estimado por supermercado" section stays as it is (PD-84).
- All new copy is Spanish in `es.json` (PD-8).

## Tasks

The backend does not change: the filter works on the items and totals of `GET /api/lists/{id}` (PD-83).

### Frontend (domain)

- [ ] E13.1 Add pure functions in `features/shopping-list/domain` for the supermarket filter: the chips to offer from a `ShoppingList` ("Todos", then one per entry of `totals`, PD-84), whether a `supermarket` value is valid (it names a chip, PD-86), the items to show for a supermarket (product items whose `product.supermarketId` matches, available or not, keeping the order of the list, PD-85) and the number of hidden items without a supermarket (free-text items and products with a `null` supermarket, PD-85).

### Frontend (presentational)

- [ ] E13.2 Add the `SupermarketFilter` component in `features/shopping-list/ui/components`: an accessible group of toggle chips ("Todos" and one per supermarket with its `SupermarketBadge` colors and `formatPrice` total), the selected one marked with `aria-pressed`. It only gets props and emits `onSelect(supermarketId | null)`. Chips use theme tokens only, with a `cva` variant for the selected state if needed.

### Frontend (container)

- [ ] E13.3 Wire the filter into `ListPageContainer`: read `supermarket` from the URL, show `SupermarketFilter` above the items and render only the items of E13.1 for the chosen supermarket, with the hidden-items notice (PD-85). Choosing a chip writes the parameter, "Todos" removes it (PD-86). When the parameter names no chip, on load or after a polling update, replace the URL without it (PD-86). The add form keeps its current behavior (PD-87). Container tests with `FakeShoppingLists` cover choosing a chip, the notice, going back to "Todos", reload with the parameter, an unknown parameter, the chosen supermarket losing its last available product through polling, and adding a free-text item while filtered.
- [ ] E13.4 Add the Spanish copy to `es.json`: the filter label, "Todos" and the hidden-items notice with plural forms ("{{count}} elemento sin supermercado no se muestra" / "{{count}} elementos sin supermercado no se muestran") (PD-8).

### End to end

- [ ] E13.5 Extend `frontend/e2e/lists.spec.ts`: a user opens a list with products of two supermarkets and a free-text item, sees the chips with their totals, chooses one supermarket and sees only its products and the notice, reloads and keeps the filter, changes a quantity and sees the chip total change, and goes back to "Todos" with every item still in the list.

### Docs

- [ ] E13.6 Update `docs/architecture.md` → Frontend structure (the filter functions, `SupermarketFilter` and the `supermarket` parameter of `/lists/:listId`) and the README (filtering a list by supermarket in "What it does").
