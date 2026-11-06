# WPEssential Checkout Impact Regression

A planned WPEssential add-on that detects how plugin updates and checkout or payment configuration changes affect real customer checkout scenarios.

It is designed to answer **what changed, for whom, and where** by comparing saved cart and customer scenarios before and after a change. The primary output is a clear impact report, for example:

- **Germany:** expected shipping method is missing.
- **United Kingdom:** shipping cost changed from $7 to $12.
- **United States:** checkout behavior is unchanged.
- **WELCOME10:** applied discount differs from the saved expectation.

This is a change-impact regression tool. Checkout pass/fail monitoring is useful context, but the product's core job is comparing expected checkout behavior across saved scenarios.

## Project status

This repository is in the **early implementation** stage. Checkout replay and observation remain unsupported until exact runtime proofs reopen that scope. The plan below is the source of truth for phases and progress. Dates are recorded as YYYY-MM-DD; **TBD** means the schedule has not yet been estimated.

| Phase | Status | Progress | Start date | End date |
|---|---|---:|---|---|
| 0. Project brief and AI handoff foundation | Complete | ██████████ 100% | 2026-11-06 | 2026-11-06 |
| 1. Market and competitor research | Complete | ██████████ 100% | 2026-11-06 | 2026-11-06 |
| 2. Requirements and compatibility matrix | Complete (planning) | ██████████ 100% | 2026-11-06 | 2026-11-06 |
| 3. Options bank and system architecture | Complete (planning) | ██████████ 100% | 2026-11-06 | 2026-11-06 |
| 4. Checkout scenario and comparison design | Complete (design) | ██████████ 100% | 2026-11-06 | 2026-11-06 |
| 5. UX and React-based admin interface | Complete (UX/prototype) | ██████████ 100% | 2026-11-06 | 2026-11-06 |
| 6. Pre-development technical proof | Complete (no-go decision; runtime modes unsupported) | ██████████ 100% | 2026-11-06 | 2026-11-06 |
| 7. Plugin implementation and verification | Blocked at runtime gate (read-only shell only) | ░░░░░░░░░░ 5% | 2026-11-06 | TBD |
| 8. Release readiness | Not started | ░░░░░░░░░░ 0% | TBD | TBD |

