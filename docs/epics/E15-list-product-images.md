# E15 — Product images in lists

**Goal:** the owner and the members of a shopping list see each product's catalog image in the list's rows and in the suggestions of its add-item field, so they can tell products apart without relying on the name alone. A broken image falls back to the placeholder everywhere a product image is shown. Requested in [issue #67](https://github.com/joeljoanlopez/jolo-market/issues/67).

Related decisions: PD-8, PD-22, PD-48, PD-97, PD-98, PD-99. Builds on [E12 — Product search in lists](E12-product-search-in-lists.md).

## Acceptance criteria

- On a list page, each product item shows its catalog image at 48 px between the checkbox and the text (PD-97).
- A product item without an image, and a product the catalog no longer knows, shows the "Sin imagen" placeholder at the same size (PD-97).
- A free-text item shows no image or placeholder, and its text lines up with the product names (PD-97).
- The image of a checked item is faded; an unavailable product keeps its image unfaded, next to "No disponible" (PD-22, PD-97).
- Tapping the image of a known product opens its product page. The image link is not a separate tab stop and is not announced twice. A product the catalog no longer knows has no image link (PD-48, PD-97).
- In the add-item suggestions, each product shows its image (or the placeholder) at 48 px, and tapping the image picks the product like the rest of the option. It is not a link (PD-97).
- When an image URL fails to load, the "Sin imagen" placeholder is shown instead, in list rows, suggestions, search results and the product page (PD-98).
- The backend and the API do not change: `GET /api/lists/{id}` and `GET /api/products` already return `imageUrl`.
- The dummy catalog stays without images. Playwright checks the placeholder in the list journey (PD-99).
- No new Spanish copy: the existing `catalog.product.noImage` and `catalog.product.imageAlt` are reused (PD-8).

## Tasks

The backend does not change: `ListJson` and `ProductJson` already return `imageUrl`, and the frontend already maps it (`ListProduct.imageUrl`, `Product.imageUrl`).

### Frontend (presentational)

- [x] E15.1 Add a `ProductImage` atom in `features/catalog/ui/components`: given an image URL (or `null`) and the product name, it renders the image with `catalog.product.imageAlt` and `loading="lazy"`, or a `role="img"` placeholder labelled `catalog.product.noImage` when the URL is `null` **or the image fails to load** (PD-98). Add `cva` variants: `size` (`sm` 48 px `size-12`, `md` 64 px `size-16`, `lg` 96 px `size-24`) and `faded` (lower opacity, PD-97). Theme tokens only. Component tests cover an image, a `null` URL, a load error and the faded variant.
- [ ] E15.2 Use `ProductImage` in `ProductCard` (`md`) and `ProductDetailView` (`lg`) instead of their own image/placeholder markup, so search results and the product page fall back on a broken image (PD-98). The product page keeps eager loading. Tests: a broken image shows the placeholder in each.
- [ ] E15.3 Show the image in `ListItemRow` for product items: `ProductImage` `sm` between the checkbox and the text, faded when the item is checked and not faded for an unavailable product (PD-22, PD-97). For a known product (`name` not `null`) it is wrapped in a link to `productPath(id)`, kept out of the tab order and hidden from assistive technology, because the name link already serves them (PD-48). A product the catalog no longer knows shows the placeholder without a link. A free-text item gets no image, and its text is indented by the image width plus the gap, so it lines up with product names (PD-97). Component tests cover each case.
- [ ] E15.4 Show `ProductImage` `sm` at the start of each product option in `ProductSuggestions` (`ProductOption`), not wrapped in a link, so tapping it picks the product. The text option has no image (PD-97). Component tests: a product option shows its image or the placeholder, and clicking the image calls `onPickProduct`.

### End to end

- [ ] E15.5 Extend `frontend/e2e/lists.spec.ts`: a user types in "Qué necesitas", sees the "Sin imagen" placeholder on the suggested products (the dummy catalog has no images, PD-99), adds one, sees the placeholder in its row and opens the product page by clicking it, then goes back to the list and checks that a free-text item shows no image.

### Docs

- [ ] E15.6 Update `docs/architecture.md` → Frontend structure (`ProductImage` in the catalog components, its use in `ProductCard`, `ProductDetailView`, `ListItemRow` and `ProductSuggestions`) and the README ("What it does": product images in lists and in the add-item suggestions).
