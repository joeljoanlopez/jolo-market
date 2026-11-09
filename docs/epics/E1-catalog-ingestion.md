# E1 — Catalog ingestion

**Goal:** periodically import the catalogs of Mercadona, Bonpreu i Esclat and Aldi for the instance postal code. Store products with a normalized unit price and append a price snapshot on every run.

Related decisions: PD-1, PD-5, PD-6, PD-21, PD-22, PD-23, PD-24, PD-25, PD-26, PD-27, PD-28, PD-29, PD-30, PD-31, PD-32. Open questions: none.

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

- [x] E1.13 OQ-5 (answered by PD-28): **Spike Mercadona**: find the data source (public store API, postal code to warehouse resolution, category traversal), the rate limits and the terms of use. Write the findings here.
- [x] E1.14 Write the Mercadona adapter against recorded fixtures (including postal code handling).
  - Note: first real run (postal code 46001, 2026-11-08): 4 396 products in 154 s, none skipped; 397 priced by reference (approximate size), 30 without unit price (for example saffron, finer than a gram).
- [x] E1.15 OQ-5 (answered by PD-32): **Spike Bonpreu i Esclat**: find the data source, whether location affects price, and the terms of use. Write the findings here.
- [x] E1.16 Write the Bonpreu i Esclat adapter against recorded fixtures.
  - Note: it sends a browser User-Agent, the only one the store's WAF accepts, waits 90 seconds between API requests to stay under the WAF allowance, and retries a challenged request after six minutes (PD-32).
  - Note: a first real run with one request a minute was challenged on its ninth request (6 168 products in 503 s), hence the longer pause and the retry.
  - Note: second real run (2026-11-09): 21 415 products in 5 824 s (about 97 minutes), none skipped; 21 357 with a unit price, 512 priced by weight and 7 119 by the piece (mostly non-food items sold as one unit), 58 without unit price.
  - Note: products sold by variable weight ("150g - 400g") or by the piece with no size are priced by the store's own unit price (PD-29).
- [x] E1.17 OQ-5 (answered by PD-31): **Spike Aldi**: find what the Spanish online catalog exposes (prices, packaging) and the terms of use. Write the findings here.
- [x] E1.18 Write the Aldi adapter against recorded fixtures.
  - Note: the first spike (PD-30) sampled product pages and found no food prices. Category pages do carry them (PD-31).
  - Note: first real run (2026-11-09): 2 478 products in 167 s, 2 222 with a unit price; 187 skipped because Aldi publishes no price for them, and 1 because of a broken image URL (since fixed: the image is left out).
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

Re-checked on 2026-11-09 with a browser User-Agent, following the reverse-engineered notes of [bonpreu-cli](https://github.com/pmontp19/bonpreu-cli/blob/main/docs/bonpreu-api-discovery.md) and the store's own JavaScript.

- **Source:** `www.compraonline.bonpreuesclat.cat`, a white-labelled Ocado Smart Platform store with an internal JSON API under `/api/webproductpagews/`. The sitemaps list 21 457 products and 2 868 categories.
  - `GET /` gives the session cookies and a CSRF token in `window.__INITIAL_STATE__.session.csrf.token`. Every API call sends both, plus `ecom-request-source: web`.
  - `GET /api/webproductpagews/v1/categories?decoration=false&categoryDepth=4` returns the tree: 19 top categories, 1 478 leaves. Some top categories are virtual (Ofertes, Novetats, Productes Km0…).
  - `GET /api/webproductpagews/v6/product-pages?maxProductsToDecorate=1000&maxPageSize=1000&tag=web&tag=category-item&categoryId={id}` returns every product under a category, up to 1000 per page (the store caps larger sizes), all decorated, in `productGroups[].decoratedProducts`. `metadata.nextPageToken` gives the next page (`pageToken={token}`).
- **Product data:** `retailerProductId`, `name` (brand inside), `brand`, `packSizeDescription` ("1L", "6 x 1L", "0.5kg", "12 per paquet"), `price.amount`, `unitPrice` (their own price per litre, kg, dozen or each), `categoryPath`, `image.src`. A product on offer has a promotion path ("Ofertes > Preu rebaixat") instead of its real one.
- **robots.txt:** `Disallow: /api/`.
- **Protection:** AWS WAF. It answers `403 Request blocked` to an identifying User-Agent and `200` to a browser one. Without an `aws-waf-token` cookie (only set by running the challenge JavaScript), the seventh API request within about five minutes gets `202` with `x-amzn-waf-action: challenge` and an empty body, whatever the pace, category or session. The challenge lifts after about five minutes.
- **Location and price:** anonymous sessions get the region `Catalunya` (`retailerRegionId` 31001) and the store reports a `CENTRALIZED` business model. The postal code is only used to check delivery (`/ecomdeliverydestinations/v2/deliverability`). Assumed: one price for every postal code.
- **Cost:** about 40 pages of up to 12 MB each, so about 300 MB per run, and about an hour with one API request every 90 seconds.
- **Loose products:** variable-weight products have `catchweight` and a range size ("150g - 400g"); products sold by the piece have no size and a unit price per each. Their `unitPrice` is the store's price per kg or per piece.
- **Terms of use:** not found through search.

### E1.17 Aldi

Re-checked on 2026-11-09 with a full crawl (the first sample, below, was wrong).

- **Source:** `www.aldi.es` (Next.js). Every page embeds its data in `__NEXT_DATA__`:
  - `/productos.html` and each section page (`/productos/despensa.html`) list their sub-categories in `apiData`, under the `PRODUCT_MGNL_CATEGORY_CHILDREN_GET` request (`reference.path`).
  - Each leaf page (`/productos/despensa/conservas-de-pescado.html`) embeds its Algolia results (`algoliaState.initialResults.an_prd_es_es_pen_products2`, `hitsPerPage` 1000, one page): `objectID`, `name`, `brandName`, `salesUnit` ("1 l unidad", "pack de 6 x 200 ml", "63 g peso esc. unidad", "precio kg (a granel)"), `currentPrice.priceValue` and `basePrice` (their price per kg or L), `hierarchicalCategories`, `assets`.
  - A product is listed in its category and in its brand's (`/productos/marcas/...`): the adapter returns it once, with the category from `hierarchicalCategories`.
- **Prices:** 2 480 of 2 666 products had a price. Most of the ones without are loose fresh produce. Prices are the peninsula ones ("Precios válidos según región"; Baleares and Canarias may differ, and in Cataluña sugary drinks exclude the sugar tax).
- **Packaging:** 2 168 priced products have a size the parser reads, 68 are approximate or loose (priced by `basePrice`, PD-29), 244 have no usable size (capsules, washes, plain "unidad").
- **robots.txt:** allows `/productos/`; disallows the Canarias and Baleares versions (`/can/`, `/bal/`).
- **Cost:** 163 pages, about 3 minutes per run with the 1 s delay.
- **Terms of use:** not read yet.

First sample (2026-11-08): 30 food product pages, none with a price. That sample was not representative.
