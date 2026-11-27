# Epics

Progress tracking for jolo-market. Each epic file lists its tasks as checkboxes. Tick a box only when the task meets the [Definition of done](../../AGENTS.md#6-definition-of-done).

| Epic | Goal | Depends on | Status |
| --- | --- | --- | --- |
| [E0 — Foundation](E0-foundation.md) | Repo skeleton, tooling, CI, Docker dev environment | — | Done |
| [E1 — Catalog ingestion](E1-catalog-ingestion.md) | Catalog domain + Mercadona, Bonpreu i Esclat and Aldi adapters | E0 | Done |
| [E2 — Search & comparison](E2-search-and-comparison.md) | Search products and rank by unit price | E1 | Done |
| [E3 — Identity & access](E3-identity-and-access.md) | Admin-created users, activation link, mandatory TOTP 2FA, login | E0 | Done |
| [E4 — Shared shopping lists](E4-shared-shopping-lists.md) | Owned lists, join through secret link, members, items, totals, polling | E3 (E2 for product items) | Done |
| [E5 — Price history](E5-price-history.md) | Expose and chart price evolution | E1 | Done |
| [E6 — Self-hosting](E6-self-hosting.md) | Production compose, backups, operator docs | E0–E4 | Done |
| [E7 — Auto-deploy](E7-auto-deploy.md) | Optional self-update of the server after a merge to `main` with a green CI | E0 | Done |
| [E8 — Offline development environment](E8-offline-dev-environment.md) | Dummy catalog sources, price history backfill and dummy users, so dev works without internet | E1, E3, E5 | Done |
| [E9 — Makefile](E9-makefile.md) | `make` targets for the development stack and its checks; agents ask about new targets | E0, E7 | Done |
| [E10 — Receipts and spending](E10-receipts-and-spending.md) | Upload receipt photos to a list, read them with local OCR, link lines to products and track monthly spending | E2, E4, E6 | Not started |
| [E11 — Dark mode](E11-dark-mode.md) | Dark theme that follows the system by default, with a Sistema / Claro / Oscuro selector in the header, stored in the browser | E0 | Done |
| [E12 — Path-filtered CI](E12-path-filtered-ci.md) | On branches, CI runs only the jobs the changed files affect; docs-only pull requests run no tests | E0, E7 | Done |
| [E12 — Product search in lists](E12-product-search-in-lists.md) | The add-item field of a list suggests catalog products while typing, so products are added without leaving the list | E2, E4 | Done |
| [E13 — Filter a list by supermarket](E13-filter-list-by-supermarket.md) | Chips above a list's items show only the products of one supermarket with its estimated total, kept in the URL | E4, E12 | Done |
| [E14 — Group a list by section](E14-list-sections.md) | A list shows its pending items grouped by store section, in a fixed order; sections come from the catalog category and any member can change them | E4, E13 | In progress |
| [E15 — Product images in lists](E15-list-product-images.md) | Product items of a list and the add-item suggestions show the catalog image (48 px, link to the product page in the list); a broken image falls back to the placeholder everywhere | E2, E4, E12 | Done |
| [E16 — Bilingual search with Typesense](E16-bilingual-search.md) | Search in Spanish or Catalan finds every supermarket's products through a Typesense index with a Spanish ↔ Catalan dictionary, and falls back to typo-tolerant matching | E2, E6 | In progress |
| [E17 — Totals count pending items only](E17-pending-totals.md) | Checking an item as bought lowers its supermarket's total and chip total; the totals count only what is still to buy | E4, E13 | Done |

E2 can be built before E3. Its endpoints are put behind login in task E3.21.

Status values: `Not started` · `In progress` · `Blocked (question)` · `Done`.

## Conventions

- Task ids are `E<epic>.<n>` (for example `E1.4`), so commits and questions can reference them.
- `Spike` tasks are time-boxed research. Their output is a written note in the epic, not production code.
- `❓` marks a task blocked by an [open question](../architecture.md#open-questions). Ask before starting it.
