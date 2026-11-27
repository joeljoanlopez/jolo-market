---
name: implement-epic
description: Implement an existing epic of docs/epics from its number (for example "E12" or "12"), task by task with TDD, after asking the product owner every open question and undefined case and recording the answers, and open a pull request with the implementation. Use when the user asks to implement, build or start an epic ("implementa la E12", "haz la épica 14", "/implement-epic E12").
---

# Implement epic

Implement every pending task of one epic and deliver it as a single pull request. The product owner (the user) decides product behavior, so **before and while coding, every open question and every case the epic does not clearly define is asked, answered and written down**. Never invent behavior to keep going.

This skill always ends with a pull request: create the branch, commit, push and open the PR without asking. Never commit to `main` or merge the PR; the product owner reviews and merges it.

Follow [AGENTS.md](../../../AGENTS.md) at all times (TDD, hexagonal architecture, language, Definition of done). Talk to the user in their language; everything written to the repository is in English, except the Spanish UI copy in `es.json`.

## 1. Find the epic

1. Normalize the argument: `E12`, `e12` and `12` all mean epic number 12. If no number was given, ask for it.
2. List `docs/epics/E<n>-*.md`.
   - **None**: say so, list the epics of `docs/epics/README.md` that are not `Done`, and stop.
   - **More than one** (epic numbers have been reused, for example two `E12` files): ask which one with `AskUserQuestion`, showing each file with its index status, and recommend the one that is not `Done`.
3. If the epic is `Done` in the index, or every task is ticked, tell the user and stop unless they ask for something specific.
4. Check that the working tree is clean. If it is not, stop and ask what to do with the changes; never stash, reset or discard them yourself.

## 2. Understand the epic

1. Read the whole epic: goal, related decisions, acceptance criteria, tasks and the notes under them. Ticked tasks are already done; work only on the unticked ones, in order.
2. Read `docs/epics/README.md` (conventions and **Depends on**). If a dependency epic is not `Done`, tell the user what is missing and ask whether to continue.
3. Read `docs/architecture.md`: every PD the epic references, the **Open questions** (OQ-n), and the sections the tasks touch (bounded contexts, HTTP API, flows, frontend structure, development environment).
4. Explore the code each task changes, using the real names the tasks mention (use cases, ports, endpoints, features, translation keys, e2e specs, workflows, scripts). Note what exists and what each task must add.

## 3. Ask before coding

Build the list of questions **before writing any code**:

- Every `OQ-n` the epic or its `❓` tasks reference.
- Every case the epic leaves undefined that you found while reading the code: a missing rule, an edge case, an acceptance criterion that can be read two ways, docs that conflict with each other or with the code, a task that turns out bigger than one PR or needs a product choice.
- A `make` target for every development command the epic adds or changes, when the epic does not already say (PD-61).

Do not ask what the docs, the code or a sensible technical default answers. Technical choices that follow the architecture (layer, port, adapter, names) are yours; product behavior is the user's.

Ask **one question at a time**, most fundamental first:

- Use the `AskUserQuestion` tool. Each question stands on its own: say briefly what you found that raises it and which task it affects.
- Give 2–4 concrete options with their tradeoffs. Put your recommended option first, marked "(Recommended)".
- After each answer, update the list: drop questions it settled, add the ones it raised.

Record every answer **right away**, in the same style as the existing docs:

- A product decision becomes the next `PD-n` in `docs/architecture.md` → Product decisions, ending with *(Confirmed by the product owner on YYYY-MM-DD.)*. A decision that replaces another says *(Supersedes PD-x.)* and the old one is marked as replaced.
- An answered `OQ-n` is removed from Open questions (leave "None right now." when the section becomes empty) and becomes a `PD-n`.
- In the epic, add the new PD to **Related decisions**, remove the `❓` marker and the OQ id from the tasks it unblocks, and adjust the acceptance criteria or tasks the answer changes.
- Facts that matter for the implementation but are not product decisions (a scope note, why a task was split, a technical finding) go as an indented `- Note:` under the task, like the existing epics do.

If the user prefers to leave a question for later, the tasks that depend on it stay unticked with their `❓`. Implement the rest only if it still makes sense on its own; otherwise stop and say so.

