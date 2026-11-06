# AI assistant instructions

These rules apply to every AI assistant working on this repository (Claude Code, Codex, Copilot, etc.). They are mandatory. If a rule blocks you, stop and ask. Do not work around it.

## 1. Ask before assuming

- The product owner decides product behavior. Confirmed decisions live in [`docs/architecture.md` → Product decisions](docs/architecture.md#product-decisions).
- If you find **any case that is not clearly defined** (a missing rule, an edge case, conflicting docs, an ambiguous acceptance criterion), **stop and ask** before writing code for it. Do not invent behavior "to keep going".
- Check [Open questions](docs/architecture.md#open-questions) before starting a task. If the task depends on an open question, ask it first.
- Ask one question at a time, with a recommended option and its tradeoffs.
- When a question is answered, record it in `docs/architecture.md` as a new `PD-n` and remove it from Open questions.

## 2. Language

- **All code is in English**: identifiers, comments, docblocks, test names, commit messages, log messages, exception messages, technical docs.
- **User-facing UI copy** is Spanish, and it lives **only** in translation files (`frontend/src/shared/i18n/locales/es.json`). It is never hard-coded in components.
- Comments explain *why*, not *what*. Prefer clear names over comments.

## 3. Test-Driven Development (mandatory)

Every behavior change follows **red → green → refactor**:

1. **Red**: write one small failing test that describes the next piece of behavior. Run it and **see it fail for the expected reason** (not a typo or a missing import).
2. **Green**: write the **minimum** production code that makes it pass. Run the test.
3. **Refactor**: improve names and structure in both test and production code while all tests stay green. Run the full relevant suite.
4. Repeat.

Rules:

- No production code without a failing test that requires it.
- One behavior per test. Name tests by behavior: `it_ranks_products_by_unit_price_ascending`, `returns 404 for an unknown share token`.
- Test through the **public interface** (use case, entity method, HTTP endpoint, rendered component). Never test private methods.
- Every acceptance criterion in an epic has at least one test that covers it.
- When fixing a bug, first write a test that reproduces it.
- Domain and application tests use **in-memory fakes** of ports, not mocking-library mocks of domain objects. Use mocks only to verify interactions with outbound ports when no fake makes sense.
- Tests are deterministic: inject `Clock` and `IdGenerator`. No `sleep`, no real network, no dependency on test order.
- **Never call real supermarket websites in tests.** Adapters are tested against recorded fixtures in `backend/tests/Fixtures/`.
- Keep scratch state (temp dirs, caches) out of the repository.

## 4. Hexagonal architecture

Full description: [`docs/architecture.md`](docs/architecture.md#architecture-style).

- **Screaming structure**: top-level folders are business contexts (`Catalog`, `ShoppingList`), not technical layers.
- **Domain** is pure PHP: no Symfony, no Doctrine, no attributes from vendors, no I/O. It may depend only on `Shared\Domain`.
- **Application** contains use cases (one public `__invoke`), command/query DTOs and ports for application concerns. It depends on Domain only.
- **Infrastructure** holds adapters: controllers, console commands, DBAL repositories, supermarket HTTP sources, clock, token generator. It implements ports.
- **Ports are owned by the inner layer**. Interfaces are defined where they are used, not where they are implemented.
- **Cross-context access** goes only through the other context's application query API, wrapped in a port of the consuming context. Never import another context's Domain.
- Controllers map HTTP ↔ DTOs and call one use case. They contain no business logic.
- Entities protect invariants: named constructors, no public setters. Value objects are `final readonly` and validate themselves.
- Money is always integer cents in a `Money` value object. Never use floats for money.
- **Deptrac must pass.** Never add exceptions to `deptrac.yaml` to make a build green. Ask instead.

### Frontend equivalent

- Feature-first folders: `features/<feature>/{domain,application,infrastructure,ui}`.
- `domain/` has no React and no fetch. `infrastructure/` maps API DTOs to domain types.
- **Container/presentational**: containers fetch and hold state; presentational components get props and emit events only.
- Shared UI components follow **atomic design** (`atoms`, `molecules`, `organisms`, `templates`).
- Styling is **Tailwind with theme tokens only**: no arbitrary values (`[13px]`, `[#hex]`) and no `@apply`. Repeated class combinations become a component with `cva` variants. If a token is missing, add it to the theme and ask if it changes the visual design.
- TypeScript `strict` mode. No `any`. No `@ts-ignore` without a linked reason.

## 5. Workflow and tracking

- Work is tracked in [`docs/epics/`](docs/epics/README.md). Before starting, read the epic and the task.
- Tick a checkbox (`- [x]`) **only** when the task is implemented, tested, and all quality checks pass.
- If a task turns out to be bigger, or needs a decision, add a note under it and ask. Do not silently change its scope.
- Keep the epic index (`docs/epics/README.md`) status column up to date.
- When adding or changing an endpoint, command or config variable, update `docs/architecture.md` and the README in the same change.

## 6. Definition of done

A task is done when all of these hold:

- [ ] Tests were written first and cover every acceptance criterion of the task.
- [ ] Backend: PHPUnit, PHPStan (max level), Deptrac and PHP-CS-Fixer pass: `docker compose exec php composer check`.
- [ ] Frontend: Vitest, `tsc --noEmit`, ESLint and Prettier pass: `npm run check` in `frontend/`. Playwright passes if the journey is affected: `npm run test:e2e` in `frontend/`, with the Docker stack running.
- [ ] No hard-coded user-facing strings. Spanish copy lives in translation files.
- [ ] Docs updated (architecture, README, epic checkbox).
- [ ] No unanswered assumptions. Every new decision has been confirmed and recorded.

Commands:

| Scope | Command | Runs |
| --- | --- | --- |
| Backend | `docker compose exec php composer check` | PHP-CS-Fixer (dry run), PHPStan max, Deptrac, every PHPUnit suite |
| Backend | `docker compose exec php composer test:unit` | Unit suite only (fast TDD loop) |
| Frontend | `npm run check` (in `frontend/`) | Prettier check, ESLint, `tsc --noEmit`, Vitest |
| Frontend | `npm run test:watch` (in `frontend/`) | Vitest in watch mode (fast TDD loop) |
| End to end | `npm run test:e2e` (in `frontend/`) | Playwright against the running Docker stack |

CI ([`.github/workflows/ci.yml`](.github/workflows/ci.yml)) runs the backend check, the frontend check and the end-to-end suite on every push. A red CI means the task is not done.

## 7. Git

- Conventional Commits (`feat:`, `fix:`, `test:`, `refactor:`, `docs:`, `chore:`, `build:`, `ci:`), with a scope when useful: `feat(catalog): ...`.
- Small commits that keep tests and code together. Tests are never committed in a separate commit from the behavior they cover.
- Do not commit, push or open PRs unless the product owner asks.
