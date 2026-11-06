# E5 — Price history

**Goal:** show how a product's price evolved over time, using the snapshots stored by E1.

Related decisions: PD-6. Open questions: OQ-2.

## Acceptance criteria

- `GET /api/products/{id}/price-history` returns the snapshots ordered by date, with an optional date range.
- The product detail view shows a price-over-time chart and the minimum, maximum and current price.
- Consecutive identical prices are shown as one flat segment (no visual noise).

## Tasks

### Backend

- [ ] E5.1 Add the `GetPriceHistory` query and handler (unit tests: ordering, range filter, unknown product).
- [ ] E5.2 Add a DBAL read model for snapshots by product and range (integration test).
- [ ] E5.3 Add the `GET /api/products/{id}/price-history` endpoint (functional tests).
- [ ] E5.4 ❓ OQ-2 Agree on a retention policy and, if needed, add a `app:catalog:prune-history` command.

### Frontend

- [ ] E5.5 Choose a lightweight chart library (decide and record it in the architecture doc).
- [ ] E5.6 Add pure functions to compute min, max and current values and to collapse equal consecutive points.
- [ ] E5.7 Add a `PriceHistoryChart` presentational component and a product detail container (`/product/:id`).
- [ ] E5.8 Link the detail view from search results and from list product items.
