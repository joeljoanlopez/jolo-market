# E12 — Product search in lists

**Goal:** the owner and the members of a shopping list add supermarket products from inside the list. The "Qué necesitas" field searches the catalog while they type and suggests products, so they no longer have to go to `/search` and pick the list. Free-text items keep working as before.

Related decisions: PD-4, PD-8, PD-22, PD-33, PD-45, PD-80, PD-81, PD-82.

## Acceptance criteria

- On a list page, typing at least 2 characters (after trimming) in "Qué necesitas" shows, after the search debounce, a suggestion panel with "Añadir «…» como texto", the first 10 search results in the order of PD-33 (every supermarket) and a "Ver todos los resultados" link (PD-80).
- Each product suggestion shows its name, brand, supermarket, shelf price and unit price. Only available products are suggested (PD-22).
- With fewer than 2 characters there is no panel (PD-80).
- With no results the panel says so and still offers adding the text as a free-text item (PD-80).
- When the search fails the panel shows an error with "Reintentar", and adding as free text still works (PD-80).
- A click or tap on a product suggestion adds that product to the list with the quantity of the form (PD-81).
- The arrow keys highlight suggestions, and Enter adds the highlighted one. With nothing highlighted, Enter or "Añadir" adds the typed text as a free-text item, as before (PD-81).
- After adding, the field is empty, the quantity is 1, the panel is closed and the focus is in the field (PD-81).
- Adding a product already in the list and unchecked adds to its quantity (PD-45).
- "Ver todos los resultados" opens `/search?q=<text>&list=<listId>`. There, "Añadir a la lista" adds to that list without the picker, and a "Volver a <list name>" link goes back to the list. Typing, filtering and paging keep `list` (PD-82).
- With a `list` the user cannot access, or one that does not exist, `/search` works as without it (PD-82).
- The suggestion field is an accessible combobox (role `combobox`, a `listbox` of options, `aria-activedescendant` for the highlighted one, Escape closes the panel).
- All new copy is Spanish in `es.json` (PD-8).

## Tasks

The backend does not change: the list reuses `GET /api/products` and `POST /api/lists/{id}/items` with a `productId`.

### Frontend (domain)

- [ ] E12.1 Add pure functions in `features/shopping-list/domain` for the suggestion panel: the options to show from a search page (the text option first, then at most 10 products in the order the API returns, PD-80) and the keyboard highlight (ArrowDown / ArrowUp move and wrap, nothing highlighted at first, a new text clears it, PD-81).

### Frontend (presentational)

- [ ] E12.2 Add the `ProductSuggestions` component in `features/shopping-list/ui/components`: a `listbox` with the "Añadir «…» como texto" option, one option per product (name, brand, `SupermarketBadge`, shelf price and unit price, reusing the catalog `PriceTag`), and the "Ver todos los resultados" link, plus its loading, empty and error-with-retry states (PD-80). It only gets props and emits `onPickText`, `onPickProduct` and `onRetry`.
- [ ] E12.3 Turn `AddItemForm` into a combobox: the text field gets `role="combobox"`, `aria-expanded`, `aria-controls` and `aria-activedescendant`, and shows `ProductSuggestions` below it while the panel is open. ArrowDown / ArrowUp move the highlight, Enter adds the highlighted option or, with none, submits the free text, and Escape closes the panel (PD-81).

### Frontend (container)

- [ ] E12.4 Wire the search into `ListPageContainer`: use the catalog `useProductSearch` with the typed text (2 characters minimum and the same debounce), and on picking a product call the existing add-product mutation (`useListItems`) with the form quantity. On success, empty the field, set the quantity back to 1, close the panel and keep the focus in the field (PD-81). Errors adding use the existing "No se ha podido añadir" message. Tests with MSW cover adding a product by click, by keyboard, a free text with Enter, the empty and failed searches, and the quantity of an existing product (PD-45).
- [ ] E12.5 Support the `list` URL parameter in `SearchPageContainer`: read it, keep it when typing, filtering and paging, and when it names a list from "My lists" show "Volver a <list name>" (link to `/lists/:listId`) and make each result's "Añadir a la lista" add straight to that list without the `AddToListPicker` (extend `AddToListContainer` with an optional target list). An unknown or inaccessible list is ignored (PD-82). "Ver todos los resultados" in `ProductSuggestions` builds this URL.
- [ ] E12.6 Add the Spanish copy to `es.json`: the text option ("Añadir «…» como texto"), "Ver todos los resultados", the empty and error states of the panel, "Reintentar" (reuse the existing key if it fits) and "Volver a {{name}}" (PD-8).

### End to end

- [ ] E12.7 Extend `frontend/e2e/lists.spec.ts`: a user opens a list, types a product name, sees suggestions, adds one product with a click and another with the keyboard, adds a free-text item with Enter, and sees the products in the list and in the totals per supermarket. Then "Ver todos los resultados" opens `/search` with the list, "Añadir a la lista" adds without the picker, and "Volver a …" returns to the list with the new item.

### Docs

- [ ] E12.8 Update `docs/architecture.md` → Frontend structure (the shopping-list combobox and `ProductSuggestions`, and the `list` parameter of `/search`) and the README (adding products from inside a list in "What it does").
