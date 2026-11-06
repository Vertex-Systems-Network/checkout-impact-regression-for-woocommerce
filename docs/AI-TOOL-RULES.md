# AI tool instruction loading

**Checked:** 2026-11-06  
**Canonical rules:** `AGENTS.md`  
**Product/phase source of truth:** `README.md` and `docs/DEVELOPMENT-PLAN.md`

This repository has one canonical instruction set plus small tool-specific entry points. Tool-specific files point contributors back to the same product rules so that project scope does not fork across assistants.

| Tool | Repository entry point | How it is loaded | Reliability note |
|---|---|---|---|
| Cursor IDE/Agent | `AGENTS.md` and `.cursor/rules/repository-context.mdc` | Cursor documentation lists AGENTS.md as a project instruction option and supports version-controlled `.cursor/rules/*.mdc`; this project rule is marked always apply. | Confirm it appears in Cursor's active rules for the workspace. |
| Claude Code | `CLAUDE.md` | Claude Code project memory file; this file imports `AGENTS.md` using Claude's documented `@path` import. | Check `/memory` in Claude Code if verifying that files loaded. |
| Gemini CLI | `GEMINI.md` | Gemini CLI hierarchical context file loaded from the project tree. | Confirm the context is visible in the current CLI version/session. |
| ChatGPT / ChatGPT with GitHub access | `CHATGPT.md` | Manual handoff: explicitly ask ChatGPT to open/read this file, then AGENTS.md, README, and the detailed plan. Codex can read AGENTS.md when working from the repository. | A generic ChatGPT conversation is not guaranteed to discover repository files automatically. If file access is unavailable, attach/paste the files; never assume it loaded them. |

## Startup checklist for any assistant

1. State whether the repository files are actually accessible in this session.
2. Read the tool entry point where one is available, then read `AGENTS.md`, `README.md`, and `docs/DEVELOPMENT-PLAN.md`.
3. Identify a backlog task ID, phase, dependencies, and acceptance criteria before working.
4. If any file could not be read, say so and stop short of claiming its instructions were followed.
5. Preserve the no-product-CLI-before-final-release constraint and update README progress only with evidence.

## Official documentation checked

- [Cursor Rules for AI](https://docs.cursor.com/context/rules-for-ai) — documents `AGENTS.md` and project rules under `.cursor/rules`.
- [Claude Code memory](https://docs.anthropic.com/en/docs/claude-code/memory) — documents project `CLAUDE.md`, automatic loading, and `@path/to/import`.
- [Gemini CLI context files](https://google-gemini.github.io/gemini-cli/docs/cli/gemini-md.html) — documents hierarchical `GEMINI.md` context.
- [OpenAI Codex prompting guide](https://developers.openai.com/cookbook/examples/gpt-5/codex_prompting_guide) — documents repository `AGENTS.md` discovery by Codex CLI.

Tool behavior may change. Re-check its official docs when changing the entry-point scheme.
