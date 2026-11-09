# ChatGPT repository handoff guide

This file is a manual entry point for ChatGPT sessions connected to or reviewing this GitHub repository. Do not assume a general ChatGPT chat automatically discovers or loads it.

## Required startup

Before changing code or reporting a project status:

1. Read this file, `AGENTS.md`, `README.md`, `docs/DEVELOPMENT-PLAN.md`, and the current Phase 7 evidence relevant to the next task.
2. Confirm in your working context that repository files were actually read. Do not claim instructions were followed if access failed.
3. Inspect the latest commit, task table, source, CI run/artifact, and latest staging evidence. Do not repeat completed work or trust stale README claims without checking newer evidence.
4. Select the highest-priority unblocked backlog task and its acceptance criteria. Treat Phase 6 NO-GO as a blocker only for checkout replay/automated observation; continue independent implementation and verification work.

## Execution behavior

- The user has already directed ongoing autonomous implementation and authorized the named test environment. Messages such as “continue”, “start”, “...”, “?”, or an equivalent resume prompt mean resume the active repo task. Do not ask the user to say start again or request permission for routine implementation, local checks, CI, documentation, or authorized synthetic-data staging checks.
- Do not substitute progress narration, summaries, roadmaps, or repeated task tables for actual changes. Start by performing the selected task. Continue to the next unblocked acceptance-complete task while the current run allows.
- For each task, implement, run applicable lint/contract checks, build a package when code changes, and exercise the changed flow on staging if required and safe. Fix failures before moving on.
- Update the specific backlog row, Phase 7 evidence, and README in the same delivery slice. Commit/push to authorized main only after checks pass.
- Never claim a task or phase is complete from attempted actions or CI alone when its acceptance criteria require runtime proof. Use the progress formula and status counts in AGENTS.md; partial tasks do not count as complete. The README progress tracker must agree with the detailed backlog.
- Never run real checkout, create real orders, capture payments, or use customer data while the Phase 6 safety gate is NO-GO. This gate does not stop work on unrelated unblocked tasks.
- If one task is genuinely blocked, record the exact missing prerequisite and immediately continue with other unblocked work. Ask the user only for a specific external input that cannot be inferred or safely supplied by the existing authorization.
- Finish a coherent deliverable before replying. If the environment ends the run, leave a committed checkpoint with exact next task and evidence so continuation does not restart from planning.

Keep the core product focused on before/after comparisons across saved checkout scenarios. Verify platform or competitor claims with official documentation. Do not add a product CLI before final release. Update the README progress table only with evidence-backed task completion and the defined denominator.
