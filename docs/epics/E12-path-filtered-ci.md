# E12 — Path-filtered CI

**Goal:** a contributor gets faster feedback and wastes no CI minutes: on a branch, CI runs only the jobs that the branch's changes affect, so a pull request that only touches documentation runs no tests. Pushes to `main` still run every job, so auto-deploy keeps deploying only fully checked commits.

Related decisions: PD-50, PD-51, PD-61, PD-107, PD-108, PD-109, PD-110, PD-111.

## Acceptance criteria

- On a branch whose changes since its merge-base with `origin/main` are only documentation (`docs/**`, `*.md`, `.claude/**`, `LICENSE`), every check job is skipped and CI is green.
- On a branch that only changes `frontend/**`, Frontend check and End-to-end run; Backend check and Deploy scripts are skipped.
- On a branch that only changes `backend/**`, `compose.yaml`, `.env.example`, `docker/php/**` or `docker/mysql/**`, Backend check and End-to-end run; Frontend check and Deploy scripts are skipped.
- On a branch that only changes `docker/nginx/**`, only End-to-end runs.
- On a branch that only changes `deploy/**`, `Makefile` or `docker/bats/**`, only Deploy scripts runs.
- On a branch that only changes `docker/php/entrypoint.sh`, Backend check, End-to-end and Deploy scripts run (PD-110).
- `make ci-scope` prints which jobs CI would run for the current branch (PD-111).
- On a branch that changes `.github/workflows/**`, the scope script, or any file that matches no rule, every job runs.
- The scope covers every file changed since the merge-base, not only the last push (PD-108).
- End-to-end runs when either check runs, and only after the backend and frontend checks have passed or been skipped.
- On a push to `main`, every job runs, Production images included (PD-107).

## Tasks

### Scope script

- [x] E12.1 Add `deploy/ci-scope.sh`: given a base ref (default `origin/main`), it lists the files changed since the merge-base with `HEAD` and prints `backend=`, `frontend=`, `e2e=` and `deploy=` with `true` or `false` following the PD-109 rules (documentation triggers nothing; workflows, the script itself and unmatched files trigger everything; any frontend or backend trigger also sets `e2e`). Test it with bats in `deploy/tests/ci-scope.bats` against temporary git repositories (PD-51), one test per rule and per acceptance criterion above (docs only, frontend only, backend files, nginx, deploy files, `docker/php/entrypoint.sh`, workflow change, unmatched file, docs commit on top of a backend commit). ShellCheck already covers `deploy/*.sh`. Add the `make ci-scope` target that runs it (PD-111), tested in `deploy/tests/makefile.bats`.
  - Note: when the merge-base cannot be computed (base ref missing, unrelated history), the script warns on stderr and runs every job, the same "to be safe" default PD-109 gives unmatched files. A rename counts both its old and new path.
  - Note: the CI decisions were numbered PD-80 to PD-82, ids also used by the list-suggestion decisions of the other E12. They were renumbered PD-107 to PD-109 on 2026-11-22.

### Workflow

- [ ] E12.2 In `.github/workflows/ci.yml`, add a `changes` job (checkout with enough history for the merge-base) that runs `deploy/ci-scope.sh` and exposes its values as outputs, forcing all of them to `true` on `refs/heads/main` (PD-107). Gate Backend check, Frontend check, Deploy scripts and End-to-end with `if:` on those outputs; End-to-end needs `changes`, `backend` and `frontend` and runs when `e2e` is true and neither check failed or was cancelled (a skipped check does not block it). Production images keeps its `main`-only condition. Verify on this epic's own pull request branch: a docs-only push skips every check, and a push touching one area runs only its jobs.

### Docs

- [ ] E12.3 Update the README (Continuous integration: which files trigger which job, everything on `main`) and `docs/architecture.md` → Development environment (the `changes` job and `deploy/ci-scope.sh`, PD-107 to PD-111).
