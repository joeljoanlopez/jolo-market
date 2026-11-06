# E2 — Search & comparison

**Goal:** a user types a product name and sees results from all supermarkets ranked by unit price, with the cheapest option obvious at a glance.

Related decisions: PD-3, PD-8. Open questions: OQ-6, OQ-7.

## Acceptance criteria

- Search is case- and accent-insensitive ("platano" finds "Plátano").
- Results come from all enabled supermarkets, ordered by unit price ascending. Products without a unit price go last.
- Each result shows the name, supermarket, package size, price, unit price and image.
- Results are paginated.
- The UI is in Spanish, with every string in `es.json`.
- From a result, the user can add the product to a list (wired in E4).

## Tasks

### Backend

- [ ] E2.1 ❓ OQ-7 Agree on the MVP filters (supermarket? category? brand?) and the tie-break rule.
- [ ] E2.2 Add the `SearchProductsQuery` and its handler, with a `ProductSearchResult` read-model DTO (unit tested with an in-memory read port).
- [ ] E2.3 Add a DBAL search read model using a FULLTEXT index on name and brand, with an accent-insensitive collation (integration tests with seeded Spanish products).
- [ ] E2.4 Add the `GET /api/products` endpoint: validates `q` (min length), pagination and an optional `supermarket` filter. Functional tests cover the happy path, empty query and unknown supermarket.
- [ ] E2.5 Add the `GET /api/products/{id}` endpoint (returns 404 when the id is unknown).
- [ ] E2.6 Add the `GET /api/supermarkets` endpoint.

### Frontend

- [ ] E2.7 Add catalog domain types and pure functions: `formatPrice`, `formatUnitPrice`, `isCheapest`.
- [ ] E2.8 Add a catalog API client in `infrastructure/` that maps DTOs to domain types (tested with MSW).
- [ ] E2.9 Add the `useProductSearch` hook (debounced, paginated, built on TanStack Query).
- [ ] E2.10 Add presentational components: `SearchBox` (molecule), `PriceTag` (molecule), `ProductCard` (organism), `ProductResultList` (organism), `SupermarketBadge` (atom).
- [ ] E2.11 Add the `SearchPageContainer` and the `/search?q=` route, with loading, empty, error and results states.
- [ ] E2.12 Highlight the cheapest unit price in each result set.
- [ ] E2.13 Add a Playwright journey: search "leche", results ordered by unit price.
