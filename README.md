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
| 7. Plugin implementation and verification | In progress: v0.2.1 staging smoke passed; scenario CRUD/version/delete and exact 7.25 read-back verified; invalid country rejected without saving; CI covers decimal, REST capability, multilingual/currency, synthetic privacy, schema install, change-event redaction/idempotency, malformed-payload and sensitive-payload no-write contracts, and preserve-by-default/explicit-erase uninstall behavior (run #155 passed); latest hardened v0.2.1 artifact is active on staging and rejects invalid country input. CI run #164 verifies bounded expired-run cleanup and authorization; #173 verifies scenario route permissions/methods; #182 verifies scheduler lifecycle; #189 verifies mocked schema uniqueness/indexes. IMPL-023 scenario version history UI/API passed CI run #199 and staging: synthetic shipping expectation 5→6 appeared as immutable Version 1/Version 2, then the temporary scenario was deleted and the library returned empty. IMPL-024 synthetic baseline approval contract passed CI run #211 after correcting a PHP namespace import caught by run #207; it covers authorization, scenario/version linkage, context fingerprints, sensitive payload rejection and immutable revision increments. Runtime database/privacy, report history, adapters and compatibility remain | ███████▌░░ 75% | 2026-11-06 | TBD |
| 8. Release readiness | Not started | ░░░░░░░░░░ 0% | TBD | TBD |

**Overall tracked progress: 86%** — rounded unweighted average across nine phases. Phases 0–6 are complete for their documented research/design/no-go outcomes; Phase 7 is 70% and in progress; Phase 8 has not started. The no-go decision is not a checkout runtime pass: replay and checkout integration remain unsupported until a version-specific, isolated safety proof is completed. The user confirmed `/el-test/` is a shared test environment used for multiple in-progress plugins. Site Health reports WordPress 7.1.4 and PHP 8.3.36; WooCommerce 11.2.2 is active and Elementor is not installed. The v0.2.0 package was updated from workflow artifact `13558177915` (run #77, commit `69e6ee6d5daaf396c4bd4e7d2844bb7afcdba700`; expires 2027-02-05). WordPress confirmed the replacement installed successfully. The Checkout Impact screen showed the Edit action after asset `filemtime` cache busting. On the current user-uploaded v0.2.0 build, a second synthetic GB/GBP scenario was created, its saved fields were loaded in Edit, shipping expectation 7→8 was saved as an immutable version, and the record was deleted; WordPress confirmed deletion and an empty library. A synthetic Germany/EUR scenario was created; the saved fields were fetched into the form, then expected shipping cost was changed from 5 to 6 and saved as a new immutable version. The UI confirmed “New scenario version saved. Checkout execution is still disabled.” The browser confirmation was subsequently accepted; WordPress displayed “Scenario deleted” and an empty scenario library, confirming the temporary scenario and its versions were removed. No checkout, order, payment, shipping, or coupon request was run; coupon data was only saved as a synthetic expectation. Current source uses Composer PSR-4 with `vendor/autoload.php`, no SPL loader, and no product CLI. Workflow #77 passed Composer validation, optimized PSR-4 generation, PHP lint, JavaScript syntax, pure comparison checks, ZIP integrity and package generation. Scenario create/read/edit/version/delete persistence is verified through synthetic staging smoke tests. Exact decimal and capability-authorization contracts pass CI in workflow run #107, artifact `13575111534` (package v0.2.1; expires 2026-12-07). The staging site was updated to v0.2.1; a synthetic GB/GBP `7.25` shipping expectation saved and read back exactly in Edit, then its record was deleted and the scenario library verified empty. The v0.2.1 fractional-value path is runtime-verified for `7.25`; database migration/version, privacy hooks, retention, event hooks, reports/history, and runtime role/accessibility matrices remain unverified. CI authorization tests cover only the REST capability callback. Automatic checkout modes remain no-go until an isolated side-effect proof passes. See the [Phase 7 task evidence](docs/validation/PHASE-7-TASK-EVIDENCE.md), [Phase 6 closure decision](docs/validation/PHASE-6-CLOSURE-DECISION.md), and [runtime proof runbook](docs/validation/RUNTIME-PROOF-RUNBOOK.md#0-environment-provisioning-gate). No CLI is included or planned before final release.

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

## Downloading a test build

A versioned installable plugin package is built by GitHub Actions. To build or download one:

1. Open the repository's [Actions](https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression/actions) page.
2. Select **Plugin CI and installable build**. New commits and pull requests build automatically; use **Run workflow** on the `main` branch to request a build manually.
3. Open a successful run and download its `installable-plugin-v<version>` artifact. The artifact contains the WordPress install ZIP and a SHA-256 checksum file.
4. Extract the downloaded artifact archive once. In WordPress Admin, go to **Plugins → Add Plugin → Upload Plugin**, select the inner `wpessential-checkout-impact-regression-<version>.zip`, then install or replace the existing version.

The workflow reads the version from the plugin header, generates Composer's optimized PSR-4 loader, runs validation/lint/comparison checks, verifies the ZIP contents and uploads the package artifact for 30 days. Run #107 produced v0.2.1 (artifact ID `13575111534`), including exact decimal expectation handling and scenario REST authorization contract tests. This is a downloadable test build, not a marketplace release. No product CLI is involved.

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