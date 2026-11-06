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