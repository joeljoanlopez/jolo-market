# E17 — Totals count pending items only

**Goal:** the owner and the members of a shopping list see, per supermarket, how much they still have to spend: checking an item as bought lowers the totals and the chip totals instead of leaving them at the cost of the whole list. Requested in [issue #74](https://github.com/joeljoanlopez/jolo-market/issues/74).

Related decisions: PD-7, PD-22, PD-45, PD-84, PD-85, PD-120, PD-121, PD-122.

## Acceptance criteria

- Checking an item lowers the total of its supermarket, in the totals section and in its chip; unchecking it raises it again. Another member's check or uncheck arriving through polling does the same (PD-7, PD-120).
- The item count of a supermarket counts the same items as its total: available, unchecked products (PD-120).
- A supermarket whose available products are all checked keeps its chip and its row, with 0,00 € and "0 productos", and its checked items still show under its chip (PD-121, PD-84, PD-85).
- Unavailable products and free-text items are still left out of the totals (PD-22, PD-120).
- The totals section is titled "Pendiente de comprar por supermercado", in `es.json` (PD-122).

## Tasks

The frontend already shows what `GET /api/lists/{id}` returns in `totals`, so the rule changes in the backend and the API shape stays the same.

### Application (ShoppingList)

- [ ] E17.1 In `GetListHandler`, make the per-supermarket totals skip checked items: `totalCents` and `itemCount` add only available, unchecked product items (PD-120). Test with the in-memory fakes: checking an item lowers its supermarket's total and count, and unchecking brings them back; unavailable products and free-text items stay left out (PD-22).
- [ ] E17.2 A supermarket whose available products are all checked stays in `totals` with `totalCents` 0 and `itemCount` 0, still sorted by name (PD-121). Test it through `GetListHandler`.

### Frontend

- [ ] E17.3 Make the `FakeShoppingLists` totals follow the same rule (available, unchecked items only, a fully checked supermarket kept at 0), so container tests behave like the API (PD-120, PD-121).
- [ ] E17.4 Test in `ListPageContainer` that checking an item lowers the row total and the chip total, unchecking restores them, and a fully bought supermarket keeps its row and chip at 0,00 € with its checked items listed under the chip (PD-84, PD-85, PD-121).
- [ ] E17.5 Rename `lists.totals.title` in `es.json` to "Pendiente de comprar por supermercado" (PD-122) and update the docblock of `SupermarketTotals`, which still says it counts every available product (PD-45).

### End to end

- [ ] E17.6 Extend `frontend/e2e/lists.spec.ts`: a user adds a product to a list, checks it and sees its supermarket's total and chip drop to 0,00 €, then unchecks it and sees the total come back (PD-120, PD-121).

### Docs

- [ ] E17.7 Update `docs/architecture.md` (the **Totals** paragraph of the ShoppingList context and the `totals` description of `GET /api/lists/{id}`) and the README line about estimated totals to the pending-only rule (PD-120). Set E17 to `Done` in `docs/epics/README.md`.
