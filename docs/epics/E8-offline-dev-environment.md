# E8 — Offline development environment

**Goal:** a developer can start the development stack and try every case of the app **without internet and without real supermarket data**: the catalog comes from dummy sources of the three chains, with a price history, and dummy users let them log in at once. The real adapters stay available in dev on demand, and production does not change.

Related decisions: PD-13, PD-14, PD-22, PD-23, PD-25, PD-29, PD-37, PD-53, PD-54, PD-55, PD-56, PD-57. Open questions: none.

## Acceptance criteria

- In `APP_ENV=dev`, `app:catalog:ingest` (and the scheduled ingestion) finishes without any network access and imports the dummy catalog of Mercadona, Bonpreu i Esclat and Aldi, with no postal code to configure.
- With `CATALOG_SOURCES=real`, dev ingestion uses the real adapters again. Production always uses them.
- Each dummy chain has 30 to 50 products, comparable products across chains, and every case: kg, L, unit, packs, no unit price, approximate size with a reference price, and a malformed product that is skipped and counted.
- Two runs on the same day import the same data. Runs on different days change some prices, and some products become unavailable and later available again.
- `app:dev:catalog:backfill --days=N` leaves one dummy run per day for the last N days, and the product page shows that history. Running it again adds nothing.
- After `app:dev:seed`, a developer can log in as `admin` and as `usuario` with `Dev-password-1` and the code printed by `app:dev:totp <username>`.
- Running `app:dev:seed` again changes nothing and never touches existing users. `--reset` (confirmed, or with `--force`) leaves only the dummy data.
- The `app:dev:*` commands and the dummy sources do not exist outside `APP_ENV=dev`.
- No test reaches a real supermarket or the network.

## Tasks

### Catalog — dummy sources (infrastructure, dev only)

- [ ] E8.1 Add `Catalog\Infrastructure\Source\Dummy\DummyCatalogSource` (one `SupermarketCatalogSource` per slug) with a hand-written catalog of 30 to 50 products per chain covering every case of PD-54. Test through `fetch` that each chain returns every case, and through `IngestCatalogHandler` with in-memory repositories that the malformed product is skipped and the approximate one gets its reference unit price (PD-23, PD-29).
- [ ] E8.2 Make the dummy catalog deterministic by the run date (PD-54): with a `FixedClock`, the same day returns the same products and prices, some prices change on other days, and the products missing on a given day are marked unavailable by the ingestion and come back later (PD-22, PD-25).
- [ ] E8.3 Select the sources by environment (PD-53): in `when@dev`, the dummy sources replace the three real adapters unless `CATALOG_SOURCES=real`; `prod` and `test` are not affected. Add `INSTANCE_POSTAL_CODE=08001` and `CATALOG_SOURCES=dummy` to `backend/.env.dev`. Functional test (dev kernel): `app:catalog:ingest` imports the dummy catalog of the three chains; with `CATALOG_SOURCES=real` the real adapter services are the ones tagged `catalog.supermarket_source`.

### Catalog — backfill (dev only)

- [ ] E8.4 Add the `app:dev:catalog:backfill [--days=180]` console command (PD-55): it runs `IngestCatalogHandler` once per day from N days ago to today with the clock moved to that day (a dev `Clock` adapter that can be set), so snapshots and runs carry that date. Test: N runs per chain and their snapshots dated on each day; days that already have a dummy run are skipped on a second call; `--days` outside 1–730 exits with `2` and ingests nothing.

### Identity — dummy users (dev only)

- [ ] E8.5 Add the `app:dev:users` console command (PD-56): it creates `admin` (`ADMIN`) and `usuario` (`USER`), active, with the password `Dev-password-1` and their fixed TOTP secrets, through the Identity ports (`UserRepository`, password hasher, secret encrypter). Test: both can log in through `POST /api/auth/login` and `POST /api/auth/2fa`; an existing user with either name, or any other user, is left untouched; a second call creates nothing.
- [ ] E8.6 Add the `app:dev:totp <username>` console command (PD-56): it prints the current TOTP code of an active user and how many seconds it is still valid for. Test with a fixed clock: the printed code is the one `OtphpTotpService` accepts at that instant; an unknown or not active user exits with `1`.

### Seed and reset (dev only)

- [ ] E8.7 Add dev-only reset commands per context (PD-57), each emptying only its own tables: Catalog (`product`, `price_snapshot`, `ingestion_run`), Identity (`user`, `setup_code`, and ending every session through `session_version`) and ShoppingList (`shopping_list`, `list_member`, `list_item`). Integration test per command against the test database.
- [ ] E8.8 Add the `app:dev:seed [--reset] [--force]` console command (PD-57): it runs `app:dev:users` and `app:dev:catalog:backfill` (and, with `--reset`, the reset commands first) by name, so no context imports another. Test: a fresh database ends with the two dummy users and 180 days of dummy history; a second call changes nothing; `--reset` asks for confirmation and does nothing if refused; without interaction it refuses (exit `1`) unless `--force`.
- [ ] E8.9 Register every `app:dev:*` command and the dummy sources only in `when@dev` (PD-57). Test: in the `test` and `prod` containers (kernel boot) none of them is defined.

### Docs

- [ ] E8.10 README: in "Getting started", replace the real ingestion step with `app:dev:seed` and the dummy logins (`admin` / `usuario`, `Dev-password-1`, `app:dev:totp`); add a "Development data" section (what the dummy catalog contains, backfill, `--reset`, `CATALOG_SOURCES=real` to use the real stores). `docs/architecture.md`: document `CATALOG_SOURCES`, the dummy sources and the `app:dev:*` commands in "Development environment", and the new rows in the Symfony variables table.
