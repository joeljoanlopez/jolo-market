# E7 — Auto-deploy

**Goal:** a self-hosted server updates itself when a pull request is merged into `main`, without opening ports or installing a GitHub runner. It is **optional** and **off** by default. Modelled on E11 of jolo-PM.

Related decisions: PD-50, PD-51.

## Acceptance criteria

- With `AUTO_DEPLOY=false` (or unset) in the root `.env`, the timer runs and changes nothing.
- With `AUTO_DEPLOY=true`, within one interval after a merge whose CI is green, the server fast-forwards to the new commit, rebuilds the containers, installs the backend dependencies, applies the database migrations and restarts the scheduler and the frontend.
- A commit whose CI is red or still running is not deployed (a running CI is checked again on the next run).
- Nothing is touched when the copy is on another branch, has uncommitted changes in tracked files or has commits that are not in `origin/main`: it logs why and exits.
- A deploy that fails after the merge (compose, dependencies or migrations) is retried on every run until it succeeds.
- Two runs never overlap.

## Tasks

- [x] E7.1 Add a bats-core test harness in Docker (with ShellCheck) and a CI job that runs it.
- [x] E7.2 Add `deploy/auto-deploy.sh` with the checks on `AUTO_DEPLOY`, branch, local changes, local commits and CI state, the fast-forward, `docker compose up` and the retry marker.
- [x] E7.3 Add `deploy/post-deploy.sh`: `composer install`, database migrations, restart the scheduler and the frontend.
- [x] E7.4 Add `deploy/install-auto-deploy.sh`, which installs and uninstalls the user systemd units `jolo-market-deploy.service` and `.timer` with the absolute path of the repository.
- [x] E7.5 Document `AUTO_DEPLOY` in `.env.example`, and the setup, logs and requirements in the README and the architecture doc.
- [x] E7.6 Try it on the server: no-op while disabled, deploy after a merge, a red CI blocks it. *(Done by the product owner.)*
