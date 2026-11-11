# Development plan

**Product:** Checkout Impact Regression for WooCommerce (by WPEssential), plus the paid Checkout Impact Regression Pro add-on  
**Repository:** `Vertex-Systems-Network/checkout-impact-regression-for-woocommerce`  
**WordPress.org slug:** `checkout-impact-regression-for-woocommerce` · **Plugin URI:** https://wpessential.org/plugins/checkout-impact-regression-for-woocommerce  
**Stage:** v1.0.0 release candidate (Phase 7 v1 scope done; Phase 8 submission prepared, waiting on owner actions)  
**Plan last updated:** 2026-11-12

This document is the detailed product and engineering plan. Keep it aligned with the phase tracker in the repository README. Any implementation proposal must fit the product goals, compatibility decisions, privacy constraints, and the no-CLI boundary below.

## 1. Product brief

### Problem

A plugin update or shipping, payment, tax, or coupon setting change can alter checkout behavior for only some customer journeys. A store owner may not notice that a shipping option disappeared for German customers or that a coupon now applies a different discount until customers report the problem.

Most checkout monitors emphasize whether a checkout succeeded or failed. This add-on is intended to find **what changed between a known baseline and the checkout behavior after a change**, across multiple saved customer/cart scenarios.

### Product outcome

Let a store owner save representative scenarios and a known-good baseline. After a relevant plugin update or configuration change, the product compares observed behavior with that baseline and reports:

- what behavior changed;
- which scenario, customer region, or cart is affected;
- expected versus observed values;
- whether the change appears broken, materially different, unchanged, or inconclusive;
- evidence, timestamp, environment, and a suggested next investigation step.

Illustrative findings:

- Germany: expected shipping option is missing.
- United Kingdom: shipping cost changed from $7 to $12.
- United States: checkout behavior is unchanged.
- Coupon `WELCOME10`: observed discount differs from the saved expectation.

The examples describe intended reporting, not validated capabilities or supported integrations.

### Positioning

**A checkout change-impact regression tool for WooCommerce stores.** It complements uptime and success/failure monitoring by prioritizing before/after differences across saved checkout scenarios.

### Intended users

- WooCommerce store owners and operators;
- ecommerce QA and support teams;
- agencies maintaining client stores;
- developers diagnosing regressions introduced by plugin or settings changes.

## 2. Product principles and boundaries

1. **Regression comparison first.** Health checks can provide context, but the core workflow is baseline → change → replay/observe → compare → explain.
2. **Scenario-specific results.** A global green/red status is insufficient when only one country, shipping zone, gateway, product type, or coupon is affected.
3. **Explainable differences.** Show expected and observed results, the scenario and environment, and enough evidence to reproduce the finding.
4. **No unsafe real orders.** Design test execution to avoid charging a real payment method, creating unintended orders, sending customer notifications, or changing inventory. Determine safe mechanisms in architecture research.
5. **Privacy and least privilege.** Minimize personal data, use synthetic scenario identities where feasible, protect stored credentials/secrets, define retention, and redact reports.
6. **Polished WordPress experience.** Use React for the admin interface with accessible patterns and a visual system that feels coherent with WordPress and WooCommerce.
7. **Compatibility is explicit.** Publish only tested versions and integrations. Handle unsupported or inconclusive cases honestly.
8. **No product CLI before final release.** Do not add a customer-facing CLI, WP-CLI commands or CLI-based setup to the *product*. Developer tooling (Composer, build scripts, local WordPress test sites, CI, WP-CLI or Plugin Check used only for testing) is allowed and encouraged (decision D-017).
9. **AI assists; documented evidence governs.** AI tools must follow this plan, preserve provenance for research, and avoid silently converting assumptions into requirements.

## 3. Core workflow to validate

