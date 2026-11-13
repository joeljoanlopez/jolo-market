# E9 — Makefile

**Goal:** a developer sets up, runs and checks the development stack with short `make` commands instead of copying the Docker, Composer and npm commands from the README, and AI assistants ask whether each new development command deserves a `make` target.

Related decisions: PD-51, PD-52, PD-58, PD-59, PD-60, PD-61.

## Acceptance criteria

- `make` with no target prints every target with a one-line description.
- On a fresh clone, `make init` creates the root `.env` and `backend/.env.local` when they are missing, installs the frontend dependencies on the host when Node is installed, starts the stack, installs the backend dependencies and applies the migrations; it warns when `INSTANCE_POSTAL_CODE` is not set.
- Running `make init` again never overwrites `.env` or `backend/.env.local`.
- `make start`, `make stop`, `make restart`, `make logs` (optionally `s=<service>`), `make shell` and `make migrate` run the matching development command; `make stop` never removes volumes.
- `make check` runs the backend check, the frontend check and the deploy script tests, and fails when any of them fails; `make check-backend`, `make check-frontend`, `make test-unit` and `make test-deploy` run one of them.
- `make check-frontend` runs `npm run check` on the host and fails with a clear message when Node is missing.
- With `compose.prod.yaml` in `COMPOSE_FILE` of the root `.env`, every target that runs Docker fails without running anything.
- The README, `docs/architecture.md`, `AGENTS.md` and the Definition of done use the `make` targets, each with the command it runs.
- `AGENTS.md` and the `new-epic` skill tell assistants to ask whether a new or changed development command should get a `make` target.

## Tasks

### Test harness

- [ ] E9.1 Install `make` in the bats image (`docker/bats/Dockerfile`), add a fake `npm` to `deploy/tests/fakes/` and a `deploy/tests/makefile.bats` that runs the root `Makefile` against a temporary copy with fake `docker` and `npm` on the `PATH`, so `deploy/test.sh` (CI job "Deploy scripts", PD-51) runs it.

### Makefile

- [ ] E9.2 Add the root `Makefile` with `help` as the default target (lists every target and its description) and the PD-60 guard: any target that runs Docker fails, running nothing, when the root `.env` lists `compose.prod.yaml` in `COMPOSE_FILE`.
- [ ] E9.3 Add `start` (`docker compose up -d --build --wait`), `stop` (`docker compose down`, never `-v`), `restart`, `logs` (`docker compose logs -f`, only `$(s)` when given), `shell` (`sh` in the php container) and `migrate` (`doctrine:migrations:migrate --no-interaction`) (PD-58).
- [ ] E9.4 Add `init` (PD-59): create `.env` and `backend/.env.local` only when missing, `npm ci` in `frontend/` when Node is installed (otherwise a warning), `up -d --build --wait`, `composer install`, migrations, and a warning when `backend/.env.local` does not set `INSTANCE_POSTAL_CODE`. Test that a second run overwrites nothing.
- [ ] E9.5 Add `check-backend` (`composer check` in the php container), `test-unit` (`composer test:unit`), `check-frontend` (`npm run check` in `frontend/` on the host, a clear failure without Node), `test-deploy` (`deploy/test.sh`) and `check`, which runs the three checks and fails when any fails (PD-58).

### Docs

- [ ] E9.6 Make `make` the documented way in the README (requirements, Getting started, Backend checks, Frontend checks, Deploy scripts, a target table with the command each runs) and in `docs/architecture.md` → Development environment.
- [ ] E9.7 Update `AGENTS.md`: the commands table and the Definition of done use the `make` targets (with the raw command next to them), and §5 adds the PD-61 rule; add the same question to the interview checklist of `.claude/skills/new-epic/SKILL.md`.
