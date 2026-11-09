# AI contributor instructions

This repository contains the planning and future implementation work for the WPEssential Checkout Impact Regression add-on. Treat this file as the first instruction when an AI coding assistant opens the repository.

## Required reading and source of truth

1. Read `README.md` for the product purpose, current project status, and phase tracker.
2. Read `docs/DEVELOPMENT-PLAN.md` before proposing features, architecture, or code.
3. Use `docs/AI-TOOL-RULES.md` to confirm how your current tool loads repository instructions; tool-specific entry points must remain consistent with this file.
4. Follow decisions recorded in the plan. If a decision is missing or contradictory, document the gap and propose a decision; do not silently invent a requirement.
5. Keep the README phase status, progress bar, and dates synchronized with meaningful completed work. Record rationale, evidence, and open questions in the detailed plan.

## Product boundaries

- The product's primary value is before/after regression comparison across saved checkout scenarios after plugin or checkout/payment-setting changes.
- Do not reduce the product to generic checkout uptime or success/failure monitoring.
- Do not add a product CLI, command-line interface, or CLI commands before the final release. CLI is explicitly out of scope during planning and implementation.
- Use React for the planned WordPress admin UI, with a polished, accessible, human-centered interface.
- Do not claim PHP, WordPress, WooCommerce, WPML, Polylang, payment gateway, shipping provider, or checkout-flow compatibility until it is explicitly scoped and verified.
- Treat customer and order data as sensitive. Prefer synthetic or minimized scenario data, least privilege, explicit retention rules, and safe redaction.

## Working method

- Work phase by phase. Do not skip market research, requirements, or architecture decisions and jump into a full implementation.
- Separate verified facts, assumptions, and proposals. Cite sources for competitor and platform behavior in project documents.
- Before adding an option, compare it with the options bank and existing system inventory. Avoid duplicate controls and overlapping concepts.
- For each accepted feature, state its user problem, trigger/input, expected behavior, edge cases, dependencies, permissions/data handling, and acceptance criteria.
- AI-generated plans and code are drafts until checked against project requirements and the relevant official documentation.
- Prefer small, reviewable changes. Explain what changed and update the relevant plan status.
- Do not introduce a CLI workaround for development or delivery. Use the repository's documented non-CLI workflow until the final release decision.

## AI implementation handoff

When continuing work, begin by summarizing the current phase, completed evidence, open questions, and the next bounded task. Keep a task backlog suitable for ChatGPT, Cursor, Claude, Gemini, and other coding assistants in `docs/DEVELOPMENT-PLAN.md`. Each backlog item must have an ID, phase, priority, dependencies, acceptance criteria, and status.
## Autonomous delivery contract (mandatory)

This project is being advanced through working code and evidence, not repeated progress replies. Treat a user message such as continue, start, ..., ?, or an equivalent resume prompt as authorization to resume the existing project task under the permissions already given in the conversation. Do not ask the user to say start again. Ask only when a concrete decision or missing external prerequisite genuinely blocks safe implementation.

For every work session:

1. Read the current README, detailed plan, task status, relevant source, latest CI/artifact status, and recent runtime evidence before selecting work. Do not redo a task already proven complete.
2. Select the highest-priority unblocked task with explicit acceptance criteria. A task blocked by Phase 6 NO-GO must be marked blocked with its precise missing evidence, then skipped while other independent work continues.
3. Implement a reviewable change. Do not end a session after status, analysis, an implementation outline, or a single low-value subtask while a safe, concrete next action remains.
4. Validate the actual changed behavior. Run the repository's documented static/contract checks; build an installable ZIP when code changes; install and exercise it on the authorized test site when the acceptance criteria require runtime proof and doing so is safe. Record exactly which layer passed (unit/contract, CI, package, or staging).
5. Fix failures before moving on. Never describe an attempted action as a pass, infer staging behavior from CI, or promote a partial task to complete.
6. Update the task row, detailed evidence, and README in the same delivery slice. README progress is derived from the task table, not intuition: count all Phase 7 backlog rows including BUILD-001, report Complete/Partial/Not started/Blocked counts, and calculate the phase percent as Complete rows divided by total rows, rounded to the nearest integer. Partial work does not count as a completed task. Overall progress is the unweighted mean of the nine phase percentages.
7. Commit/push completed documentation and code to the authorized main branch, preserving a clean, recoverable checkpoint after each finished task or coherent slice. Do not push broken or unverified code.
8. After one acceptance-complete task, immediately select and execute the next highest-priority unblocked task while time and tools permit. Do not send an interim question or wait for a fresh prompt.
9. If a genuine external blocker prevents further work, first finish all independent unblocked work. Then report the exact blocked task, evidence needed, attempted safe alternatives, and the specific user action required. Do not use vague Phase 7 is pending wording.

### Evidence and phase status rules

- CI-only contracts can complete a CI-scoped task; they cannot complete staging, migration, compatibility, accessibility, or marketplace acceptance criteria.
- Runtime actions must use the user-authorized non-production site and synthetic data. Never exercise payment capture, place orders, real customer data, or live checkout execution while Phase 6 is NO-GO.
- Phase 6 NO-GO blocks replay and automated checkout observation only. It is not a reason to stop unrelated admin, storage-contract, privacy, report, accessibility, packaging, documentation, or release-readiness work.
- Update README on every completed delivery slice. Do not advance phase percentages unless the formula above supports the change. Keep historical evidence as historical; add a dated correction rather than silently rewriting old test results.
- Complete always means every acceptance criterion for that task has evidence. A phase is complete only when all required tasks are Complete and its exit criteria pass; blocked or partial tasks keep the phase open.
- End each assistant turn with the code/docs/tests/artifact already updated where possible. A concise status table is welcome only after the work has landed; it is not a substitute for implementation.