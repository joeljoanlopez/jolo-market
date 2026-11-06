# E0 — Foundation

**Goal:** an empty but fully wired project where the TDD loop, the architecture checks and the Docker dev environment all work before any feature is built.

## Acceptance criteria

- `docker compose up` starts nginx, php, mysql and the frontend dev server.
- `GET /api/health` returns `200 {"status":"ok"}` (covered by a functional test).
- The frontend shows a placeholder home page with Spanish copy loaded through i18n.
- A single command runs all backend checks, and another runs all frontend checks. CI runs both.
- Deptrac fails the build when a Domain class imports a Symfony class (proved by a deliberate local test, then reverted).

## Tasks

### Repository

- [x] E0.1 Create the folder layout (`backend/`, `frontend/`, `docker/`, `compose.yaml`, `.env.example`) and `.editorconfig`. `.gitignore` already exists: extend it if the bootstrapped tools need more entries.
- [x] E0.2 Pin versions (PHP, Symfony LTS, MySQL LTS, Node LTS, React, Vite) to the latest stable patch and record them in the architecture doc.
  - Backend: PHP 8.5.12, Symfony 7.4 LTS, MySQL 9.7.4 LTS, nginx 1.30.6 (PD-16). Frontend: Node 26.13.0 LTS, React 19.3.1, Vite 8.3.6, TypeScript 6.0.3 (TypeScript 7 is not supported by typescript-eslint yet).

### Backend

- [x] E0.3 Bootstrap a Symfony skeleton (API only, no Twig) inside `backend/`.
- [x] E0.4 Create `src/Shared/{Domain,Application,Infrastructure}` and empty `Catalog` and `ShoppingList` contexts. Configure DI autowiring per context.
- [x] E0.5 Add the `Shared\Domain` kernel through TDD: `Money` (integer cents, EUR, add, multiply, compare), `Uuid` value object, `Clock` port. Add `SystemClock` and `FixedClock` (tests).
- [x] E0.6 Configure PHPUnit with `Unit`, `Integration` and `Functional` suites and a dedicated MySQL test database.
- [x] E0.7 Configure PHPStan at max level, PHP-CS-Fixer and **Deptrac** with the layer and context rules from the architecture doc.
  - Proved locally: a `Catalog\Domain` class importing `Symfony\Component\HttpFoundation\Request` makes `composer deptrac` report 1 violation and exit 1; PHP built-ins (`\DateTimeImmutable`, `\InvalidArgumentException`) are allowed. The probe was reverted.
- [x] E0.8 Set up Doctrine DBAL and Migrations, with `utf8mb4_0900_ai_ci` as the default collation.
- [x] E0.9 Add the `GET /api/health` endpoint (functional test first). It checks DB connectivity.
- [x] E0.10 Add an RFC 9457 problem+json error listener, tested with a sample domain exception.
- [x] E0.11 Add Composer scripts: `test`, `test:unit`, `analyse`, `deptrac`, `cs`, `check` (runs all of them).

### Frontend

- [x] E0.12 Bootstrap Vite + React + TypeScript (strict) inside `frontend/`.
- [x] E0.13 Create the folder layout: `features/`, `shared/ui/{atoms,molecules,organisms,templates}`, `shared/i18n`, `shared/http`, `app/`.
- [x] E0.14 Configure Vitest, Testing Library and MSW, and write the first component test.
- [x] E0.15 Configure ESLint (including import-boundary rules: `domain/` cannot import React or `infrastructure/`) and Prettier.
  - Proved locally: a probe `features/probe/domain` file importing `react` and `../infrastructure/api` (and an `application` file importing `infrastructure`) made `npm run lint` report 4 errors. The probe was reverted.
- [x] E0.16 Set up react-i18next with `es.json`. A test fails if a component renders a missing key.
- [x] E0.17 Add TanStack Query and React Router providers, plus an app shell with a placeholder home route.
- [ ] E0.18 Add a base `httpClient` wrapper in `shared/http` that maps problem+json to typed errors (tested with MSW).
- [ ] E0.19 Set up Tailwind CSS with the Vite plugin. Define the theme tokens (colors including one per supermarket, spacing, typography, radii). Add `class-variance-authority`, `tailwind-merge` and a `cn()` helper, plus `prettier-plugin-tailwindcss` for class ordering.
- [ ] E0.19b Add a first set of atoms with `cva` variants (`Button`, `Input`, `Text`), with component tests.
- [x] E0.20 Add npm scripts: `test`, `typecheck`, `lint`, `format:check`, `check` (runs all of them).
- [ ] E0.21 Set up Playwright with one smoke test (home page loads) against the Docker stack.

### Docker & CI

- [ ] E0.22 Write dev `compose.yaml` with nginx, php-fpm (with Xdebug optional), mysql and the frontend dev server.
  - Done for nginx, php-fpm (optional Xdebug) and mysql. Pending: the frontend dev server service, with the frontend bootstrap.
- [x] E0.23 Environment files (PD-11): create the root `.env.example` for Compose with every variable documented, and keep the Symfony `backend/.env` committed with non-secret defaults only. Document `cp .env.example .env` and the use of `backend/.env.local` for secrets in the README.
- [ ] E0.24 Set up a CI pipeline that runs backend `check` and frontend `check` on every push.
- [ ] E0.25 Update `AGENTS.md` §6 with the real commands and the README with dev setup instructions.