**Questions that come up later** (while implementing) follow the same rule: stop, ask, record, then continue. Never code past an undefined case.

## 4. Prepare the branch

1. Update `main` (`git fetch origin` and fast-forward `main` to `origin/main`) and branch from it: `<type>/e<n>-<kebab-slug>`, with the slug of the epic file name and the Conventional Commits type that fits most of the epic (`feat`, `fix`, `ci`, `build`, `refactor`, `docs`…).
2. Make sure the Docker stack is running (`make start`) when the epic needs the backend checks or the end-to-end suite.
3. If the epic has an associated issue (its goal says "Requested in [issue #n]"), move that issue to **In progress** on the project board (PD-115, see "Issue board" below).
4. In `docs/epics/README.md`, set the epic status to `In progress`. Commit it together with the docs written in step 3 (`docs(epics): record E<n> decisions`, listing the new PDs), or with the first task if there were no questions.

## 5. Implement task by task

For each unticked task, in order:

1. Re-read the task, its notes and the acceptance criteria it covers.
2. **Red → green → refactor** (AGENTS.md §3): one small failing test for the next piece of behavior, run it and see it fail for the expected reason; the minimum code to pass; refactor with the suite green. Repeat until the task is complete. Every acceptance criterion the task covers has at least one test.
3. Keep the layers and rules of AGENTS.md §4: Deptrac must pass, no `deptrac.yaml` exceptions, Money in cents, no hard-coded UI copy, Tailwind theme tokens only.
4. Update the docs the task changes in the same commit: `docs/architecture.md` and the README for endpoints, commands and config variables (AGENTS.md §5).
5. Run the checks the task affects: `make check-backend`, `make check-frontend`, `make test-deploy`, and `npm run test:e2e` in `frontend/` when a journey changes. Fix every failure; never skip or weaken a test to go green.
6. Tick the task (`- [x]`) only when it is implemented, tested and its checks pass. Commit code, tests, docs and the tick together, with a Conventional Commit that names the task: `feat(shopping-list): suggest products while typing (E12.3)`.

If a task turns out to be bigger than planned, or impossible as written, add a `- Note:` under it and ask before changing its scope.

## 6. Finish

1. Run `make check` and, when any user journey changed, `npm run test:e2e` in `frontend/`. Everything must be green.
2. Self-check:
   - Every acceptance criterion has at least one test, and every task is ticked (or unticked with a `❓` and a recorded reason).
   - No assumption is left unconfirmed; every new decision is a PD-n referenced from the epic.
   - Docs (architecture, README, epic) match what was built.
3. In `docs/epics/README.md`, set the status to `Done` when every task is ticked, or keep `In progress` / set `Blocked (question)` otherwise. Commit it with the last task or as `docs(epics): mark E<n> as done`.

## 7. Open the pull request

1. Push the branch and run `gh pr create`:
   - Title: `<type>(<scope>): <epic title> (E<n>)`.
   - Body: the goal, the tasks implemented (E<n>.m with one line each), the decisions recorded during the work (PD-n) and their notes, anything left pending (unticked tasks and why), how it was verified (checks and e2e), and the attribution line required in this session.
2. Watch CI with `gh pr checks --watch`. If it fails, fix it on the branch and push again. A red CI means the epic is not done.
3. If the epic has an associated issue, move it to **In review** on the project board as soon as the PR exists (PD-115).
4. Give the user the PR link, the decisions recorded and anything left pending.

## Issue board

Epics requested from an issue follow PD-115: `To do` when the epic is added (`/new-epic` PR), `In progress` when implementation starts, `In review` when the implementation PR is open. To change the status:

1. Find the project and the item: `gh project list --owner joeljoanlopez`, then `gh project item-list <project-number> --owner joeljoanlopez --format json` and pick the item whose content is the issue.
2. Set the field: `gh project field-list <project-number> --owner joeljoanlopez` for the `Status` field and option ids, then `gh project item-edit --id <item-id> --project-id <project-id> --field-id <status-field-id> --single-select-option-id <option-id>`.
3. If it fails (for example the token lacks the `project` scope), tell the user which move is pending. Do not skip it silently and do not block the PR on it.
