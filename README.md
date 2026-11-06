# jolo-market

Self-hosted supermarket price comparator and shared shopping list, inspired by [quefalta.es](https://quefalta.es/).

> **Status:** foundation in progress (backend skeleton, frontend skeleton and Docker dev stack). Progress is tracked in [`docs/epics/`](docs/epics/README.md).

## What it does

- **Compare prices** across supermarkets by searching a product (e.g. "leche entera") and ranking every result by **unit price** (€/kg, €/L or €/unit).
- **Shared shopping lists**: share a list through a secret link. Whoever opens it while logged in joins the list, and the owner manages its members.
- **Private instance**: users are created by an admin, activate their account through a one-time link, and log in with mandatory TOTP 2FA.
- **Estimated totals** per supermarket for list items linked to a concrete product.
- **Price history**: every catalog refresh stores a dated price snapshot, so price evolution can be charted.

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
├── docker/             # Dockerfiles and nginx config
├── docs/
│   ├── architecture.md # Stack, architecture, domain model, decisions
│   └── epics/          # Epics with task checklists (progress tracking)
├── compose.yaml        # Development stack: nginx, php-fpm, mysql
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

3. Start the stack and install the backend dependencies:

   ```sh
   docker compose up -d --build
   docker compose exec php composer install
   ```

4. Check that it works:

   ```sh
   curl http://localhost:8080/api/health   # {"status":"ok"}
   ```

The MySQL test database (`<MYSQL_DATABASE>_test`) is created automatically the first time the `mysql-data` volume is initialised. If you created the volume before that script existed, recreate it with `docker compose down -v`.

### Backend checks

Run them inside the php container (`docker compose exec php composer <script>`):

| Script | What it runs |
| --- | --- |
| `test` | Every PHPUnit suite (Unit, Integration, Functional) |
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

`npm run dev` starts the Vite dev server on port 5173.

### Xdebug

Set `INSTALL_XDEBUG=true` in `.env`, rebuild with `docker compose build php`, then choose the mode with `XDEBUG_MODE` (for example `debug` or `coverage`) and run `docker compose up -d`.

Production setup is covered by [E6 — Self-hosting](docs/epics/E6-self-hosting.md).

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
