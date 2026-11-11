# AI contributor instructions

Canonical rules for any AI assistant (Claude, ChatGPT/Codex, Cursor, Gemini) working on **Checkout Impact Regression for WooCommerce** (WPEssential) and its Pro add-on. Tool-specific files (`CLAUDE.md`, `GEMINI.md`, `CHATGPT.md`, `.cursor/rules/`) only point here.

## Read first

1. `README.md` – what the plugin is and current status.
2. `docs/DEVELOPMENT-PLAN.md` – scope, decisions (D-001…D-021), backlog and progress formula. It is the source of truth.
3. The evidence file for the area you touch (`docs/validation/PHASE-7-TASK-EVIDENCE.md`).

If a decision is missing or two documents disagree, record the gap in the plan and propose a decision. Do not invent requirements silently.

## Product boundaries

- Core value: before/after comparison of saved checkout scenarios after a plugin update or settings change. Not uptime monitoring.
- v1.0 is **manual** comparison (D-019). The plugin must never place orders, submit checkout, charge payments or email customers. Automatic observation is v1.x and stays blocked until Phase 6 is reopened with isolated evidence.
- No **product** CLI (no WP-CLI commands or CLI setup) before the final-release decision. Developer tooling is fine: Composer, `bin/build.sh`, local WordPress test sites, CI, WP-CLI/Plugin Check for testing (D-017).
- Admin UI uses WordPress-bundled React (`wp.element`), no build step. Every UI string must be a literal `__( '…', 'checkout-impact-regression-for-woocommerce' )` call (Pro: `checkout-impact-regression-pro`).
- Free plugin (WordPress.org) must stay fully useful: no locked features, trials or upsell nags. Paid features live only in the separate Pro plugin (`pro/`), connected through public hooks (D-021).
- Compatibility claims only for combinations with recorded evidence.
- Treat store data as sensitive: synthetic values, least privilege, retention, redaction.

## How to work

1. Pick the highest-priority unblocked backlog row and state its acceptance criteria.
2. Work on a branch; keep changes small and reviewable.
3. Validate at the layer the criteria require and name the layer: contract tests (`tests/php`), CI, package (`bin/build.sh`), local runtime, staging. Never report an attempt as a pass, and never infer runtime behaviour from CI.
4. Update the backlog row, evidence file and README in the same change. Compute progress with the formula in the plan (§8.1); do not hand-tune numbers.
5. Open a pull request. Do not push to `main` directly (D-020).

A message like "continue", "...", or "?" means resume the current task. It is **not** approval for external or irreversible actions: marketplace submissions, publishing releases, deleting data, changing repository settings, or anything on a live store. Ask for those explicitly.

Never run real checkout, orders or payments. Use only synthetic data on test sites the owner has authorised.

## Commands

```bash
composer install                       # autoloader
for t in tests/php/*-contract.php tests/php/normalizer-diff.php tests/php/multilingual-context-dry-run.php; do php "$t" || exit 1; done
php tests/php/uninstall-contract.php erase
./bin/build.sh                         # dist/ free + Pro ZIPs
```
