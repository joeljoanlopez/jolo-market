# E16 — Bilingual search with Typesense

**Goal:** any logged-in user searches in Spanish or Catalan and finds the products of every supermarket, although Bonpreu i Esclat names its products in Catalan, Mercadona in Spanish and Aldi in a mix of both: "leche" also finds Bonpreu's "llet". A self-hosted Typesense index, fed from MySQL, applies a Spanish ↔ Catalan dictionary kept in the repository and falls back to typo-tolerant matching when nothing matches exactly. This makes comparing the chains useful again. Requested in [issue #66](https://github.com/joeljoanlopez/jolo-market/issues/66).

Related decisions: PD-22, PD-24, PD-33, PD-61, PD-80, PD-100, PD-101, PD-102, PD-103, PD-104, PD-105, PD-124, PD-125, PD-126.

## Acceptance criteria

- Searching "leche" finds the milk of Mercadona and Aldi and the "llet" of Bonpreu; searching "llet" finds the same milk (PD-101).
- Searching "pan" and searching "pa" both include the bread of the three supermarkets ("pan" and "pa" products) (PD-101).
- A word that is only the start of a dictionary entry gets no equivalent: "lec" finds "leche…" products but not "llet" (PD-101).
- A search with exact results shows no approximate result (PD-103).
- A search with no exact result and a typo ("lece") shows the approximate results, and `/search` shows "No hay resultados para «lece». Mostrando resultados parecidos." above them (PD-103, PD-104).
- With a supermarket filter, the fallback runs when that supermarket has no exact result, and searches that supermarket only (PD-124).
- The fallback allows no typo in words under 4 letters, 1 in words of 4 to 6 letters and 2 from 7 letters (PD-125).
- Results keep the rules they have today: only available products (PD-22) of enabled supermarkets (PD-24), grouped by unit and ordered by unit price with the same tie-breaks, the cheapest of each group highlighted, the supermarket filter, 20 per page (PD-33), for exact and approximate results alike (PD-103).
- The suggestions of a list's "Qué necesitas" field use the same search, equivalents and typo fallback included, without a notice (PD-80, PD-104).
- When Typesense does not answer, `GET /api/products` answers `503`, and `/search` and the list suggestions show the existing search error with "Reintentar"; lists keep working (PD-105).
- After every ingestion run the index reflects the catalog (new products, price changes, products marked unavailable); a stack started with an empty index builds it on start, also with the dummy catalog of E8 (PD-100).
- `make reindex` (`app:catalog:reindex-search`) rebuilds the index and reloads the dictionary, and search keeps answering while it runs (PD-100).
- A change to `backend/config/search/synonyms.yaml` takes effect after the next reindex, with no code change (PD-101).
- The first dictionary holds about 150–250 frequent words of the catalog's categories, with singular and plural forms (PD-102).
- New copy is Spanish in `es.json`.

## Tasks

Typesense is reached through Symfony HttpClient from Catalog Infrastructure only. The domain and the `SearchProductsHandler` contract stay as they are, except for the `approximate` flag of `ProductSearchPage`. Integration tests run against the real Typesense service of the Compose stack. They never call a supermarket.

### Infrastructure (stack)

- [x] E16.1 Add a `typesense` service to `compose.yaml`, `compose.prod.yaml` and the e2e stack: Typesense pinned to its latest stable version (Pinned versions table), not exposed outside the Compose network, with a health check, `restart: unless-stopped` in production and a named volume so restarts are fast. php and scheduler wait for it to be healthy. Add `TYPESENSE_URL` and `TYPESENSE_API_KEY` to `.env.example`, `backend/.env`, `.env.dev`/`.env.test` (public placeholders) and the production compose. Extend `app:config:check` so a missing `TYPESENSE_API_KEY` or a malformed `TYPESENSE_URL` stops the container with a message (test first). CI starts the service for the backend and e2e jobs (PD-100).

### Dictionary (Catalog)

- [x] E16.2 Define the format of `backend/config/search/synonyms.yaml`: a list of groups of equivalent words, for example `[leche, llet]` or `[huevo, huevos, ou, ous]`. Add a loader in Catalog Infrastructure that reads it into synonym groups and rejects an invalid file with a clear message: an empty group, a group of one word, a word with spaces or a word in two groups. Words are lowercased. Unit-test the loader with small fixture files (PD-101).

### Indexing (Catalog)

- [x] E16.3 Add the outbound port `ProductSearchIndex` in Catalog Application (rebuild the index from the products of MySQL, then switch to it) and its Typesense adapter. The adapter creates a new collection with every product of enabled supermarkets (name, brand, supermarket, availability, unit, unit price, price, and the fields the PD-33 order needs), loads the synonym groups of E16.2 into it, then points the `products` alias to it and drops the previous collection, so search never sees a half-built index. Integration tests against the Typesense service cover a rebuild and a second rebuild that replaces the first (PD-100).
- [x] E16.4 Add the console command `app:catalog:reindex-search` (one use case, `ReindexProductSearchHandler`) that rebuilds the index of E16.3 and prints how many products it indexed. Exit code `0` on success and `1` when Typesense fails. Add `make reindex` to the `Makefile` with a bats test (PD-61, PD-100).
- [x] E16.5 Rebuild the index at the end of every ingestion run, also when the run failed for one supermarket, so products marked unavailable leave the results. A failing reindex is logged and makes the command exit `1`, and the catalog stays ingested. In `docker/php/entrypoint.sh`, build the index when the `products` alias is missing or empty, which covers a fresh stack, the dummy catalog of E8 and the e2e catalog loaded after the migrations. Test the ingestion with a fake `ProductSearchIndex` (PD-100).
  - Note: the dev and e2e stacks run `compose.yaml`, whose php command is not `entrypoint.sh` (production only), so the same `app:catalog:reindex-search --if-empty` runs in that command too. The e2e catalog is loaded after php has started, so CI (and the README steps) run `app:catalog:reindex-search` right after loading `catalog.sql`. The dev backfill (`app:dev:catalog:backfill`, hence `app:dev:seed`) skips the rebuild of each daily run and rebuilds once at the end. The tests of the `dev` environment (`DevKernel`) use their own alias, `products_dev_test`.

