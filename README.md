# jolo-market

Self-hosted supermarket price comparator and shared shopping list, inspired by [quefalta.es](https://quefalta.es/).

> **Status:** foundation done (backend and frontend skeletons, Docker dev stack and CI). Feature work starts with E1. Progress is tracked in [`docs/epics/`](docs/epics/README.md).

## What it does

- **Compare prices** across supermarkets by searching a product (e.g. "leche entera") and ranking every result by **unit price** (€/kg, €/L or €/unit).
- **Shared shopping lists**: share a list through a secret link. Whoever opens it while logged in joins the list, and the owner manages its members.
- **Private instance**: users are created by an admin, activate their account through a one-time link, and log in with mandatory TOTP 2FA.
- **Estimated totals** per supermarket for list items linked to a concrete product.
- **Price history**: every catalog refresh stores a dated price snapshot. Each product has a page (`/product/:id`, linked from search results and lists) with a chart of its price over the last 30 days, 6 months, year or all of it, and its minimum, maximum and current price.

### MVP scope

| Area | Decision |
| --- | --- |
| Supermarkets | Mercadona, Bonpreu i Esclat, Aldi |
| Location | One postal code per instance, configured in `.env` |
| Comparison | Free-text search + unit price ranking (no cross-chain product matching) |
| Users | Created by an admin. Username login, password set through a one-time activation link, mandatory TOTP 2FA |
| Instance access | Closed: everything requires login |
| Sharing | Secret link; opening it while logged in makes you a member. The owner can remove members and regenerate the link |
| List items | Free text **or** a concrete product from a supermarket |
| Collaboration | Polling every few seconds while a list is open |
| Price history | Stored on every refresh |
| UI language | Spanish, with i18n ready for more languages |

The full list of product decisions lives in [`docs/architecture.md`](docs/architecture.md#product-decisions).

## Stack

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.5 + Symfony 7.4 LTS (API only) |
| Persistence | MySQL 9.7 LTS, Doctrine DBAL + Doctrine Migrations |
| Frontend | TypeScript + React + Vite (SPA), Tailwind CSS |
| Backend tests | PHPUnit, PHPStan (max level), Deptrac, PHP-CS-Fixer |
| Frontend tests | Vitest, Testing Library, MSW, Playwright |
| Runtime | Docker Compose (nginx, php-fpm, mysql, scheduler) |

Rationale for each choice and exact pinned versions: [`docs/architecture.md`](docs/architecture.md#stack).

## Repository layout

```text
jolo-market/
├── backend/            # Symfony API, hexagonal architecture
├── frontend/           # React + TypeScript SPA (Vite)
├── deploy/             # Optional auto-deploy (systemd timer) and its bats tests
├── docker/             # Dockerfiles, nginx config and the bats test image
├── docs/
│   ├── architecture.md # Stack, architecture, domain model, decisions
│   └── epics/          # Epics with task checklists (progress tracking)
├── compose.yaml        # Development stack: nginx, php-fpm, scheduler, mysql, Vite dev server
├── .env.example        # Documented Compose variables (copy to .env)
└── AGENTS.md           # Rules for AI assistants (TDD, hexagonal, English code)
```

## Getting started (development)

Requirements: Docker with Docker Compose v2.

1. Create your Compose environment file and review the values (passwords, `UID`/`GID`, port):

   ```sh
   cp .env.example .env
   ```

2. Backend secrets and machine-specific overrides go in `backend/.env.local`, which git ignores. `backend/.env` is committed and holds only non-secret defaults. Outside `dev` and `test`, set at least `APP_SECRET` there, for example:

   ```sh
   echo "APP_SECRET=$(openssl rand -hex 32)" >> backend/.env.local
   ```

3. If you will also run the frontend tooling on your machine (checks, Playwright), install its dependencies **before** the first `docker compose up`. Otherwise Docker creates `frontend/node_modules` as root (it is the mount point of the container's own `node_modules` volume) and `npm ci` fails with `EACCES` (fix it with `sudo chown -R "$(id -u):$(id -g)" frontend/node_modules`):

   ```sh
   (cd frontend && npm ci)
   ```

4. Start the stack and install the backend dependencies (the `frontend` service installs its own with `npm ci` on start):

   ```sh
   docker compose up -d --build
   docker compose exec php composer install
   docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
   ```

   Run the migrations again whenever you pull new ones. `composer test` migrates the test database by itself.

5. Set the postal code whose prices are imported, then import the catalogs (see [Catalog ingestion](#catalog-ingestion)):

   ```sh
   echo "INSTANCE_POSTAL_CODE=08001" >> backend/.env.local
   docker compose exec php bin/console app:catalog:ingest
   ```

6. Check that it works:

   ```sh
   curl http://localhost:8080/api/health   # {"status":"ok"}
   ```

   Open <http://localhost:8080> for the SPA. nginx serves `/api` from Symfony and everything else from the Vite dev server, with hot reload.

The MySQL test database (`<MYSQL_DATABASE>_test`) is created automatically the first time the `mysql-data` volume is initialised. If you created the volume before that script existed, recreate it with `docker compose down -v`.

### Catalog ingestion

`app:catalog:ingest` imports the catalog of every supermarket in `ENABLED_SUPERMARKETS` (`mercadona`, `aldi` and `bonpreu` have an adapter) for `INSTANCE_POSTAL_CODE` (both in `backend/.env`, override them in `backend/.env.local`):

```sh
docker compose exec php bin/console app:catalog:ingest                         # every enabled supermarket
docker compose exec php bin/console app:catalog:ingest --supermarket=mercadona # only one
```

Requests to the stores identify themselves with `CATALOG_HTTP_USER_AGENT`, wait at least 1 s between each other and retry rate limits and server errors.

The `scheduler` service runs it automatically on `INGESTION_SCHEDULE` (daily at 05:00 Europe/Madrid by default; `docker compose exec php bin/console debug:scheduler` shows the next run).

It prints a summary per supermarket and exits with `0` when every run succeeded, `1` when any failed and `2` for invalid input or configuration. Each run is stored in the `ingestion_run` table and logged on the `ingestion` channel.

### Price history retention

Every run stores a price snapshot per product. Snapshots older than `PRICE_HISTORY_RETENTION_MONTHS` (default `24`) are deleted, except the latest one of each product, so an unavailable product keeps its last known price (PD-46). The `scheduler` service does it on `PRICE_HISTORY_PRUNE_SCHEDULE` (daily at 04:30 Europe/Madrid by default), and you can run it by hand:

```sh
docker compose exec php bin/console app:catalog:prune-history
```

It prints how many snapshots it deleted and the cutoff, and exits with `0`, or `2` when `PRICE_HISTORY_RETENTION_MONTHS` is below 1 (nothing is deleted).

### Auto-deploy (optional)

The server can update itself when a pull request is merged into `main`, without opening ports or installing a GitHub runner: a user systemd timer runs `deploy/auto-deploy.sh` every 5 minutes. When `origin/main` has a new commit whose CI is green, the script fast-forwards to it and runs `docker compose up -d --build --wait` on the [production stack](#self-hosting-production): the new images are built, the php container applies the database migrations when it starts, and the deploy succeeds once every health check passes. A CI that is still running is checked again on the next run, and a red one is not deployed. It never touches a copy on another branch, with uncommitted changes in tracked files or with commits that are not in `origin/main`: it logs why and exits.

It is off by default. To turn it on, on the server:

```sh
# 1. In the root .env (auto-deploy only deploys the production stack)
AUTO_DEPLOY=true
COMPOSE_FILE=compose.prod.yaml

# 2. Install and start the timer (no sudo; AUTO_DEPLOY_INTERVAL=10min changes the interval)
deploy/install-auto-deploy.sh

# 3. Let the timer run without an open session
loginctl enable-linger "$USER"
```

Requirements: your user can run `docker` without sudo, and either `gh` is installed and logged in or `curl` and `jq` are (the repository is public, so the API is read without a token). The service runs with systemd's default `PATH`; tools installed only in your home folder are not found.

```sh
systemctl --user list-timers jolo-market-deploy.timer   # next run
journalctl --user -u jolo-market-deploy.service -f      # logs
systemctl --user start jolo-market-deploy.service       # check now
deploy/install-auto-deploy.sh uninstall                 # remove it
```

With `AUTO_DEPLOY` other than `true` the timer keeps running and does nothing. If `COMPOSE_FILE` does not list `compose.prod.yaml`, it logs why and fails without merging anything. If anything fails after the merge (a build, the configuration check or a migration), it is retried on every run until it succeeds. Other Compose files in `COMPOSE_FILE` (for example `compose.prod.yaml:compose.override.yaml`) are deployed too.

### First admin

A fresh instance has no users. The `php` container prints a one-time setup code in its logs on every start until the first user exists:

```sh
docker compose logs php | grep "Setup code"
```

Open the app, go to `/setup`, enter the code and a username: that creates the first admin, who then sets a password and enrolls a TOTP device through the activation link. After that, `/setup` is gone for good.

### User reset

If a user, for example the only admin, loses their password or their two-factor device, the server operator resets them. The command ends their sessions and prints a new activation link (valid 72 hours, shown once) to set a new password and enroll a new device:

```sh
docker compose exec php bin/console app:user:reset <username>
```

The link starts with `DEFAULT_URI` (`backend/.env`), so set it to the public address of the instance. TOTP secrets are encrypted with `TOTP_ENCRYPTION_KEY`: development and tests use a public key from `backend/.env.dev` and `backend/.env.test`; production must set its own in `backend/.env.local`:

```sh
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

### Authentication API

The instance is closed: every `/api` route except health, login and activation needs a session opened with the password **and** a TOTP code. State-changing requests must send `X-Requested-With: XMLHttpRequest`.

```sh
curl -c jar -H 'Content-Type: application/json' -H 'X-Requested-With: XMLHttpRequest' \
  -d '{"username":"maria","password":"…"}' http://localhost:8080/api/auth/login   # 202
curl -b jar -c jar -H 'Content-Type: application/json' -H 'X-Requested-With: XMLHttpRequest' \
  -d '{"code":"123456"}' http://localhost:8080/api/auth/2fa                        # 200 {id, username, role}
curl -b jar http://localhost:8080/api/auth/me
```

Failed logins answer the same `401`, and repeated attempts get `429`. The full list of endpoints is in [docs/architecture.md](docs/architecture.md#http-api-draft).

### Catalog API

Search and product endpoints. They need a logged-in session, like every other route (use the cookie jar of the login above). Full shapes in [`docs/architecture.md`](docs/architecture.md#2-search-and-compare):

```sh
curl -b jar 'http://localhost:8080/api/supermarkets'                                 # enabled supermarkets
curl -b jar 'http://localhost:8080/api/products?q=leche%20entera&supermarket=aldi&page=1'  # search, 20 per page
curl -b jar 'http://localhost:8080/api/products/<id>'                                 # product detail
curl -b jar 'http://localhost:8080/api/products/<id>/price-history?from=2026-10-01&to=2026-11-12'  # price snapshots, oldest first; from/to optional
```

### Shopping list API

Lists, joining through the secret link and items. Writes need `X-Requested-With: XMLHttpRequest`. Full table and the `GET /api/lists/{id}` shape in [`docs/architecture.md`](docs/architecture.md#http-api-draft):

```sh
H='X-Requested-With: XMLHttpRequest'
curl -b jar 'http://localhost:8080/api/lists'                                           # my lists
curl -b jar -H "$H" -H 'Content-Type: application/json' -d '{"name":"Compra"}' http://localhost:8080/api/lists   # 201 {id}
curl -b jar -H "$H" -X POST http://localhost:8080/api/lists/join/<shareToken>           # 200 {id}
curl -b jar -i 'http://localhost:8080/api/lists/<id>'                                   # list + ETag; If-None-Match → 304
curl -b jar -H "$H" -H 'Content-Type: application/json' -d '{"type":"text","text":"pan"}' http://localhost:8080/api/lists/<id>/items   # 201 {id}
```

### Backend checks

Run them inside the php container (`docker compose exec php composer <script>`):

| Script | What it runs |
| --- | --- |
| `db:test` | Applies pending migrations to the test database |
| `test` | `db:test`, then every PHPUnit suite (Unit, Integration, Functional) |
| `test:unit` | Unit suite only, no I/O |
| `analyse` | PHPStan at max level |
| `deptrac` | Hexagonal layer and context rules |
| `cs` | PHP-CS-Fixer in dry-run mode |
| `check` | All of the above. It must pass before a task is done |

### Frontend checks

The frontend needs Node 26 (see `frontend/.nvmrc`). Install the dependencies once with `npm ci` inside `frontend/`, then run:

| Script | What it runs |
| --- | --- |
| `test` | Vitest (component and unit tests, MSW for HTTP) |
| `typecheck` | `tsc --noEmit` in strict mode |
| `lint` | ESLint, including the feature boundary rules |
| `format:check` | Prettier in check mode (`format` rewrites the files) |
| `check` | All of the above. It must pass before a task is done |

`npm run dev` starts the Vite dev server on port 5173 (the Docker stack already runs it).

### Deploy scripts

The scripts in `deploy/` (auto-deploy, E7) are checked with ShellCheck and tested with bats, both in Docker, so nothing needs to be installed:

```sh
deploy/test.sh                                # everything
deploy/test.sh deploy/tests/auto-deploy.bats  # one file
```

### End-to-end tests

Playwright runs against a running Docker stack that holds a known catalog, [`backend/tests/Fixtures/e2e/catalog.sql`](backend/tests/Fixtures/e2e/catalog.sql). The journeys expect exactly those products, so run them against a **separate Compose project** with its own database: loading the fixture into your development database would mix it with the real catalog (the fixture only replaces its own `e2e-` rows, but real "leche" products would change the results). Leave the `scheduler` service down, so nothing is ingested from the real supermarkets:

```sh
export COMPOSE_PROJECT_NAME=jolo-market-e2e HTTP_PORT=8081
docker compose up -d --wait nginx
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql --default-character-set=utf8mb4 -u"$MYSQL_USER" "$MYSQL_DATABASE"' < backend/tests/Fixtures/e2e/catalog.sql
```

Install the browser once, then run the suite from `frontend/`:

```sh
npx playwright install chromium
E2E_BASE_URL=http://localhost:8081 npm run test:e2e   # E2E_BASE_URL defaults to http://localhost:8080
```

The suite logs in as its own admin, `e2e-admin`. On an **empty** database the first journey (onboarding) creates it through the `/setup` page, with a code it gets from `docker compose exec php bin/console app:setup:ensure-code` (so run Playwright from a shell with the same `COMPOSE_PROJECT_NAME`). The onboarding journey can take up to a minute: TOTP codes are single-use, so it waits for a new one before logging in again. It keeps the session and the credentials in your system temp folder (`jolo-market-e2e/`), outside the repository, so later runs reuse them. If the database already has users but those files are gone, the run stops and asks for an empty database.

`docker compose down -v` (with the same `COMPOSE_PROJECT_NAME`) removes that stack and its database.

### Xdebug

Set `INSTALL_XDEBUG=true` in `.env`, rebuild with `docker compose build php`, then choose the mode with `XDEBUG_MODE` (for example `debug` or `coverage`) and run `docker compose up -d`.

Production setup is covered by [E6 — Self-hosting](docs/epics/E6-self-hosting.md).

### Continuous integration

GitHub Actions ([`.github/workflows/ci.yml`](.github/workflows/ci.yml)) runs on every push:

| Job | What it runs |
| --- | --- |
| Backend check | `composer check` inside the same php and mysql containers as development |
| Frontend check | `npm run check` on the Node version of `frontend/.nvmrc` |
| Deploy scripts | ShellCheck and the bats tests of `deploy/` (`deploy/test.sh`) |
| End-to-end | The Docker stack without the scheduler, migrated and loaded with the e2e catalog, plus `npm run test:e2e`, after both checks pass |

## Development principles

- **TDD always**: red → green → refactor. No production code without a failing test first.
- **Hexagonal architecture**: the domain depends on nothing; frameworks live at the edges.
- **English only** in code, identifiers, comments, commit messages and technical docs.
- **Ask, don't assume**: any undefined case found during implementation is raised as a question before it is coded.

See [`AGENTS.md`](AGENTS.md) for the full rules.

## Disclaimer

This is a personal project, not affiliated with any supermarket. Prices are taken from each chain's public online store and may differ from in-store prices. Check each source's terms of use before running ingestion.

## License

See [LICENSE](LICENSE).
