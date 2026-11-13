---
name: new-epic
description: Turn a feature or change request into a new epic in docs/epics with acceptance criteria and E<n>.<m> tasks, after interviewing the product owner about every undefined product decision, and open a pull request with it. Use when the user asks to plan, spec or create an epic for a feature or change ("crea una épica para…", "quiero que…", "/new-epic …").
---

# New epic

Turn a feature request into a ready-to-implement epic. The product owner (the user) decides product behavior, so **your main job is to find every undefined decision and ask about it before writing**. An epic whose tasks hide assumptions is worse than no epic.

This skill always ends with a pull request: create the branch, commit, push and open the PR without asking. Never commit to `main` or merge the PR; the product owner reviews and merges it. The skill only touches docs, never production code or tests.

Follow [AGENTS.md](../../../AGENTS.md) at all times. Talk to the user in their language; everything written to the repository is in English.

## 1. Understand the request

1. Read `docs/epics/README.md` (conventions, existing epics, next free number) and every epic related to the request.
2. Read `docs/architecture.md`: **Product decisions** (PD-n), **Open questions** (OQ-n), the bounded contexts, the HTTP API and the key flows the request touches.
3. Explore the code the change affects (use cases, endpoints, frontend features, translation keys, e2e journeys). Note what already exists and can be reused, and what is missing. Questions and tasks must be grounded in the real code, not guessed.
4. Restate the request in one or two sentences and check it with the user if it is ambiguous.

## 2. Interview the product owner

Build a list of **every product question the epic needs answered**. Go through this checklist and keep only the items that apply and that no existing PD already answers:

- **Scope and goal**: what problem it solves, for whom (roles: `ADMIN`, `USER`, list owner, member), what is explicitly out of scope.
- **Placement**: a new epic, or new tasks in an existing one? (Prefer a new epic when the existing one is `Done`.)
- **Behavior**: the main flow step by step, what the user sees and does, where it lives in the UI.
- **Rules and limits**: validation, minimum/maximum lengths and counts, defaults, ordering, rounding, money.
- **States**: empty, loading, no results, errors, unavailable products (PD-22), slow or failing backend.
- **Permissions and access**: who can do it, what others see, `404` vs hidden UI (PD-15).
- **Interaction with existing decisions**: which PDs it relies on or might change. Changing a PD needs explicit confirmation and a superseding PD.
- **Collaboration and concurrency** when it touches shared lists (PD-7, PD-43).
- **Existing data**: does anything already stored need migrating or keep working as before?
- **Compatibility**: does the current behavior stay available (for example free-text items next to product items)?
- **Copy**: any wording the user cares about (Spanish copy goes to `es.json`).
- **Acceptance**: what the user must be able to do for the feature to count as done, and which journey Playwright must cover.

Ask the questions **one at a time**, most fundamental first (answers often remove or create later questions):

- Use the `AskUserQuestion` tool when available; otherwise ask in plain text.
- Each question stands on its own: say briefly what you found in the code or docs that raises it.
- Give 2–4 concrete options with their tradeoffs. Put your recommended option first, marked "(Recommended)".
- Offer "decide later" when the epic can still be written without the answer. A deferred question becomes an `OQ-n` and the tasks that depend on it get the `❓` marker.
- After each answer, update your list: drop questions it settled, add the ones it raised.
- Do not ask what the code, the docs or a sensible technical default already answers. Technical choices that follow the architecture (layer, port, adapter) are yours; product behavior is the user's.

Stop interviewing when every acceptance criterion and every task can be written without an assumption.

## 3. Confirm the summary

Before writing files, show the user a short summary: goal, decisions taken (future PD-n), deferred questions (future OQ-n), acceptance criteria and the task list. Apply their corrections. Do not continue until they confirm.

## 4. Write the docs

1. **Decisions**: add each answer to `docs/architecture.md` → Product decisions as the next `PD-n`, in the existing style (bold key words, concrete values, examples, and *(Confirmed by the product owner on YYYY-MM-DD.)*). A decision that replaces another says *(Supersedes PD-x.)*, and the old one is marked as replaced.
2. **Open questions**: add deferred ones as the next `OQ-n`, with "Raised in" set to the new epic.
3. **Epic file**: `docs/epics/E<n>-<kebab-slug>.md`, following the existing epics:

   ```markdown
   # E<n> — <Title>

   **Goal:** <one paragraph: who can do what, and why>

   Related decisions: PD-a, PD-b.

   ## Acceptance criteria

   - <observable behavior, testable, one per bullet>

   ## Tasks

   ### <Group, e.g. Domain (ShoppingList), Application, API, Frontend, End to end, Docs>

   - [ ] E<n>.1 <imperative sentence: what to add or change, where, and the rule it follows (PD-x)>
   ```

   Task rules:
   - Ids are `E<n>.<m>`, numbered in implementation order. Each task is small enough for one PR and leaves `main` green.
   - Group them by layer, inside out: domain → application → infrastructure/API → frontend (domain, infrastructure client, containers and presentational UI) → Playwright journey → docs.
   - Each task states the behavior to test, so TDD can start from it. Name the contexts, use cases, endpoints and features involved, using the real names from the code.
   - Every acceptance criterion is covered by at least one task.
   - Include tasks for: new or changed endpoints in `docs/architecture.md` and the README, new Spanish copy in `es.json`, and the e2e journey when a user flow changes.
   - Spikes (time-boxed research) only when an unknown cannot be answered by the user or the code; their output is a note in the epic.
   - Mark tasks that depend on an open question with `❓` and the OQ id.
4. **Index**: add a row to the table in `docs/epics/README.md` (link, goal, depends on, status `Not started`, or `Blocked (question)` if a ❓ blocks the first task).

Do not touch production code, tests or other epics' checkboxes.

## 5. Self-check

Before committing, check that:

- Every acceptance criterion maps to at least one task, and every task to an acceptance criterion or a docs/quality need.
- No task contains an assumption the user did not confirm.
- PD and OQ ids are consecutive and referenced from the epic.
- Links work and the Markdown matches the style of the other epics.

## 6. Open the pull request

1. Branch from an up-to-date `main`: `docs/e<n>-<kebab-slug>`.
2. One commit: `docs(epics): add E<n> — <title>`, with the body listing the new PDs and OQs and ending with the attribution lines required in this session.
3. Push and run `gh pr create` with:
   - Title: same as the commit subject.
   - Body: the goal, a summary of the decisions (PD-n), deferred questions (OQ-n), the task list overview, and the attribution line.
4. Give the user the PR link and list any open questions left.
