# E1 — Catalog ingestion

**Goal:** periodically import the catalogs of Mercadona, Bonpreu i Esclat and Aldi for the instance postal code. Store products with a normalized unit price and append a price snapshot on every run.

Related decisions: PD-1, PD-5, PD-6, PD-21, PD-22, PD-23, PD-24, PD-25, PD-26, PD-27. Open questions: OQ-5.

## Acceptance criteria

- `bin/console app:catalog:ingest` imports every enabled supermarket. `--supermarket=<slug>` imports only one.
- Each product is upserted by `(supermarket, externalId)`, and every run appends one `PriceSnapshot` per product seen.
- Unit price is computed for kg, L and unit packaging. Unparseable packaging yields "no unit price", never a guessed value.
- A failure in one supermarket does not stop the others. Each run is recorded in `ingestion_run` with its status and counts.
- No test makes a real network call.

## Tasks

### Domain (Catalog)

- [x] E1.1 Add the `UnitOfMeasure` enum and the `Packaging` value object (quantity and unit, with g→kg, ml/cl→L and pack→unit conversion).
- [x] E1.2 Add a `PackagingParser` domain service that parses source strings ("1 L", "6 x 1 L", "500 g", "pack 12 ud") into `Packaging` or an explicit "unknown". Build it from a table-driven test of real samples.
  - Note: the first version covers the formats in this task plus common variants. Each spike (E1.13, E1.15, E1.17) adds the real samples it finds to the table.
- [x] E1.3 Add the `UnitPrice` value object and a `UnitPriceCalculator` (price ÷ normalized quantity, rounded to whole cents, with the rounding rule documented in the test).
- [x] E1.4 Add the `Supermarket` aggregate and the `SupermarketId` (slug) value object.
- [x] E1.5 Add the `Product` aggregate: `register`, `updateFromSource` (price, name, packaging changes) and `markUnavailable`.
- [x] E1.6 Add the `PriceSnapshot` entity and the ports `ProductRepository`, `PriceSnapshotRepository` and `SupermarketCatalogSource` (returning `SourceProduct` DTOs).
- [x] E1.7 Add the `PostalCode` value object (Spanish 5-digit format).

### Application

- [x] E1.8 Add the `IngestCatalogHandler` use case, tested with in-memory repositories and a fake source: upsert, snapshot, unavailability, per-supermarket error isolation.
- [x] E1.9 Add the `IngestionRun` record and its port, so run status is persisted.
- [x] E1.10 OQ-6 (answered by PD-22): decide how products that disappear from a catalog are treated (hidden or flagged as unavailable).

### Infrastructure — persistence

- [x] E1.11 Add migrations for the `supermarket`, `product`, `price_snapshot` and `ingestion_run` tables, plus seed data for the 3 supermarkets.
- [x] E1.12 Add DBAL `ProductRepository` and `PriceSnapshotRepository`, with integration tests against MySQL (including batch insert of snapshots).
  - Note: the DBAL `IngestionRunRepository` (port from E1.9) is added here too, so run status is persisted.

### Infrastructure — sources (one per chain)

Each adapter: spike → record fixtures → TDD the mapping from fixture to `SourceProduct` → wire into DI.

- [ ] E1.13 ❓ OQ-5 **Spike Mercadona**: find the data source (public store API, postal code to warehouse resolution, category traversal), the rate limits and the terms of use. Write the findings here.
- [ ] E1.14 Write the Mercadona adapter against recorded fixtures (including postal code handling).
- [ ] E1.15 ❓ OQ-5 **Spike Bonpreu i Esclat**: find the data source, whether location affects price, and the terms of use. Write the findings here.
- [ ] E1.16 Write the Bonpreu i Esclat adapter against recorded fixtures.
- [ ] E1.17 ❓ OQ-5 **Spike Aldi**: find what the Spanish online catalog exposes (prices, packaging) and the terms of use. Write the findings here.
- [ ] E1.18 Write the Aldi adapter against recorded fixtures.
- [x] E1.19 Add shared HTTP adapter concerns: an identifying User-Agent, a polite delay between requests, retry with backoff on 5xx/429, and timeouts.

### Driving adapters & scheduling

- [x] E1.20 Add the `app:catalog:ingest` console command (`--supermarket`), with a functional test using fake sources.
- [x] E1.21 OQ-1 (answered by PD-27): Add a Symfony Scheduler entry driven by `INGESTION_SCHEDULE`, run by the `scheduler` container.
- [x] E1.22 Add structured logging of each run (counts, duration, errors).

## Spike notes

_To be filled in by E1.13, E1.15 and E1.17._