1. **Choose monitored change sources.** Research which plugin updates and checkout/payment/shipping configuration changes can be detected reliably and safely.
2. **Create a scenario.** Define cart contents, customer region/address attributes, currency, user state, shipping/payment expectations, coupon, tax context, and other required variables. Avoid storing real customer identities by default.
3. **Capture baseline.** Run or record a known-good result in a clearly identified environment. Store version/configuration context and normalized observations.
4. **Detect a relevant change.** Link a test run to a plugin update or supported settings change. Decide how manual changes, bulk updates, rollbacks, and unrelated changes are represented.
5. **Run comparison.** Execute the scenario through a safe, repeatable method. Capture shipping methods/costs, payment availability, totals/taxes, coupon behavior, and checkout completion signals where technically supported.
6. **Classify.** Compare normalized expected and observed results. Distinguish regression, expected change, unchanged, flaky, unsupported, and inconclusive states.
7. **Review impact.** Present affected scenarios first, with a detail view containing before/after values and evidence.
8. **Resolve and learn.** Let an authorized user acknowledge a finding, mark it expected, update a baseline deliberately, or record a resolution. Preserve an audit trail and prior baselines.

This workflow is a discovery target. Phase 2 and Phase 3 must decide which parts can be implemented with WooCommerce and supported integrations without unsafe side effects.

## 4. Scope candidates

### Candidate comparison dimensions

The options bank should evaluate, deduplicate, and prioritize these dimensions:

- scenario identity and grouping;
- country/region and shipping-zone resolution;
- shipping method presence, label, cost, and package behavior;
- payment method availability and relevant checkout state;
- subtotal, discounts, coupon application, fees, taxes, and final total;
- product/cart variations, virtual/downloadable items, shipping classes, and mixed carts;
- guest versus authenticated checkout, where safe and in scope;
- currency and localization;
- checkout field/validation behavior;
- checkout completion signal without a real charge or unintended order;
- plugin/theme/WooCommerce/PHP version and relevant settings context;
- baseline history, comparison diff, severity, confidence, and evidence;
- notifications, suppression, expected-change acknowledgement, and baseline approval;
- multisite, HPOS, block checkout, classic checkout, and third-party checkout compatibility;
- retention, export, permissions, redaction, and audit history;
- performance controls, scheduling, retries, and failure diagnostics.

These are research prompts, not a commitment to ship every item.

### Candidate system inventory

Research and design non-duplicative systems for:

- scenario builder, validation, tagging, and reuse;
- baseline capture, versioning, approval, and rollback;
- change-source detection and event correlation;
- safe test execution / checkout simulation strategy;
- environment and plugin/configuration fingerprinting;
- result normalization and diff engine;
- impact classification, confidence, and severity;
- evidence storage and redaction;
- dashboard, filters, scenario detail, and history;
- notifications and expected-change suppression;
- scheduling, queues, retries, rate limits, and cleanup;
- capability detection and compatibility diagnostics;
- permissions, privacy, retention, and audit trail;
- support diagnostics and export.

Every system must have a clear responsibility and data contract. Avoid multiple systems owning the same state or independently implementing the same comparison logic.

## 5. Market and competitor research

Phase 1 will review current product behavior, setup flows, supported platforms, pricing, technical requirements, and limitations. Initial references:

