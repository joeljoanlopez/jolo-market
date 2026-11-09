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
| PD-31 | **Aldi** is ingested from the public category pages of `www.aldi.es` (allowed by its `robots.txt`), which embed their products and prices in `__NEXT_DATA__`. Prices are the **peninsula** ones (`/can/` and `/bal/` are disallowed), so the postal code is not used. Canned food is sized by its **drained weight** (`peso esc.`), the only size Aldi gives. Products without a published price (mostly loose fresh produce) are stored without one. Aldi is in the default `ENABLED_SUPERMARKETS`. *(Replaces the Aldi part of PD-30, whose spike sample was wrong: on 2026-11-09, 2 480 of 2 666 products had a price.)* |
| PD-32 | **Bonpreu i Esclat** is ingested from the internal JSON API of `www.compraonline.bonpreuesclat.cat` (Ocado Smart Platform), with a **browser User-Agent**: its WAF rejects any other client. This overrides PD-26 for Bonpreu only, and like PD-28 it is accepted for **private, self-hosted use** even though `robots.txt` disallows `/api/`. The operator takes that risk. The WAF challenges the seventh API request within about five minutes, and only a browser running its JavaScript can pass a challenge, so the adapter **waits 90 seconds between API requests** instead of trying to pass it, and after a challenge waits six minutes and retries, up to twice (a run takes about an hour and a half). The online store has one price region (Catalunya), so the postal code is not used. Bonpreu is in the default `ENABLED_SUPERMARKETS`. *(Replaces the Bonpreu part of PD-30; with PD-28 and PD-31, OQ-5 is closed.)* |

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
| Routing (FE) | React Router | Covers `/setup`, `/login`, `/activate/:token`, `/`, `/search`, `/lists/:id`, `/join/:token`, `/admin/users`, with route guards for authenticated and admin areas. |
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
```

Value objects: `Money` (cents and currency, EUR only for now), `Packaging`, `UnitOfMeasure` (enum: `KILOGRAM`, `LITRE`, `UNIT`), `UnitPrice`, `ProductName`, `ExternalProductId`, `PostalCode`.

**Unit normalization:** grams → kg, ml/cl → L, and "pack of N" → unit. A source's own unit price is used only for approximate sizes (PD-29); otherwise ours is the only one. If packaging cannot be parsed, the product is stored **without** a unit price and ranked last. That is an explicit state, not a guess.

How it is modelled:

- `Packaging` stores the quantity as an **integer number of thousandths** of its `UnitOfMeasure` (500 g → 500 thousandths of a kg, 33 cl → 330 thousandths of a L, 12 units → 12 000), so no float is ever used. Named constructors (`kilograms`, `grams`, `litres`, `centilitres`, `millilitres`, `units`) take decimal strings with a dot. A quantity finer than a thousandth (for example `0.5 g`) is rejected, not rounded. `multipliedBy(n)` models multipacks (`6 x 33 cl`).
- `PackagingParser` (domain service) accepts only strings that match a known format completely: an optional `pack`/`pack de` prefix, an optional `N x` multiplier, a quantity with a comma or dot decimal, and a unit (`kg`, `g`/`gr`, `l`/`lt`/`litro(s)`, `cl`, `ml`, `u`/`ud`/`uds`/`unidad(es)`). Anything else (approximate weights such as `1,2 kg aprox.`, ranges, unknown words) returns `null`, the explicit "unknown" state. New formats are added to its table-driven test as each supermarket spike finds them.
- `UnitPriceCalculator` computes `price ÷ normalized quantity` rounded half-up to whole cents (PD-21).
- `PostalCode` accepts five digits whose first two are a Spanish province (`01`–`52`).

**Ports (Catalog Domain):**

- `ProductRepository`: `save` (insert or update), `findBySupermarketAndExternalId`, `findAvailableNotSeenSince(supermarketId, instant)` (the products a run did not see). Search is added in E2.
- `PriceSnapshotRepository`: `append(...snapshots)` in one batch. History by product is added in E5.
- `SupermarketCatalogSource`: `supermarketId()` and `fetchCatalog(PostalCode): iterable<SourceProduct>`. **One adapter per chain.** `SourceProduct` carries plain values (external id, name, price in cents, packaging text, brand, category segments, image URL and, for approximate sizes only, a reference unit price in cents with its unit `kg`/`l`/`unit`), so one malformed product does not stop a source from returning the rest of its catalog.

`Product` is created with `register` and changed only through `updateFromSource` (new details, price and packaging; makes it available again) and `markUnavailable`. `Supermarket` is created with `create(id, name)`; `SupermarketId` is a slug of lowercase letters, digits and inner hyphens.

### Identity

```text
User (aggregate root)
  id: UserId
  username: Username           unique, case-insensitive, normalized to lowercase
  role: Role                   ADMIN | USER
  status: UserStatus           PENDING_ACTIVATION | ACTIVE | DISABLED
  passwordHash: ?PasswordHash  null until activated (hashing is a port, never done in the domain)
  totp: ?TotpEnrollment        secret (encrypted at rest) + confirmedAt; required before ACTIVE
  activation: ?ActivationToken hash of the one-time token + expiresAt; consumed on use
  createdAt, createdBy: ?UserId
