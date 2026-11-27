# Architecture

This document is the technical source of truth. If the code and this document disagree, raise it as a question. Do not silently pick one.

## Contents

1. [Product decisions](#product-decisions)
2. [Stack](#stack)
3. [Architecture style](#architecture-style)
4. [Bounded contexts](#bounded-contexts)
5. [Domain model](#domain-model)
6. [Backend structure](#backend-structure)
7. [Frontend structure](#frontend-structure)
8. [Key flows](#key-flows)
9. [HTTP API (draft)](#http-api-draft)
10. [Persistence](#persistence)
11. [Testing strategy](#testing-strategy)
12. [Runtime and deployment](#runtime-and-deployment)
13. [Open questions](#open-questions)

---

## Product decisions

Decisions confirmed by the product owner. Changing any of them requires asking first.

| ID | Decision |
| --- | --- |
| PD-1 | MVP supermarkets: **Mercadona, Bonpreu i Esclat, Aldi**. Each chain is a separate adapter; more chains come later. |
| PD-2 | **User accounts with login.** A shopping list has an **owner** and **members**. It is shared through a **secret link** (unguessable token). Opening the link **requires being logged in** and makes the user a member, so the list appears in their "My lists". The owner sees the members, can remove them and can **regenerate the link** (the old one stops working). *(Supersedes the original "no accounts" decision.)* |
| PD-3 | Comparison is **search + unit price**: a search returns products from every chain, ranked by price per kg / L / unit. There is **no cross-chain product matching** (no EAN grouping). |
| PD-4 | A list item is either **free text** ("leche") or a **concrete product** of a supermarket. Product items show price and contribute to an **estimated total per supermarket**. |
| PD-5 | **One postal code per instance**, set in `.env`. Ingestion fetches prices for that location only. |
| PD-6 | **Price history is stored**: each ingestion run writes dated price snapshots. |
| PD-7 | Collaboration uses **polling** (~5 s) while a list is open. No WebSockets or Mercure. |
| PD-8 | UI language is **Spanish**, with all copy in translation files (i18n ready). Code, comments and technical docs are **English**. |
| PD-9 | The instance is **closed**: the whole app (search included) requires login. There is no self sign-up. *(Supersedes the original "open instance" decision.)* |
| PD-10 | It is a **web app** (SPA). No native mobile app for now. |
| PD-11 | Environment files: the **backend follows the Symfony convention**. `backend/.env` (and `.env.test`) is committed with non-secret defaults. Real values and secrets go in `backend/.env.local`, which is ignored. The **root `.env`** used by Docker Compose is ignored, and a committed `.env.example` documents it. |
| PD-12 | **Users are created by an admin** of the platform. Users log in with a **username** (no email is stored or sent). Creating a user generates a **one-time activation link** that expires. The admin passes it to the user by any channel, and the user sets their password through it on first access. |
| PD-13 | **TOTP two-factor authentication is mandatory for every user.** It is configured on first access, right after setting the password, and nobody can use the app without it. |
| PD-14 | Roles: `ADMIN` (manages users and can also use the app) and `USER`. **First admin (setup):** while the platform has **no users**, the SPA redirects to `/setup`. Using it requires a **one-time setup code** that the backend prints in the container logs at startup, so whoever reaches the URL first cannot take over the instance. After entering the code and a username, the normal activation flow follows (password + TOTP). Once a user exists, `/setup` and its endpoints answer `404` forever. |
| PD-15 | Admin areas are **hidden in the frontend** for non-admins: no links, menu entries or buttons are rendered. If a non-admin still reaches an admin route or endpoint, the API answers `404` and the SPA shows the not-found page. The real protection is the backend; hiding is UX only. |
| PD-16 | Backend runtime: **PHP 8.5** and **MySQL 9.7 LTS** (newest stable PHP branch and newest MySQL LTS when E0 started). *(Supersedes the original PHP 8.4 and MySQL 8.4 LTS choice.)* |
| PD-17 | `GET /api/health` is **public**. It answers `200 {"status":"ok"}` when the database answers, and `503 {"status":"unavailable"}` when it does not. No internal details are exposed. |
| PD-18 | API errors map a small **domain error hierarchy** in `Shared\Domain\Exception` to HTTP: `NotFound` → `404`, `InvalidArgument` → `422`, `Conflict` → `409`. Symfony HTTP errors keep their status (unknown route `404`, wrong method `405`). Any other exception is a `500` with a generic title and **no internal details**, in every environment. Only domain errors include their message as `detail`. |
| PD-19 | `Money` is **never negative**: amounts and multipliers below zero are rejected. A signed amount (price differences, discounts) needs its own decision. |
| PD-20 | Each supermarket has its own color token, **inspired by its brand** so the chain is recognisable at a glance: Mercadona green, Bonpreu i Esclat orange, Aldi navy. The rest of the UI uses a neutral palette with one primary color. |
| PD-21 | The unit price (price ÷ normalized quantity) is rounded to whole cents **half-up**: 2.6533 €/L → 2.65 €/L, 2.655 €/L → 2.66 €/L. It is computed with integers only. |
| PD-22 | Products that disappear from their supermarket's catalog are marked **unavailable**. Search returns **only available products**. A shopping list that already contains one keeps showing it, flagged as unavailable and **not counted in the totals**. It becomes available again when its supermarket returns it. *(Answers OQ-6.)* |
| PD-23 | During ingestion, a **malformed product** (blank name, negative price, invalid image URL…) is **skipped**: it is not imported, a warning is logged and it is counted in the run (`products_skipped`). The rest of the catalog is imported. |
| PD-24 | Which supermarkets are enabled is decided **only by `ENABLED_SUPERMARKETS`** in `.env` (comma-separated slugs). The `supermarket` table stores id and name only, with no `enabled` column. |
| PD-25 | Products not seen in a run are marked unavailable **only when the run succeeds**. A run that fails half-way or returns **zero products** is recorded as **failed**: what it imported before failing is kept (with its snapshots), and every other product keeps its previous state. |
| PD-26 | Requests to supermarkets identify the project (except Bonpreu i Esclat, PD-32): User-Agent `jolo-market/1.0 (+https://github.com/joeljoanlopez/jolo-market)` by default, overridable per instance with `CATALOG_HTTP_USER_AGENT` (operators may add their own contact). |
| PD-27 | Catalogs are ingested **daily at 05:00 Europe/Madrid** by default. `INGESTION_SCHEDULE` (cron expression, evaluated in Europe/Madrid) changes it per instance. *(Answers OQ-1.)* |
| PD-28 | **Mercadona** is ingested from the internal JSON API of `tienda.mercadona.es`, accepted for **private, self-hosted use** (one instance, one run a day, identifying User-Agent) even though its `robots.txt` disallows `/api`. The operator takes that risk. *(Partly answers OQ-5.)* |
| PD-29 | When a product's size is **approximate** (loose fruit and vegetables) and the source gives its own price per kg, L or unit, that **reference price is the unit price**. Otherwise the unit price is always computed by us. Adapters pass a reference price only for approximate sizes. |
| PD-30 | *(Replaced by PD-31 for Aldi and PD-32 for Bonpreu i Esclat.)* The Bonpreu i Esclat and Aldi adapters were deferred: Bonpreu's WAF blocks an identifying User-Agent and Aldi seemed to publish no food prices. |
| PD-31 | **Aldi** is ingested from the public category pages of `www.aldi.es` (allowed by its `robots.txt`), which embed their products and prices in `__NEXT_DATA__`. Prices are the **peninsula** ones (`/can/` and `/bal/` are disallowed), and they are used for every postal code, Baleares and Canarias included, so the postal code is not used. Canned food is sized by its **drained weight** (`peso esc.`), the only size Aldi gives. Products without a published price (mostly loose fresh produce) are stored without one. Aldi is in the default `ENABLED_SUPERMARKETS`. *(Replaces the Aldi part of PD-30, whose spike sample was wrong: on 2026-11-09, 2 480 of 2 666 products had a price. Peninsula prices for every postal code and drained weight as the size confirmed by the product owner on 2026-11-09.)* |
| PD-32 | **Bonpreu i Esclat** is ingested from the internal JSON API of `www.compraonline.bonpreuesclat.cat` (Ocado Smart Platform), with a **browser User-Agent**: its WAF rejects any other client. This overrides PD-26 for Bonpreu only, and like PD-28 it is accepted for **private, self-hosted use** even though `robots.txt` disallows `/api/`. The operator takes that risk. The WAF challenges the seventh API request within about five minutes, and only a browser running its JavaScript can pass a challenge, so the adapter **waits 90 seconds between API requests** instead of trying to pass it, and after a challenge waits six minutes and retries, up to twice (a run takes about an hour and a half). The online store has one price region (Catalunya): every postal code gets the same price, so the postal code is not used. Bonpreu is in the default `ENABLED_SUPERMARKETS`. *(Replaces the Bonpreu part of PD-30; with PD-28 and PD-31, OQ-5 is closed. One price for every postal code confirmed by the product owner on 2026-11-09.)* |
| PD-33 | Search has **one filter in the MVP: supermarket** (brand and category later). Results are **grouped by unit price unit** (kg, L, unit): the unit with the most results for the query comes first, and each group is ordered by unit price ascending. Products without a unit price go last. Ties on unit price are broken by the **lower shelf price**, then name, then id, so pages are stable. The cheapest unit price is highlighted **within each unit group**. Units with as many results go in the order kg, L, unit. Every query word must match the **start of a word** in the name or brand, short words ("de", "xl") included, so search works while a word is still being typed. *(Answers OQ-7.)* *(Matching amended by PD-101 and PD-103.)* |
| PD-34 | An **activation link is valid for 72 hours**. While a user is still pending activation, an admin can **regenerate** their link: the new link gets a fresh 72 hours and the previous one stops working at once. *(Answers OQ-8, confirmed by the product owner on 2026-11-09.)* |
| PD-35 | **Lost password or lost 2FA device: an admin resets the user.** The user goes back to pending activation, their password and TOTP secret are removed, their sessions stop working, and a new activation link (PD-34) is generated so they set a new password and enroll a new TOTP device. Their lists and memberships are kept. There are **no TOTP recovery codes** in the MVP. For a locked-out admin, the server operator runs the console command `app:user:reset <username>`, which does the same and prints the new link. *(Answers OQ-9, confirmed by the product owner on 2026-11-09.)* |
| PD-36 | **Admin user management beyond creation: disable/enable and change role, no delete.** A disabled user cannot log in and their sessions stop working; their lists and memberships are kept and other members still see them. Enabling returns the user to active. An admin can change a role between `USER` and `ADMIN`. The **last active admin** cannot be disabled or lose the `ADMIN` role, and an admin cannot disable themselves. Users cannot be deleted in the MVP. *(Answers OQ-10, confirmed by the product owner on 2026-11-09.)* |
| PD-37 | **Password policy:** at least **12 characters** (at most 128), with at least one **uppercase letter**, one **lowercase letter**, one **digit** and one **special character** (any character that is not a letter or a digit). There is no breached-password check. **Session:** expires after **7 days of inactivity**, renewed on each request (sliding expiry); there is no "remember me". Every login asks for the TOTP code (PD-13). *(Answers OQ-11, confirmed by the product owner on 2026-11-09.)* |
| PD-38 | **Self-service profile page.** A logged-in user can **change their password** (asks for the current password and a TOTP code; the new one follows PD-37) and **re-enroll TOTP** (asks for the current password and a code from the current device, then shows a new QR code and manual secret, and the new device is active only after a code from it is confirmed; until then the old device keeps working). Both actions **end every other session** of the user and keep the current one. *(Answers OQ-12, confirmed by the product owner on 2026-11-09.)* |
| PD-39 | A **username** has 3 to 32 characters: lowercase letters `a`–`z`, digits, `.`, `_` and `-`, starting with a letter or a digit. It is case-insensitive and stored in lowercase (`Maria` is `maria`). Accents, `ñ` and spaces are not allowed. *(Confirmed by the product owner on 2026-11-09.)* |
| PD-40 | **Renewing the session cookie keeps the session id.** Once an hour (and on the first request after an activation) the API re-sends the session cookie with 7 more days, with the **same id**. Changing the id there would destroy the old one, and a request the browser sent at the same moment would get a `401` and log the user out. The id still changes on every login (session fixation), and logout still ends the session on the server. *(Bug found while protecting the catalog in E3.21; fix confirmed by the product owner on 2026-11-10.)* |
| PD-41 | **The TOTP QR code is drawn in the browser** with the npm package `qrcode` (pinned), from the `provisioningUri` the API already returns. The API contract does not change and the secret never leaves the user's browser. *(Confirmed by the product owner on 2026-11-10.)* |
| PD-42 | **Admin actions that remove access ask first.** On the users page, "Resetear" (removes password and TOTP, ends sessions) and "Desactivar" (ends sessions) turn into an inline "¿…? Confirmar / Cancelar" in the user's row. Regenerating a link, enabling and changing the role apply at once. An admin is not offered to reset or disable their own account. *(Confirmed by the product owner on 2026-11-10.)* |
| PD-43 | **Concurrent list edits: last write wins, per operation.** Operations state the intent (check sets `checked = true`, it never toggles), so two people checking the same item agree. Two edits of the same field keep the last one; edits to different items or fields never undo each other, because saving a list checks the `version` it was loaded with and the API retries the operation on a fresh copy when someone else saved first. The user never sees a conflict error. *(Answers OQ-3, confirmed by the product owner on 2026-11-11.)* |
| PD-44 | **The owner can delete a list; a member can leave it; ownership cannot be transferred.** Deleting asks for confirmation and removes the list for everyone: it disappears from every "My lists" and anyone who has it open sees "list not found" (`404`). A member can leave on their own and can join again later through the link while it is valid. The owner cannot leave their own list, only delete it. *(Answers OQ-4, confirmed by the product owner on 2026-11-11.)* |
| PD-45 | **List details.** A list name is required, 1 to 80 characters after trimming. A free-text item has 1 to 120 characters after trimming. A quantity is a whole number from 1 to 99, 1 by default. Adding a product that is already in the list and unchecked adds the quantity to that item instead of creating another one (above 99 → `422`). New items go to the end; the list shows unchecked items first, in their order, then checked ones. *(How the list is shown is replaced by PD-93.)* The total per supermarket adds `price × quantity` of every **available** product item, checked or not (the estimated cost of the whole list). A removed member can join again while the link is valid (the owner regenerates it to stop that). *(Confirmed by the product owner on 2026-11-11.)* |
| PD-46 | **Price history retention: 24 months.** Snapshots older than `PRICE_HISTORY_RETENTION_MONTHS` (default `24`) are deleted by `app:catalog:prune-history`, which the scheduler runs daily at `PRICE_HISTORY_PRUNE_SCHEDULE` (cron in Europe/Madrid, default `30 4 * * *`, before the ingestion) and the operator can also run by hand. The **latest snapshot of each product is always kept**, however old, so an unavailable product still shows its last known price. *(Answers OQ-2, confirmed by the product owner on 2026-11-12.)* |
| PD-47 | **Price history range.** `GET /api/products/{id}/price-history` takes optional `from` and `to` dates (`YYYY-MM-DD`), both **inclusive** and read as days in **Europe/Madrid**. Without them it returns the whole history. A malformed date or `from` after `to` is a `422`; an unknown product a `404`. *(Confirmed by the product owner on 2026-11-12.)* |
| PD-48 | **Product detail page** `/product/:id`: image, name, brand, supermarket, packaging, price, an "unavailable" flag, "Añadir a una lista" (available products only) and the price history. The chart shows the **shelf price**. A range selector offers "30 días", "6 meses" (default), "1 año" and "Todo"; minimum and maximum are those of the selected range, and "current" is the product's current price. With no snapshot in the range it says so (no minimum or maximum); one snapshot is drawn as a single point. The time axis goes from the first to the last snapshot of the range. The **product name** links to this page from search results and from the product items of a list (not for a product the catalog no longer knows). *(Confirmed by the product owner on 2026-11-12.)* |
| PD-49 | **The price chart is our own SVG component** (`PriceHistoryChart`), with no chart library: a step line of the price over time, colored with theme tokens and testable in jsdom. *(E5.5, confirmed by the product owner on 2026-11-12.)* |
| PD-50 | **Optional auto-deploy (E7), off by default.** A user systemd timer runs `deploy/auto-deploy.sh` every 5 minutes. With `AUTO_DEPLOY=true` in the root `.env`, when `origin/main` has a new commit whose CI is **green**, it fast-forwards and runs `docker compose up -d --build --remove-orphans` with the Compose files the root `.env` selects (`COMPOSE_FILE`, `compose.yaml` today), then `deploy/post-deploy.sh`: `composer install`, **database migrations applied automatically** and a restart of the scheduler and the frontend (the code is mounted from the host, so a build alone does not pick up new dependencies). A running CI is checked again on the next run; a red one is not deployed. It never touches a copy on another branch, with uncommitted changes in tracked files or with local commits. A deploy that fails after the merge is retried on every run. No GitHub runner, webhook or open port. *(Modelled on E11 of jolo-PM; confirmed by the product owner on 2026-11-12.)* |
| PD-51 | **Shell scripts are tested with bats-core** (pinned Docker image) against temporary git repositories, with fake `docker`, `gh` and `curl` on the `PATH`, and checked with **ShellCheck**. A CI job runs both. *(Confirmed by the product owner on 2026-11-12.)* |
| PD-52 | **Auto-deploy deploys only the production stack.** `deploy/auto-deploy.sh` refuses to run (exit `1`, nothing merged) unless `COMPOSE_FILE` in the root `.env` lists `compose.prod.yaml` and not `compose.yaml`, and deploys with `docker compose up -d --build --remove-orphans --wait --wait-timeout 600` (a container stuck restarting must not hang the run, which holds the lock). `deploy/post-deploy.sh` is removed: the production php container applies the migrations when it starts, and `--wait` fails the deploy (retried on the next run) when the stack does not become healthy. *(Supersedes the post-deploy part of PD-50; confirmed by the product owner on 2026-11-13.)* |
| PD-53 | **The development environment works offline.** With `APP_ENV=dev`, `app:catalog:ingest` and the `scheduler` use **dummy catalog sources** for Mercadona, Bonpreu i Esclat and Aldi instead of the real adapters, so no request leaves the machine. `CATALOG_SOURCES=real` in `backend/.env.dev.local` switches dev back to the real adapters (opt-in, default `dummy`; see PD-72). Production always uses the real adapters and the test environment keeps its own fakes. `backend/.env.dev` sets `INSTANCE_POSTAL_CODE=08001`, so dev ingestion works without configuring anything. *(Confirmed by the product owner on 2026-11-13.)* |
| PD-54 | **Dummy catalog.** **30 to 50 products per chain**, in several categories, with products comparable across chains (milk, olive oil, eggs…), and every case the app handles: prices per kg, L and unit, packs, a product without unit price, an approximate size priced by its reference price (PD-29) and one malformed product that is skipped (PD-23). It is **deterministic by the run date**: the same day always returns the same data; some products change price from one day to another, and one or two per chain are missing on some days, so they become unavailable and come back (PD-22, PD-25). *(Confirmed by the product owner on 2026-11-13.)* |
| PD-55 | **Price history backfill in dev.** `app:dev:catalog:backfill [--days=180]` (1 to 730 days) runs the dummy ingestion once per day, from N days ago to today, with the clock moved to that day, so a fresh database has a price history. Days that already have a dummy run are skipped, so it can be run again. *(Confirmed by the product owner on 2026-11-13.)* |
| PD-56 | **Dummy users in dev.** Two users, `admin` (`ADMIN`) and `usuario` (`USER`), both with the password `Dev-password-1` (PD-37) and **fixed TOTP secrets**, documented as development-only. 2FA stays mandatory (PD-13): `app:dev:totp <username>` prints the current code of any active user and the seconds it is still valid for (the secret can also be added to an authenticator app). Creating them skips `/setup`, which then answers `404` like on any instance with users (PD-14). *(Confirmed by the product owner on 2026-11-13.)* |
| PD-57 | **`app:dev:seed` loads the development data.** It creates the dummy users that are missing (existing users, dummy or not, are left untouched) and runs the backfill (PD-55). Running it again changes nothing. `--reset` first empties the catalog (products, snapshots, ingestion runs), the users (with their sessions and the setup code) and the shopping lists; it asks for confirmation, and without interaction it refuses unless `--force` is given. Every `app:dev:*` command exists **only in `APP_ENV=dev`**. The README replaces the real ingestion step of "Getting started" with `app:dev:seed`. Dummy shopping lists are out of scope. *(Confirmed by the product owner on 2026-11-13.)* |
| PD-58 | **A root `Makefile` for the development stack (E9).** It covers only the **development lifecycle** (`init`, `start`, `stop`, `restart`, `logs`, `shell`, `migrate`) and the **checks and tests** (`check`, `check-backend`, `check-frontend`, `test-unit`, `test-deploy`). The end-to-end stack and production operations (ingestion, backup, upgrade, user reset) stay out. `make` with no target prints the help: every target with a one-line description. `make` becomes the **documented way** in the README, `AGENTS.md` and the Definition of done, and `make` joins the development requirements; next to each target the docs show the command it runs, so it can still be run by hand. `stop` runs `docker compose down` and **never removes volumes**. `check` runs `check-backend` (`composer check` in the php container), `check-frontend` (`npm run check` **on the host**, like CI; it fails with a clear message when Node is missing) and `test-deploy` (`deploy/test.sh`), and fails when any of them fails. *(Confirmed by the product owner on 2026-11-13.)* |
| PD-59 | **`make init` prepares a fresh clone and is idempotent.** It creates the root `.env` from `.env.example` and an empty `backend/.env.local` **only when they do not exist** (never overwrites them); runs `npm ci` in `frontend/` on the host when Node is installed (otherwise it warns and goes on), before the first `docker compose up`, so `frontend/node_modules` is not created as root; then `docker compose up -d --build --wait`, `composer install` and the database migrations. It does **not** ask for `INSTANCE_POSTAL_CODE` (the warning about it was dropped by PD-73). Running it again changes nothing that is already set up. *(Confirmed by the product owner on 2026-11-13.)* |
| PD-60 | **The `Makefile` never touches the production stack.** Every target that runs Docker refuses to run (non-zero exit, nothing executed) when `COMPOSE_FILE` in the root `.env` lists `compose.prod.yaml`, with a message saying the `Makefile` is for the development stack only. *(The reverse of PD-52; confirmed by the product owner on 2026-11-13.)* |
| PD-61 | **Agents ask about `make` targets.** When an assistant adds or changes a development command (a Composer or npm script, a script in `deploy/`, a console command used while developing), it asks the product owner whether it should get a `make` target. The rule lives in `AGENTS.md` (§5) and in the interview checklist of the `new-epic` skill, so the question comes up while planning an epic. *(Confirmed by the product owner on 2026-11-13.)* |
| PD-62 | **Local OCR is the fallback receipt reader (E10).** When the AI reader of PD-112 is not configured or fails, a receipt photo is read on the server with **Tesseract** (Spanish and Catalan language data), and a **parser per chain** (Mercadona, Bonpreu i Esclat, Aldi) turns the text into a date, a total and lines. Parsers are tested against recorded photos and their OCR text in `backend/tests/Fixtures/`, with the card digits covered. *(Confirmed by the product owner on 2026-11-14; changed to a fallback by PD-112 on 2026-11-27.)* |
| PD-63 | **Receipts belong to a shopping list.** The owner and the members of a list can upload, correct, confirm and delete **any** receipt of that list, like items (PD-43). The uploader is stored and shown. A receipt stays in the list when its uploader leaves or is removed. Deleting the list deletes its receipts and their photos (PD-44). Anyone who is not owner or member gets `404` for the receipts and their photos (PD-15). *(Confirmed by the product owner on 2026-11-14.)* |
| PD-64 | **The user picks the supermarket when uploading**, among the enabled ones (`ENABLED_SUPERMARKETS`, PD-24); it chooses the parser. A supermarket that is not enabled is a `422`. There is no automatic detection of the chain. *(Confirmed by the product owner on 2026-11-14.)* |
| PD-65 | **Receipt lifecycle: reading → draft → confirmed.** The upload answers at once and the receipt is `reading`; a background worker reads it (AI first, OCR as fallback, PD-112) and moves it to `draft` with what it read. When reading fails or finds nothing, the receipt is still a `draft`, empty, to be filled in by hand. The list shows the change through its polling (PD-7). A draft must be **reviewed and confirmed** by a member; **only confirmed receipts count as spending**. A confirmed receipt can be edited again (it stays confirmed) and can be deleted; deleting asks for confirmation. *(Confirmed by the product owner on 2026-11-14.)* |
| PD-66 | **Receipt contents.** A receipt has a **date** (read from the photo, editable, required to confirm, never in the future, a day in Europe/Madrid) and the **total paid** printed on it (editable, required to confirm, more than 0), which is the amount that counts as spending. Lines are of two kinds: a **product line** (the text as printed, 1 to 120 characters; a quantity, either whole units 1 to 999 or a weight in kg with three decimals, 0.001 to 99.999, stored as thousandths; the amount paid, more than 0; and an optional linked product) and a **discount line** (text and a positive amount that is subtracted). Every amount is positive integer cents (PD-19). A receipt has at most 200 lines. When products minus discounts differ from the total, the review shows a warning, but the receipt can still be confirmed. *(Confirmed by the product owner on 2026-11-14.)* |
| PD-67 | **Linking lines to products: suggestion plus a shared memory.** Each product line can be linked to a product of the **receipt's supermarket**. When a draft is created, a line whose text the memory knows gets that product; any other line gets the most similar product of that supermarket as a **suggestion**. The user accepts it, picks another one with the search, or leaves the line unlinked. Confirming a receipt stores **"text as printed → product" per supermarket for the whole instance**, so later receipts of any list link that text by themselves; when two confirmations disagree, the last one wins. A linked product that becomes unavailable stays linked (PD-22). Receipt lines **never change catalog prices or the price history**. *(Confirmed by the product owner on 2026-11-14.)* |
| PD-68 | **Receipt photo.** One photo per receipt, **JPEG, PNG or WebP, up to 10 MB**; anything else is a `422`. It is kept in its own volume, shown next to the receipt (to members only, PD-63) and deleted with the receipt or its list. Receipts longer than one photo and PDF receipts are out of scope. *(Confirmed by the product owner on 2026-11-14.)* |
| PD-69 | **Backups include the receipt photos.** `deploy/backup.sh` also archives the photo volume (`jolo-market-<UTC timestamp>-receipts.tar.gz`) next to the database dump, and the README explains how to restore both. *(Confirmed by the product owner on 2026-11-14.)* |
| PD-70 | **Spending in a list.** The list shows its **confirmed receipts** (date, supermarket, total and uploader), newest first, and the **total spent per month** (by receipt date, Europe/Madrid), newest month first, with its breakdown per supermarket. Drafts and receipts still being read are listed apart and do not count. Spending per product, charts and a personal total across lists are out of scope. *(Confirmed by the product owner on 2026-11-14.)* |
| PD-71 | **Receipts do not change the list items.** Confirming a receipt does not check, add or remove any item of the list. Dummy receipts in `app:dev:seed` are out of scope. *(Confirmed by the product owner on 2026-11-14.)* |
| PD-72 | **Development overrides go in `backend/.env.dev.local`.** `backend/.env.dev` sets `CATALOG_SOURCES=dummy` and `INSTANCE_POSTAL_CODE=08001` (PD-53). Symfony loads `.env.dev` after `.env.local`, so those two values are changed in `backend/.env.dev.local` (loaded last), not in `backend/.env.local`; a postal code already in `.env.local` is ignored in dev. *(Confirmed by the product owner on 2026-11-14.)* |
| PD-73 | **`make init` does not warn about `INSTANCE_POSTAL_CODE`.** `backend/.env.dev` sets it (PD-53) and dev overrides go in `backend/.env.dev.local` (PD-72), so the development stack always has one and the PD-59 warning would never be useful. `make init` still creates an empty `backend/.env.local` when it is missing. *(Recommended option, applied when the product owner asked to implement E9 on 2026-11-16 without choosing; to be confirmed in the E9 pull request.)* |
| PD-74 | **Dark mode with three choices: Sistema, Claro, Oscuro (E11).** The default is **Sistema**: the app follows the operating system (`prefers-color-scheme`) and switches live when it changes. **Claro** and **Oscuro** force a theme. A browser that does not report a preference gets the light theme. *(Confirmed by the product owner on 2026-11-16.)* |
| PD-75 | **The theme choice is stored in the browser**, in `localStorage`, per device. Nothing is stored in the backend and it is not synced between devices. It applies to every page, including `/login`, `/setup` and `/activate/:token`, and it is applied **before the first paint**, so a page never flashes the wrong theme. A missing or unknown stored value means Sistema. *(Confirmed by the product owner on 2026-11-16.)* |
| PD-76 | **The theme selector is a dropdown in the header.** A `Select` labelled "Tema" with "Sistema", "Claro" and "Oscuro" lives in the `AppShell` header, so it is on every logged-in page. Pages without the header (`/login`, `/setup`, `/activate/:token`) have no selector and use the stored choice or the system. *(Confirmed by the product owner on 2026-11-16.)* |
| PD-77 | **Every color token has a dark value.** The dark palette keeps the same token names (PD-20 tokens included), and every text/background pair the UI uses (`foreground` and `muted` on `background` and `surface`, each `*-foreground` on its color) reaches **WCAG AA contrast (4.5:1)** in both themes, checked by a test. The exact shades are reviewed by the product owner in the implementation pull request. *(Confirmed by the product owner on 2026-11-16.)* |
| PD-78 | **The activation QR code is always dark on light**, in both themes, so every authenticator app can scan it (PD-41). It uses its own fixed tokens instead of `background`/`foreground`. *(Confirmed by the product owner on 2026-11-16.)* |
| PD-79 | **Supermarket colors have a dark-theme variant.** Each chain gets a shade for the dark theme that still recalls its brand (for example a lighter blue for Aldi, whose navy disappears on a dark background) and reaches AA with its foreground. It extends PD-20, which still applies to the light theme. *(Confirmed by the product owner on 2026-11-16.)* |
| PD-80 | **The add-item field of a list searches the catalog.** On a list page, owner and members type in the single "Qué necesitas" field. With **at least 2 characters** after trimming, and after the same debounce as `/search`, it shows a suggestion panel below the field: first an option **"Añadir «…» como texto"**, then the **first 10 search results** in the order of PD-33 (every supermarket, no filter), each with name, brand, supermarket, shelf price and unit price, and at the end a link **"Ver todos los resultados"** (PD-82). Only available products appear (PD-22). With no results the panel says so and the text option stays. When the search fails the panel shows an error with a retry, and adding as free text still works. Free-text items stay available (PD-4). *(Confirmed by the product owner on 2026-11-17.)* |
| PD-81 | **Adding from the suggestions.** **Enter** or the "Añadir" button adds the typed text as a **free-text item**, as before, unless a suggestion is highlighted with the arrow keys: then Enter adds that suggestion. A click or tap on a product adds that product. The quantity field of the form applies to both kinds of item. After adding, the field is emptied, the quantity goes back to 1, the panel closes and the focus stays in the field, so the next item can be typed at once. A product already in the list follows PD-45 (its quantity is added). *(Confirmed by the product owner on 2026-11-17.)* |
| PD-82 | **"Ver todos los resultados" remembers the list.** The link opens `/search?q=<text>&list=<listId>`. While `list` names a list the user can access, "Añadir a la lista" on each result adds to that list directly, without the "My lists" picker, and the page shows a link **"Volver a <list name>"**. Typing, filtering and paging keep the `list` parameter. When the list does not exist or the user cannot access it, the parameter is ignored and the page works as without it. *(Confirmed by the product owner on 2026-11-17.)* |
| PD-83 | **A list can be filtered by supermarket (E13).** On a list page, owner and members can choose one supermarket to see only the list's products of that chain, with its estimated total, so they know what to buy in each store and how much it costs. It is a **view of whoever is looking**: it never changes the list, and the other members do not see it. There are no equivalent products across chains (PD-3 still applies). The API does not change: the filter works on the data of `GET /api/lists/{id}`. *(Confirmed by the product owner on 2026-11-17.)* |
| PD-84 | **The supermarket filter is a row of chips above the items.** **"Todos"** comes first and is selected by default, then one chip per supermarket that has **at least one available product** in the list, in the order of the totals, each with its estimated total (PD-45). A supermarket whose products in the list are all unavailable gets no chip; its products show only under "Todos". The "Total estimado por supermercado" section stays as it is. *(Confirmed by the product owner on 2026-11-17.)* |
| PD-85 | **What the filtered view shows.** With a supermarket chosen, the items are that chain's products, checked or not, in the order of PD-45, its unavailable products flagged (PD-22). Free-text items and products removed from the catalog (they have no supermarket) are hidden, and a notice says how many: "N elementos sin supermercado no se muestran". Products of other chains are hidden without a notice. Quantity changes, checks and other members' edits arriving through polling (PD-7) update the view and the chip totals. *(Confirmed by the product owner on 2026-11-17.)* |
| PD-86 | **The chosen supermarket lives in the URL**: `/lists/:listId?supermarket=<supermarketId>`, so it survives a reload and works with back and forward. Without the parameter the view is "Todos". When the parameter names a supermarket that has no chip (unknown, or its last available product was removed, also by another member through polling), the view goes back to "Todos" and the parameter is dropped. *(Confirmed by the product owner on 2026-11-17.)* |
| PD-87 | **Adding items ignores the supermarket filter.** The "Qué necesitas" field keeps working as in PD-80 and PD-81 (suggestions from every supermarket, free text allowed). An item that does not belong to the chosen supermarket is saved and shows under "Todos" (a free-text item also counts in the PD-85 notice). *(Confirmed by the product owner on 2026-11-17.)* |
| PD-88 | **GitHub issue templates are written in Spanish.** The forms in `.github/ISSUE_TEMPLATE/` are filled in by the product owner, so their labels, descriptions and placeholders are Spanish. It is the only repository content besides `es.json` that is not in English (AGENTS.md §2); file names, field `id`s and labels stay in English. *(Confirmed by the product owner on 2026-11-21.)* |
| PD-89 | **Three issue forms, blank issues allowed.** Requests are opened with one of three GitHub issue forms: **Nueva funcionalidad** (`feature.yml`, label `enhancement`), **Cambio de funcionamiento** (`change.yml`, label `change`, for behavior that works as decided but should work differently) and **Bug** (`bug.yml`, label `bug`). Blank issues stay enabled for quick notes that fit no form. Feature and change requests are turned into epics with `/new-epic`. *(Confirmed by the product owner on 2026-11-21.)* |
| PD-90 | **List sections (E14).** The app has its own fixed set of sections, shown in this order, the same for every list: **Fruta y verdura · Panadería · Carne y pescado · Charcutería y quesos · Lácteos y huevos · Despensa · Congelados · Bebidas · Limpieza y hogar · Cuidado personal · Bebé · Mascotas · Otros**. "Otros" is always last. The order cannot be changed per list. Their ids in code and in the API are English (`fruit-and-vegetables`, `bakery`, `meat-and-fish`, `deli-and-cheese`, `dairy-and-eggs`, `pantry`, `frozen`, `drinks`, `household`, `personal-care`, `baby`, `pets`, `other`); the Spanish names live in `es.json` (PD-8). *(Confirmed by the product owner on 2026-11-22.)* |
| PD-91 | **A product's section comes from its catalog category.** A mapping in the code (Catalog context) turns each chain's category path into a section by its **longest matching prefix** (for example Bonpreu "Frescos > Fruita" → Fruta y verdura, Aldi "Despensa" → Despensa). A product without a category, or with a category the mapping does not know, is in **"Otros"**. A list product removed from the catalog (no supermarket) is in "Otros". The section is worked out when it is read, so extending the mapping moves the items already in lists. *(Confirmed by the product owner on 2026-11-22.)* |
| PD-92 | **Any member can change an item's section.** A free-text item is in "Otros" by default. The owner and the members can choose another section for **any pending item**, free text or product, with a section selector on the item; the choice is stored on that item and every member sees it (last write wins, PD-43). For a product the selector also offers **"Automática (<catalog section>)"**, which removes the choice so the item follows the catalog again (PD-91). *(Confirmed by the product owner on 2026-11-22.)* |
| PD-93 | **The list is always grouped by section.** Unchecked items are shown under a header per section, in the order of PD-90, and inside each section in their list order (PD-45). A section without unchecked items is not shown. The header shows only the section name. Checked items stay in **one separate block at the end**, in their list order, as before. There is no ungrouped view. The supermarket filter (PD-85) shows its items grouped the same way. *(Replaces how the list is shown in PD-45.)* *(Confirmed by the product owner on 2026-11-22.)* |
| PD-94 | **Sections and existing data.** Adding a product already in the list (PD-45) adds the quantity and **keeps the section chosen by hand**. Nothing is migrated: existing free-text items are in "Otros" and existing product items in their catalog section until someone changes them. *(Confirmed by the product owner on 2026-11-22.)* |
| PD-95 | **Finding unmapped categories.** The console command `app:catalog:unmapped-categories` lists, per supermarket, the stored category paths that the mapping sends to "Otros" (PD-91), with their number of products, most products first. It changes nothing. The mapping is extended in a pull request. It gets the `make unmapped-categories` target, which like every target refuses the production stack (PD-60). *(Make target confirmed under PD-61.)* *(Confirmed by the product owner on 2026-11-22.)* |
| PD-96 | **Sections in the list API.** Each item of `GET /api/lists/{id}` carries `section` (the section it is shown in: its own choice, else its catalog section, else `other`) and `sectionOverride` (the section chosen by hand, or `null`). `PATCH /api/lists/{id}/items/{itemId}` accepts `section`: a section id sets the choice, `null` removes it (a free-text item is then in "Otros"). An unknown section id is a `422`. *(Confirmed by the product owner on 2026-11-22.)* |
| PD-97 | **Product images in lists and in the add-item suggestions (E15).** A product item of a list shows its catalog image at **48 px** (`size-12`), between the checkbox and the text. A product without an image, and a product the catalog no longer knows, shows the grey "Sin imagen" box instead. A **free-text item** shows no box, but its text is **indented** so it lines up with the product names. The image of a **checked** item is **faded**, like its struck-through name; an unavailable product keeps its image as is, next to "No disponible" (PD-22). In a list row the image **links to the product page**, like the name (PD-48), but it is not a second tab stop and is not announced twice (the name link already is); a product the catalog no longer knows has no link. In the **suggestions** of the add-item field ([E12 — Product search in lists](epics/E12-product-search-in-lists.md)) each product shows its image at 48 px **without a link**: tapping it picks the product, like the rest of the option. *(Confirmed by the product owner on 2026-11-22.)* |
| PD-98 | **A broken product image falls back to the placeholder.** When a product image URL fails to load (the store removed or moved it), the grey "Sin imagen" box is shown instead of the browser's broken-image icon, everywhere a product image appears: list rows, add-item suggestions, search results and the product page. *(Confirmed by the product owner on 2026-11-22.)* |
| PD-99 | **The dummy catalog stays without images (E15).** The offline development catalog (E8) keeps no image URLs, so development and Playwright show the placeholder. Real images and the broken-image fallback are covered by component tests. *(Confirmed by the product owner on 2026-11-22.)* |
| PD-100 | **Product search runs on Typesense.** A self-hosted Typesense service joins the stack (development, production and the e2e stack); MySQL stays the source of truth and Typesense only holds a **search index rebuilt from it**: after every ingestion run and, when the index is missing or empty, when the stack starts (also with the dummy catalog of E8). The index is derived data, so it is not in the backups. The console command `app:catalog:reindex-search` rebuilds it and reloads the dictionary of PD-101 by hand; `make reindex` runs it (PD-61). Typesense was chosen over a MySQL-only dictionary for its typo tolerance (PD-103). *(Requested in issue #66. Confirmed by the product owner on 2026-11-22.)* |
| PD-101 | **A Spanish ↔ Catalan dictionary of equivalent words**, so a search in either language finds the products of every supermarket (Bonpreu names are Catalan, Mercadona's Spanish, Aldi's mixed): "leche" also finds "llet", "llet" also finds "leche". It is a **versioned file in the repository** (`backend/config/search/synonyms.yaml`), changed through a pull request, and loaded into Typesense as synonyms. An equivalent applies when a typed word **is a whole entry** of the dictionary, and the equivalent is then searched **as a prefix**, like the typed word (PD-33): "pan" finds pan\* and pa\*, "pa" finds pa\* and pan\*, so both include the bread of the three supermarkets, and also noise such as "pasta". A word that is only the start of an entry ("lec") gets no equivalent. *(Confirmed by the product owner on 2026-11-22.)* |
| PD-102 | **The first dictionary covers the basics of each category**: about **150–250 frequent words** of the catalog's categories (dairy, bakery, fruit and vegetables, meat, fish, drinks, cleaning, personal care…), taken from the Bonpreu product names, with singular and plural forms. The product owner reviews the list in its pull request. *(Confirmed by the product owner on 2026-11-22.)* |
| PD-103 | **Typo tolerance, only as a fallback.** A search first runs exactly (prefix matching of PD-33 plus the equivalents of PD-101). **Only when that finds no product at all** does it run again allowing **up to 2 typos** per word ("lece" finds "leche"). Approximate results follow the same grouping, order, supermarket filter and availability rules as exact ones (PD-22, PD-24, PD-33); exact and approximate results are never mixed. *(Amends the matching rule of PD-33. Confirmed by the product owner on 2026-11-22.)* |
| PD-104 | **Approximate results are announced on `/search`.** When the results come from the typo fallback (PD-103), the API says `"approximate": true` and the search page shows "No hay resultados para «{{query}}». Mostrando resultados parecidos." above them. The suggestions of a list's "Qué necesitas" field (PD-80) show no notice. *(Confirmed by the product owner on 2026-11-22.)* |
| PD-105 | **When Typesense does not answer, search fails.** The API answers `503` and the SPA shows the existing search error with "Reintentar" (on `/search` and in the list suggestions of PD-80). There is no fallback to the MySQL search. Everything else, lists included, keeps working. *(Confirmed by the product owner on 2026-11-22.)* |
| PD-106 | **Epics are implemented with `/implement-epic`.** `/implement-epic E<n>` (the `implement-epic` skill) reads the epic, asks the product owner every open question (OQ-n) and every case the epic does not clearly define **before coding**, one at a time, and records each answer as a `PD-n` (or a note under the task). It then implements the pending tasks in order with TDD, one commit per task with its tick, and opens **one pull request for the whole epic**, which the product owner reviews and merges. When two epic files share a number, it asks which one. The procedure lives in `.claude/skills/implement-epic/SKILL.md` and is referenced from `AGENTS.md` (§5). *(Confirmed by the product owner on 2026-11-22.)* |
| PD-107 | **CI filters jobs by the changed files on branches only (E12).** On a push to any branch other than `main`, each CI job runs only when the branch changes files that affect it (PD-109); the jobs that do not apply are reported as **skipped** and CI is still green, so a pull request with only documentation runs no tests. On a push to `main` **every job always runs**, Production images included, so auto-deploy (PD-50) only deploys commits that passed the full check. *(Formerly numbered PD-80, a duplicate id. Confirmed by the product owner on 2026-11-21.)* |
| PD-108 | **The changed files of a branch are those of its pull request**: every file the branch changes since its merge-base with `origin/main`, not only the files of the last push. Adding a docs-only commit to a branch that already changed the backend still runs the backend check. *(Formerly numbered PD-81, a duplicate id. Confirmed by the product owner on 2026-11-21.)* |
| PD-109 | **Which changed files trigger which CI job.** `frontend/**` → Frontend check and End-to-end. `backend/**`, `compose.yaml`, `.env.example`, `docker/php/**` and `docker/mysql/**` → Backend check and End-to-end (`docker/php/entrypoint.sh` also → Deploy scripts, PD-110). `docker/nginx/**` → End-to-end. `deploy/**`, `Makefile` and `docker/bats/**` → Deploy scripts. `.github/workflows/**` and the script that computes the scope → **every job**. **Documentation** (`docs/**`, any `*.md` file anywhere, `.claude/**`, `LICENSE`) → no job. Any other file that matches no rule (for example `compose.prod.yaml`, `.gitignore`, `.editorconfig`) → **every job**, to be safe. End-to-end runs only after the backend and frontend checks have passed or been skipped. *(Formerly numbered PD-82, a duplicate id. Confirmed by the product owner on 2026-11-21.)* |
| PD-110 | **`docker/php/entrypoint.sh` also triggers Deploy scripts.** `deploy/test.sh` ShellChecks it, so on a branch a change to it runs Deploy scripts besides Backend check and End-to-end (PD-109); otherwise a lint error would only show on `main`. *(Confirmed by the product owner on 2026-11-22.)* |
| PD-111 | **`make ci-scope` previews the CI jobs of a branch.** It runs `deploy/ci-scope.sh` (E12.1) on the host and prints which jobs CI would run for the current branch against `origin/main` (PD-108, PD-109). It runs no Docker, so it works without the stack and PD-60 does not apply to it. *(Make target confirmed under PD-61, on 2026-11-22.)* |
| PD-112 | **Receipts are read first by a local AI (E10).** The worker sends the photo, with the receipt's supermarket, to a vision model on an **OpenAI-compatible** server (`POST <base URL>/chat/completions`, as Ollama, llama.cpp, vLLM or LM Studio expose it) and asks for **structured JSON** through `response_format` `json_schema`: date, total, product lines (text, units or kg, amount) and discount lines. That JSON goes straight into the draft, with no chain parser. The reader falls back to the local OCR of PD-62 **only on a technical failure**: AI not configured, network error, timeout, a non-2xx answer, or JSON that does not match the schema or the receipt rules (PD-66). It makes one attempt, with no retry. A valid answer is accepted as it is, even when it is empty or incomplete, and the user fixes it in the review. When the fallback also fails, the draft is empty (PD-65). Tests use recorded AI answers in `backend/tests/Fixtures/`, never a real model. *(Confirmed by the product owner on 2026-11-27.)* |
| PD-113 | **The AI reader is optional and configured by environment.** `RECEIPT_AI_BASE_URL` (empty = OCR only, the default for dev, CI and the tests), `RECEIPT_AI_MODEL`, `RECEIPT_AI_API_KEY` (optional, sent as a Bearer token) and `RECEIPT_AI_TIMEOUT_SECONDS` (default 120). The URL is not validated: the README warns that it must point to an AI the operator runs, because otherwise the receipt photo leaves the server. *(Confirmed by the product owner on 2026-11-27.)* |
| PD-114 | **A receipt records who read it.** `readBy` is `ai`, `ocr` or `none` (both failed). It is stored with the receipt and logged without the receipt text, so AI failures can be diagnosed. It is not shown in the UI. *(Confirmed by the product owner on 2026-11-27.)* |
| PD-115 | **The dummy catalog's sections (E14).** The offline development catalog (E8) maps each top category to the section of the same name, and by the more specific prefix **"Lácteos y huevos > Quesos" and "Carne y pescado > Embutidos" go to Charcutería y quesos**, so every dummy product has a section other than "Otros" and every section but Congelados, Bebé, Mascotas and Otros has products in development. *(Confirmed by the product owner on 2026-11-27.)* |
| PD-116 | **The section selector is a small select in every pending row (E14).** Each pending item shows a compact native select, labelled with the item's name, under its quantity line, with the current section chosen. Checked items show no selector. *(Confirmed by the product owner on 2026-11-27.)* |
| PD-117 | **A checked item can change section through the API.** `PATCH /api/lists/{id}/items/{itemId}` with `section` is accepted (`204`) on a checked item too, and the choice shows once the item is unchecked, so a member who changes a section while someone else checks the item never gets an error (PD-43). Only the UI limits the selector to pending items (PD-116). *(Confirmed by the product owner on 2026-11-27.)* |
| PD-118 | **The catalog section of a list product is in the API too.** The `product` object of each product item of `GET /api/lists/{id}` also carries `section`: the section its catalog category maps to (PD-91), `other` for an unmapped product or one the catalog no longer knows. The selector shows it in "Automática (<catalog section>)" even while another section is chosen by hand (PD-92). *(Extends PD-96.)* *(Confirmed by the product owner on 2026-11-27.)* |

## Stack

| Concern | Choice | Why |
| --- | --- | --- |
| Language (BE) | PHP 8.5 | Required stack. Readonly classes, enums and property hooks fit value objects well. Newest stable branch (PD-16). |
| Framework (BE) | **Symfony 7.4 LTS** | Long-term support. Its DI container and components (Console, Scheduler, HttpClient, Messenger) can stay strictly in the infrastructure layer. It is less intrusive than Laravel for hexagonal design: no Active Record and no facades. |
| Persistence | **Doctrine DBAL** + Doctrine Migrations | Explicit SQL in infrastructure repositories keeps domain classes free of ORM metadata. Bulk inserts for price snapshots are simple and fast. |
| Database | **MySQL 9.7 LTS** | Required stack (PD-16). `utf8mb4_0900_ai_ci` gives accent- and case-insensitive search. FULLTEXT indexes cover product search. |
| HTTP client | Symfony HttpClient | Used only inside supermarket adapters. Supports retries and mock responses for tests. |
| Scheduling | Symfony Scheduler + Console command | Ingestion runs as a console command, triggered by a scheduler worker container. |
| Logging | Monolog (`symfony/monolog-bundle`) | Standard Symfony logging. Structured context, JSON to stderr in production so the container runtime collects it. Used only in Infrastructure, behind application ports. |
| Authentication | Symfony Security (session cookie) + `scheb/2fa-bundle` with `scheb/2fa-totp` + Symfony RateLimiter | The SPA and the API share the same origin (nginx), so an `HttpOnly`, `Secure`, `SameSite=Lax` session cookie is simpler and safer than JWT in browser storage. `scheb/2fa` is the standard Symfony TOTP integration. Login and 2FA attempts are rate-limited. Passwords are hashed with Symfony's `auto` hasher (bcrypt/argon2id). All of this lives in Infrastructure; the Identity domain is framework-free. |
| Language (FE) | TypeScript (strict) | Required stack. |
| Framework (FE) | **React + Vite** | Mature ecosystem. Fits atomic design and the container/presentational pattern. Vite gives fast dev builds and a static output that nginx serves. |
| Server state (FE) | TanStack Query | Caching, background refetch and **polling** (`refetchInterval`) out of the box, which covers PD-7. |
| Routing (FE) | React Router | Covers `/setup`, `/login`, `/activate/:token`, `/`, `/search`, `/product/:id`, `/lists/:id`, `/join/:token`, `/admin/users`, with route guards for authenticated and admin areas. |
| i18n (FE) | react-i18next | Covers PD-8. |
| Styling (FE) | **Tailwind CSS** (Vite plugin) + `class-variance-authority` + `tailwind-merge` | Design tokens (colors, spacing, typography, radii) are defined once in the Tailwind theme. Only values from that scale are used, which keeps the UI consistent. Component variants are declared in one place with `cva`. Class conflicts are resolved with `tailwind-merge`. No runtime CSS-in-JS. |
| BE quality | PHPUnit, PHPStan (max), **Deptrac** (layer rules), PHP-CS-Fixer, Infection (mutation testing, optional gate) | Deptrac enforces the hexagonal dependency rule in CI. |
| FE quality | Vitest, Testing Library, MSW, Playwright, ESLint, Prettier, `tsc --noEmit` | |
| Runtime | Docker Compose: `nginx`, `php` (fpm), `scheduler`, `mysql` | Single-host self-hosting. |

### Pinned versions

Pinned in [E0](epics/E0-foundation.md) to the latest stable patch, checked on 2026-11-06 against the official sources. Bump them deliberately, one at a time, with the full `composer check` passing.

| Component | Version | Pinned in | Source |
| --- | --- | --- | --- |
| PHP (fpm, Alpine image) | 8.5.12 | `docker/php/Dockerfile`, `config.platform` in `backend/composer.json` | [php.net](https://www.php.net/supported-versions.php) |
| Symfony | 7.4 LTS (7.4.23 at pin time) | `extra.symfony.require: 7.4.*` in `backend/composer.json`; exact patch in `composer.lock` | [symfony.com/releases](https://symfony.com/releases) |
| MySQL | 9.7.4 LTS | `compose.yaml`, `server_version` in `backend/config/packages/doctrine.yaml` | [dev.mysql.com](https://dev.mysql.com/doc/relnotes/mysql/9.7/en/) |
| nginx | 1.30.6 (stable) | `compose.yaml` | [nginx.org](https://nginx.org/en/download.html) |
| Composer | 2.11.1 | `docker/php/Dockerfile` | [getcomposer.org](https://getcomposer.org/download/) |
| Xdebug (optional) | 3.5.3 | `docker/php/Dockerfile` | [pecl.php.net](https://pecl.php.net/package/xdebug) |

| Node.js | 26.13.0 LTS ("Lithium") | `frontend/.nvmrc`, `engines` in `frontend/package.json` | [nodejs.org](https://nodejs.org/en/about/previous-releases) |
| React | 19.3.1 | `frontend/package.json` (exact) | [npm](https://www.npmjs.com/package/react) |
| Vite | 8.3.6 | `frontend/package.json` (exact) | [npm](https://www.npmjs.com/package/vite) |
| TypeScript | 6.0.3 | `frontend/package.json` (exact) | [npm](https://www.npmjs.com/package/typescript) |

Backend libraries (PHPUnit, PHPStan, PHP-CS-Fixer, Deptrac, Doctrine) are locked in `backend/composer.lock`. Every frontend dependency is pinned to an exact version in `frontend/package.json` and locked in `frontend/package-lock.json`.

TypeScript stays on **6.0** on purpose: TypeScript 7 (7.0.2 at pin time) is not yet supported by `typescript-eslint` (it requires `<6.1`). Move to 7 when `typescript-eslint` supports it.

## Architecture style

**Hexagonal architecture (ports and adapters)** combined with **screaming architecture**: top-level folders are named after business capabilities, not technical layers.

```mermaid
flowchart TB
    subgraph DrivingAdapters["Infrastructure: driving adapters"]
        Driving["HTTP controllers, Console commands, Scheduler"]
    end

    subgraph ApplicationLayer["Application"]
        App["Use cases, DTOs, application ports"]
    end

    subgraph DomainLayer["Domain: pure PHP"]
        Dom["Entities, Value objects, Domain services, Ports"]
    end

    subgraph DrivenAdapters["Infrastructure: driven adapters"]
        Driven["DBAL repositories, Supermarket HTTP sources, Clock, IdGenerator"]
    end

    Driving -->|calls| App
    App -->|uses| Dom
    Driven -.->|implements ports| Dom
```

Solid arrows are calls, and dashed arrows are interface implementations. Every dependency points **inwards**: Domain knows nothing about the other boxes.

### Dependency rules (enforced by Deptrac)

| Layer | May depend on | Must never depend on |
| --- | --- | --- |
| `Domain` | `Shared\Domain` only | Application, Infrastructure, Symfony, Doctrine, any vendor |
| `Application` | Domain, `Shared\Domain`, `Shared\Application` | Infrastructure, Symfony, Doctrine |
| `Infrastructure` | Everything above + vendor | — |
| Context A | Context B **only** through B's `Application` public API (query services) | B's Domain or Infrastructure |

Allowed cross-context paths today: ShoppingList Infrastructure → Catalog Application (`GetProductsHandler`) and Identity Application (`FindUsernamesHandler`).

### Rules of thumb

- **Ports** are interfaces owned by the inner layer (`Domain` for repositories and domain-facing collaborators, `Application` for application concerns such as a transaction manager).
- **Adapters** live in `Infrastructure` and implement ports.
- **Use cases** have one public method (`__invoke`), receive a command/query DTO and return a DTO or nothing. They never return entities to controllers.
- **Entities** protect their invariants. No public setters. They are created through named constructors.
- **Value objects** are immutable, `final readonly` and self-validating.
- **Money** is stored as integer cents. Floats are never used for money.
- **Time and IDs** come from ports (`Clock`, `IdGenerator`) so tests stay deterministic.
- **Controllers** only map HTTP ↔ DTOs and call a use case.

## Bounded contexts

| Context | Responsibility | Main aggregates |
| --- | --- | --- |
| **Catalog** | Supermarkets, their products, current prices, price history, ingestion from external sources, search and comparison. | `Supermarket`, `Product` |
| **Identity** | Users, roles, activation links, password, mandatory TOTP 2FA, login. | `User` |
| **ShoppingList** | Lists with owner and members, joining through the secret link, items (free text or product reference), checking items, per-supermarket totals. | `ShoppingList` |
| **Shared** | Shared kernel: `Money`, `Uuid`, `UserId`, `Clock`, base exceptions. Kept minimal. | — |

`ShoppingList` reads product data (name, price, supermarket) through a **Catalog query port** (`ProductCatalog` interface in ShoppingList's Application layer, implemented in Infrastructure by calling Catalog's application query service). It never imports Catalog domain classes.

`ShoppingList` only knows users by `UserId` (shared kernel). The current user reaches use cases as an explicit `actorId` in each command/query. Use cases never read Symfony's security token; the controller extracts the id and passes it in. Display names of members are read through a `UserDirectory` port implemented on top of Identity's application query service.

## Domain model

### Catalog

```text
Supermarket (aggregate root)
  id: SupermarketId            e.g. "mercadona", "bonpreu", "aldi" (stable slug)
  name: string                 enabled or not is configuration (PD-24), not state

Product (aggregate root)
  id: ProductId                internal UUID
  supermarketId: SupermarketId
  externalId: ExternalProductId  id in the supermarket's store (unique per supermarket)
  details: ProductDetails       name: ProductName, brand: ?string, category: ?CategoryPath, imageUrl: ?Url
  packaging: ?Packaging        quantity + unit (e.g. 1 L, 500 g, 6 units); null when unknown
  currentPrice: Money
  unitPrice: ?UnitPrice        Money per UnitOfMeasure (kg | L | unit), always derived from
                               currentPrice and packaging; null when packaging is unknown
  lastSeenAt: DateTimeImmutable
  available: bool              false when the last ingestion did not return it

PriceSnapshot (append-only, created by Product::captureSnapshot)
  productId, price: Money, unitPrice: ?UnitPrice, capturedAt

Section (enum, E14)            the app's store sections in display order, "other" last (PD-90)
```

Value objects: `Money` (cents and currency, EUR only for now), `Packaging`, `UnitOfMeasure` (enum: `KILOGRAM`, `LITRE`, `UNIT`), `UnitPrice`, `ProductName`, `ExternalProductId`, `PostalCode`.

**Unit normalization:** grams → kg, ml/cl → L, and "pack of N" → unit. A source's own unit price is used only for approximate sizes (PD-29); otherwise ours is the only one. If packaging cannot be parsed, the product is stored **without** a unit price and ranked last. That is an explicit state, not a guess.

How it is modelled:

- `Packaging` stores the quantity as an **integer number of thousandths** of its `UnitOfMeasure` (500 g → 500 thousandths of a kg, 33 cl → 330 thousandths of a L, 12 units → 12 000), so no float is ever used. Named constructors (`kilograms`, `grams`, `litres`, `centilitres`, `millilitres`, `units`) take decimal strings with a dot. A quantity finer than a thousandth (for example `0.5 g`) is rejected, not rounded. `multipliedBy(n)` models multipacks (`6 x 33 cl`).
- `PackagingParser` (domain service) accepts only strings that match a known format completely: an optional `pack`/`pack de` prefix, an optional `N x` multiplier, a quantity with a comma or dot decimal, and a unit (`kg`, `g`/`gr`, `l`/`lt`/`litro(s)`, `cl`, `ml`, `u`/`ud`/`uds`/`unidad(es)`). Anything else (approximate weights such as `1,2 kg aprox.`, ranges, unknown words) returns `null`, the explicit "unknown" state. New formats are added to its table-driven test as each supermarket spike finds them.
- `UnitPriceCalculator` computes `price ÷ normalized quantity` rounded half-up to whole cents (PD-21).
- `SectionMapping` (E14, PD-91) turns a supermarket id and a `CategoryPath` into a `Section` by the longest prefix of whole segments in its rules; a missing path, an unknown path and a supermarket without rules are `other`. The rules of the dummy catalog (PD-115) apply to the three chains, whose own rules come on top. The section is never stored: `DbalProductViewReadModel` works it out on every read, so extending the rules moves the items already in lists. `CategoryPath::fromString` reads back the stored `A > B` form.
- `PostalCode` accepts five digits whose first two are a Spanish province (`01`–`52`).

**Ports (Catalog Domain):**

- `ProductRepository`: `save` (insert or update), `findBySupermarketAndExternalId`, `findAvailableNotSeenSince(supermarketId, instant)` (the products a run did not see). Search is added in E2.
- `PriceSnapshotRepository`: `append(...snapshots)` in one batch; `deleteCapturedBefore(cutoff)` deletes older snapshots but never the latest of a product (PD-46). The history of a product is read through the application read model `PriceHistoryReadModel` (E5).
- `SupermarketCatalogSource`: `supermarketId()` and `fetchCatalog(PostalCode): iterable<SourceProduct>`. **One adapter per chain.** `SourceProduct` carries plain values (external id, name, price in cents, packaging text, brand, category segments, image URL and, for approximate sizes only, a reference unit price in cents with its unit `kg`/`l`/`unit`), so one malformed product does not stop a source from returning the rest of its catalog.

`Product` is created with `register` and changed only through `updateFromSource` (new details, price and packaging; makes it available again) and `markUnavailable`. `Supermarket` is created with `create(id, name)`; `SupermarketId` is a slug of lowercase letters, digits and inner hyphens.

### Identity

```text
User (aggregate root)
  id: UserId
  username: Username                  PD-39; unique, case-insensitive, normalized to lowercase
  role: Role                          ADMIN | USER
  status: UserStatus                  PENDING_ACTIVATION | ACTIVE | DISABLED
  passwordHash: ?PasswordHash         null until set (hashing is a port, never done in the domain)
  totpSecret: ?EncryptedTotpSecret    confirmed secret (encrypted at rest) + totpConfirmedAt; required before ACTIVE
  pendingTotpSecret: ?EncryptedTotpSecret  enrolled but not confirmed yet
  activation: ?ActivationToken        SHA-256 hash of the one-time token + expiresAt (72 h, PD-34); null once used
  createdAt, createdBy: ?UserId       createdBy is null for the first admin (PD-14)
```

Lifecycle: `PENDING_ACTIVATION` → (valid activation link → set password → scan the TOTP QR code → confirm a code) → `ACTIVE`. Only the **hash** of the activation token is stored. The plain token appears only once, in the link shown to the admin.

`User::createPending` is followed by `setInitialPassword`, `startTotpEnrollment` (starting again replaces the unconfirmed secret) and `confirmTotp`. Each step checks the activation link again, so an expired link cannot finish an activation already started; an unknown, expired or used link is an `ActivationLinkNotValid` (404), and a step out of order is an `ActivationStepOutOfOrder` (409). Checking the TOTP code needs the decrypted secret, so the application layer does it through the ports before `confirmTotp`.

Value objects: `Username` (PD-39), `PlainPassword` (a new password, checked against PD-37; its errors never include the password), `TotpCode` (six digits, spaces ignored), `ActivationToken`, `PasswordHash`, `EncryptedTotpSecret`.

Ports (`Identity\Domain\Port`): `UserRepository` (`save`, `findById`, `findByUsername`, `findByIds`, `findAll` ordered by username, `findByActivationTokenHash`, `countActiveAdmins`, `hasAnyUser`), `PasswordHasher` (`hash(PlainPassword)`, `verify(hash, typed text)`), `TotpService` (`generateSecret`, `provisioningUri`, `verify(secret, code, now)`), `SecretEncrypter` (`encrypt`, `decrypt`) and `ActivationTokenGenerator` (`generate(): string`).

Use cases (`Identity\Application`). Admin use cases first check the actor through `AdminActor`: anyone but an active admin gets `AdminRequired` (404, PD-15).

- `CreateUserHandler` (admin): username + role → user pending activation; returns the **plain activation token once** with its expiry. A taken username (any case) is a `UsernameAlreadyTaken` (409).
- `ValidateActivationTokenHandler`: token → username and expiry of a valid link.
- `SetInitialPasswordHandler`: token + password (PD-37) → stores the hash and starts the TOTP enrollment; returns the secret and the `otpauth://` provisioning URI. Calling it again gives a new secret.
- `ConfirmTotpEnrollmentHandler`: token + code → checks the code against the pending secret (`TotpCodeRejected`, 422, when wrong), activates the user and returns its id.
- `ListUsersHandler` (admin): every user, ordered by username, with role, status and the expiry of a pending activation link.
- `FindUsernamesHandler`: the query API other contexts use (E3.9): user ids → usernames; unknown or malformed ids are left out.
- `RegenerateActivationLinkHandler` (admin, PD-34): pending users only (`UserStateConflict`, 409, otherwise). The new link has a fresh 72 h and drops a half-finished activation.
- `ResetUserHandler` (admin, PD-35): back to pending activation, credentials removed, sessions ended; returns the new link. Not on oneself (`CannotManageYourself`, 409) and not on a disabled user (enable first). `ResetUserByUsernameHandler` does the same for the server operator, without an actor (console only).
- `DisableUserHandler`, `EnableUserHandler`, `ChangeUserRoleHandler` (admin, PD-36): only an active user can be disabled and only a disabled one enabled. Disabling ends the user's sessions. Disabling or demoting the last active admin is a `LastActiveAdmin` (409); an admin cannot disable themselves.
- `ChangePasswordHandler`, `StartTotpReEnrollmentHandler`, `ConfirmTotpReEnrollmentHandler` (the user themselves, PD-38): changing the password and starting a re-enrollment ask for the current password and a code (`CredentialsRejected`, 422, the same for both). The old device works until a code from the new one is confirmed. Both changes return the new session version.

**Ending sessions.** `User` has a `sessionVersion` (starting at 1) that a reset, disabling, a new password or a new TOTP device increases. A session stores the version it started with and stops working when it no longer matches; the session that changed its own password or device moves to the new version and stays logged in.

**Ports (Identity):** `UserRepository`, `PasswordHasher`, `TotpService` (generate secret, verify code), `ActivationTokenGenerator`, `SecretEncrypter`.

### ShoppingList

```text
ShoppingList (aggregate root)
  id: ShoppingListId           internal UUID
  ownerId: UserId
  members: Member[]            userId + joinedAt of users who joined through the link (owner excluded), in join order
  shareToken: ShareToken       base64url, 22–64 characters (≥128 bits); the "secret link" used only to JOIN
  name: ListName               1–80 characters after trimming (PD-45)
  items: ListItem[]            in position order; an item's position is its index
  createdAt, updatedAt
  version: int                 moves on every real change (ETag, optimistic lock); loadedVersion is the one it was loaded with (0 = new)

ListItem (entity inside the aggregate)
  id: ListItemId
  content: FreeTextItem(ItemText, 1–120 characters) | ProductItem(ProductId)
  quantity: Quantity           whole number 1–99, 1 by default (PD-45)
  checked: bool
```

`ProductItem` keeps only the product id: name, price and supermarket are always read fresh from the catalog.

**Access rules:** owner and members can read and edit items. Only the owner can rename the list, remove members, regenerate the share token and delete the list (PD-44). A member can leave; the owner cannot (`OwnerCannotLeave`, 409). Anyone else, and a member trying an owner-only action, gets `ShoppingListNotFound` (404, PD-15). Joining with a valid token is idempotent, joining your own list is a no-op, and a removed member can join again. Operations state their intent (PD-43): `check`/`uncheck` set the flag, and a call that changes nothing does not move the version. `moveItem` takes a 0-based position; a position past the end moves the item last, a negative one is `InvalidItemPosition` (422).

**Ports (ShoppingList Domain):** `ShoppingListRepository` (`save` with the version check, throwing `ShoppingListChangedConcurrently`; `remove`; `findById`; `findByShareToken`; `findByUser`, owned or joined) and `ShareTokenGenerator`. **Ports (ShoppingList Application):** `ProductCatalog` (`findByIds` → `CatalogProduct`, available or not) and `UserDirectory` (`usernamesOf`).

**Use cases** (`ShoppingList\Application`), each with an explicit `actorId`: `CreateList`, `JoinList`, `ManageList/{RenameList, RegenerateShareToken, RemoveMember, LeaveList, DeleteList}`, `EditItems/{AddItem, UpdateItem, RemoveItem, ClearCheckedItems}`, and the queries `GetList` and `GetMyLists`. Changing use cases go through `ListUpdater`: load, apply the operation, save; when someone else saved first it runs the whole operation again on a fresh copy, up to 5 attempts (PD-43). Adding a product checks through `ProductCatalog` that it exists and is available (`ProductNotAvailable`, 422).

**Totals:** the estimated total per supermarket is the sum of `price × quantity` of the **available** product items of that supermarket, checked or not (PD-45); unavailable products are flagged and left out (PD-22). Free-text items are listed but not priced. It is computed in `GetList` with a fresh price from the `ProductCatalog` port.

**Adapters (ShoppingList Infrastructure):** `DbalShoppingListRepository` (`UPDATE … WHERE version = :loaded`, then members and items rewritten in the same transaction), `RandomShareTokenGenerator` (`random_bytes(32)`, base64url, 43 characters), `CatalogProductCatalog` on Catalog's `GetProductsHandler` (ids → `ProductView`s, one lookup per product) and `IdentityUserDirectory` on Identity's `FindUsernamesHandler`. Controllers take the actor id from the session user's identifier (`UserInterface::getUserIdentifier()`), so they do not depend on Identity's infrastructure.

## Backend structure

```text
backend/
├── src/
│   ├── Catalog/
│   │   ├── Domain/
│   │   │   ├── Model/           # Supermarket, Product, PriceSnapshot
│   │   │   ├── ValueObject/     # Packaging, UnitPrice, UnitOfMeasure...
│   │   │   ├── Port/            # ProductRepository, SupermarketCatalogSource...
│   │   │   ├── Service/         # UnitPriceCalculator, SectionMapping (pure)
│   │   │   └── Exception/
│   │   ├── Application/
│   │   │   ├── IngestCatalog/   # IngestCatalogCommand + IngestCatalogHandler
│   │   │   ├── SearchProducts/  # SearchProductsQuery + handler + result DTO
│   │   │   ├── GetProduct/      # GetProductQuery + handler + ProductView; GetProductsHandler (query API for lists)
│   │   │   ├── ListSupermarkets/ # ListSupermarketsQuery + handler
│   │   │   ├── PrunePriceHistory/ # PrunePriceHistoryCommand + handler (PD-46)
│   │   │   ├── FindUnmappedCategories/ # query + handler + StoredCategories port (PD-95)
│   │   │   └── GetPriceHistory/ # GetPriceHistoryQuery (PriceHistoryRange, PD-47) + handler + PriceHistory DTO
│   │   └── Infrastructure/
│   │       ├── Persistence/Dbal/
│   │       ├── Source/Mercadona/
│   │       ├── Source/BonpreuEsclat/
│   │       ├── Source/Aldi/
│   │       ├── Api/             # controllers
│   │       ├── Http/            # HTTP client of the supermarket sources
│   │       ├── Scheduler/       # CatalogSchedule: ingestion and price history prune
│   │       └── Console/         # app:catalog:ingest, app:catalog:prune-history, app:catalog:unmapped-categories
│   ├── Identity/
│   │   ├── Domain/ Application/ Infrastructure/   # same layout; Symfony Security + scheb/2fa adapters live in Infrastructure/Security
│   ├── ShoppingList/
│   │   ├── Domain/ Application/ Infrastructure/   # same layout
│   └── Shared/
│       ├── Domain/              # Clock port, ValueObject/ (Money, Currency, Uuid), Exception/ (PD-18)
│       ├── Application/
│       └── Infrastructure/      # SystemClock, RandomTokenGenerator, Symfony kernel glue
├── tests/
│   ├── Unit/                    # Domain + Application, in-memory fakes, no I/O
│   ├── Integration/             # DBAL repos vs real MySQL, adapters vs recorded fixtures
│   ├── Functional/              # HTTP API through Symfony kernel
│   └── Fixtures/                # recorded supermarket responses (JSON/HTML)
├── migrations/
├── config/
└── deptrac.yaml
```

Test folders mirror `src/` (for example, `tests/Unit/Catalog/Domain/ValueObject/PackagingTest.php`). Reusable test doubles of shared ports live in `tests/Support/` (for example `FixedClock`).

### Shared kernel

| Class | Notes |
| --- | --- |
| `Shared\Domain\ValueObject\Money` | Integer cents, `Currency::EUR` only. `eur()`, `add()`, `multiply(int)`, `equals()`, `compareTo()`, `isGreaterThan()`, `isLessThan()`. Never negative (PD-19). |
| `Shared\Domain\ValueObject\Uuid` | Canonical `8-4-4-4-12` hex string, normalized to lowercase. It only validates; generation belongs to an `IdGenerator` port. |
| `Shared\Domain\ValueObject\UserId` | Wraps a `Uuid`. Other contexts know users only by this id. |
| `Shared\Domain\Clock` | Port returning the current `DateTimeImmutable`. `Shared\Infrastructure\Clock\SystemClock` implements it in **UTC**; tests use `Tests\Support\FixedClock`. |
| `Shared\Domain\IdGenerator` | Port returning a new `Uuid`. `Shared\Infrastructure\Id\UuidV7Generator` implements it with time-ordered UUIDv7 (`symfony/uid`); tests use `Tests\Support\SequentialIdGenerator`. |

## Frontend structure

Feature-first (screaming) folders. Each feature has a light hexagonal split. Shared UI follows **atomic design**, and screens follow **container/presentational**.

```text
frontend/src/
├── features/
│   ├── catalog/
│   │   ├── domain/          # types + pure functions (formatUnitPrice, isCheapest)
│   │   ├── application/     # hooks/use cases (useProductSearch) depending on ports
│   │   ├── infrastructure/  # API client implementing the port (fetch), DTO mappers
│   │   └── ui/
│   │       ├── containers/  # SearchPageContainer: wires hooks → presentational
│   │       └── components/  # ProductResultList, PriceTag... (props only, no fetching)
│   ├── auth/                # login, activation, 2FA setup, session (current user)
│   ├── admin/               # user management
│   └── shopping-list/       # same layout
├── shared/
│   ├── ui/                  # atoms/ molecules/ organisms/ templates/
│   ├── i18n/                # react-i18next setup + locales/es.json
│   └── http/                # base fetch wrapper, error mapping, 401 → login redirect
├── app/                     # router (with auth/admin guards), providers, layout
└── main.tsx
```

Rules:

- The `@/` import alias points to `src/`.
- Boundaries inside a feature are enforced by ESLint (`no-restricted-imports` in `frontend/eslint.config.js`): `domain/` cannot import React, `application/`, `infrastructure/` or `ui/`; `application/` cannot import `infrastructure/` or `ui/`.
- i18n: `createI18n()` in `shared/i18n` loads `locales/es.json`. In tests, any key rendered without a translation fails the test (`src/test/setup.ts`).
- Test helpers live in `src/test/`: `renderWithProviders`, the MSW `server` (unhandled requests fail the test), the test i18n instance and in-memory fakes of each port (`FakeProductCatalog` knows `products` and answers price histories with `respondHistory`). The test query client has `retry: false` and `retryDelay: 0`, so a hook with its own retry policy fails at once.
- Presentational components get data through props, render it, and emit events. They never fetch.
- Containers hold state and data access. They contain no styling logic and no Tailwind classes beyond layout.
- Styling uses Tailwind utilities **with theme tokens only**. Arbitrary values (`w-[13px]`, `text-[#3a3a3a]`) are not allowed. If a value is missing, add it to the theme.
- Repeated class combinations become an atom or molecule (with `cva` variants), never a copy-pasted string. `@apply` is avoided.
- Theme tokens live in `@theme` in `frontend/src/index.css`. Tailwind's default colors, font sizes, fonts and radii are **reset**, so only these tokens exist:
  - Colors: `background`, `surface`, `border`, `foreground`, `muted`, `primary` (+ `-hover`, `-foreground`), `danger` (+ `-hover`, `-foreground`), `focus`, and one per supermarket with a readable foreground: `mercadona`, `bonpreu`, `aldi` (+ `-foreground`) (PD-20).
  - `qr` and `qr-background` are fixed (black on white in both themes) and are only used by `QrCode` (PD-78).
  - **Dark theme** (E11, PD-74 to PD-79): `:root[data-theme='dark']` redefines every color token except the QR ones, with `color-scheme: dark` (`:root` has `color-scheme: light`), so native controls follow the theme. Components use the same token names in both themes and never `dark:` variants. `shared/ui/theme.test.ts` checks that every token has a dark value and that every text/background pair the UI uses reaches WCAG AA (4.5:1) in both themes, with `contrastRatio()` from `shared/ui/contrast.ts`. A new token needs a dark value, and a new text/background pair goes into that test.
  - Font sizes `xs`–`3xl`, font `sans`, radii `sm`, `md`, `lg`, `full`, and the spacing unit `--spacing: 0.25rem`.
  - A new token must also be added to `cn()` (`shared/ui/lib/cn.ts`) so `tailwind-merge` resolves conflicts correctly.
- `cn()` joins classes and lets the last conflicting utility win. Atoms (`shared/ui/atoms`): `Alert` (an error message with `role="alert"`), `QrCode` (an SVG QR code of `value`, dark on light with a 4-module quiet zone, from `qrcode`; PD-41), `Button` (`variant`: primary, secondary, danger; `size`: sm, md), `Input` (`invalid`), `Label`, `Select` (native, same look as `Input`), `Text` (`as`, `size`, `tone`: default, muted, danger; `weight`). Molecules (`shared/ui/molecules`): `FormField` (a labelled `Input` with an optional `hint` and `error`, both linked with `aria-describedby`; an error marks the input invalid), `SearchBox` (controlled `role="search"` form with a labelled search box; a submit button only when `onSubmit` is given; Enter never reloads) and `Pagination` (previous / next and a status text; the caller passes the copy). Shared components take their copy as props; feature components read it with `t()`.
- `prettier-plugin-tailwindcss` sorts classes, also inside `cva()` and `cn()`.
- HTTP: `createHttpClient({ baseUrl })` in `shared/http` wraps `fetch` (`get`, `post`, `put`, `patch`, `delete`). It sends same-origin cookies and JSON, adds `X-Requested-With: XMLHttpRequest` to every request that is not a `GET` (CSRF, E3.18), resolves with the parsed body as `unknown` (`undefined` when empty) and rejects with typed errors: `UnauthorizedError` (401), `NotFoundError` (404), `ConflictError` (409), `ValidationError` (422), `TooManyRequestsError` (429), `ServerError` (5xx), `HttpError` for any other status, and `NetworkError` when no response arrives. `getConditional(path, { etag })` sends `If-None-Match` and resolves with `{ notModified: true }` on a `304` or `{ notModified: false, body, etag }`; the list page polls with it (PD-7). Each error carries the parsed `problem` (falling back to the status when the body is not problem+json). Feature `infrastructure/` maps the `unknown` body to domain types; the UI picks Spanish copy from the error class, never from `problem.detail`.
- Unexpected API bodies: feature `infrastructure/` rejects a successful response whose body does not have the expected shape with `UnexpectedResponseError` (`shared/http/errors.ts`).
- Ports of a feature live in its `application/` and are injected with a React context (e.g. `ProductCatalogContext`), so hooks never import `infrastructure/` and tests pass an in-memory fake.
- Catalog feature (`features/catalog`):
  - `domain/`: types (`Product`, `ProductDetail`, `ProductSearchPage`, `Supermarket`, `Packaging`, `UnitPrice`, `Unit`, `CheapestUnitPriceCents`) and pure functions `formatPrice(cents)` (`1,25 €`), `formatUnitPrice(unitPrice, unitSymbols)` (`0,83 €/L`, `9,00 €/kg`, `0,30 €/ud`), `formatPackaging(packaging, unitSymbols)` (`1,5 L`, `0,5 kg`, `6 ud`) and `isCheapest(product, cheapestUnitPriceCents)` (lowest unit price of its unit over the whole query, PD-33). Price history (`domain/priceHistory.ts`, E5.6): `PriceHistory` and `PricePoint` (`capturedAt` as a `Date`, shelf `priceCents`, `unitPrice`), the periods `30d`, `6m` (default), `1y`, `all` (PD-48), `collapseEqualPrices(points)` (keeps the first point of each run of equal prices and always the last one, so a run is one flat segment that reaches the latest snapshot), `priceRange(points)` (`{minCents, maxCents}` or `null`) and `periodStart(period, now)` (the first day of the period as `YYYY-MM-DD` in Europe/Madrid, a day past the end of a shorter month becoming its last day; `null` for `all`). Formats use `Intl` with `es-ES`. The unit symbols (`kg`, `L`, `ud`) are UI copy: they live in `es.json` (`catalog.units`) and `ui/useUnitSymbols()` reads them for the formatters.
  - `application/`: port `ProductCatalog` (`searchProducts`, `getProduct`, `listSupermarkets`, `getPriceHistory(productId, {from?, to?})`), `ProductCatalogContext` / `useProductCatalog()` (the app provides `createCatalogApi(createHttpClient({ baseUrl: window.location.origin }))` through `App`'s `productCatalog` prop), `useSupermarkets()` (enabled supermarkets for the filter; an empty list while loading or on failure, so the filter offers only "all"), and `useProductSearch({ text, supermarket?, page? })` on TanStack Query. The initial text is searched at once (landing on a search URL); later changes of the text wait 300 ms without typing (`SEARCH_DEBOUNCE_MS`); the filter and the page apply at once. A trimmed text shorter than 2 characters (`MIN_SEARCH_LENGTH`) does not query and is `idle` at once, without the debounce (clearing the box); a valid text still waiting for the debounce is `loading`. Pagination is page-based (`page` input, `keepPreviousData` keeps the previous page on screen while the next loads); the caller resets the page when the text or the filter changes. It returns `{ status: 'idle' | 'loading' | 'error' | 'success' }`, with `error` and `retry()` on error, and `data` and `isFetching` on success. `useProduct(id)`: `loading`, `notFound` (a `404` is not retried), `error` with `retry`, or `success` with the product. `usePriceHistory(id, period, now?)` asks for `from = periodStart(period, now)` (no `to`) and keeps the previous history on screen (`isFetching`) while another period loads.
  - `infrastructure/`: `createCatalogApi(httpClient)` implements `ProductCatalog` over `GET /api/products`, `GET /api/products/{id}`, `GET /api/products/{id}/price-history` and `GET /api/supermarkets`, validating every body into domain types (an unparsable `capturedAt` is an `UnexpectedResponseError`).
  - `ui/components/`: `SupermarketBadge` (atom; the chain's theme colors, neutral for any other), `ProductImage` (atom; the catalog image, lazy by default, or a grey `role="img"` "Sin imagen" placeholder when the URL is `null` or fails to load, PD-98; `cva` variants `size` `sm` 48 px / `md` 64 px / `lg` 96 px and `faded`; used by `ProductCard` at `md`, `ProductDetailView` at `lg` with eager loading, and by the shopping list's `ListItemRow` and `ProductSuggestions` at `sm`, PD-97), `SupermarketFilter` (molecule; "Todos" + the enabled supermarkets), `PriceTag` (molecule; shelf price and unit price, `highlighted` variant with a "Más barato" label), `ProductCard` (organism; `ProductImage`, name linking to the product page, brand, badge, packaging, `PriceTag`) and `ProductResultList` (organism; a labelled list that highlights each product for which `isCheapest(product, cheapestUnitPriceCents)` holds, PD-33). Product page (E5.7, PD-48, PD-49): `PriceHistoryChart` (organism; an SVG step line in a 600 × 200 view box that scales with its width, after `collapseEqualPrices`, from the first to the last snapshot; one snapshot is a single point; `role="img"` with the period and the min and max as its name, and the first and last day below), `PriceHistoryPeriodSelector` (molecule; the four periods as pressed / not pressed buttons), `PriceSummary` (molecule; a `<dl>` of minimum, maximum and current price, only the current one without snapshots), `PriceHistoryPanel` (organism; "Evolución del precio": selector, then loading, error with retry, "no prices in this period" or chart, and the summary; `aria-busy` while another period loads), `ProductDetailView` (organism; the product, an "unavailable" flag, the actions and the history) and `ProductNotFound`.
  - `domain/productPath(id)` (`/product/<id>`) is how other features link to the product page: the search cards and the product items of a list (`ListItemRow`; not a product the catalog no longer knows).
  - `ui/containers/ProductPageContainer` is `/product/:productId`: `useProduct` and `usePriceHistory` with the period in local state (`6m` by default). It shows loading, "Producto no encontrado" for a `404`, an error with retry, or `ProductDetailView` with `PriceHistoryPanel`. "Añadir a una lista" comes from `renderProductActions` (wired in `app/routes.tsx` like the search page) and is offered only for an available product.
  - `ui/containers/SearchPageContainer` is the `/search?q=&supermarket=&page=` page. The URL is the source of truth: typing writes `q` with a history *replace* and drops `page`; choosing a supermarket ("Todos" removes the param) and changing page push a new entry, and the filter also drops `page`. The first page is never written as `page=1`. The search box keeps its text in local state (the URL commits a moment after each key press) and follows the URL on Back / Forward. States: idle (type at least 2 characters), loading, error (with retry), empty ("No hay resultados para «…»"), results (total, list, pagination; the previous page stays on screen with `aria-busy` while the next loads). An optional `list` parameter (PD-82) is kept on every URL change and handed, untouched, to `renderProductActions(product, listId)` and `renderListLink(listId)`: the catalog does not know what it means, `app/routes.tsx` wires it to the shopping-list feature.
- Auth feature (`features/auth`):
  - `domain/`: `CurrentUser` (`id`, `username`, `role`), `Role` (`ADMIN`, `USER`), `isAdmin(user)`, `ActivationLink` (`username`, `expiresAt`), `TotpEnrollment` (`secret`, `provisioningUri`), `meetsPasswordPolicy(password)` (PD-37, the same rules as the API's `PlainPassword`, counting characters, not bytes) and `safeRedirectTarget(next)`: the page to open after login. Only a path of this app is accepted (never another site, a `//host` URL or the login page itself); anything else is `/`.
  - `application/`: port `AuthGateway`, injected with `AuthGatewayContext` (`App`'s `authGateway` prop): session (`getCurrentUser` → the user or `null`, `logIn`, `verifyLoginCode`, `logOut`), activation (`getActivationLink`, `setInitialPassword`, `confirmTotpEnrollment`), setup (`isSetupRequired`, `completeSetup`) and profile (`changePassword`, `startTotpReEnrollment`, `confirmTotpReEnrollment`). Hooks:
    - `useCurrentUser()` loads the session once: `loading`, `error` with `retry`, `anonymous` (with `loggedOut` when the user logged out on purpose) or `authenticated` with `user`. `useSetCurrentUser()` stores the user after a login or an activation. `useEndSessionOnUnauthorized()` watches every query and mutation: any `401` makes the session anonymous.
    - `useLogin()` runs the two login steps (`invalidCredentials`, `invalidCode`, `tooManyAttempts`, `failed`). `useLogout()` ends the session (a `401` counts as logged out; a network error keeps it and says so) and drops the cached data of that user.
    - `useActivation(token)`: `checking`, `checkFailed` (retry), `invalid` (a `404` at any step), `password`, `totp`, then `activated`, which also logs the user in.
    - `useSetupStatus(enabled)` asks `GET /api/setup` once, only for an anonymous visitor; a failed check lets the app carry on. `useCompleteSetup()` creates the first admin and returns their activation token.
    - `useChangePassword()` checks PD-37 before sending, so a `422` means a wrong current password or code. `useTotpReEnrollment()`: `proof` (password + current code), then `confirm` (the new device's code, or cancel); a `409` (nothing to confirm) starts over.
  - `infrastructure/`: `createAuthApi(httpClient)` over `/api/auth/*`, `/api/setup` and `/api/me/*` (a `401` on `/api/auth/me` is `null`; a `404` on `GET /api/setup` means not required).
  - `ui/containers/`:
    - `LoginPageContainer` (`/login?next=`): username and password, then the TOTP code ("Volver" goes back and clears the password). Once logged in, or when already logged in, it goes to `safeRedirectTarget(next)`.
    - `ActivationPageContainer` (`/activate/:token`): greets the user, asks for a password (with the policy as hint), shows the QR code and the manual secret, asks for a code, then opens `/`. An unknown, expired or used link shows "Este enlace no funciona".
    - `SetupPageContainer` (`/setup`): setup code + username of the first admin (PD-14), then their `/activate/:token`. Once users exist it goes to `/`.
    - `ProfilePageContainer` (`/profile`, PD-38): "Cambiar contraseña" (current password, current code, new password) and "Cambiar de app de autenticación" (proof, then the new device). Both say the other sessions ended.
    - `SessionMenuContainer`, in the app header: the username, "Mi perfil", "Usuarios" for an ADMIN only (PD-15) and "Cerrar sesión".
  - `ui/components/`: `LoginCredentialsForm`, `LoginCodeForm`, `NewPasswordForm`, `TotpEnrollmentForm` (QR code, manual secret, code; optional cancel), `SetupForm`, `ChangePasswordForm`, `TotpProofForm`, `SessionMenu`.
- Admin feature (`features/admin`, ADMIN only):
  - `domain/`: `AdminUser` (`id`, `username`, `role`, `status`: `PENDING_ACTIVATION` / `ACTIVE` / `DISABLED`, `createdAt`, `activationExpiresAt`), `ActivationLinkGrant` (a link shown once), `activationPath(grant)` and `formatDateTime(date)` (`13/11/2026, 10:05`, in the browser's time zone).
  - `application/`: port `UserAdministration` (`listUsers`, `createUser`, `regenerateActivationLink`, `resetUser`, `disableUser`, `enableUser`, `changeRole`), injected with `UserAdministrationContext` (`App`'s `userAdministration` prop). `useUsers()`, `useCreateUser()` (`taken` on 409, `invalid` on 422) and `useUserActions()` (one action at a time; a `409` is a rule of PD-34 to PD-36 and gets its own message). The list reloads after each change.
  - `infrastructure/`: `createAdminApi(httpClient)` over `/api/admin/users/*`.
  - `ui/`: `UsersPageContainer` is `/admin/users`: the create form, the users (`UserList`) with their role, status and link expiry, and per user the actions that apply (`UserActionButtons`, PD-42). A new link (create, regenerate or reset) appears once in `ActivationLinkPanel` as the full URL with "Copiar enlace", until "Hecho".
- Route guards (`app/guards`), from the outside in:
  - `RequireSetupDone` wraps `/login` and every page that needs a session. While a fresh instance has no users it sends everyone to `/setup` (PD-14). `/setup` and `/activate/:token` are outside it.
  - `RequireAuth` wraps every route except `/login`, `/setup` and `/activate/:token`. While the session loads it shows "Cargando…". An anonymous visitor goes to `/login?next=<path + query>` (just `/login` from `/` or after a logout), so a `/join/:token` link comes back after login. It also calls `useEndSessionOnUnauthorized()`, so a session that ends while the app is open sends the user to the login page.
  - `RequireAdmin` goes inside `RequireAuth` (around `/admin/users`) and renders the not-found page to a non-admin, never a "forbidden" message (PD-15).
- Theme feature (`features/theme`, E11):
  - `domain/`: `ThemePreference` (`system`, `light`, `dark`), `Theme` (`light`, `dark`), `parseThemePreference(value)` (anything unknown or missing is `system`, PD-75) and `resolveTheme(preference, systemPrefersDark)` (`system` follows the system; no system preference counts as light, PD-74).
  - `application/`: ports `ThemePreferenceStore` (`read`, `write`) and `SystemColorScheme` (`prefersDark`, `subscribe`). `ThemePreferenceProvider` wraps the router in `App` (whose `themePreferenceStore` and `systemColorScheme` props default to the browser adapters; tests pass `InMemoryThemePreferenceStore` and `FakeSystemColorScheme`). It sets `data-theme` on `<html>`, and while the preference is `system` it repaints when the system scheme changes, on every page, the login included. `useThemePreference()` returns `preference` and `setPreference`.
  - `infrastructure/`: `createLocalStorageThemePreferenceStore()` under the key `jolo-market.theme` (`THEME_STORAGE_KEY`); `system` removes the key, and an unavailable `localStorage` reads `system` and never throws. `createMatchMediaSystemColorScheme()` reads `(prefers-color-scheme: dark)`; without `matchMedia` the system is light.
  - **Before the first paint** (PD-75), the inline `<script id="theme-boot">` in the `<head>` of `frontend/index.html` reads the same key and `matchMedia` and sets `data-theme`, so no page flashes the wrong theme. It mirrors `resolveTheme()`; `themeBootScript.test.ts` runs it for every preference and system scheme and compares the result.
  - `ui/`: `ThemeSelect` (a labelled `Select`: "Tema" with "Sistema", "Claro", "Oscuro") and `ThemeSelectContainer`, rendered in the `AppShell` header next to the session menu (PD-76).
- Home (`features/home`): the home page has a search box that opens `/search?q=…`, and below it "Mis listas" with the create-list form.
- Shopping-list feature (`features/shopping-list`):
  - `domain/`: `ShoppingList`, `ListItem` (text or product; a product the catalog no longer knows has `null` fields; every item has its `section` and `sectionOverride`, and its product the catalog `section`, PD-96, PD-118), `ListSummary`, `ListRole`, `sections.ts` (`SECTIONS`: the thirteen section ids in the order of PD-90, `SectionId`, `isSectionId`, and `groupBySection(items)`: the unchecked items grouped by `section` in that order, list order inside each group and no empty group, plus the checked items apart in list order, PD-93; the supermarket filter passes its products through it too), pure functions in `supermarketFilter.ts` for the supermarket filter (`supermarketChips`: one per entry of `totals`, PD-84; `isSupermarketChip`, PD-86; `itemsOfSupermarket`: that chain's products, available or not, PD-85; `hiddenWithoutSupermarket`: free-text items and products without a supermarket, PD-85), pure functions in `suggestions.ts` for the add-item suggestions (`suggestionOptions`: the text option, then at most 10 products, PD-80; `highlightAfter`: arrow-key highlight that wraps and clears on a new text, PD-81; `searchResultsPath(text, listId)`), and pure functions `itemsForDisplay` (unchecked first, then checked, each in `position` order, PD-45), `isOwner`, `withItemChecked`, `unpricedItems` (unavailable products, left out of the totals), `joinPath(token)` and the PD-45 checks for name, text and quantity.
  - `application/`: port `ShoppingLists` over every `/api/lists` route, injected with `ShoppingListsContext` (`App`'s `shoppingLists` prop). `useShoppingList(id)` polls every 5 s only while the page is visible, keeps the ETag and treats a `304` as unchanged; a `404` is `notFound`. `useMyLists`, `useCreateList`, `useJoinList`, `useListItems` (add, quantity, section (`changeSection(itemId, section | null)` → `updateItemSection`, PD-92), remove; check/uncheck is shown at once and undone if the API fails, PD-43), `useAddProductToList` and `useListManagement` (regenerate link, remove member, delete, leave).
  - `infrastructure/`: `createShoppingListApi(httpClient)`, validating every body into domain types.
  - `ui/containers/`: `MyListsContainer` (home), `ListPageContainer` (`/lists/:listId`: items grouped by section (a `SectionHeader` and a list named after the section per group, then the checked items in one list at the end, all inside the "Elementos de la lista" list, PD-93), add form with catalog suggestions from the catalog `useProductSearch` (PD-80, PD-81), the supermarket filter read from and written to the `supermarket` parameter (`/lists/:listId?supermarket=<supermarketId>`; choosing a chip pushes a history entry, "Todos" removes the parameter, and a value that names no chip is replaced out of the URL, PD-86) with the notice of hidden items without a supermarket (PD-85), totals per supermarket, and for the owner the share link, members panel and delete; for a member "Abandonar lista"), `JoinPageContainer` (`/join/:token`: joins and opens the list; an invalid or revoked link shows "Este enlace no funciona") `AddToListContainer` (the "Añadir a una lista" picker on each search result, wired in `app/routes.tsx` through `SearchPageContainer`'s `renderProductActions`, so the catalog never imports this feature; with the search's `list` and that list among "My lists" it is a single "Añadir a la lista" button that adds straight to it, PD-82) and `BackToListContainer` ("Volver a <list name>" on `/search?list=`, nothing for an unknown or inaccessible list). Deleting the list and regenerating the link ask first (PD-42 style).
  - `ui/components/`: `MyListsList`, `CreateListForm`, `ListItemRow` (the name of a known product links to its page; a product item shows its `ProductImage` at 48 px between the checkbox and the text, faded once checked, and for a known product wrapped in a link to its page with `tabIndex={-1}` and `aria-hidden`, so it is neither a second tab stop nor announced twice; a free-text item gets an empty spacer of the same width, PD-97; a pending item shows a compact native `Select` (`size="sm"`) labelled "Sección de <name>" under its quantity line with the thirteen sections, and for a product first "Automática (<catalog section>)"; it emits `onSectionChange(section | null)`, `null` for "Automática" and for "Otros" on a free-text item, PD-92, PD-116), `SectionHeader` (an `h2` with only the section name, PD-93), `AddItemForm` (the text field is an accessible combobox: `aria-expanded`, `aria-controls`, `aria-activedescendant`; ArrowDown / ArrowUp highlight, Enter adds the highlighted product or the free text, Escape closes), `ProductSuggestions` (the `listbox` panel: text option, products with their `ProductImage` at 48 px (not a link and hidden from assistive technology, so tapping it picks the product, PD-97), the catalog `SupermarketBadge` and `PriceTag`, "Ver todos los resultados", loading, empty and error-with-retry states), `BackToListLink`, `SupermarketFilter` (a `group` of toggle chips: "Todos" and one per supermarket with its `SupermarketBadge` and total, the chosen one with `aria-pressed`; it emits `onSelect(supermarketId | null)`), `SupermarketTotals`, `ShareLinkButton`, `MembersPanel`, `ListActions`, `ListNotFound`, `JoinStatus`, `AddToListPicker`. Shared atom `Checkbox`.
- `domain/` has no React imports.
- API DTOs are mapped to domain types in `infrastructure/`. Components never see raw API shapes.
- All user-visible strings go through `t()`. No hard-coded Spanish in components.

## Key flows

### 1. Catalog ingestion

```text
scheduler → app:catalog:ingest [--supermarket=mercadona]
  → IngestCatalogHandler
      for each supermarket in ENABLED_SUPERMARKETS (or only the requested one, which must be enabled):
        save IngestionRun (running)
        source = SupermarketCatalogSource(supermarket)
        for each SourceProduct from source.fetchCatalog(INSTANCE_POSTAL_CODE):
          skip it if its external id was already seen in this run
          validate it; a malformed product is skipped and counted (PD-23)
          parse packaging → unit price (unknown packaging → no unit price)
          upsert Product (by supermarketId + externalId)
          queue a PriceSnapshot (appended in batches of 500)
        zero valid products → the run fails
        mark products not seen in this run as unavailable (only on success, PD-25)
        save IngestionRun (succeeded or failed, with counts and error)
      a failure in one supermarket does not abort the others
```

`IngestionRun` (Catalog Application, port `IngestionRunRepository`) records each run: supermarket, `startedAt`, `finishedAt`, status (`running`, `succeeded`, `failed`), `productsSeen`, `productsSkipped`, `productsMarkedUnavailable` and `error`. A run left as `running` was interrupted. `IngestionSettings` (Application port) gives the instance postal code and the enabled supermarkets. The handler returns an `IngestCatalogResult` with one summary per run.

Adapter tests run against **recorded fixtures**. Tests never call a real supermarket.

### 2. Search and compare

`GET /api/products?q=leche entera` → FULLTEXT search over available products (PD-22) of enabled supermarkets → grouped by unit and ranked by `unitPrice` ascending (PD-33, products without a unit price go last) → paginated, 20 per page.

`SearchProductsHandler` (Catalog Application) takes a `SearchProductsQuery` (`text` of at least 2 characters after trimming, optional `supermarket` slug, `page` ≥ 1, `perPage` ≥ 1; `InvalidSearchProductsQuery` otherwise). It searches the supermarkets of `ENABLED_SUPERMARKETS` (port `EnabledSupermarkets`, PD-24), or only the one of the filter, which must be enabled (`SupermarketNotEnabled` otherwise). The port `ProductSearchReadModel` (adapter `DbalProductSearchReadModel`) returns a `ProductSearchPage`: the `ProductSearchResult` DTOs of the page, the `total` of the whole query and `cheapestUnitPriceCentsByUnit` (lowest unit price per unit over the whole query, for the highlight of PD-33).

- **Matching:** every word of the text must match the start of a word of the name or brand, ignoring case and accents. Words are split on anything that is not a letter or a digit, so FULLTEXT operators typed by the user are ignored. Each word becomes a required prefix term (`+word*`) of `MATCH (name, brand) AGAINST (… IN BOOLEAN MODE)` on the `utf8mb4_0900_ai_ci` columns. Words InnoDB does not index (shorter than `innodb_ft_min_token_size` = 3, or a default stopword such as "de" or "la") are matched with `LIKE '% word%'` on the name and brand instead.
- **Order (PD-33), in SQL so pages are stable:** products without a unit price last; units with more results first (units with as many results as `kg`, `l`, `unit`); then unit price, shelf price, name and id ascending.

`SearchProductsController` (Catalog Infrastructure `Api`) maps `q`, `supermarket` and `page` to the query with a fixed page size of 20; a `page` that is not an integer is an `InvalidQueryParameter` (422). Search results and the product detail share one JSON shape (`ProductJson`):

```json
{
  "items": [
    {
      "id": "0190f3c2-…", "name": "Leche entera", "brand": "Hacendado",
      "supermarket": { "id": "mercadona", "name": "Mercadona" },
      "packaging": { "quantity": 1.5, "unit": "l" },
      "priceCents": 125,
      "unitPrice": { "cents": 83, "unit": "l" },
      "imageUrl": "https://…"
    }
  ],
  "page": 1, "perPage": 20, "total": 37,
  "cheapestUnitPriceCents": { "l": 70, "kg": 900 }
}
```

`brand`, `packaging`, `unitPrice` and `imageUrl` may be `null`. Units are `kg`, `l` or `unit`. `packaging.quantity` is a decimal number (stored in thousandths). `cheapestUnitPriceCents` holds the lowest unit price of each unit over the whole query, so the SPA highlights the cheapest of each group (PD-33); it is `{}` when nothing matches.

`GET /api/products/{id}` → `GetProductHandler` (`GetProductQuery`, port `ProductViewReadModel`, adapter `DbalProductViewReadModel`) returns the same shape plus `"available": bool`. It returns a product **even when it is unavailable**, because lists still show it (PD-22). An unknown or malformed id is a `ProductNotFound` (404).

`GET /api/supermarkets` → `ListSupermarketsHandler` returns the supermarkets of `ENABLED_SUPERMARKETS` (port `EnabledSupermarkets`, PD-24) in that order, with their names from the `supermarket` table (port `SupermarketNames`, adapter `DbalSupermarketNames`): `[{"id":"mercadona","name":"Mercadona"}, …]`. An enabled slug without a `supermarket` row is left out.

`GET /api/products/{id}/price-history` → `GetPriceHistoryHandler` (`GetPriceHistoryQuery`; port `PriceHistoryReadModel`, adapter `DbalPriceHistoryReadModel`) returns the snapshots of the product oldest first (E5, PD-47). The query turns `from` and `to` into a `PriceHistoryRange`: the start of `from` and the start of the day after `to`, in Europe/Madrid, as UTC instants (`captured_at >= from AND captured_at < before`, an index range scan on `(product_id, captured_at)`); a malformed day or `from` after `to` is an `InvalidPriceHistoryRange` (422). An unknown or malformed product id is a `ProductNotFound` (404), checked through `ProductViewReadModel`, so an unavailable product keeps its history (PD-22).

```json
{
  "productId": "0190f3c2-…",
  "points": [
    { "capturedAt": "2026-11-08T04:00:00+00:00", "priceCents": 125, "unitPrice": { "cents": 83, "unit": "l" } },
    { "capturedAt": "2026-11-09T04:00:00+00:00", "priceCents": 129, "unitPrice": null }
  ]
}
```

`capturedAt` is UTC (ISO 8601). `unitPrice` is `null` when the packaging was unknown at that run.

These endpoints are **public** until E3.21 puts them behind login.

### 3. User onboarding

```mermaid
sequenceDiagram
    actor Admin
    actor User
    participant SPA
    participant API

    Admin->>SPA: Create user "maria"
    SPA->>API: POST /api/admin/users
    API-->>SPA: activation link (shown once)
    Admin-->>User: Sends the link (any channel)
    User->>SPA: Opens /activate/{token}
    SPA->>API: POST /api/auth/activation/password {token, password}
    API-->>SPA: TOTP secret + QR code
    User->>SPA: Scans QR, enters 6-digit code
    SPA->>API: POST /api/auth/activation/totp {token, code}
    API-->>SPA: User ACTIVE, session started
```

### 4. Login

1. `POST /api/auth/login` `{username, password}` → `LogInHandler`. On success the API answers `202 {"twoFactorRequired": true}`, gives the session a new id and remembers the user as **2FA-pending** for five minutes. Unknown user, wrong password, pending or disabled user: the same `401` (`InvalidCredentials`, detail `Invalid credentials.`).
2. `POST /api/auth/2fa` `{code}` → `VerifyLoginCodeHandler`. On success the session is fully authenticated (`Security::login()`) and the API answers `200 {id, username, role}`. A wrong code or no pending login: the same `401`.
3. Every other `/api` route requires a fully authenticated session. Otherwise it returns `401` problem+json, and the SPA redirects to `/login`.

Security wiring (E3.17 to E3.19, technical decision confirmed by the product owner on 2026-11-09: no `scheb/2fa-bundle`):

- The `main` firewall (`^/api/`) has no request authenticator: the controllers call the Identity use cases and then `Security::login()`, backed by `ProgrammaticLoginAuthenticator`. `SessionUserProvider` reloads the `SecurityUser` (id, username, role, session version) through `FindSessionUserHandler` on every request; a user who is no longer active, or whose `sessionVersion` or role changed, loses the session (so a token never keeps the roles of a demoted admin). `ProblemJsonAuthenticationEntryPoint` answers `401`.
- Session cookie: `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS (`auto`), 7 days. `SessionInactivityListener` ends a session after 7 days without requests and re-sends the cookie at most once an hour with the **same** session id and 7 more days (PD-37, PD-40). Symfony only sends the cookie when the id changes, so the listener adds it to the response itself.
- TOTP replay: a code is accepted once. `TotpService::matchingTimeStep` returns the 30-second step of a valid code and `User::claimTotpStep` rejects a step that is not after the last one used (`user.totp_last_used_step`). This covers login, activation, profile checks and re-enrollment.
- CSRF (E3.18): `RequireCustomHeaderListener` rejects every `POST`/`PUT`/`PATCH`/`DELETE` under `/api/` without `X-Requested-With: XMLHttpRequest` (`403`). Another site cannot add that header without a CORS preflight, which the API never allows.
- Rate limits (E3.19, `config/packages/rate_limiter.yaml`, sliding windows of 15 minutes): login 5 per username and 20 per IP; 2FA code 5 per user and 20 per IP; profile changes (`/api/me/*`) 5 per user and 20 per IP; activation code 20 per IP; setup code 10 per IP. Every attempt counts and a successful login clears the per-user counter. Over the limit: `429` with `Retry-After`.

### 5. Shared list via secret link

1. `POST /api/lists` creates a list owned by the current user.
2. The owner copies the link `/join/{shareToken}` and sends it.
3. A logged-in user opening it calls `POST /api/lists/join/{shareToken}`, becomes a member and is redirected to `/lists/{id}`. A user who is not logged in goes through login first and comes back to the link.
4. Lists are then accessed by **id**, and only owner and members are authorized. The token is used only to join.
5. While the list is open, the SPA polls `GET /api/lists/{id}` every ~5 s with `If-None-Match`. The server answers `304` when `version` has not changed.

## HTTP API (draft)

JSON over REST under `/api`. Errors use RFC 9457 `application/problem+json` (PD-18):

```json
{ "type": "about:blank", "title": "Not Found", "status": 404, "detail": "Shopping list \"42\" was not found." }
```

| Thrown | Status | `detail` |
| --- | --- | --- |
| `Shared\Domain\Exception\Unauthorized` subclass | `401` | Exception message |
| `Shared\Domain\Exception\NotFound` subclass | `404` | Exception message |
| `Shared\Domain\Exception\InvalidArgument` subclass | `422` | Exception message |
| `Shared\Domain\Exception\Conflict` subclass | `409` | Exception message |
| Symfony `HttpException` (unknown route, wrong method...) | Its own status | — |
| Anything else | `500` | — (never exposed; the error is logged) |

Domain error messages are English and must be safe to show (no secrets, no internals). The SPA shows Spanish copy chosen from `status`, never the raw `detail`. The rendering lives in `Shared\Infrastructure\Http\ProblemJsonExceptionListener`.

All routes require a fully authenticated session (login + 2FA) except those marked **public**. Every `POST`/`PUT`/`PATCH`/`DELETE` needs the header `X-Requested-With: XMLHttpRequest` (CSRF, E3.18), or the API answers `403`. A wrong JSON body is a `422`.

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/api/health` | **public** — `200 {"status":"ok"}` when the database answers, `503 {"status":"unavailable"}` otherwise (PD-17) |
| POST | `/api/auth/login` | **public** — `{username, password}` → `202`, 2FA-pending session; `401` on any failure; rate-limited |
| POST | `/api/auth/2fa` | **public**, needs a 2FA-pending session — `{code}` → `200 {id, username, role}`, authenticated; `401` otherwise; rate-limited |
| POST | `/api/auth/logout` | End session → `204` |
| GET | `/api/auth/me` | Current user (id, username, role) |
| GET | `/api/setup` | **public** — `200 {required: true}` while there are no users; `404` once any user exists |
| POST | `/api/setup` | **public**, only while there are no users — `{code, username}` → `201 {id, username, activationToken, activationExpiresAt}` (first `ADMIN`, pending activation; the code is used up). Wrong code → `422`; users exist → `404`; 10 attempts per IP per 15 min |
| POST | `/api/auth/activation` | **public** — `{token}` → `{username, expiresAt}` of a valid link; unknown, expired or used → `404`. The token goes in the body, never in a URL, so it stays out of logs |
| POST | `/api/auth/activation/password` | **public** — `{token, password}` (PD-37, else `422`) → `{secret, provisioningUri}` (`Cache-Control: no-store`) |
| POST | `/api/auth/activation/totp` | **public** — `{token, code}` → activates the user, logs them in and answers `200 {id, username, role}`; wrong code → `422`; rate-limited |
| GET | `/api/admin/users` | ADMIN — `[{id, username, role, status, createdAt, activationExpiresAt}]`, ordered by username |
| POST | `/api/admin/users` | ADMIN — `{username, role}` → `201 {id, username, activationToken, activationExpiresAt}` (shown once, `no-store`; the SPA builds `/activate/{token}`). Taken username → `409`; invalid → `422` |
| POST | `/api/admin/users/{id}/activation-link` | ADMIN — new link for a pending user (PD-34) → `{id, username, activationToken, activationExpiresAt}`; not pending → `409` |
| POST | `/api/admin/users/{id}/reset` | ADMIN — reset (PD-35) → same shape as above; own account or disabled user → `409` |
| POST | `/api/admin/users/{id}/disable` | ADMIN — `204`; own account, last active admin or not active → `409` (PD-36) |
| POST | `/api/admin/users/{id}/enable` | ADMIN — `204`; not disabled → `409` |
| PUT | `/api/admin/users/{id}/role` | ADMIN — `{role}` → `204`; demoting the last active admin → `409` |
| POST | `/api/me/password` | `{currentPassword, code, newPassword}` → `204`; other sessions end, this one stays (PD-38). Wrong password or code → `422`; rate-limited |
| POST | `/api/me/totp` | `{currentPassword, code}` → `{secret, provisioningUri}` of the new device (`no-store`); rate-limited |
| POST | `/api/me/totp/confirm` | `{code}` from the new device → `204`; other sessions end, this one stays |
| GET | `/api/supermarkets` | enabled supermarkets, in `ENABLED_SUPERMARKETS` order: `[{id, name}]` |
| GET | `/api/products?q=&supermarket=&page=` | search available products, grouped by unit and ranked by unit price (PD-33), 20 per page. `q` (≥ 2 characters after trimming) is required; `page` ≥ 1 defaults to 1; `supermarket` must be enabled. Invalid input → `422` |
| GET | `/api/products/{id}` | product detail with `available`, also for unavailable products (PD-22). Unknown or malformed id → `404` |
| GET | `/api/products/{id}/price-history?from=&to=` | price snapshots of the product, oldest first, also for unavailable products: `{productId, points: [{capturedAt, priceCents, unitPrice}]}`. `from`/`to` are optional `YYYY-MM-DD` days in Europe/Madrid, both inclusive (PD-47). Malformed date or `from` after `to` → `422`; unknown or malformed id → `404` |
| GET | `/api/lists` | Lists the current user owns or is a member of: `[{id, name, role, ownerUsername, itemCount, updatedAt}]` (`role` is `owner` or `member`), most recently updated first |
| POST | `/api/lists` | `{name}` (PD-45) → `201 {id}`; the current user is the owner. Invalid name → `422` |
| POST | `/api/lists/join/{shareToken}` | Join a list through its secret link → `200 {id}` (idempotent; the owner joining is a no-op). Unknown, malformed or revoked token → `404` |
| GET | `/api/lists/{id}` | The list (shape below), with `ETag: "<version>"`; `If-None-Match` with the current ETag → `304` |
| PATCH | `/api/lists/{id}` | Owner — `{name}` → `204` |
| DELETE | `/api/lists/{id}` | Owner — delete the list for everyone (PD-44) → `204` |
| POST | `/api/lists/{id}/share-token` | Owner — regenerate the secret link → `200 {shareToken}`; the old token stops working, members stay |
| DELETE | `/api/lists/{id}/members/{userId}` | Owner — remove a member → `204`; a user who is not a member → `404` |
| POST | `/api/lists/{id}/leave` | Member — leave the list (PD-44) → `204`; the owner → `409` |
| POST | `/api/lists/{id}/items` | `{type: "text", text, quantity?}` or `{type: "product", productId, quantity?}` (PD-45) → `201 {id}`. A product already in the list and unchecked gets the quantity added instead (`201` with that item's id). Unknown or unavailable product, invalid text or quantity → `422` |
| PATCH | `/api/lists/{id}/items/{itemId}` | Any of `{quantity, checked, position, section}` → `204`. `position` is the 0-based index to move the item to. `section` is a section id that sets the item's section by hand, or `null` that removes the choice (PD-92, PD-96); a checked item accepts it too (PD-117). Unknown section id → `422`. Unknown item → `404` |
| DELETE | `/api/lists/{id}/items/{itemId}` | Remove item → `204`; unknown item → `404` |
| DELETE | `/api/lists/{id}/items?checked=true` | Clear checked items → `204` |

List routes (`/api/lists/{id}…`) answer `404` to anyone who is neither owner nor member, and owner-only routes also answer `404` to members (same rule as admin areas, PD-15). Every change increments the list `version`.

`GET /api/lists/{id}` returns:

```json
{
  "id": "0193…", "name": "Compra semanal", "role": "owner", "version": 7,
  "shareToken": "kq3…",
  "owner": { "id": "0192…", "username": "maria" },
  "members": [{ "id": "0192…", "username": "joan" }],
  "items": [
    { "id": "0193…", "position": 0, "quantity": 2, "checked": false, "type": "text", "text": "pan", "product": null,
      "section": "bakery", "sectionOverride": "bakery" },
    { "id": "0193…", "position": 1, "quantity": 1, "checked": true, "type": "product", "text": null,
      "product": { "id": "0191…", "name": "Leche entera", "brand": "Hacendado", "supermarketId": "mercadona", "priceCents": 125, "imageUrl": null, "available": true, "section": "dairy-and-eggs" },
      "section": "dairy-and-eggs", "sectionOverride": null }
  ],
  "totals": [{ "supermarketId": "mercadona", "supermarketName": "Mercadona", "totalCents": 125, "itemCount": 1 }]
}
```

`shareToken` is `null` for members. Items come in `position` order (the SPA groups the unchecked ones by section and shows the checked ones at the end, PD-93). Each item's `section` is the section it is shown in: `sectionOverride` (the one chosen by hand, PD-92) if set, else the catalog section of its product, else `other`; `product.section` is always the catalog section (`other` for a product the catalog no longer knows), which the selector names in "Automática (…)" (PD-96, PD-118). Section ids are those of PD-90. `totals` has one entry per supermarket with at least one available product item, ordered by supermarket name; unavailable products are flagged and left out (PD-22). A product the catalog no longer knows is shown as unavailable with `name: null`.

A list the user cannot access returns `404` (never `403`), and so do unknown, invalid or revoked share and activation tokens. The API never reveals whether a resource exists.

## Persistence

Tables (draft, finalized in migrations):

| Table | Key columns |
| --- | --- |
| `supermarket` | `id` (slug PK), `name` — seeded with `mercadona`, `bonpreu`, `aldi` |
| `product` | `id` (BINARY(16)), `supermarket_id`, `external_id`, `name`, `brand`, `category` (`A > B`), `image_url`, `package_quantity` (thousandths), `package_unit`, `price_cents`, `unit_price_cents`, `unit_price_unit`, `available`, `last_seen_at` — UNIQUE(`supermarket_id`, `external_id`), INDEX(`supermarket_id`, `available`, `last_seen_at`), FULLTEXT(`name`, `brand`) |
| `price_snapshot` | `id` (auto-increment), `product_id`, `price_cents`, `unit_price_cents`, `unit_price_unit`, `captured_at` — INDEX(`product_id`, `captured_at`) |
| `ingestion_run` | `id`, `supermarket_id`, `started_at`, `finished_at`, `status`, `products_seen`, `products_skipped`, `products_marked_unavailable`, `error` |
| `setup_code` | `code_hash`, `created_at` — at most one row, deleted when the first admin is created |
| `user` | `id`, `username` UNIQUE, `role`, `status`, `password_hash`, `totp_secret_encrypted`, `totp_confirmed_at`, `pending_totp_secret_encrypted`, `activation_token_hash` UNIQUE, `activation_expires_at`, `session_version`, `totp_last_used_step`, `created_at`, `created_by` (FK to `user`) |
| `setup_code` | `id` (always 1), `code_hash`, `created_at` — the current first-admin setup code (PD-14) |
| `shopping_list` | `id` (BINARY(16)), `owner_id`, `share_token` (ascii_bin, case-sensitive) UNIQUE, `name` (80), `version`, `created_at`, `updated_at` — INDEX(`owner_id`) |
| `list_member` | `list_id`, `user_id`, `joined_at` — PK(`list_id`, `user_id`), INDEX(`user_id`), FK `list_id` ON DELETE CASCADE |
| `list_item` | `id`, `list_id`, `type` (`free_text`/`product`), `text` (120), `product_id`, `quantity`, `checked`, `section` (the section chosen by hand, PD-92; `NULL` follows the catalog, so existing rows needed no migration, PD-94), `position` (0-based) — INDEX(`list_id`, `position`), FK `list_id` ON DELETE CASCADE. `owner_id`, `user_id` and `product_id` belong to other contexts and have no foreign key |

Charset and collation: `utf8mb4` / `utf8mb4_0900_ai_ci`.

## Testing strategy

| Level | Scope | Tooling | I/O |
| --- | --- | --- | --- |
| Unit | Domain and application (use cases with in-memory port fakes) | PHPUnit / Vitest | None |
| Integration | DBAL repositories, supermarket adapters (recorded fixtures), FE API clients (MSW) | PHPUnit + MySQL test DB / Vitest + MSW | Real DB, fake HTTP |
| Functional | HTTP API end to end through the Symfony kernel | PHPUnit `WebTestCase` | Real DB |
| Component | Presentational and container components | Vitest + Testing Library | MSW |
| E2E | Critical journeys (activation → login with 2FA → search → product page with price history → add to list → join through link) | Playwright (TOTP codes computed from the secret the API returns, `e2e/support/totp.ts`) | Full stack in Docker |
| Shell scripts | `deploy/` scripts against temporary git repositories (bare `origin.git` reached through `url.<path>.insteadOf`, a `dev` clone that pushes "merges", a `server` clone that deploys), with fake `docker`, `gh` and `curl` on the `PATH` (PD-51); the root `Makefile` (`deploy/tests/makefile.bats`) in a temporary copy with fake `docker` and `npm` | bats-core 1.11.1 (`docker/bats`) + ShellCheck 0.10.0, run by `deploy/test.sh` | Temporary folders |
| Architecture | Layer dependency rules | Deptrac (BE), ESLint import rules (FE) | — |

Playwright runs three projects in order:

1. `onboarding` (`e2e/onboarding.spec.ts`, E3.29): on a fresh instance it goes through `/setup` with a code from `docker compose exec php bin/console app:setup:ensure-code` (the command the php container runs at startup), activates `e2e-admin` through the UI and saves its credentials. On an instance an earlier run set up, it logs in as that admin instead. Then the admin creates a user, the user activates the account in another browser, logs out and logs in again with a TOTP code. Codes are single-use, so a login right after an activation waits for the next 30-second step (`nextUnusedCode`).
2. `session` (`e2e/session.setup.ts`): leaves a logged-in admin session (`storageState`) for the other tests. It reuses the saved session, or logs in with the saved credentials (and creates the admin through the API if no onboarding ran first).
3. `chromium`: every other journey, starting logged in. Tests that need no session reset `storageState`.

The session and the credentials live in the system temp folder (`jolo-market-e2e/<base URL>/`, outside the repository), so later runs against the same instance reuse them. TOTP codes are computed from the secret (`e2e/support/totp.ts`).

The TDD workflow is defined in [`AGENTS.md`](../AGENTS.md).

## Runtime and deployment

```mermaid
flowchart LR
    browser["Browser<br/>(React SPA)"]
    proxy["User's reverse proxy<br/>TLS termination<br/>(outside this stack)"]

    subgraph compose["Docker Compose"]
        nginx["<b>nginx</b><br/>Serves the SPA build (static files)<br/>Proxies /api → php"]
        php["<b>php</b><br/>php-fpm + Symfony API"]
        scheduler["<b>scheduler</b><br/>Same image as php<br/>messenger:consume scheduler_default"]
        mysql[("<b>mysql</b><br/>MySQL 9.7")]
        volume[("mysql-data<br/>named volume")]
    end

    stores["Supermarket online stores<br/>Mercadona · Bonpreu i Esclat · Aldi"]

    browser -->|HTTPS| proxy
    proxy -->|HTTP| nginx
    nginx -->|FastCGI /api| php
    php -->|SQL| mysql
    scheduler -->|SQL| mysql
    scheduler -->|HTTPS catalog ingestion| stores
    mysql --- volume
```

| Service | Role |
| --- | --- |
| `nginx` | Serves the frontend build and proxies `/api` to php-fpm. It is the only exposed port. |
| `php` | Symfony API (php-fpm). It never calls supermarkets during a request. |
| `scheduler` | Runs scheduled jobs (catalog ingestion). It uses the same image and code as `php`. |
| `mysql` | Database, persisted in a named volume. Not exposed outside the Compose network. |

In development, a `frontend` service runs the Vite dev server (HMR), and nginx proxies to it instead of serving the static build.

Configuration lives in `.env` (`APP_SECRET`, `DATABASE_URL`, `INSTANCE_POSTAL_CODE`, `ENABLED_SUPERMARKETS`, `INGESTION_SCHEDULE`, `PRICE_HISTORY_RETENTION_MONTHS`, `PRICE_HISTORY_PRUNE_SCHEDULE`, `TOTP_ENCRYPTION_KEY`).

### Production stack

`compose.prod.yaml` (E6) has the same project name (`jolo-market`) and `mysql-data` volume as `compose.yaml`, so switching stacks keeps the database. Every service restarts `unless-stopped`.

- Images are built from the repository root (`.dockerignore` keeps out `.git`, `vendor/`, `node_modules/`, the root `.env`, `backend/.env.local` and the public keys of `backend/.env.dev` and `backend/.env.test`). `docker/php/Dockerfile` has a `dev` target (`compose.yaml`) and a `prod` target: Composer dependencies without dev packages and an authoritative classmap, code owned by root and read-only for php-fpm, `var/` writable, `APP_ENV=prod`, the Symfony cache warmed at build time and OPcache without timestamp checks (`docker/php/prod.ini`). `docker/nginx/Dockerfile` builds the SPA with Node (`npm run build`) and copies `dist/` into nginx.
- Configuration comes from the root `.env` only: the code is baked into the image, so `backend/.env.local` is not used. Compose passes `APP_SECRET` (required: Compose refuses to start without it), `DATABASE_URL` (built from the MySQL variables), and `DEFAULT_URI`, `INSTANCE_POSTAL_CODE`, `ENABLED_SUPERMARKETS`, `TOTP_ENCRYPTION_KEY`, `CATALOG_HTTP_USER_AGENT`, `INGESTION_SCHEDULE`, `PRICE_HISTORY_RETENTION_MONTHS`, `PRICE_HISTORY_PRUNE_SCHEDULE` and `SYMFONY_TRUSTED_PROXIES` only when the `.env` sets them; otherwise the defaults of `backend/.env` apply.
- `docker/php/entrypoint.sh` (php and scheduler) first runs `bin/console app:config:check`, which stops the container with one message per missing or invalid variable (E6.4): `INSTANCE_POSTAL_CODE` (a valid Spanish postal code), `ENABLED_SUPERMARKETS` (slugs, each with a catalog adapter) and `TOTP_ENCRYPTION_KEY` (32 bytes in base64). Each context implements `Shared\Infrastructure\Configuration\ConfigurationCheck` (`CatalogConfigurationCheck`, `IdentityConfigurationCheck`), reusing the parsing and validation of `EnvIngestionSettings` and `SodiumSecretEncrypter`. For `php-fpm` it then waits up to 60 s for MySQL (`dbal:run-sql "SELECT 1"`), runs `doctrine:migrations:migrate --allow-no-migration` and `app:setup:ensure-code` (PD-14) before starting php-fpm.
- Sessions: Symfony uses PHP's native handler (no `handler_id` or `save_path`), so `docker/php/prod.ini` points `session.save_path` to `var/sessions`, the mount point of the `php-sessions` volume: a deploy keeps users logged in (PD-37). The rate limiter counters live in the `cache.app` pool inside the container and start from zero after each deploy.
- Health checks: mysql (`mysqladmin ping`), php (php-fpm accepts connections on port 9000, which only happens after the migrations) and nginx (`GET /`). nginx and the scheduler start once php is healthy.
- nginx (`docker/nginx/prod.conf`): `/api` goes to Symfony's front controller in the php container; files under `/assets/` (hashed by Vite) are cached for a year (`immutable`); any other path falls back to `index.html` with `Cache-Control: no-cache`; any other `.php` path is a 404. It appends the client address to `X-Forwarded-For`, so with `SYMFONY_TRUSTED_PROXIES=private_ranges` Symfony sees the real client IP (login rate limit) and the `https` scheme of a TLS proxy (secure cookies).
- Backups (E6.6): `deploy/backup.sh [directory]` writes `jolo-market-<UTC timestamp>.sql.gz` (default `backups/`, git-ignored) with `mysqldump --single-transaction --set-gtid-purged=OFF --routines --triggers` run as root inside the mysql container (the application user lacks the `RELOAD`/`FLUSH_TABLES` privilege it needs). A failed dump leaves no file and exits `1`. Restore: see the README.
- CI job "Production images" builds `compose.prod.yaml` on every push to `main` (E6.8). TLS is terminated by the user's own reverse proxy.

### Development environment

`compose.yaml` runs `nginx`, `php` (php-fpm, built from `docker/php/Dockerfile`), `scheduler` (same image, Messenger worker for scheduled jobs), `mysql` and `frontend` (Vite dev server on Node 26, `npm ci` on start, `node_modules` in the `frontend-node-modules` named volume).

| Variable (root `.env`, documented in `.env.example`) | Purpose |
| --- | --- |
| `HTTP_PORT` | Host port published by nginx (default `8080`). |
| `MYSQL_ROOT_PASSWORD`, `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD` | MySQL credentials. Compose builds the backend `DATABASE_URL` from them, which overrides the default in `backend/.env`. |
| `UID`, `GID` | Host user and group the php container runs as, so files written through the bind mount stay yours. |
| `INSTALL_XDEBUG` | Build argument: `true` installs Xdebug in the php image (rebuild after changing it). |
| `XDEBUG_MODE` | Xdebug mode at runtime (`off`, `debug`, `coverage`, `develop`). |
| `COMPOSE_FILE` | Compose files to use. `compose.prod.yaml` selects the production stack; auto-deploy requires it (PD-52) and every `make` target that runs Docker refuses it (PD-60). |
| `APP_SECRET`, `DEFAULT_URI`, `INSTANCE_POSTAL_CODE`, `ENABLED_SUPERMARKETS`, `TOTP_ENCRYPTION_KEY`, `CATALOG_HTTP_USER_AGENT`, `INGESTION_SCHEDULE`, `PRICE_HISTORY_RETENTION_MONTHS`, `PRICE_HISTORY_PRUNE_SCHEDULE`, `SYMFONY_TRUSTED_PROXIES` | Production stack only: passed to the php and scheduler containers (see [Production stack](#production-stack)). In development they live in `backend/.env.local`. |
| `AUTO_DEPLOY` | `true` turns on the auto-deploy of `deploy/` (E7, PD-50); anything else, or unset, keeps it off. Default `false`. Read by the scripts, not by Compose. |

Symfony variables (`backend/.env`, committed with non-secret defaults; real values in `backend/.env.local`):

| Variable | Purpose |
| --- | --- |
| `APP_ENV` | Symfony environment (`dev` by default). |
| `APP_SECRET` | Framework secret. Empty in `backend/.env`; public placeholders in `.env.dev` and `.env.test`. Required in production. |
| `DATABASE_URL` | MySQL connection. Overridden by Compose in development. |
| `DEFAULT_URI` | Base URI for absolute URLs generated outside HTTP requests (console commands). Default `http://localhost`. |
| `INSTANCE_POSTAL_CODE` | Spanish postal code whose prices are imported (PD-5). Empty in `backend/.env`: set it in `backend/.env.local`. `backend/.env.dev` sets `08001` (PD-53; override it in `backend/.env.dev.local`, PD-72). `app:catalog:ingest` refuses to run without a valid one. |
| `CATALOG_SOURCES` | Development only (PD-53): `dummy` (default, set in `backend/.env.dev`) ingests the offline dummy catalog, `real` the real adapters. Set it in `backend/.env.dev.local` (PD-72). Any other value fails the ingestion. Ignored in `prod` and `test`. |
| `ENABLED_SUPERMARKETS` | Comma-separated slugs of the supermarkets to import and show (PD-24). Default `mercadona,aldi,bonpreu` (PD-31, PD-32). |
| `CATALOG_HTTP_USER_AGENT` | User-Agent sent to the supermarkets (PD-26). |
| `INGESTION_SCHEDULE` | Cron expression for catalog ingestion, evaluated in Europe/Madrid (PD-27). Default `0 5 * * *`. |
| `PRICE_HISTORY_RETENTION_MONTHS` | Price snapshots older than this many months are deleted, except the latest of each product (PD-46). Default `24`; below 1 the prune command refuses to run. |
| `PRICE_HISTORY_PRUNE_SCHEDULE` | Cron expression for `app:catalog:prune-history`, evaluated in Europe/Madrid (PD-46). Default `30 4 * * *`. |
| `TOTP_ENCRYPTION_KEY` | 32 random bytes in base64 that encrypt TOTP secrets at rest (libsodium secretbox). Empty in `backend/.env`: required in production (`php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"`); public placeholders in `.env.dev` and `.env.test`. Changing it makes every enrolled TOTP device unreadable, so every user must then be reset. |

- `Makefile` (E9, PD-58 to PD-60, PD-73): shortcuts for the development lifecycle and the checks; `make` (target `help`) lists every target with the description after its `##`. `init` (creates `.env` from `.env.example` and an empty `backend/.env.local` only when missing, `npm ci` in `frontend/` when `npm` is on the `PATH`, else a warning, then `up -d --build --wait`, `composer install` and the migrations), `start` (`docker compose up -d --build --wait`), `stop` (`docker compose down`, never `-v`), `restart` (`stop` then `start`), `logs` (`docker compose logs -f $(s)`), `shell` (`sh` in php), `migrate`, `check-backend` (`composer check` in php), `test-unit` (`composer test:unit`), `check-frontend` (`npm run check` in `frontend/` on the host; fails when `npm` is missing), `test-deploy` (`deploy/test.sh`) and `check` (the three checks, all of them run, fails when any failed). Every target that runs Docker depends on `dev-stack-only`, which fails before anything runs when an uncommented `COMPOSE_FILE` line of the root `.env` lists `compose.prod.yaml`; `check-frontend` does not use Docker and skips it.
- nginx routes `/api` to Symfony (`backend/public/index.php`) and every other path to the Vite dev server (`frontend:5173`), including the HMR websocket. The SPA and the API share the origin `http://localhost:${HTTP_PORT}`.
- MySQL starts with `utf8mb4` / `utf8mb4_0900_ai_ci`, and DBAL creates tables with the same default. On the first start of the `mysql-data` volume, `docker/mysql/init/01-create-test-database.sh` creates `<MYSQL_DATABASE>_test`. The test environment uses that database (Doctrine `dbname_suffix: _test`).
- CI (`.github/workflows/ci.yml`, GitHub Actions) runs on every push: the backend `composer check` inside the Compose php and mysql containers, the frontend `npm run check`, and then the Playwright suite against the stack (without the scheduler) loaded with the e2e catalog. A first `changes` job (E12, PD-107 to PD-110) checks out the whole history and runs `deploy/ci-scope.sh origin/main`, which lists the files changed since the merge-base with `HEAD` (`git diff --name-only --no-renames`, so a rename counts both paths) and prints `backend=`, `frontend=`, `e2e=` and `deploy=` (`true`/`false`) into `$GITHUB_OUTPUT`; on `refs/heads/main` the job sets all four to `true` without running it. Backend check, Frontend check and Deploy scripts run only when their output is `true`; End-to-end needs `changes`, `backend` and `frontend` and runs when `e2e` is `true` and neither check failed or was cancelled (`!cancelled() && !failure()`, so a skipped check does not block it). Production images keeps its `main`-only condition. The script tests documentation first (nothing), then `.github/workflows/**` and itself (everything), then each area; a file no rule matches, or a missing merge-base, runs everything. Its bats tests are `deploy/tests/ci-scope.bats`; `make ci-scope` runs it on the host (PD-111). Skipped jobs count as passed for auto-deploy, which only ever sees `main`.
- End-to-end tests (Playwright, `frontend/e2e/`) run against the running stack: `npm run test:e2e` in `frontend/`. `E2E_BASE_URL` overrides the target (default `http://localhost:8080`).
- The journeys need a known catalog and must never reach the real supermarkets, so the e2e stack runs without the `scheduler` service and, after `doctrine:migrations:migrate`, loads the SQL fixture `backend/tests/Fixtures/e2e/catalog.sql` with the `mysql` client of the `mysql` container. Its products are in the category "Lácteos y huevos", which the dummy section mapping puts in Lácteos y huevos (PD-115), so the sections journey of `lists.spec.ts` (E14.13) sees them under that header. It adds no production code. The fixture deletes and inserts only its own rows (external ids `e2e-…`), so it can be loaded again. Locally it goes into a separate Compose project (`COMPOSE_PROJECT_NAME`, `HTTP_PORT`), never the development database (README → End-to-end tests). The search journey (`search.spec.ts`) searches "leche" and checks the PD-33 order: the L group by ascending unit price, then kg, then unit, then the product without a unit price; the cheapest of each group highlighted; the unavailable product absent. The product journey (`product.spec.ts`, E5) opens "Leche entera" from the search results and checks its page: "Añadir a una lista", the 6-month period selected, the chart and the minimum, maximum and current price of the snapshots the fixture adds (dated relative to now, so they stay within the period).
- Frontend quality commands run in `frontend/` with Node 26 (`npm ci` first): `npm run test`, `npm run typecheck`, `npm run lint`, `npm run format:check` and `npm run check` (runs all of them; `make check-frontend`).
- Schema changes are Doctrine migrations in `backend/migrations/` (raw SQL, UTC `DATETIME(6)`, UUIDs as `BINARY(16)`). Apply them with `bin/console doctrine:migrations:migrate`. `composer test` first runs `composer db:test`, which migrates the test database.
- Catalog ingestion: `bin/console app:catalog:ingest [--supermarket=<slug>]`. It prints one row per run (status, seen, skipped, marked unavailable, duration, error). Exit code `0` when every run succeeded, `1` when any run failed, `2` for invalid input or configuration (unknown or disabled `--supermarket`, invalid `INSTANCE_POSTAL_CODE`), in which case nothing is ingested. Supermarket adapters implement `SupermarketCatalogSource` and are collected through the `catalog.supermarket_source` tag (`_instanceof` in `config/services/catalog.yaml`). In the test environment two `ConfigurableCatalogSource` fakes replace them.
- Scheduling (E1.21, E5.4): `Catalog\Infrastructure\Scheduler\CatalogSchedule` (Symfony Scheduler, schedule `default`) sends `app:catalog:ingest` as a `RunCommandMessage` on `INGESTION_SCHEDULE` and `app:catalog:prune-history` on `PRICE_HISTORY_PRUNE_SCHEDULE`. A failing command does not throw (it is already recorded and logged), so Messenger never retries a whole ingestion or prune. The schedule is stateful (cache): if the worker was down at the scheduled time, it runs once when it comes back. The `scheduler` Compose service runs `messenger:consume scheduler_default --time-limit=3600` and restarts after each hour. `bin/console debug:scheduler` shows the next run.
- Supermarket HTTP (E1.19): adapters use the `catalog.http_client` service built by `CatalogHttpClientFactory` on Symfony HttpClient. It sends the PD-26 User-Agent, uses a 10 s idle timeout and a 30 s maximum per request, leaves at least 1 s between the start of two requests (`PoliteHttpClient`, with an injected `Sleeper` so tests never sleep) and retries `429`/`5xx` up to 3 times with exponential backoff from 1 s (a `Retry-After` header wins). The numbers are arguments of `CatalogHttpOptions` in `config/services/catalog.yaml`; the spikes may tune them per chain.
- Mercadona adapter (E1.14, PD-28): `Catalog\Infrastructure\Source\Mercadona\MercadonaCatalogSource`. It turns the postal code into a warehouse (`PUT /api/postal-codes/actions/change-pc/`, header `x-customer-wh`), lists the categories and reads every leaf category with `?wh=`. Packaging text is `unit_size` + `size_format` (already the total size of a pack); approximate sizes pass Mercadona's `reference_price` instead (PD-29). Prices are converted from decimal strings with `DecimalCents` (no floats). A full run is about 150 requests and 2.5 minutes. Tests use recorded fixtures in `backend/tests/Fixtures/Mercadona/`; in the test container `ConfigurableCatalogSource` decorates the adapter so it is never called.
- Logging: Monolog. Ingestion logs go to the `ingestion` channel through the `IngestionLogger` application port (`PsrIngestionLogger`): a warning per skipped product and one record per finished run (`info` on success, `error` on failure) with `run_id`, `supermarket`, `status`, counts, `duration_ms` and `error` in the context. In `prod` this channel is written as JSON to stderr at `info` level, outside the `fingers_crossed` buffer.
- Identity adapters (E3.16): `DbalUserRepository` (insert or update by id; never `ON DUPLICATE KEY UPDATE`, which would also fire on the unique username), `SymfonyPasswordHasher` (Symfony `NativePasswordHasher`, the "auto" algorithm; cost 4 in the test environment), `OtphpTotpService` (`spomky-labs/otphp`: SHA-1, 6 digits, 30 s, 160-bit secrets, 15 s of tolerated clock drift), `SodiumSecretEncrypter` (base64 of nonce + ciphertext, `TOTP_ENCRYPTION_KEY`) and `RandomActivationTokenGenerator` (256 random bits, base64url, 43 characters). `BinaryUuid` and `DbalDateTime` live in `Shared\Infrastructure\Persistence\Dbal`.
- First-admin setup (E3.22, PD-14): the `php` service runs `bin/console app:setup:ensure-code` before `php-fpm`. While there are no users it stores the SHA-256 of a new code in `setup_code` (one row, replaced on each start) and prints `Setup code: XXXX-XXXX-XXXX-XXXX` (Crockford base32, 80 bits; case, spaces and hyphens are ignored when typed). Once a user exists it prints nothing useful and removes any old code. A failure (no `vendor/` or migrations yet) never stops `php-fpm`.
- Price history prune (E5.4, PD-46): `bin/console app:catalog:prune-history` → `PrunePriceHistoryHandler` (`PrunePriceHistoryCommand` with `PRICE_HISTORY_RETENTION_MONTHS`, at least 1, else `InvalidRetentionPeriod`). The cutoff is the same instant N months before now (UTC), moved to the last day of a shorter month (31 May − 3 months → 28 February). `DbalPriceSnapshotRepository::deleteCapturedBefore` runs one `DELETE … JOIN (SELECT product_id, MAX(captured_at) …)` that keeps every row at the latest instant of its product. It prints the count and the cutoff; exit code `0`, or `2` for an invalid retention (nothing deleted).
- Unmapped categories (E14.6, PD-95): `bin/console app:catalog:unmapped-categories` (`make unmapped-categories`) → `FindUnmappedCategoriesHandler` over the `StoredCategories` port (`DbalStoredCategories`: every distinct `product.category` per supermarket with its product count, available or not; products without a category are left out) and `SectionMapping`. It prints one table per supermarket (by id) of the paths that map to `other`, most products first, or "Every stored category maps to a section." It changes nothing; its output is the input for extending the mapping in a pull request. Exit code `0`.
- User reset (PD-35): `bin/console app:user:reset <username>` sends the user back to activation, ends their sessions and prints a new activation link built on `DEFAULT_URI` (`<DEFAULT_URI>/activate/<token>`), shown only once. Exit code `0` on success, `1` for an unknown user or one that cannot be reset (disabled).
- Auto-deploy (E7, PD-50): `deploy/auto-deploy.sh` exits at once unless the root `.env` has `AUTO_DEPLOY=true` (read with `grep`, never sourced). It takes a `flock` on `.git/auto-deploy.lock`, requires the `main` branch, no uncommitted changes in tracked files and no local commits, fetches `origin/main` and asks GitHub for the CI of the new commit (`gh api` when `gh` is logged in, otherwise anonymous `curl` + `jq`): check runs that are all `success`, `neutral` or `skipped` and no failed commit status. No check yet or one still running is pending (next run); a failure skips the commit. It also exits with `1`, before fetching, unless `COMPOSE_FILE` in the root `.env` lists `compose.prod.yaml` and not `compose.yaml` (PD-52). Then `git merge --ff-only` and `docker compose up -d --build --remove-orphans --wait --wait-timeout 600`: the php container migrates on start and `--wait` waits for every health check. Until that succeeds the commit stays in `.git/auto-deploy.pending` and every run retries it. Exit code `0` when it deployed or had nothing to do, `1` on a failure. Its tests and ShellCheck run with `deploy/test.sh` (CI job "Deploy scripts"). `deploy/install-auto-deploy.sh [install|uninstall]` writes the user units `jolo-market-deploy.service` (oneshot, the absolute path of the copy) and `jolo-market-deploy.timer` (`OnBootSec=2min`, `OnUnitInactiveSec=$AUTO_DEPLOY_INTERVAL`, 5min by default) to `~/.config/systemd/user`, enables the timer and warns when lingering is off or `AUTO_DEPLOY` is not `true`.
- Development data (E8, PD-53 to PD-57, PD-72). Every class of it lives in a `Dev` folder (`Catalog\Infrastructure\Dev`, `Identity\Infrastructure\Dev`, `ShoppingList\Infrastructure\Dev`, `Shared\Infrastructure\Dev`) or in `Catalog\Infrastructure\Source\Dummy`. The `config/services/*.yaml` resources exclude them and register them under `when@dev` only, so they do not exist in `prod` or `test`.
  - Dummy sources: `DummyCatalogSource` (one service per chain, `catalog.dev.dummy_source.<slug>`, not tagged) returns `DummyCatalog::forSupermarket()`: one hand-written list of 40 products shared by the three chains, each with its own price, sold by one, two or three of them (37 to 38 per chain), plus one malformed product per chain (blank name). It covers kg, L, unit, packs (`6 x 1,5 L`, `pack 6 x 33 cl`), packaging we cannot parse (no unit price), approximate sizes with a reference price per kg (PD-29) and no image URLs. It depends only on the UTC day: `daily` products cycle through ±3 % and ±6 % of their price every day, `weekly` ones every week, and each `intermittent` product (one or two per chain) is missing one day out of five. `catalog.dev.sources` (`DevCatalogSources`) gives `IngestCatalogHandler` the dummy sources, or the tagged real adapters when `CATALOG_SOURCES=real`, read at runtime. The dummy sources read the time from `SettableClock`.
  - `app:dev:catalog:backfill [--days=180]` (`BackfillCatalogConsoleCommand`): for each day from N−1 days ago to today and each enabled chain without a run that UTC day (`DbalIngestionRunDays`), it sets `SettableClock` (a `Clock` that tells the wrapped clock's time until it is set) to now minus that many days and runs `catalog.dev.backfill_handler`, an `IngestCatalogHandler` with the dummy sources and that clock, so runs and snapshots carry the day. Exit `0`, `1` when a run failed, `2` when `--days` is not 1 to 730 (nothing ingested).
  - `app:dev:users` (`DevUsers`): creates `admin` (`ADMIN`) and `usuario` (`USER`) through the real activation steps (`createPending`, password `Dev-password-1` hashed by `PasswordHasher`, fixed base32 secrets encrypted by `SecretEncrypter`, `confirmTotp`); a user with either name is left untouched. `app:dev:totp <username>` prints the current code (OTPHP with the `OtphpTotpService` settings) and the seconds left; exit `1` for an unknown or not active user.
  - Reset commands, one per context, each deleting only its own tables in a transaction: `app:dev:catalog:reset` (`price_snapshot`, `product`, `ingestion_run`), `app:dev:users:reset` (`setup_code`, `user`; every session ends because a session's user no longer exists and new users get new ids) and `app:dev:lists:reset` (`list_item`, `list_member`, `shopping_list`). They confirm through `ResetConfirmation`: `--force` skips the question, without interaction they refuse with exit `1`.
  - `app:dev:seed [--reset] [--force]` (`SeedConsoleCommand`) runs, by name, the three resets (with `--reset`, after its own confirmation, lists first), `app:dev:users` and `app:dev:catalog:backfill`. Exit `1` when the reset is not confirmed or a step fails.
  - DBAL profiling is off in `dev` (no web profiler is installed), so a full seed (about 40 000 queries) fits in memory.
  - Tests: `tests/Support/Dev/DevKernel` boots `dev` with the test database (`dbname_suffix: _test`), `var/cache/dev_test`, mock sessions and a `FixedClock` frozen at boot (`test.dev.clock`); `DevEnvironment` makes test classes use it and enables the three chains. `DevOnlyCodeTest` boots `dev`, `test` and `prod` (`IsolatedKernel`, own cache) and checks that only `dev` has the `app:dev:*` commands and the dev classes in its container.
- Backend quality commands run inside the php container: `composer db:test`, `composer test`, `composer test:unit`, `composer analyse`, `composer deptrac`, `composer cs` and `composer check` (runs all of them; `make check-backend`, and `make test-unit` for `composer test:unit`).

## Open questions

These have **not** been decided. Implementation must stop and ask before coding any of them.

None right now.

Once answered, move each item to [Product decisions](#product-decisions) with a new `PD-n` id.