- [Cart Lookout on WordPress.org](https://wordpress.org/plugins/cartlookout/)
- [ShopMonitor](https://shopmonitor.io/)

For each source, record the checked date, URL, evidence, verified features, unresolved questions, and relevance. Prefer official plugin listings, vendor documentation, changelogs, and product pages. Do not infer functionality from a name or marketing headline.

### Required research outputs

- competitor capability matrix;
- common checkout monitoring patterns;
- gaps related specifically to before/after scenario comparison;
- feature/options bank with duplicates merged and rationale attached;
- system inventory with responsibility boundaries and data-flow notes;
- differentiators and risks that remain unverified.

## 6. Technical requirements to finalize before implementation

### Platform compatibility matrix

Do not guess compatibility versions. Phase 2 records a source-based proposed support floor and test strategy in [Requirements and compatibility plan](requirements/REQUIREMENTS.md). Planned targets are not tested compatibility claims. Before release, validate exact versions and update the tested matrix for:

| Platform | Minimum supported version | Tested versions | Decision/status |
|---|---|---|---|
| PHP | 8.3+ proposed | Not tested | WooCommerce 10.8 server recommendation |
| WordPress | 6.9+ proposed | Not tested | WooCommerce 10.8 minimum/recommendation |
| WooCommerce | 10.8+ planning floor | Not tested | Exact release intersection required before claims |
| WPML | Optional; WPML core + WCML required for Woo multilingual/multicurrency | Not tested | See requirements source register |
| Polylang | Optional; Polylang/Pro + Polylang for WooCommerce required | Not tested | See requirements source register |
| MySQL/MariaDB | MySQL 8.0+ / MariaDB 10.11+ proposed | Not tested | Stricter WordPress recommended DB floor |
| Checkout experience | Classic and Blocks are separate targets | Not tested | No support declaration before mode-specific validation |
| HPOS | Planned validation target | Not tested | Separate HPOS/legacy matrix required |

Full source links, dependency behavior, unsupported-case handling and the verification strategy are in [Requirements and compatibility plan](requirements/REQUIREMENTS.md).

### Integrations

Research WooCommerce shipping and payment extension APIs and the most-used shipping companies that provide official APIs. Build an integration matrix containing provider, official API/docs, supported regions, credentials/scopes, test/sandbox availability, rate limits, data flow, operational dependencies, and priority.

Do not promise “automatic” carrier integration until each provider flow is validated. Prefer WooCommerce's registered methods and provider extensions where possible; direct carrier APIs may be a later adapter layer. Apply the same evidence-led process to payment gateways. Never make a real charge as part of regression testing.

### Architecture areas

- WordPress plugin lifecycle and update/change event capture;
- WooCommerce hooks and checkout extension points;
- classic checkout and Checkout Blocks behavior;
- scenario and normalized-result schemas;
- secure storage and redaction;
- background execution, scheduling, retries, and resource limits;
- test isolation and safe checkout execution;
- extensibility/adapters for shipping and payment integrations;
- localization and multilingual behavior;
- permissions, nonce/capability checks, validation, escaping, and auditability;
- React admin UI packaging and WordPress integration;
- migrations, rollback, uninstall, and retention behavior.

## 7. AI-native workflow and engineering standards

AI-native means the project plan, acceptance criteria, architecture decisions, and backlog are structured so multiple assistants can contribute consistently. It does not mean accepting generated output without review.

All assistants (ChatGPT, Cursor, Claude, Gemini, and others) must:

- read `AGENTS.md`, this plan, and relevant decision records first;
- state the current phase and task ID before implementation;
- use the options bank and system inventory to avoid duplicate features;
- cite official documentation for platform behavior and version claims;
- mark facts, assumptions, proposals, and unresolved questions separately;
- follow the approved data flow and privacy/security boundaries;
- make bounded changes that satisfy stated acceptance criteria;
- update docs and phase progress after approved work;
- avoid adding CLI surfaces before final release;
- report what changed, evidence, unresolved risks, and exact next steps.

### Engineering quality goals

- maintainable, modular PHP aligned with WordPress/WooCommerce conventions;
- typed and validated boundaries where supported by the chosen compatibility matrix;
- clear separation between capture, normalization, comparison, presentation, and integrations;
- accessible React components and responsive admin layouts;
- localization-ready UI and data presentation;
- measurable resource limits and protection against runaway scenario execution;
- focused static, unit, integration, compatibility, and end-to-end verification, defined during architecture;
- performance optimization guided by measurements, with no speculative complexity.

An optimizer task must identify the measured bottleneck, baseline, change, and post-change result. Do not trade correctness, compatibility, security, or readable maintenance for a speed claim.

## 8. Phase plan and exit criteria

Dates remain TBD until the team estimates the work. Record actual start/end dates in README as work begins and finishes.

### Phase 0 — Project brief and AI handoff foundation

**Status:** Complete, 2026-11-06  
**Deliverables:** README brief and phase tracker; this plan; AI contributor rules.

**Exit criteria:** project purpose, boundaries, AI entry point, and planning status are discoverable from the repository home page.

### Phase 1 — Market and competitor research

**Status:** Complete (100%); started and completed 2026-11-06.

**Completed evidence:** A dated public-materials desk review of 10 direct/adjacent products, including the named Cart Lookout and ShopMonitor, is recorded in [Competitor research](research/COMPETITOR-RESEARCH.md). A deduplicated, ID-based [options bank](research/OPTIONS-BANK.md) and proposed [system inventory](architecture/SYSTEM-INVENTORY.md) are recorded. Pricing pages were reviewed where official public prices were available; the $119/year proposal remains unvalidated and is not treated as a decision. Completed backlog: PLAN-001 through PLAN-005.

**Finding:** Generic checkout tests, update/change awareness, local configuration snapshots, manual country-specific shipping previews, and run evidence are already documented across products. The narrower product hypothesis is a persistent approved baseline plus field-level before/after observed checkout outcome diffs across a saved scenario suite, linked to change context. No public material reviewed establishes this as an exclusive market gap; validate it through demos and user interviews before locking positioning.

**Limits:** Desk review only; no private account/demo, plugin installation, source audit, vendor interview, or independent reliability test. This phase is complete for the stated desk-research deliverables, not an exhaustive proof that all competitors/features have been found.

**Exit criteria met:** Relevant products were reviewed from official/vendor materials; claims in the report are sourced; overlaps, uncertainty, pricing caveats, option IDs, ownership, dependencies, risks, and system boundaries are recorded.

### Phase 2 — Requirements and compatibility matrix

**Status:** Complete for planning deliverables; 2026-11-06.

**Completed evidence:** [Requirements and compatibility plan](requirements/REQUIREMENTS.md) defines seven user journeys, prioritized functional and non-functional requirements with IDs, rationale/dependencies/acceptance criteria, privacy and safe-run gates, explicit exclusions, a source-linked proposed PHP/WordPress/WooCommerce/WPML/Polylang/database/checkout/HPOS matrix, dependency behavior, and a release verification strategy. Backlog REQ-001 through REQ-003 is complete for planning.

**Compatibility boundary:** Proposed minimums are planning targets only. The plugin has not been implemented or tested. Classic checkout, Checkout Blocks, HPOS, multilingual integrations and exact version combinations must each pass the specified tests before any compatibility claim. No automatic checkout replay is approved; see the Phase 6 no-go decision. Reopen the phase before claiming any exact compatibility entry.

**Exit criteria met:** each requirement has an ID, rationale, priority, dependencies, and acceptance criteria; version claims cite official sources and are explicitly marked proposed/untested; the test strategy and unsupported-case behavior are defined.

### Phase 3 — Options bank and system architecture

**Status:** Complete for planning deliverables; 2026-11-06.

**Completed evidence:** The [options bank](research/OPTIONS-BANK.md) now uses a single SYS owner ID for every candidate; OI-16 is explicitly deferred with no owner. The [system inventory](architecture/SYSTEM-INVENTORY.md) remains the component responsibility map. [Proposed architecture](architecture/ARCHITECTURE.md) adds the option-to-owner crosswalk, two data-flow diagrams, conceptual domain records, boundaries, failure behavior, proposed decision records and a validation plan. The [shipping/payment integration matrix](architecture/INTEGRATION-MATRIX.md) records official API capabilities, authentication/onboarding, test environments, constraints and priority for WooCommerce's native interfaces and candidate carriers/gateways. ARCH-001 through ARCH-003 are complete for planning.

**Limits:** This is a source-linked desk study and architecture proposal. No carrier/gateway integration or checkout replay has been implemented or tested. Rate limits, regional eligibility and sandbox fidelity remain provider-specific where the official sources do not settle them. Runtime execution remains unsupported until the Phase 6 gate is reopened with isolated evidence.

**Exit criteria met:** accepted/candidate options have one accountable owner; data inputs, conceptual state, permissions, retention, events and failures are mapped; integration facts cite official sources; critical unknowns have explicit validation tasks.

### Phase 4 — Checkout scenario and comparison design

**Status:** Complete for design deliverables; 2026-11-06.

**Completed evidence:** [Scenario and comparison specification](design/SCENARIO-AND-COMPARISON-SPEC.md) defines the conceptual scenario fields and versions, immutable baseline rules/context binding, typed observations, exact money representation, comparability constraints, run/field/finding states, deterministic V-01–V-07 vectors, a sample impact report, and the Phase 6 safe-run proof criteria. The schema covers shipping, payment availability, coupon/discount, tax/fees/totals, controls and unsupported/inconclusive cases.

**Phase boundary:** Phase 4 finalizes comparison semantics and expected vectors; it does not claim executable or runtime-tested behavior. Checkout GET semantics are version-sensitive: the published API reference describes persisted draft data, while WooCommerce 10.9 release notes/current source defer persisted draft creation for fresh sessions and retain a retry path. Checkout GET is treated as stateful until exact-version testing; POST processes checkout/order/payment. Cart API mutations can alter session state. No automatic replay is approved by the Phase 6 closure decision; reopen SPIKE-001 before implementing an exact runtime mode. SCN-001 through SCN-003 are complete as design work.

**Exit criteria met:** representative expected outcomes and comparator behavior are specified deterministically; context mismatch and missing/unsupported data do not become false green results; report examples and safety boundaries are documented. Executable replay proof remains a later technical gate, not a Phase 4 completion claim.

### Phase 5 — UX and React-based admin interface

**Status:** Complete for UX/prototype deliverables; 2026-11-06.

**Completed evidence:** [Admin UX and React interface specification](design/ADMIN-UX-SPEC.md) defines information architecture, first-use/scenario/baseline/report/history/settings journeys, screen hierarchy, WordPress React component map, server/client responsibility boundary, visual language, responsive behavior and loading/empty/error/partial/unsupported/blocked states. The [interactive admin concept prototype](design/admin-interface-prototype.html) provides Overview, Scenario list, a four-step scenario builder, impact detail, Runs & history, and Settings screens with fictional data and prototype-only actions.

**React boundary:** The HTML prototype is a visual/interaction review artifact, not product code. The production interface remains React within wp-admin using WordPress runtime/components; PHP remains authoritative for permissions and comparison behavior. Accessibility criteria are designed but have not been tested with assistive technology. Runtime React implementation and verification remain Phase 7.

**Exit criteria met for design:** users can understand an impact difference through expected/current values and evidence without reading raw logs; keyboard, responsive, loading/empty/error/unsupported states and component ownership are specified. No screen implies safe checkout replay or live payment.

### Phase 6 — Pre-development technical proof

**Status:** Closed 2026-11-06 as **NO-GO** for automatic checkout replay. This is a support-exclusion decision, not a runtime pass. In progress accounting it counts as *closed*, not as delivered product value.

**Evidence:** Source-backed spikes, safety protocol, runbook and V-01–V-07 proof matrix; see [Phase 6 closure decision](validation/PHASE-6-CLOSURE-DECISION.md). No isolated runtime proof was run, so Classic checkout, Checkout Blocks, cart replay, gateway/shipping integrations, WPML/WCML and Polylang automatic modes stay unsupported.

**How to reopen (v1.x):** ENV-001 is now unblocked on the tooling side: a local WordPress 7.1.4 + WooCommerce 11.2.2 site (SQLite) was built in minutes from GitHub sources on 2026-11-12 (see evidence). Reopening needs an isolated store with a test-only gateway and the SPIKE-001/002 side-effect measurements. Owner: unassigned → assign before v1.1 planning.

### Phase 7 — Plugin implementation and verification

**Status:** v1.0 scope complete on 2026-11-12 (see backlog and progress below). Automatic observation (IMPL-005/006/009) is out of v1 scope and deferred to v1.x behind ENV-001.

**What v1.0 is (decision D-019):** a *manual* before/after comparison product. The user records what checkout shows; the plugin stores scenarios, versions, approved baselines, reports and a change log, and compares exactly. It never runs checkout. This is honest, useful today and safe for WordPress.org review.

**v1.0 delivered:**
- Scenarios with immutable versions; approved baselines with revisions bound to version and context fingerprint.
- Manual comparison of shipping method, shipping cost, coupon code, coupon discount and payment methods; exact decimal money; "Needs review" for anything not recorded.
- Saved reports with configurable retention (7–365 days, default 30) and daily bounded purge.
- Change log for plugin updates and `woocommerce_*_settings` options, now with readable labels (schema v2, migration verified v1→v2).
- Tabbed full-width admin screen under **WooCommerce → Checkout Impact** (Overview, Scenarios, Compare, Reports, Change log, Settings, Docs) with WooCommerce pickers for countries, currencies, products, shipping rates and gateways; built-in documentation page; Plugins-screen links (Open, Settings, Documentation).
- Privacy: exporter/eraser, privacy-policy suggestion, preserve-by-default uninstall with opt-in erase.
- HPOS and Cart/Checkout blocks compatibility declared (the plugin never touches orders).
- Extension points for add-ons: `wpecir_loaded`, `wpecir_change_event_recorded`, `wpecir_manual_report_saved`, `wpecir_pro_active`, `wpecir_tracked_option_names`; JS filters `wpecir.adminTabs`, `wpecir.reportActions`.
- **Pro add-on** (`pro/checkout-impact-regression-pro`): email digest alerts for recorded changes (15-minute batching, up to 5 recipients, no setting values in mail) and CSV/JSON report export with spreadsheet-formula neutralisation.

**Evidence layers (2026-11-12):** contract tests (18 scripts, all pass); local runtime on WordPress 7.1.4 / WooCommerce 11.2.2 / PHP 8.3 / SQLite: browser end-to-end flow, package install on a fresh site, activation, schema install and v1→v2 upgrade, REST authorization (anonymous 403, subscriber 403), uninstall preserve and erase, privacy export/erase, Pro alerts and export. CI adds PHP 8.2/8.3/8.4 lint+contracts, package build and the official WordPress.org Plugin Check action. Details: [Phase 7 task evidence](validation/PHASE-7-TASK-EVIDENCE.md).

**Not yet verified:** MySQL/MariaDB runtime (local runs used the SQLite drop-in), PHP 8.2 runtime (lint only), assistive-technology testing, WPML/Polylang runtime, Plugin Check results (run in CI on the PR).

### Phase 8 — Release readiness

**Status:** In progress. Packages, readme, assets, deploy workflow and checklists are ready; submissions require the owner's WordPress.org and WooCommerce.com accounts.

**Deliverables ready:** `readme.txt`; `wordpress-org/` icon, banner and 7 screenshots; `bin/build.sh` producing both ZIPs; `.github/workflows/wporg-deploy.yml` (SVN deploy after approval); [WordPress.org submission checklist](release/WPORG-SUBMISSION.md); [WooCommerce Marketplace checklist](release/WOO-MARKETPLACE-SUBMISSION.md); [distribution plan](release/DISTRIBUTION-PLAN.md).

**Owner actions:** see REL-002/REL-003 below.

## 8.1 Working rules and progress accounting

- Work on a branch and open a pull request; the owner reviews and merges. Do not push directly to `main` (D-020).
- A resume message ("continue", "...", "?") resumes the current task; it is **not** approval for irreversible or external actions (marketplace submission, deleting data, publishing).
- Evidence layers stay separate: contract, CI, package, local runtime, staging, marketplace. Never promote "attempted" to "passed".
- Never run real checkout, orders or payments while Phase 6 is NO-GO.

**Progress formula (D-018).** Progress is reported in two lines so test-only tasks cannot inflate it:

1. **v1.0 product scope** = Complete rows ÷ rows marked *v1* in the Phase 7 backlog.
2. **Overall** = mean of the nine phase percentages, where Phase 6 counts as closed (100%) only because its decision is made; it is listed separately as "NO-GO" in the README.

Deferred rows (*v1.x*) are listed but excluded from the v1 denominator. Recalculate from row statuses; never hand-tune.

**Current snapshot (2026-11-12):** Phase 7 v1 scope: 26 of 28 rows Complete, 2 Partial (IMPL-010, IMPL-011) = **93%**. Deferred v1.x: 4 rows (ENV-001, IMPL-005, IMPL-006, IMPL-009). Phase 8: 1 of 4 Complete, 3 Partial = **25%**. Overall = (100×7 + 93 + 25) ÷ 9 = **91%**.

## 9. Backlog

Rows from Phases 1–6 are unchanged and Complete/Deferred as recorded on 2026-11-06 (PLAN-001…005, REQ-001…003, ARCH-001…003, SCN-001…003, SPIKE-001…009, UX-001…002). Their full text is preserved in git history and in the phase sections above.

### Phase 7 — v1.0 scope

| ID | Scope | Priority | Task | Acceptance criteria | Status |
|---|---|---|---|---|---|
| BUILD-001 | v1 | P1 | Versioned installable packages | `bin/build.sh` checks version consistency (header, constant, Stable tag), builds free and Pro ZIPs from an allowlist, verifies contents/checksums, rejects dev files; CI uploads both | Complete (2026-11-12; local build + fresh-site install) |
| IMPL-001 | v1 | P0 | Bootstrap, PSR-4 autoload, dependency and capability guards | Autoloader, missing-vendor notice, `Requires Plugins: woocommerce`, capability-gated screen; package activates on a fresh site | Complete (2026-11-12; fresh-site activation from ZIP) |
| IMPL-002 | v1 | P0 | Privacy-first storage, migrations, retention, uninstall | Versioned tables; install and upgrade verified in runtime; retention configurable; uninstall preserve/erase verified; privacy export/erase verified | Complete (2026-11-12; runtime evidence) |
| IMPL-003 | v1 | P0 | Change-event collector | Plugin updates and tracked settings recorded idempotently with redaction and readable labels; correlation only | Complete (2026-11-12; real option-update hooks recorded on runtime) |
| IMPL-004 | v1 | P0 | Scenario catalog and builder | Create/edit(version)/delete with WooCommerce pickers; validation; accessible labels | Complete (2026-11-12; browser e2e) |
| IMPL-007 | v1 | P0 | Normaliser and diff engine (manual fields) | Exact money, five supported fields, mismatch/incomplete never "unchanged" | Complete for v1 manual fields; adapter normalisation moves with IMPL-005 |
| IMPL-008 | v1 | P0 | Baseline approval, comparison, reports, history UI | Approve/prefill baseline, stale-version guard, compare, save, list, retention notice | Complete (2026-11-12; browser e2e) |
| IMPL-010 | v1 (Pro) | P1 | Notifications and redacted export | Digest email with dedupe/batching and no values; CSV/JSON export; delivery failure is surfaced | Partial (alerts and export verified on runtime; a failed `wp_mail` is not yet shown in the UI) |
| IMPL-011 | v1 | P0 | Compatibility, security, performance, accessibility | Published matrix contains only verified entries | Partial (WP 7.1.4 / Woo 11.2.2 / PHP 8.3 runtime on SQLite; CI lint PHP 8.2–8.4; MySQL runtime, AT testing and Plugin Check result pending) |
| IMPL-012 … IMPL-025 | v1 | P1 | Contract-test hardening tasks from 2026-11-07 | As recorded in evidence | Complete (14 rows; unchanged) |
| IMPL-026 | v1 | P0 | Settings: retention days and erase-on-delete | REST + UI; 7–365 validated server-side; applies to new reports | Complete (2026-11-12; contract + browser) |
| IMPL-027 | v1 | P0 | Admin UI v2 and in-plugin documentation | Full-width tabbed screen under WooCommerce, keyboard-operable tabs, Docs tab, Plugins-screen links, responsive to 390 px | Complete (2026-11-12; screenshots in evidence) |
| IMPL-028 | v1 (Pro) | P1 | Pro add-on package | Separate plugin requiring the free plugin; Alerts tab and export via public hooks; uninstall removes Pro options only | Complete (2026-11-12; runtime + contract) |
| IMPL-029 | v1 | P1 | i18n readiness | All UI strings literal and translatable in JS and PHP; script translations loaded | Complete (2026-11-12) |
| IMPL-030 | v1 | P1 | WooCommerce feature compatibility declarations | HPOS and Cart/Checkout blocks declared compatible (no order access) | Complete (2026-11-12) |

Counting: BUILD-001, IMPL-001–004, 007, 008, 010, 011, IMPL-012…025 (14), IMPL-026…030 (5) = 28 rows.

### Deferred to v1.x (blocked by Phase 6 NO-GO)

| ID | Task | Unblocks when |
|---|---|---|
| ENV-001 | Isolated, resettable WooCommerce runtime with test-only gateway | Owner assigns it; local tooling path proven 2026-11-12 |
| IMPL-005 | Cart Token scenario adapter | SPIKE-001 passes on ENV-001 |
| IMPL-006 | Classic and Blocks checkout observation adapters | SPIKE-001/002 pass per mode |
| IMPL-009 | Bounded orchestration, retries, cleanup | IMPL-005 and safety proof |

### Phase 8 — Release

| ID | Priority | Task | Acceptance criteria | Status |
|---|---|---|---|---|
| REL-001 | P0 | Free/Pro boundary, licence, naming | Free is fully useful (no locks, no trial); Pro is a separate plugin; GPL-2.0-or-later; slug fixed | Complete (2026-11-12; D-018, D-021) |
| REL-002 | P0 | WordPress.org submission | Plugin Check clean; ZIP uploaded by owner; review approved; SVN secrets added | Partial (package, readme, assets ready; owner uploads ZIP) |
| REL-003 | P0 | WooCommerce Marketplace submission (Pro) | Vendor approved; product submitted with QIT results, docs and demo instructions | Partial (package and checklist ready; owner applies as vendor) |
| REL-004 | P0 | Release operations | Tag v1.0.0, deploy workflow, support channel and changelog | Partial (deploy workflow ready; needs approval + secrets) |

## 10. Decisions and open questions

### Recorded decisions

- **D-001:** Position as checkout change-impact regression comparison across saved scenarios.
- **D-002:** *(Superseded 2026-11-12 by D-019.)* The repository started in pre-development planning.
- **D-003:** React admin interface using WordPress-bundled React (`wp.element`), no build step (see D-014).
- **D-004:** No product CLI or CLI commands before final release (product only; see D-017).
- **D-005:** Compatibility claims only for verified combinations.
- **D-006:** README is the short status page; this plan holds scope, decisions and backlog; dated evidence lives in `docs/validation/`.
- **D-007 … D-013:** unchanged (competitor scope, integration matrix, positioning hypothesis, AGENTS.md canonical, dual channel, channel-specific packages, Phase 6 NO-GO).
- **D-014:** WordPress-bundled React and `wp-i18n` without a build tool.
- **D-015:** Per-site custom tables, non-autoloaded options, 30-day default retention configurable 7–365 days, preserve-on-uninstall unless erase is chosen. *(Correction 2026-11-12: the earlier clause "keep implementation blocked until runtime migration evidence passes" was overtaken by events — storage was implemented on 2026-11-07; runtime install/upgrade/uninstall/privacy evidence was added on 2026-11-12.)*
- **D-016:** Money is stored and transmitted as exact decimal strings.
- **D-017 (2026-11-12):** The no-CLI rule applies to the product only. Local WordPress test sites, Composer, build scripts, CI, WP-CLI/Plugin Check for testing are allowed. Reason: the earlier blanket rule blocked migration and runtime proof.
- **D-018 (2026-11-12):** Progress is reported as v1-scope completion plus deferred count; Phase 6 NO-GO is shown explicitly. Reason: the old task-count formula let test-only tasks raise the bar while 0 of 11 core tasks were complete.
- **D-019 (2026-11-12):** v1.0 is the manual comparison product. Automatic observation is v1.x, gated by ENV-001 and Phase 6.
- **D-020 (2026-11-12):** AI contributors work on branches and open pull requests; the owner merges. "..." or "?" does not authorise external or irreversible actions.
- **D-021 (2026-11-12):** Free plugin slug `checkout-impact-regression-for-woocommerce` (name "Checkout Impact Regression for WooCommerce", author WPEssential, Plugin URI `https://wpessential.org/plugins/<slug>`). Pro is a separate plugin `checkout-impact-regression-pro` sold on WooCommerce.com: email alerts and report export. No feature in the free plugin is locked or time-limited.

### Open questions

- Pro pricing (the earlier $119/year idea is unvalidated).
- Which isolated environment will host ENV-001 for v1.1 automatic observation.
- Whether WooCommerce.com accepts the Pro add-on with a WordPress.org dependency (confirm during vendor onboarding).

## 11. Change control

- Add scope only with an ID, user need, priority, dependencies and acceptance criteria.
- Update this plan and README in the same pull request as the change.
- Keep historical evidence; add dated corrections instead of rewriting results.
- Dated progress logs that used to live here were moved to [Phase 7 task evidence](validation/PHASE-7-TASK-EVIDENCE.md) on 2026-11-12.
