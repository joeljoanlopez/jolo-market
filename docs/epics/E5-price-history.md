# E5 — Price history

**Goal:** show how a product's price evolved over time, using the snapshots stored by E1.

Related decisions: PD-6, PD-46 to PD-49 (OQ-2 answered by PD-46).

## Acceptance criteria

- `GET /api/products/{id}/price-history` returns the snapshots ordered by date, with an optional date range.
- The product detail view shows a price-over-time chart and the minimum, maximum and current price.
- Consecutive identical prices are shown as one flat segment (no visual noise).

## Tasks

### Backend

- [x] E5.1 Add the `GetPriceHistory` query and handler (unit tests: ordering, range filter, unknown product).
- [x] E5.2 Add a DBAL read model for snapshots by product and range (integration test).
- [x] E5.3 Add the `GET /api/products/{id}/price-history` endpoint (functional tests).
- [x] E5.4 Add the `app:catalog:prune-history` command and schedule it daily (PD-46: 24 months, the latest snapshot of each product is kept).

### Frontend

- [x] E5.5 Choose a lightweight chart library (decide and record it in the architecture doc).
  - Decided: no library, our own SVG component (PD-49).
- [x] E5.6 Add pure functions to compute min, max and current values and to collapse equal consecutive points.
  - "Current" is the product's current price (PD-48), so only min and max are computed from the points (`priceRange`).
- [x] E5.7 Add a `PriceHistoryChart` presentational component and a product detail container (`/product/:id`).
- [x] E5.8 Link the detail view from search results and from list product items.