```

Lifecycle: `PENDING_ACTIVATION` → (valid activation link → set password → scan the TOTP QR code → confirm a code) → `ACTIVE`. Only the **hash** of the activation token is stored. The plain token appears only once, in the link shown to the admin.

**Ports (Identity):** `UserRepository`, `PasswordHasher`, `TotpService` (generate secret, verify code), `ActivationTokenGenerator`, `SecretEncrypter`.

### ShoppingList

```text
ShoppingList (aggregate root)
  id: ShoppingListId           internal UUID
  ownerId: UserId
  members: UserId[]            users who joined through the link (owner excluded)
  shareToken: ShareToken       ≥128-bit random, URL-safe; the "secret link" used only to JOIN
  name: string
  items: ListItem[]
  createdAt, updatedAt
  version: int                 incremented on every change (used for ETag)

ListItem (entity inside the aggregate)
  id: ListItemId
  content: FreeTextItem(text) | ProductItem(productId, supermarketId)
  quantity: positive int
  checked: bool
  position: int
```

**Access rules:** owner and members can read and edit items. Only the owner can rename the list, remove members and regenerate the share token. Joining with a valid token is idempotent, and joining your own list is a no-op.

**Totals:** the estimated total per supermarket is the sum of `price × quantity` of product items belonging to that supermarket. Free-text items are listed but not priced. It is computed in the Application layer with a fresh price from the `ProductCatalog` port.

## Backend structure

```text
backend/
├── src/
│   ├── Catalog/
│   │   ├── Domain/
│   │   │   ├── Model/           # Supermarket, Product, PriceSnapshot
│   │   │   ├── ValueObject/     # Packaging, UnitPrice, UnitOfMeasure...
│   │   │   ├── Port/            # ProductRepository, SupermarketCatalogSource...
│   │   │   ├── Service/         # UnitPriceCalculator (pure)
│   │   │   └── Exception/
│   │   ├── Application/
│   │   │   ├── IngestCatalog/   # IngestCatalogCommand + IngestCatalogHandler
│   │   │   ├── SearchProducts/  # SearchProductsQuery + handler + result DTO
│   │   │   └── GetPriceHistory/
│   │   └── Infrastructure/
│   │       ├── Persistence/Dbal/
│   │       ├── Source/Mercadona/
│   │       ├── Source/BonpreuEsclat/
│   │       ├── Source/Aldi/
│   │       ├── Http/            # controllers
│   │       └── Console/         # app:catalog:ingest
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
| `Shared\Domain\Clock` | Port returning the current `DateTimeImmutable`. `Shared\Infrastructure\Clock\SystemClock` implements it in **UTC**; tests use `Tests\Support\FixedClock`. |
| `Shared\Domain\IdGenerator` | Port returning a new `Uuid`. `Shared\Infrastructure\Id\UuidV7Generator` implements it with time-ordered UUIDv7 (`symfony/uid`); tests use `Tests\Support\SequentialIdGenerator`. |

## Frontend structure

Feature-first (screaming) folders. Each feature has a light hexagonal split. Shared UI follows **atomic design**, and screens follow **container/presentational**.

