# Claude Code project instructions

@AGENTS.md

Before starting work, read `README.md` and `docs/DEVELOPMENT-PLAN.md`. Treat `AGENTS.md` as the canonical project rules and the development plan as the source of truth for product scope, status, decisions, and backlog.

Identify the highest-priority unblocked task and its acceptance criteria, then execute it. If one task is blocked, mark the exact blocker and continue with independent work. A user resume message such as “continue”, “start”, “...”, or “?” means resume the existing authorized project task; do not ask them to say start again or provide a progress-only reply while work remains. Implement, run relevant checks, build and stage-test where required and safe, fix failures, update the task row/evidence/README, and push verified work to authorized main. Follow the execution loop and task-count progress formula in AGENTS.md. Phase 6 NO-GO blocks automated checkout replay only, not unrelated Phase 7 work. Do not add product CLI commands before final release.