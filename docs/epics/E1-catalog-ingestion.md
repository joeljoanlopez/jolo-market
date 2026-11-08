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

- [x] E1.13 ❓ OQ-5 **Spike Mercadona**: find the data source (public store API, postal code to warehouse resolution, category traversal), the rate limits and the terms of use. Write the findings here.
- [ ] E1.14 Write the Mercadona adapter against recorded fixtures (including postal code handling).
- [x] E1.15 ❓ OQ-5 **Spike Bonpreu i Esclat**: find the data source, whether location affects price, and the terms of use. Write the findings here.
- [ ] E1.16 Write the Bonpreu i Esclat adapter against recorded fixtures.
- [x] E1.17 ❓ OQ-5 **Spike Aldi**: find what the Spanish online catalog exposes (prices, packaging) and the terms of use. Write the findings here.
- [ ] E1.18 Write the Aldi adapter against recorded fixtures.
- [x] E1.19 Add shared HTTP adapter concerns: an identifying User-Agent, a polite delay between requests, retry with backoff on 5xx/429, and timeouts.

### Driving adapters & scheduling

- [x] E1.20 Add the `app:catalog:ingest` console command (`--supermarket`), with a functional test using fake sources.
- [x] E1.21 OQ-1 (answered by PD-27): Add a Symfony Scheduler entry driven by `INGESTION_SCHEDULE`, run by the `scheduler` container.
- [x] E1.22 Add structured logging of each run (counts, duration, errors).

## Spike notes

Run on 2026-11-08 with a handful of spaced requests and the PD-26 User-Agent. Raw responses were kept out of the repository. **The product owner has to accept each source (OQ-5) before its adapter is written.**

### E1.13 Mercadona

- **Source:** the JSON API behind `tienda.mercadona.es` (not documented, not official).
  - `PUT /api/postal-codes/actions/change-pc/` with `{"new_postal_code": "46001"}` answers with the warehouse in the `x-customer-wh` header (`vlc1`). Prices depend on the warehouse.
  - `GET /api/categories/?lang=es` lists 26 top categories and 152 leaf categories.
  - `GET /api/categories/{leafId}/?lang=es&wh={warehouse}` returns sub-categories with their products.
  - A full run is about 153 requests, around 3 minutes with the 1 s delay.
- **Product data:** `id`, `display_name` (brand inside the name, no separate field), `thumbnail`, `packaging` (container type: "Garrafa", "Pack-12"), and `price_instructions`: `unit_price` (price you pay, decimal string), `unit_size` + `size_format` (`kg`, `l`, `ud`; total size, already multiplied for packs), `is_pack`, `pack_size`, `total_units`, `approx_size`, `reference_price` + `reference_format` (their own unit price: `kg`, `L`, `ud`, `dc` = dozen).
- **Approximate weight:** loose fruit and vegetables come as `approx_size: true` (for example "Plátano, Pieza, 0.16 kg, 0.51 €", reference 3.20 €/kg). With the current rules (PD-23 parser: approximate means unknown) they would have no unit price. Needs a decision.
- **robots.txt:** `Disallow: /api` for every user agent (only `/`, `/product` and the sitemap are allowed).
- **Protection:** Akamai bot cookies (`_abck`, `bm_sz`) on API responses. The API answered normally to our User-Agent.
- **Terms of use:** not read. The legal notice (`info.mercadona.es/es/aviso-legal`) answers `403` to our User-Agent; we did not try to get around it. The product owner should read it in a browser.

### E1.15 Bonpreu i Esclat

- **Source:** `www.compraonline.bonpreuesclat.cat` (Next.js-style store with an internal `/api/`). The sitemaps list 21 457 products and 2 868 categories.
- **robots.txt:** `Disallow: /api/`. Product and category pages are allowed.
- **Protection:** the home page answers `200`, but product and category pages answer `403 Request blocked` (CloudFront WAF) to our User-Agent. With an identifying User-Agent there is no usable source today.
- **Location and price:** not checked, because product pages are blocked.
- **Terms of use:** not found through search.

### E1.17 Aldi

- **Source:** `www.aldi.es` (Next.js). Product pages embed their data in `__NEXT_DATA__` (`name`, `brandName`, `salesUnit` such as "1/40/56 unidades unidad / pack", `currentPrice.priceValue`, promotion validity dates). The products sitemap lists 2 824 products.
- **robots.txt:** allows product pages.
- **Prices:** a promoted non-food item had a price. A permanent food item ("Cogollos") had **no price** in the page data: Aldi Spain does not sell groceries online, so most of the food catalog seems to have no public price. Checked on only two products: to be confirmed.
- **Cost:** one request per product (about 2 800 requests, around 47 minutes per run with the 1 s delay). No postal code: prices are national.
- **Terms of use:** not read yet.