```text
frontend/src/
├── features/
│   ├── catalog/
│   │   ├── domain/          # types + pure functions (formatUnitPrice, rankByUnitPrice)
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
- Test helpers live in `src/test/`: `renderWithProviders`, the MSW `server` (unhandled requests fail the test) and the test i18n instance.
- Presentational components get data through props, render it, and emit events. They never fetch.
- Containers hold state and data access. They contain no styling logic and no Tailwind classes beyond layout.
- Styling uses Tailwind utilities **with theme tokens only**. Arbitrary values (`w-[13px]`, `text-[#3a3a3a]`) are not allowed. If a value is missing, add it to the theme.
- Repeated class combinations become an atom or molecule (with `cva` variants), never a copy-pasted string. `@apply` is avoided.
- Theme tokens live in `@theme` in `frontend/src/index.css`. Tailwind's default colors, font sizes, fonts and radii are **reset**, so only these tokens exist:
  - Colors: `background`, `surface`, `border`, `foreground`, `muted`, `primary` (+ `-hover`, `-foreground`), `danger` (+ `-hover`, `-foreground`), `focus`, and one per supermarket with a readable foreground: `mercadona`, `bonpreu`, `aldi` (+ `-foreground`) (PD-20).
  - Font sizes `xs`–`3xl`, font `sans`, radii `sm`, `md`, `lg`, `full`, and the spacing unit `--spacing: 0.25rem`.
  - A new token must also be added to `cn()` (`shared/ui/lib/cn.ts`) so `tailwind-merge` resolves conflicts correctly.
- `cn()` joins classes and lets the last conflicting utility win. Atoms (`shared/ui/atoms`): `Button` (`variant`: primary, secondary, danger; `size`: sm, md), `Input` (`invalid`), `Text` (`as`, `size`, `tone`, `weight`).
- `prettier-plugin-tailwindcss` sorts classes, also inside `cva()` and `cn()`.
- HTTP: `createHttpClient({ baseUrl })` in `shared/http` wraps `fetch` (`get`, `post`, `patch`, `delete`). It sends same-origin cookies and JSON, resolves with the parsed body as `unknown` (`undefined` when empty) and rejects with typed errors: `UnauthorizedError` (401), `NotFoundError` (404), `ConflictError` (409), `ValidationError` (422), `ServerError` (5xx), `HttpError` for any other status, and `NetworkError` when no response arrives. Each error carries the parsed `problem` (falling back to the status when the body is not problem+json). Feature `infrastructure/` maps the `unknown` body to domain types; the UI picks Spanish copy from the error class, never from `problem.detail`.
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

`GET /api/products?q=leche entera` → FULLTEXT search over available products (PD-22) of enabled supermarkets → ranked by `unitPrice` ascending (products without a unit price go last) → paginated.

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
    SPA->>API: POST /api/auth/activation/{token}/password
    API-->>SPA: TOTP secret + QR code
    User->>SPA: Scans QR, enters 6-digit code
    SPA->>API: POST /api/auth/activation/{token}/totp
    API-->>SPA: User ACTIVE, session started
```

### 4. Login

1. `POST /api/auth/login` with username and password. On success, the session is created in a **2FA-pending** state.
2. `POST /api/auth/2fa` with the TOTP code. On success, the session is fully authenticated.
3. Every other `/api` route requires a fully authenticated session. Otherwise it returns `401`, and the SPA redirects to `/login`.

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
| `Shared\Domain\Exception\NotFound` subclass | `404` | Exception message |
| `Shared\Domain\Exception\InvalidArgument` subclass | `422` | Exception message |
| `Shared\Domain\Exception\Conflict` subclass | `409` | Exception message |
| Symfony `HttpException` (unknown route, wrong method...) | Its own status | — |
| Anything else | `500` | — (never exposed; the error is logged) |

Domain error messages are English and must be safe to show (no secrets, no internals). The SPA shows Spanish copy chosen from `status`, never the raw `detail`. The rendering lives in `Shared\Infrastructure\Http\ProblemJsonExceptionListener`.

All routes require a fully authenticated session (login + 2FA) except those marked **public**.

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/api/health` | **public** — `200 {"status":"ok"}` when the database answers, `503 {"status":"unavailable"}` otherwise (PD-17) |
| POST | `/api/auth/login` | **public** — username + password → 2FA-pending session |
| POST | `/api/auth/2fa` | 2FA-pending session — TOTP code → authenticated |
| POST | `/api/auth/logout` | End session |
| GET | `/api/auth/me` | Current user (id, username, role) |
| GET | `/api/setup` | **public** — `{ required: bool }` (true only while there are no users) |
| POST | `/api/setup` | **public**, only while there are no users — setup code + username → creates the first `ADMIN` and returns its activation token |
| GET | `/api/auth/activation/{token}` | **public** — check that an activation link is valid |
| POST | `/api/auth/activation/{token}/password` | **public** — set password, returns TOTP enrollment data |
| POST | `/api/auth/activation/{token}/totp` | **public** — confirm TOTP code, activates user and logs in |
| GET | `/api/admin/users` | ADMIN — list users and their status |
| POST | `/api/admin/users` | ADMIN — create user → activation link |
| GET | `/api/supermarkets` | Enabled supermarkets |
| GET | `/api/products?q=&supermarket=&page=` | Search, ranked by unit price |
| GET | `/api/products/{id}` | Product detail |
| GET | `/api/products/{id}/price-history` | Price snapshots |
| GET | `/api/lists` | Lists the current user owns or is a member of |
| POST | `/api/lists` | Create list (current user is the owner) |
| POST | `/api/lists/join/{shareToken}` | Join a list through its secret link → `{ id }` |
| GET | `/api/lists/{id}` | List with items, members and per-supermarket totals (ETag) |
| PATCH | `/api/lists/{id}` | Owner — rename list |
| POST | `/api/lists/{id}/share-token` | Owner — regenerate the secret link |
| DELETE | `/api/lists/{id}/members/{userId}` | Owner — remove a member |
| POST | `/api/lists/{id}/items` | Add free-text or product item |
| PATCH | `/api/lists/{id}/items/{itemId}` | Change quantity, check/uncheck, reorder |
| DELETE | `/api/lists/{id}/items/{itemId}` | Remove item |
| DELETE | `/api/lists/{id}/items?checked=true` | Clear checked items |

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
| `user` | `id`, `username` UNIQUE, `role`, `status`, `password_hash`, `totp_secret_encrypted`, `totp_confirmed_at`, `activation_token_hash` UNIQUE, `activation_expires_at`, `created_at`, `created_by` |
| `shopping_list` | `id`, `owner_id`, `share_token` UNIQUE, `name`, `version`, `created_at`, `updated_at` |
| `list_member` | `list_id`, `user_id`, `joined_at` — PK(`list_id`, `user_id`) |
| `list_item` | `id`, `list_id`, `type` (`free_text`/`product`), `text`, `product_id`, `quantity`, `checked`, `position` |

Charset and collation: `utf8mb4` / `utf8mb4_0900_ai_ci`.

## Testing strategy

| Level | Scope | Tooling | I/O |
| --- | --- | --- | --- |
| Unit | Domain and application (use cases with in-memory port fakes) | PHPUnit / Vitest | None |
| Integration | DBAL repositories, supermarket adapters (recorded fixtures), FE API clients (MSW) | PHPUnit + MySQL test DB / Vitest + MSW | Real DB, fake HTTP |
| Functional | HTTP API end to end through the Symfony kernel | PHPUnit `WebTestCase` | Real DB |
| Component | Presentational and container components | Vitest + Testing Library | MSW |
| E2E | Critical journeys (activation → login with 2FA → search → add to list → join through link) | Playwright (TOTP codes generated from a known test secret) | Full stack in Docker |
| Architecture | Layer dependency rules | Deptrac (BE), ESLint import rules (FE) | — |

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

Configuration lives in `.env` (`APP_SECRET`, `DATABASE_URL`, `INSTANCE_POSTAL_CODE`, `ENABLED_SUPERMARKETS`, `INGESTION_SCHEDULE`, `TOTP_ENCRYPTION_KEY`, `ACTIVATION_LINK_TTL`). TLS is terminated by the user's own reverse proxy.

### Development environment

`compose.yaml` runs `nginx`, `php` (php-fpm, built from `docker/php/Dockerfile`), `scheduler` (same image, Messenger worker for scheduled jobs), `mysql` and `frontend` (Vite dev server on Node 26, `npm ci` on start, `node_modules` in the `frontend-node-modules` named volume).

| Variable (root `.env`, documented in `.env.example`) | Purpose |
| --- | --- |
| `HTTP_PORT` | Host port published by nginx (default `8080`). |
| `MYSQL_ROOT_PASSWORD`, `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD` | MySQL credentials. Compose builds the backend `DATABASE_URL` from them, which overrides the default in `backend/.env`. |
| `UID`, `GID` | Host user and group the php container runs as, so files written through the bind mount stay yours. |
| `INSTALL_XDEBUG` | Build argument: `true` installs Xdebug in the php image (rebuild after changing it). |
| `XDEBUG_MODE` | Xdebug mode at runtime (`off`, `debug`, `coverage`, `develop`). |

Symfony variables (`backend/.env`, committed with non-secret defaults; real values in `backend/.env.local`):

| Variable | Purpose |
| --- | --- |
| `APP_ENV` | Symfony environment (`dev` by default). |
| `APP_SECRET` | Framework secret. Empty in `backend/.env`; public placeholders in `.env.dev` and `.env.test`. Required in production. |
| `DATABASE_URL` | MySQL connection. Overridden by Compose in development. |
| `DEFAULT_URI` | Base URI for absolute URLs generated outside HTTP requests (console commands). Default `http://localhost`. |
| `INSTANCE_POSTAL_CODE` | Spanish postal code whose prices are imported (PD-5). Empty in `backend/.env`: set it in `backend/.env.local`. `app:catalog:ingest` refuses to run without a valid one. |
| `ENABLED_SUPERMARKETS` | Comma-separated slugs of the supermarkets to import and show (PD-24). Default `mercadona,aldi,bonpreu` (PD-31, PD-32). |
| `CATALOG_HTTP_USER_AGENT` | User-Agent sent to the supermarkets (PD-26). |
| `INGESTION_SCHEDULE` | Cron expression for catalog ingestion, evaluated in Europe/Madrid (PD-27). Default `0 5 * * *`. |

- nginx routes `/api` to Symfony (`backend/public/index.php`) and every other path to the Vite dev server (`frontend:5173`), including the HMR websocket. The SPA and the API share the origin `http://localhost:${HTTP_PORT}`.
- MySQL starts with `utf8mb4` / `utf8mb4_0900_ai_ci`, and DBAL creates tables with the same default. On the first start of the `mysql-data` volume, `docker/mysql/init/01-create-test-database.sh` creates `<MYSQL_DATABASE>_test`. The test environment uses that database (Doctrine `dbname_suffix: _test`).
- CI (`.github/workflows/ci.yml`, GitHub Actions) runs on every push: the backend `composer check` inside the Compose php and mysql containers, the frontend `npm run check`, and then the Playwright suite against the full stack.
- End-to-end tests (Playwright, `frontend/e2e/`) run against the running stack: `npm run test:e2e` in `frontend/`. `E2E_BASE_URL` overrides the target (default `http://localhost:8080`).
- Frontend quality commands run in `frontend/` with Node 26 (`npm ci` first): `npm run test`, `npm run typecheck`, `npm run lint`, `npm run format:check` and `npm run check` (runs all of them).
- Schema changes are Doctrine migrations in `backend/migrations/` (raw SQL, UTC `DATETIME(6)`, UUIDs as `BINARY(16)`). Apply them with `bin/console doctrine:migrations:migrate`. `composer test` first runs `composer db:test`, which migrates the test database.
- Catalog ingestion: `bin/console app:catalog:ingest [--supermarket=<slug>]`. It prints one row per run (status, seen, skipped, marked unavailable, duration, error). Exit code `0` when every run succeeded, `1` when any run failed, `2` for invalid input or configuration (unknown or disabled `--supermarket`, invalid `INSTANCE_POSTAL_CODE`), in which case nothing is ingested. Supermarket adapters implement `SupermarketCatalogSource` and are collected through the `catalog.supermarket_source` tag (`_instanceof` in `config/services/catalog.yaml`). In the test environment two `ConfigurableCatalogSource` fakes replace them.
- Scheduling (E1.21): `Catalog\Infrastructure\Scheduler\CatalogIngestionSchedule` (Symfony Scheduler, schedule `default`) sends `app:catalog:ingest` as a `RunCommandMessage` on `INGESTION_SCHEDULE`. A failing command does not throw (the runs are already recorded and logged), so Messenger never retries a whole ingestion. The schedule is stateful (cache): if the worker was down at the scheduled time, it runs once when it comes back. The `scheduler` Compose service runs `messenger:consume scheduler_default --time-limit=3600` and restarts after each hour. `bin/console debug:scheduler` shows the next run.
- Supermarket HTTP (E1.19): adapters use the `catalog.http_client` service built by `CatalogHttpClientFactory` on Symfony HttpClient. It sends the PD-26 User-Agent, uses a 10 s idle timeout and a 30 s maximum per request, leaves at least 1 s between the start of two requests (`PoliteHttpClient`, with an injected `Sleeper` so tests never sleep) and retries `429`/`5xx` up to 3 times with exponential backoff from 1 s (a `Retry-After` header wins). The numbers are arguments of `CatalogHttpOptions` in `config/services/catalog.yaml`; the spikes may tune them per chain.
- Mercadona adapter (E1.14, PD-28): `Catalog\Infrastructure\Source\Mercadona\MercadonaCatalogSource`. It turns the postal code into a warehouse (`PUT /api/postal-codes/actions/change-pc/`, header `x-customer-wh`), lists the categories and reads every leaf category with `?wh=`. Packaging text is `unit_size` + `size_format` (already the total size of a pack); approximate sizes pass Mercadona's `reference_price` instead (PD-29). Prices are converted from decimal strings with `DecimalCents` (no floats). A full run is about 150 requests and 2.5 minutes. Tests use recorded fixtures in `backend/tests/Fixtures/Mercadona/`; in the test container `ConfigurableCatalogSource` decorates the adapter so it is never called.
- Logging: Monolog. Ingestion logs go to the `ingestion` channel through the `IngestionLogger` application port (`PsrIngestionLogger`): a warning per skipped product and one record per finished run (`info` on success, `error` on failure) with `run_id`, `supermarket`, `status`, counts, `duration_ms` and `error` in the context. In `prod` this channel is written as JSON to stderr at `info` level, outside the `fingers_crossed` buffer.
- Backend quality commands run inside the php container: `composer db:test`, `composer test`, `composer test:unit`, `composer analyse`, `composer deptrac`, `composer cs` and `composer check` (runs all of them).

## Open questions

These have **not** been decided. Implementation must stop and ask before coding any of them.

| ID | Question | Raised in |
| --- | --- | --- |
| OQ-2 | Price history retention: keep forever, or downsample/prune after N months? | E5 |
| OQ-3 | Concurrent edits on the same item (two people check/uncheck at once): is last-write-wins acceptable? | E4 |
| OQ-4 | Can a list be deleted (by whom, and what do members see)? Can a member leave a list on their own? Can ownership be transferred? | E4 |
| OQ-7 | Search ranking ties and filters (brand, category, supermarket) needed in the MVP? | E2 |
| OQ-8 | Activation link lifetime (proposal: 72 h), and can the admin regenerate it for a pending user? | E3 |
| OQ-9 | Lost password or lost 2FA device: without email, the proposal is that the admin resets the user (new activation link → new password + new TOTP). Also, do users get TOTP recovery codes? | E3 |
| OQ-10 | Admin user management beyond creation: disable/enable, delete (what happens to their lists?), change role? | E3 |
| OQ-11 | Password policy (minimum length, breached-password check?) and session lifetime ("remember me"?). | E3 |
| OQ-12 | Can a logged-in user change their own password and re-enroll TOTP from a profile page? | E3 |

Once answered, move each item to [Product decisions](#product-decisions) with a new `PD-n` id.
