# Epics

Progress tracking for jolo-market. Each epic file lists its tasks as checkboxes. Tick a box only when the task meets the [Definition of done](../../AGENTS.md#6-definition-of-done).

| Epic | Goal | Depends on | Status |
| --- | --- | --- | --- |
| [E0 — Foundation](E0-foundation.md) | Repo skeleton, tooling, CI, Docker dev environment | — | Done |
| [E1 — Catalog ingestion](E1-catalog-ingestion.md) | Catalog domain + Mercadona, Bonpreu i Esclat and Aldi adapters | E0 | Done |
| [E2 — Search & comparison](E2-search-and-comparison.md) | Search products and rank by unit price | E1 | Done |
| [E3 — Identity & access](E3-identity-and-access.md) | Admin-created users, activation link, mandatory TOTP 2FA, login | E0 | In progress |
| [E4 — Shared shopping lists](E4-shared-shopping-lists.md) | Owned lists, join through secret link, members, items, totals, polling | E3 (E2 for product items) | Not started |
| [E5 — Price history](E5-price-history.md) | Expose and chart price evolution | E1 | Not started |
| [E6 — Self-hosting](E6-self-hosting.md) | Production compose, backups, operator docs | E0–E4 | Not started |

E2 can be built before E3. Its endpoints are put behind login in task E3.21.

Status values: `Not started` · `In progress` · `Blocked (question)` · `Done`.

## Conventions

- Task ids are `E<epic>.<n>` (for example `E1.4`), so commits and questions can reference them.
- `Spike` tasks are time-boxed research. Their output is a written note in the epic, not production code.
- `❓` marks a task blocked by an [open question](../architecture.md#open-questions). Ask before starting it.