### Search (Catalog)

- [x] E16.6 Add `approximate` (bool) to `ProductSearchPage` and add `TypesenseProductSearchReadModel`, which implements `ProductSearchReadModel` on the index. Every word must match the start of a word of the name or brand, ignoring case and accents. A word that is a whole dictionary entry also matches its equivalents as prefixes, and a word that is only the start of an entry gets none (PD-101). Results keep the PD-22, PD-24 and PD-33 rules: units with more results first (ties kg, l, unit), no unit price last, then unit price, shelf price, name and id, and `total` and `cheapestUnitPriceCentsByUnit` over the whole query. Integration tests against Typesense, with a small indexed catalog, cover "leche"/"llet", "pan"/"pa", "lec" (no equivalent), the order, the supermarket filter and unavailable products. Port the current `DbalProductSearchReadModel` tests that still apply (PD-33, PD-101).
- [x] E16.7 Add the typo fallback to `TypesenseProductSearchReadModel`: when the exact search has no result, search again allowing up to 2 typos per word, with the same filters and order, and return `approximate: true`. Exact and approximate results are never mixed. Tests cover "lece" (approximate results), a query with exact results (no approximate one), a query that matches nothing either way (empty page, `approximate: false`) and a filtered query with no exact result in that supermarket (PD-103, PD-124, PD-125).
  - Note: the number of typos per word follows the word length (PD-125); with a supermarket filter the fallback is decided inside that supermarket (PD-124).
- [x] E16.8 Wire `TypesenseProductSearchReadModel` as the `ProductSearchReadModel` and remove `DbalProductSearchReadModel` and its tests, together with the FULLTEXT index on `product(name, brand)` (migration), since nothing else uses it. Check this with a search of the code first (PD-100, PD-105).

### API

- [x] E16.9 Add `"approximate": bool` to the response of `GET /api/products`. When Typesense does not answer or returns an error, the endpoint answers `503` with a generic title and no internal details, through a `SearchUnavailable` exception mapped like the PD-18 errors. Functional tests cover the flag and the `503` (with a Typesense adapter pointed at an unreachable URL) (PD-104, PD-105).

### Dictionary content

- [ ] E16.10 Write the first `backend/config/search/synonyms.yaml`: about 150–250 frequent words of the catalog's categories (dairy, bakery, fruit and vegetables, meat, fish, drinks, pantry, frozen, cleaning, personal care), paired Spanish ↔ Catalan with singular and plural forms, from common grocery vocabulary, covering every word of the dummy catalog that has a Catalan equivalent. A test checks that the shipped file loads (E16.2). The product owner reviews the list in the pull request (PD-102, PD-126).
  - Note: the dummy catalog gives the three chains the same Spanish names, so it has no Catalan words to take (PD-126).

### Frontend

- [ ] E16.11 Map `approximate` in `features/catalog/infrastructure/catalogApi.ts` onto the domain `ProductSearchPage` (`catalogApi.test.ts`). A `503` follows the existing error path of `useProductSearch`. Test that `/search` and the list suggestions show their current error and retry on a `503` (PD-105).
- [ ] E16.12 Show the approximate-results notice in `SearchPageContainer` above the results when `approximate` is true, and never in the list suggestions. Add `search.approximate` to `es.json`: "No hay resultados para «{{query}}». Mostrando resultados parecidos.". Container tests cover the notice with approximate results and its absence with exact ones (PD-104).

### End to end

- [ ] E16.13 Add products to `backend/tests/Fixtures/e2e/catalog.sql`: a Mercadona "Leche…" and a Bonpreu "Llet…" at least, plus "Pan…" and "Pa…". Extend `frontend/e2e/search.spec.ts`: searching "leche" shows both supermarkets, searching "pa" shows the bread of both, and searching "lece" shows the approximate notice with the milk. Extend `frontend/e2e/lists.spec.ts`: typing "llet" in "Qué necesitas" suggests the Mercadona milk (PD-101, PD-103, PD-104).

### Docs

- [ ] E16.14 Update `docs/architecture.md`: Stack (Typesense, MySQL no longer does the search), Pinned versions, Key flow 2 (index, equivalents, typo fallback, `approximate`, `503`), HTTP API (`GET /api/products`), the runtime diagram and services table (`typesense`), Production stack, Development environment (variables, `app:catalog:reindex-search`, `make reindex`, entrypoint indexing) and the Catalog backend structure. Update the README: search in Spanish or Catalan, the new service, variables and command, and how to add words to the dictionary.
