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
| 7. Plugin implementation and verification | In progress: v0.2.0 installed/activated on shared test site; Composer dependencies and scenario UI render. Runtime CRUD, schema proof, run/report/history and compatibility work remain | █████░░░░░ 48% | 2026-11-06 | TBD |
| 8. Release readiness | Not started | ░░░░░░░░░░ 0% | TBD | TBD |

**Overall tracked progress: 83%** — unweighted average across nine phases. Phases 0–6 are complete for their documented research/design/no-go outcomes; Phase 7 is 48% and in progress; Phase 8 has not started. The no-go decision is not a checkout runtime pass: replay and checkout integration remain unsupported until a version-specific, isolated safety proof is completed. The user confirmed `/el-test/` is a shared test environment used for multiple in-progress plugins. Site Health reports WordPress 7.1.4 and PHP 8.3.36; the WPEssential Plugins panel lists WooCommerce 11.2.2 active and Elementor not installed. On 2026-11-07, package artifact `13549856652` (v0.2.0) was installed and activated on `/el-test/`; the Checkout Impact screen rendered without the Composer dependency warning, the React scenario form loaded, and the scenario library returned an empty list. A stale `-main` copy was deactivated and left installed. This proves the current admin bootstrap/UI smoke path only. No scenario was saved; database migration/schema version, REST create/delete, checkout, order, payment, shipping, or coupon behavior were not exercised. Current repository source is v0.2.0. It declares Composer PSR-4 mapping for `WPEssential\\CheckoutImpactRegression\\` to `includes/`, and the plugin bootstrap loads `vendor/autoload.php`; the manual SPL loader has been removed. The installable package must include Composer-generated `vendor/` files. The latest full implementation workflow run #61 passed against commit `ac47188e5131f3baafb5558643e55da99935752e`: Composer validation, optimized PSR-4 autoload generation, PHP syntax lint, JavaScript syntax, normalizer/diff checks, ZIP integrity, and installable package artifact generation all succeeded. The [workflow run](https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression/actions/runs/42313891368) produced artifact `wpessential-checkout-impact-regression-0.2.0` (artifact ID `13549856652`, expires 2027-02-04). Current v0.2.0 package was installed and activated on the shared test site on 2026-11-07; its admin smoke evidence and remaining limits are recorded in the Phase 7 task evidence. The local environment has no PHP or Composer executable. Source now includes admin-only scenario REST list/create/delete routes and a React create/list/delete screen for synthetic country, product, currency, shipping and coupon expectations; it does not execute checkout. Repository methods also provide immutable scenario versions, baseline approval/listing, redacted idempotent change events and bounded daily WP-Cron expired-run cleanup. WordPress privacy exporter/eraser hooks and opt-in uninstall erasure are present; erasure remains disabled unless explicitly enabled because no settings UI exposes that option. Exact money normalization and context-aware field diffs are covered by passing CI fixtures. Scenario list rendering is verified in the admin UI; schema migration/table version, CRUD persistence, privacy hooks, retention, and event hooks remain unverified in WordPress. No checkout, order, payment, shipping, coupon, storage migration, or safety-proof test ran. The shared test site has no recorded side-effect snapshots/sinks/reset proof, so all automatic checkout modes remain no-go. See [Phase 7 task evidence](docs/validation/PHASE-7-TASK-EVIDENCE.md), the [Phase 6 closure decision](docs/validation/PHASE-6-CLOSURE-DECISION.md), and [runtime proof runbook](docs/validation/RUNTIME-PROOF-RUNBOOK.md#0-environment-provisioning-gate). No CLI is included or planned before final release.

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
13. Review the [plugin bootstrap and PSR-4 loader](wpessential-checkout-impact-regression.php), [namespaced admin page](includes/Admin/AdminPage.php), [versioned storage schema scaffold](includes/Storage/Schema.php), [React admin shell](assets/js/admin.js), and [admin styles](assets/css/admin.css). The v0.2 bootstrap/admin UI passed a limited WordPress smoke test; database migration, CRUD persistence, and checkout runtime remain unverified.
14. Read the [Phase 7 task evidence](docs/validation/PHASE-7-TASK-EVIDENCE.md) for precise PHP lint, storage, and checkout-safety status.
15. Update the phase status, progress bar, start date, and end date in this README when project progress changes. Keep detailed decisions and evidence in the development plan.

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