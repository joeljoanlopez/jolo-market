# jolo-market

Self-hosted supermarket price comparator and shared shopping list, inspired by [quefalta.es](https://quefalta.es/).

> **Status:** design phase. No code has been written yet. Progress is tracked in [`docs/epics/`](docs/epics/README.md).

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
| Backend | PHP 8.4 + Symfony 7.4 LTS (API only) |
| Persistence | MySQL 8.4 LTS, Doctrine DBAL + Doctrine Migrations |
| Frontend | TypeScript + React + Vite (SPA), Tailwind CSS |
| Backend tests | PHPUnit, PHPStan (max level), Deptrac, PHP-CS-Fixer |
| Frontend tests | Vitest, Testing Library, MSW, Playwright |
| Runtime | Docker Compose (nginx, php-fpm, mysql, scheduler) |

Rationale for each choice: [`docs/architecture.md`](docs/architecture.md#stack).

## Repository layout (planned)

```text
jolo-market/
├── backend/            # Symfony API, hexagonal architecture
├── frontend/           # React + TypeScript SPA
├── docker/             # Dockerfiles and nginx config
├── docs/
│   ├── architecture.md # Stack, architecture, domain model, decisions
│   └── epics/          # Epics with task checklists (progress tracking)
├── compose.yaml
├── .env.example
└── AGENTS.md           # Rules for AI assistants (TDD, hexagonal, English code)
```

## Getting started

Not available yet. Setup instructions will be written as part of [E0 — Foundation](docs/epics/E0-foundation.md) and [E6 — Self-hosting](docs/epics/E6-self-hosting.md).

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