**Overall tracked progress: 78%** — unweighted average across nine phases: Phases 0–6 are closed for their documented outcomes, Phase 7 is 5% complete and blocked pending runtime prerequisites, and Phase 8 has not started. Phase 6 closed with an explicit no-go/support-exclusion decision, not a runtime pass: no automatic checkout replay mode or integration is approved; untested combinations remain unsupported until version-specific evidence reopens that scope. Phase 7 remains blocked after the source-only read-only plugin/admin shell. A temporary WPRun environment booted WordPress 6.9.1 on PHP 8.4, but its plugin installer marks WooCommerce 11.2.2 incompatible, and repeated browser-control attempts could not open or operate the native file chooser to transfer the locally packaged plugin ZIP. A follow-up local setup check found the ZIP in the workspace but no PHP, WordPress, WP-CLI, Docker, database, or cached install packages; apt package-index access failed in this managed environment. The ZIP archive integrity check passed (four expected plugin files, no unsafe archive paths), and the admin JavaScript passed `node --check`. Source review confirms the current shell declares automatic checkout requests disabled and checks admin capability in its render callback. These are static checks only. PHP lint could not run because PHP is absent. The plugin was not installed or activated, and no admin UI, checkout scenario, or safety test ran. This confirms only that the disposable WordPress admin loads; it is not a plugin runtime pass or compatibility claim. The first visit to the user-provided WordPress test subsite presented an invisible reCAPTCHA before the login form. On a later visit, the user had logged in and the admin became accessible: WordPress 7.1.4 and PHP 8.3.36 are reported by Site Health. The user confirmed that `/el-test` is a shared testing environment where multiple in-progress plugins are tested; Site Health's `production` label is therefore not treated as proof that this is a live production store. Because other plugin tests share the site, testing must avoid changing unrelated plugins or shared store data. The WPEssential plugin panel currently lists WooCommerce 11.2.2 as active and Elementor as not installed; this differs from the earlier Installed Plugins screen observation, so plugin inventory should be checked in its owning admin surface before any test. The plugin ZIP upload form is visible, but the browser automation currently cannot select the local ZIP file. The plugin has not been uploaded or activated. The separate WordPress Playground attempt remains blocked before WordPress loads. No scenario data is stored, and no checkout request is made. The plugin's stored-scenario comparison goal remains the target, but cannot be represented as available functionality. Storage planning is accepted: per-site versioned tables, non-autoloaded configuration, a proposed 30-day run retention setting, and preserve-on-uninstall by default; runtime migration and privacy proofs are still required. The entry-point description now identifies this as a development scaffold and explicitly says automatic checkout requests are disabled. The admin menu and render callback now share one capability rule in source, and the React mount point has an escaped server-rendered loading fallback; runtime behavior remains unverified. See the [Phase 6 closure decision](docs/validation/PHASE-6-CLOSURE-DECISION.md) and [reopen checklist](docs/validation/RUNTIME-PROOF-RUNBOOK.md#0-environment-provisioning-gate).

**Documentation check:** a previous repository-wide link audit was recorded on 2026-11-06. The exact counts are not re-certified in this update; navigation checks are not runtime evidence.

## Start here

1. Read [AGENTS.md](AGENTS.md) before making project changes. It explains how AI coding tools should work in this repository.
2. Read the [Development Plan](docs/DEVELOPMENT-PLAN.md) for scope, phases, requirements, research areas, and acceptance criteria.
3. Use [AI tool instruction loading](docs/AI-TOOL-RULES.md) to confirm the correct entry point for Cursor, Claude, Gemini, or ChatGPT.
4. Check the [competitor research report](docs/research/COMPETITOR-RESEARCH.md) for dated findings and source links.
5. Use the [deduplicated options bank](docs/research/OPTIONS-BANK.md) and [system inventory](docs/architecture/SYSTEM-INVENTORY.md) before adding scope or proposing architecture.
6. Read the [proposed architecture and data flow](docs/architecture/ARCHITECTURE.md), [storage/retention decision](docs/architecture/STORAGE-AND-RETENTION-DECISION.md), and [shipping/payment integration matrix](docs/architecture/INTEGRATION-MATRIX.md).
7. Read the [scenario and comparison specification](docs/design/SCENARIO-AND-COMPARISON-SPEC.md) before changing scenario, baseline or diff semantics.
8. Review the [admin UX/React specification](docs/design/ADMIN-UX-SPEC.md) and [interactive interface prototype](docs/design/admin-interface-prototype.html) for the planned screens and states.
9. Read the [requirements and compatibility plan](docs/requirements/REQUIREMENTS.md); planned targets are not tested compatibility claims.
10. For Phase 6 checkout safety, adapter boundaries, canonical field mapping, change-event handling and runtime proof cases, read the [safe checkout proof protocol](docs/validation/PREDEVELOPMENT-SAFE-CHECKOUT-PROOF.md), [Store API observation adapter spike](docs/validation/STORE-API-OBSERVATION-ADAPTER-SPIKE.md), [Store API canonicalization spike](docs/validation/STORE-API-CANONICALIZATION-SPIKE.md), [change-source hooks spike](docs/validation/CHANGE-SOURCE-HOOKS-SPIKE.md), [background run scheduling spike](docs/validation/BACKGROUND-RUN-SCHEDULING-SPIKE.md) and [runtime proof runbook](docs/validation/RUNTIME-PROOF-RUNBOOK.md), including the exact isolated-environment provisioning gate and V-01–V-07 before/after operator matrix.
11. Read the [multilingual checkout compatibility spike](docs/validation/MULTILINGUAL-CHECKOUT-COMPATIBILITY-SPIKE.md) for WPML/WCML and Polylang scenario fingerprints and proof cases; these integrations are not runtime-supported.
12. Read the [Phase 6 closure decision](docs/validation/PHASE-6-CLOSURE-DECISION.md) before treating any automatic replay mode as implementation-ready.
13. Review the [plugin bootstrap](wpessential-checkout-impact-regression.php), [permission-checked admin page](includes/class-wpecir-admin-page.php), [React admin shell](assets/js/admin.js), and [admin styles](assets/css/admin.css). This first slice is source-only and has not been runtime-tested.
14. Update the phase status, progress bar, start date, and end date in this README when project progress changes. Keep detailed decisions and evidence in the development plan.

## Product principles

- Compare saved, representative customer/cart scenarios before and after a plugin or settings change.
- Report material differences by country/region, shipping method and cost, payment option, taxes, coupons/discounts, and checkout completion behavior where supported.
- Distinguish a confirmed regression from an environment-dependent or inconclusive result.
- Make comparison results understandable to store owners, with evidence and a clear expected-versus-observed view.
- Keep scenario data, customer privacy, store performance, accessibility, and recovery behavior in the architecture from the start.
- Use a polished, human-centered React interface that fits naturally into WordPress administration.
- Finalize and document PHP, WordPress, WooCommerce, WPML, and Polylang compatibility before implementation scope is locked.
- **Do not add a CLI to the product before the final release.** No CLI product interface or CLI commands are in scope during planning and implementation.
- Treat Store API Cart Tokens as session identifiers, not side-effect isolation; use the [adapter spike](docs/validation/STORE-API-OBSERVATION-ADAPTER-SPIKE.md) for the observation boundary.
- The sequenced Phase 7 implementation backlog is recorded in the [development plan](docs/DEVELOPMENT-PLAN.md); tasks that depend on safe replay stay gated by Phase 6 runtime evidence.
- Do not claim compatibility or automated detection capability until it has been validated.

## Planned research references

Competitor research begins in Phase 1. Initial references supplied for review:

- [Cart Lookout](https://wordpress.org/plugins/cartlookout/)
- [ShopMonitor](https://shopmonitor.io/)

Research must verify the current feature set and limitations from primary product materials, record findings and sources, and use them to build a deduplicated options bank. No feature should be copied into scope without a user need, rationale, and acceptance criteria.

## Planned distribution

The release target is a **free edition for the WordPress.org Plugin Directory** and a **paid edition for the WooCommerce.com Marketplace**. These are planning targets only; neither marketplace application nor product submission has been made. The free edition must provide genuine standalone value and comply with WordPress.org's licensing and no-trialware rules. Paid packaging, update ownership, pricing and cross-marketplace presentation still require review.

See the [distribution and marketplace submission plan](docs/release/DISTRIBUTION-PLAN.md) for current requirements, release gates, edition decisions and official references. The proposed $119/year price is not approved.

## License

No license has been selected yet. A GPL-compatible release license is a submission prerequisite and remains a decision.