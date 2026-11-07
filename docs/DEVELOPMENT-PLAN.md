# Development plan

**Product:** WPEssential Checkout Impact Regression  
**Repository:** `Vertex-Systems-Network/wpessential-checkout-impact-regression`  
**Stage:** Early implementation (Phase 7)  
**Plan last updated:** 2026-11-07

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
8. **No CLI before final release.** Do not add a customer-facing CLI, internal plugin CLI commands, or CLI-based setup/interface to the product during planning or implementation. Revisit only as a final-release decision.
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

**Status:** Complete (2026-11-06) as a no-go/support-exclusion decision; no runtime pass is claimed and no automatic checkout replay mode is approved.

**Completed evidence:** Source-backed designs, adapter/canonicalization/event/queue/multilingual spikes, safety protocol, environment gates, operator worksheet, and V-01–V-07 proof matrix are complete. The [Phase 6 closure decision](validation/PHASE-6-CLOSURE-DECISION.md) formally excludes every unproven automatic replay mode and defines the evidence required to reopen support. WooCommerce checkout API reference and versioned source/release behavior conflict on fresh-session draft creation; no exact-version runtime test was performed. This is a completed no-go decision, not runtime validation.

**Closure outcome / runtime exclusion:** No disposable isolated WooCommerce staging store or redacted runtime evidence was available. This workspace has no PHP, Docker, WP-CLI or WordPress runtime. The earlier Playground attempt hit browser verification; the fresh Blueprint attempt failed because its web worker and OPFS storage worker did not load. No checkout case, draft cleanup, or side-effect containment test ran. Per the formal closure decision, Classic checkout, Checkout Blocks, cart replay, payment/shipping integrations, WPML/WCML and Polylang are unsupported until their exact combination passes a reopened runtime proof.

**Deliverables:** small technical spikes for high-risk WordPress/WooCommerce hooks, checkout modes, safe execution, normalization, extension points, and implementation-ready specifications. Research and protocol artifacts may complete a task, but do not count as runtime validation.

**Exit criteria / closure result:** architecture risk is explicit; every untested runtime mode is formally unsupported rather than assumed safe; the environment limitation and absent test evidence are disclosed; implementation backlog is sequenced. Phase 6 is closed under this no-go branch. Reopen it before enabling any unsupported mode; do not read 100% as a runtime pass.

### Phase 7 — Plugin implementation and verification

**Status:** In progress (started 2026-11-06; 68% tracked). Composer PSR-4, PHP/JavaScript syntax, comparison/scenario contracts, synthetic multilingual/currency context dry-run, and installable artifact packaging pass CI. On `/el-test/`, v0.2.1 scenario smoke saved and read back GB/GBP `7.25`, then deleted it. Workflow #133 passed malformed nested-payload validation tests, and artifact `13576112602` was uploaded over v0.2.1 on staging; the plugin remained active, the admin screen loaded, and an invalid `ZZZ` country was rejected without persisting a record. Mocked schema/privacy/change-event contracts pass; live migration/privacy/hook verification, reports, adapters and compatibility/accessibility remain open.

**Completed evidence:** Workflow run #107 passed on 2026-11-07 (commit `a2c9087460b1039eea3470c5257bbf6c7520b601`): strict Composer validation, optimized PSR-4 autoload generation, PHP lint, JavaScript syntax, normalizer/diff/scenario contract tests, scenario REST capability allow/deny contract checks, ZIP integrity and artifact upload. Artifact `13575111534` contains v0.2.1 and expires 2026-12-07. The v0.2.1 package was installed on `/el-test/` (WordPress 7.1.4 / PHP 8.3.36 / WooCommerce 11.2.2); synthetic GB/GBP `7.25` was saved, read back exactly in Edit, then deleted and the empty library confirmed. Workflow #117 (commit `3b3732625b210e3d4af8ddddb5af8da97ad6ee8e`) passed the new synthetic multilingual/currency context dry-run and rebuilt v0.2.1 artifact `13575403375` (33,662 bytes; expires 2026-12-07). This is comparator-contract evidence, not runtime WPML/WCML or Polylang compatibility. Staging validation on 2026-11-07 rejected synthetic destination `ZZZ` and left the scenario library empty. CI run #128 passes synthetic privacy exporter/eraser callback contracts using an in-memory database double; live WordPress privacy processing remains pending.

Current source has a seven-table versioned schema; administrator-guarded scenario creation/versioning/read/list/deletion and baseline approval; redacted idempotent change-event capture; privacy exporter/eraser callbacks; bounded expired-run purge; preserve-by-default uninstall behavior with explicit opt-in erasure; scenario list/create/delete REST routes with nonce/capability protection; a React scenario builder/library; exact-decimal money normalization; and context-aware field diffs. CI fixtures cover money precision, changed/unchanged/added findings, unsupported observations, context mismatch, the REST scenario capability gate (unauthorized deny, WooCommerce manager/admin allow), and synthetic locale/currency/checkout-mode/multilingual-stack context mismatch cases. The multilingual dry-run checks WPML+WCML and Polylang context fingerprints plus GBP/EUR/JPY decimal scales; it calls the pure comparator only and does not load either integration. These capability tests call the callback directly and are not a live WordPress REST authentication/nonce test. Run/observation CRUD, baseline UI, expected/current reports, notifications/export, and broader adapter-level normalization remain unimplemented. See [Phase 7 task evidence](validation/PHASE-7-TASK-EVIDENCE.md) and [storage decision](architecture/STORAGE-AND-RETENTION-DECISION.md).

**Safety disposition:** Phase 6 remains NO-GO for automatic checkout observation/replay. No checkout/order/payment/shipping/coupon request or schema migration was run in this session. The shared site lacks isolated side-effect snapshots, safe external sinks, and reset proof. No checkout request/replay route or executor is included. Keep all automatic checkout modes unsupported until an exact-version isolated safety proof passes and Phase 6 is reopened. No product CLI was added.

**Remaining prerequisites:** (1) Install the generated artifact on an isolated/resettable WordPress + WooCommerce test site and verify activation, migrations, REST authorization, scenario persistence, privacy export/erase and uninstall behavior. (2) Complete scenario version editing and deeper validation; run/observation records; baseline approval and impact report/history UI; redacted export/notifications. (3) Finish adapter-level canonical fixtures and the supported PHP/WP/Woo/HPOS/Classic/Blocks/WPML/Polylang matrix. (4) Keep adapters/replay/orchestration blocked until Phase 6 safety evidence passes. No product CLI is permitted before final release.

**Deliverables:** Reviewable implementation slices; current CI evidence; exact-version runtime/migration/privacy proof; security/performance/accessibility results; current documentation.

**Exit criteria:** All supported-matrix acceptance criteria have reproducible evidence; unsupported cases fail closed; release package is installable and upgrade/uninstall behavior is verified; no product CLI is added before final release.

### Phase 8 — Release readiness

**Status:** Not started

**Target channels:** submit the free edition to the WordPress.org Plugin Directory and the paid edition to the WooCommerce.com Marketplace, subject to both review processes and vendor approval. No applications or submissions have been made.

**Deliverables:** release packages and separate channel/update ownership; approved free/paid feature matrix; WordPress.org plugin readme and submission ZIP; Woo vendor application and paid product submission; license and pricing decisions; QIT/compatibility/security/accessibility evidence; upgrade/uninstall/rollback behavior; support/privacy docs; final compatibility statement; known limitations; release checklist.

**Compliance boundary:** the WordPress.org edition must provide meaningful independent free functionality and must not be trialware or install/update the paid package from an external server. Woo Marketplace's current freemium process requires separate free and paid submissions. Resolve the package identity, paid add-on, update and pricing model with both marketplaces before freezing release packaging. Verify requirements again at submission time.

**Exit criteria:** install/update/uninstall and rollback scenarios pass; current requirements for both channels are met; all marketplace test/review evidence is complete; official approvals and launch onboarding are recorded; known limits and support terms are published; final-release decision explicitly addresses whether any CLI is appropriate. Detailed gates are in [Distribution and Marketplace Submission Plan](release/DISTRIBUTION-PLAN.md).

## 9. AI-ready backlog

Backlog items should be assigned to an assistant only when the task is bounded and all dependencies are met. “AI” is a contributor role, not an approval authority.

| ID | Phase | Priority | Task | Dependencies | Acceptance criteria | Status |
|---|---:|---|---|---|---|---|
| PLAN-001 | 1 | P0 | Review Cart Lookout from official WordPress.org materials | None | Dated notes distinguish verified behavior, unknowns, and source links; findings added to competitor matrix | Complete (2026-11-06; see competitor research report) |
| PLAN-002 | 1 | P0 | Review ShopMonitor from official vendor materials | None | Dated notes capture verified features, supported platform, limitations, and source links | Complete (2026-11-06; first-pass public materials reviewed) |
| PLAN-003 | 1 | P1 | Identify adjacent WooCommerce checkout monitoring and testing tools | PLAN-001, PLAN-002 | Candidate list justified by relevance; no unsupported feature claims | Complete (2026-11-06; 10 products reviewed in public materials) |
| PLAN-004 | 1 | P0 | Build the deduplicated feature/options bank | PLAN-001–003 | Each option has unique ID, user need, system owner, priority, dependencies, risk, and acceptance criteria; duplicate concepts are merged | Complete (2026-11-06; see OPTIONS-BANK.md) |
| PLAN-005 | 1 | P0 | Build the system inventory and responsibility map | PLAN-004 | Each system has one owner responsibility, inputs/outputs, state ownership, failure modes, and named dependencies; overlap is resolved | Complete (2026-11-06; see SYSTEM-INVENTORY.md) |
| REQ-001 | 2 | P0 | Define scenario personas and checkout journeys | PLAN-004 | Journeys cover region-specific shipping, payment availability, coupon behavior, unchanged control scenario, and inconclusive execution | Complete (2026-11-06; requirements/REQUIREMENTS.md) |
| REQ-002 | 2 | P0 | Verify platform requirements and propose compatibility matrix | Official PHP/WP/WooCommerce/WPML/Polylang docs | Version ranges are sourced, testable, and clearly labeled proposed until accepted | Complete (2026-11-06; targets remain untested) |
| REQ-003 | 2 | P0 | Define privacy, permissions, retention, and safe test constraints | REQ-001 | Data inventory, access model, retention, redaction, and no-real-charge constraints have acceptance criteria | Complete (2026-11-06; safe replay excluded by Phase 6 closure decision) |
| ARCH-001 | 3 | P0 | Map WooCommerce shipping/payment extension and carrier API options | REQ-002, PLAN-005 | Official docs and sandbox/API facts are recorded; region, credential, rate-limit, and dependency data included | Complete (2026-11-06; integration matrix; provider support untested) |
| ARCH-002 | 3 | P0 | Propose end-to-end architecture and data flow | PLAN-004, PLAN-005, REQ-001–003 | Diagram covers change trigger through report, data stores, permissions, retries, redaction, and failure paths | Complete (2026-11-06; proposed architecture; technical proof pending) |
| ARCH-003 | 3 | P0 | Define baseline, normalized result, and diff schemas | ARCH-002 | Schemas distinguish missing, changed, unchanged, unsupported, flaky, and inconclusive values with versioning rules | Complete (2026-11-06; conceptual contracts; Phase 4 schema design pending) |
| SCN-001 | 4 | P0 | Specify scenario inputs, versions and baseline context binding | REQ-001, ARCH-002 | Schema captures synthetic cart/destination/locale/currency/tax/checkout mode and validates immutable scenario/baseline rules | Complete (2026-11-06; scenario spec) |
| SCN-002 | 4 | P0 | Define normalized observations, comparability and field diffs | SCN-001, ARCH-003 | Money, shipping, payment, coupon, tax and validation fields have normalization, context matching and explicit missing/unsupported states | Complete (2026-11-06; scenario spec; no runtime claim) |
| SCN-003 | 4 | P0 | Specify impact report vectors and safe-run boundary | SCN-002, REQ-003 | Deterministic expected vectors cover region shipping, price shift, unchanged control, coupon, partial, unsupported and context mismatch; safe-run proof is assigned to SPIKE-001 | Complete (2026-11-06; design examples; replay untested) |
| SPIKE-001 | 6 | P0 | Validate safe checkout replay for supported checkout modes | REQ-002, REQ-003, ARCH-001–003 | Evidence proves or rejects safe repeatable scenarios without real charges or unintended side effects; checkout-draft creation and cleanup are explicitly measured | Deferred; no runtime proof. All replay modes excluded until reopened with isolated evidence |
| SPIKE-002 | 6 | P0 | Audit checkout endpoint and side-effect behavior by mode | SPIKE-001; disposable staging store | Classic and Blocks evidence records endpoint/hooks, order statuses, draft cleanup, gateway, email, stock, coupon, webhook, analytics and fulfillment deltas; any uncontained effect blocks support | Deferred; no runtime proof. Classic and Blocks excluded until reopened |
| SPIKE-003 | 6 | P0 | Reconcile Store API reference with versioned WooCommerce checkout behavior | REQ-002; official release notes and versioned source | Record discrepancies and behavior per proposed WooCommerce floor/current release; update design so checkout GET is never presumed read-only; exact runtime cases remain separate | Complete (2026-11-06; API docs/source conflict documented; exact tag/runtime verification pending) |
| SPIKE-004 | 6 | P0 | Define Store API observation adapter boundary | REQ-003; Cart/Checkout and Blocks docs | Separate cart-level rates/coupons/totals from checkout-visible payment availability; specify isolated token flow, mode-specific capture and fail-closed states without claiming runtime safety | Complete (2026-11-06; source-backed design; runtime untested) |
| SPIKE-005 | 6 | P0 | Define canonicalization for Cart API values | SCN-002; Cart API response schema | Map exact minor-unit money, currency precision, shipping-rate identity, coupon and completeness guards; specify redaction and mismatched-context behavior; runtime fixtures remain required | Complete (2026-11-06; data contract defined; parser/tests unimplemented) |
| SPIKE-006 | 6 | P0 | Define plugin-update and settings-change event capture | WordPress upgrader/options hooks; WooCommerce Settings API | Record update/settings evidence with safe identities and redaction; support bulk events, source uncertainty, idempotency and correlation-only semantics; runtime hook coverage remains required | Complete (2026-11-06; source-backed contract; runtime untested) |
| SPIKE-007 | 6 | P0 | Define bounded background run scheduling and recovery | SPIKE-001; Action Scheduler/WordPress Cron docs | Specify queue state ownership, idempotency, delayed/failed states, retry caps, deactivation behavior, argument redaction and no synchronous fallback; runtime cases remain required | Complete (2026-11-06; queue design documented; runtime untested) |
| SPIKE-008 | 6 | P0 | Prepare a reproducible runtime proof runbook and evidence worksheet | SPIKE-001–007; isolated staging operator | Operator can record exact versions, safety preflight, scenario observations, side-effect deltas, cleanup and mode-specific sign-off; worksheet does not claim a runtime pass | Complete (2026-11-06; operator worksheet prepared; no runtime execution claimed) |
| SPIKE-009 | 6 | P0 | Define WPML/WCML and Polylang checkout context and proof matrix | REQ-002; SPIKE-001; official vendor docs | Language/currency fingerprints, Classic/Blocks separation, context mismatch handling, versioned proof cases and safety criteria are documented; no runtime compatibility claim is made | Complete (2026-11-06; source-backed planning; runtime support excluded) |
| ENV-001 | 6 | P0 | Provision isolated, disposable WooCommerce runtime to reopen replay support | SPIKE-001–009; authorized staging operator | Resettable non-production store, synthetic data, test-only gateway, external side-effect containment, exact version fingerprint and redacted evidence access are available; secrets never enter repo/chat | Deferred gate; required before any replay mode can be enabled |
| UX-001 | 5 | P1 | Define admin information architecture and interactive scenario/report prototype | REQ-001, PLAN-004, SCN-001–003 | Prototype covers setup, market/cart, expected values, before/after impact, evidence, history and prototype-only safety blocks; React component/API ownership is documented | Complete (2026-11-06; design artifact only) |
| UX-002 | 5 | P1 | Specify accessible interaction and async/error/unsupported states | UX-001, REQ-008 | Keyboard/focus, labels, announcements, contrast, responsive behavior and empty/loading/error/partial/blocked/unsupported states are explicitly designed | Complete (2026-11-06; not assistive-technology tested) |
| BUILD-001 | 7 | P1 | Build a downloadable, versioned WordPress install package | IMPL-001; release package file allowlist | CI derives version from plugin header, builds Composer vendor autoload, checks install ZIP/root contents, emits checksum and retains downloadable artifact; manual workflow dispatch is documented | Complete (workflow run #100 passed on commit `01eba864ba8436882857f7297732a30a62411612`; artifact `13574961457`, package v0.2.1, expires 2026-12-07. Manual `workflow_dispatch`, push and pull-request triggers are enabled) |
| IMPL-001 | 7 | P0 | Establish plugin bootstrap, Composer PSR-4 autoloading and dependency/capability guards | Phase 6 exit; REQ-002 | `composer.json` maps the plugin namespace to `includes/`; bootstrap loads generated `vendor/autoload.php`; install package includes vendor files; PHP lint passes; dependency failures are visible and non-fatal; no product CLI is introduced | Partial (run #61 on commit `ac47188e5131f3baafb5558643e55da99935752e` passed Composer validation/optimized PSR-4 generation, PHP and JavaScript syntax, normalizer checks, ZIP integrity, and built artifact `13549856652`. On 2026-11-07 the v0.2.0 package activated on `/el-test/` and the admin page loaded without the missing-Composer-dependencies notice. Broader capability/dependency failure paths and exact PHP/WP compatibility remain untested) |
| IMPL-002 | 7 | P0 | Implement privacy-first storage and domain records | IMPL-001; SYS-13; accepted storage decision; migration runtime gate | Versioned scenario, baseline, event, run and observation records have migrations, access checks, redaction and uninstall/retention behavior | Partial (source includes scenario/version/baseline repository, bounded expired-run purge with daily WP-Cron scheduling, privacy hooks and preserve-by-default opt-in uninstall script. v0.2 admin page activation passed, but table/schema-version migration was not independently inspected; run/observation CRUD, retention UI and runtime migration/privacy proof remain) |
| IMPL-003 | 7 | P0 | Implement change-context event collector | IMPL-002; SPIKE-006 runtime gate | Supported plugin updates/settings changes are idempotently recorded with safe fingerprints; unknown sources remain explicit; event is correlation only | Partial (plugin updates and WooCommerce settings are hooked and redacted fingerprints stored; runtime hook coverage and event UI/correlation remain unverified) |
| IMPL-004 | 7 | P0 | Implement scenario catalog and React scenario builder | IMPL-002; UX-001; SYS-01 | Authorized user can create/edit/version synthetic scenarios; validation, accessible states and localization-ready UI match the spec | Partial (Staging v0.2.0/v0.2.1 smoke tests verified create/read/edit/version/delete and cleanup; v0.2.1 saved/read back `7.25`. On the uploaded hardened artifact, plugin stayed active and UI loaded; synthetic country `ZZZ` was rejected with no record saved. CI tests malformed market/cart/item/expectation shapes. No checkout request ran. Schema migration/version, live permissions, broader validation, and accessibility remain unverified) |
| IMPL-005 | 7 | P0 | Implement approved Cart Token scenario adapter | IMPL-004; SPIKE-001 pass for exact matrix entry | Synthetic cart construction and cart-level capture pass isolated side-effect, token isolation, cleanup and repeatability tests; unsupported entries fail closed | Not started |
| IMPL-006 | 7 | P0 | Implement Classic and Blocks checkout observation adapters | IMPL-005; SPIKE-001 pass per mode/gateway | Payment availability and checkout fields use mode-specific adapters; results cite capability/version and never submit Place Order | Not started |
| IMPL-007 | 7 | P0 | Implement canonical normalizer and deterministic diff engine | IMPL-005/006; SPIKE-005 fixtures | Exact money, package/rate, coupon, tax and completeness contracts pass NORM fixtures; mismatch/incomplete data cannot become unchanged | Partial (typed states, canonicalization, exact decimal-to-minor-unit conversion and context-aware diffs are implemented. Run #95 verifies scenario amount input validation; run #117 passes synthetic language, currency, checkout-mode and WPML+WCML/Polylang fingerprint mismatch dry-run cases plus GBP/EUR/JPY precision cases. This exercises pure comparator contracts only; adapter-specific normalization, actual multilingual plugin integration and full domain fixtures remain pending) |
| IMPL-008 | 7 | P0 | Implement baseline approval, impact report and history UI | IMPL-007; UX-001; SYS-10 | Baselines are immutable/approved; report shows per-scenario expected/current values, evidence and unsupported/inconclusive states | Not started |
| IMPL-009 | 7 | P0 | Implement bounded orchestration, retries and cleanup | IMPL-003/005; SPIKE-001 safety pass | Runs are idempotent, cancellable, rate-limited and isolated; cleanup failures block support and are visible | Blocked (Phase 6 NO-GO; no run executor or replay scheduling may be implemented until exact-matrix safety proof passes) |
| IMPL-010 | 7 | P1 | Implement notifications and redacted export | IMPL-008; SYS-11/SYS-14 | Dedupe, user policy, privacy redaction and delivery failure behavior pass acceptance criteria | Not started |
| IMPL-011 | 7 | P0 | Verify compatibility, security, performance and accessibility matrix | IMPL-001–010; REQ-002 | Exact PHP/WP/Woo/HPOS/classic/Blocks/WPML/Polylang combinations have reproducible evidence; publish only passed entries | Not started |
| IMPL-012 | 7 | P1 | Complete synthetic WPML/Polylang context dry-run | IMPL-007; multilingual compatibility spike | Without installing WPML, WCML or Polylang, synthetic language/currency/checkout-mode/stack fingerprints prove same-context comparison and fail-closed mismatch behavior; currency precision vectors pass; evidence clearly states this is not runtime compatibility | Complete (2026-11-07; CI run #117 passed) |
| IMPL-013 | 7 | P1 | Add synthetic privacy exporter/eraser contract coverage | Privacy callbacks; WP test doubles | Verify privacy hook registration, minimal metadata export without email/cart payload, bounded user-scoped export, author-ID anonymization across scenario/version/baseline records, history retention, and unknown-user no-op; do not access live DB | Complete (2026-11-07; CI run #128 passed with an in-memory wpdb double; live WordPress privacy runtime remains pending) |
| IMPL-014 | 7 | P1 | Harden scenario validation against malformed nested payloads | IMPL-004; API validation contract | Non-array market/cart, non-string country/currency and expectation text, malformed product/quantity values and out-of-range quantity return a controlled validation error without warnings/coercion; CI contract checks pass; no checkout or persistence side effect | Complete (2026-11-07; CI run #133 passed and v0.2.1 artifact `13576112602` was installed on staging; admin remained active and invalid-country UI check rejected without saving) |
| IMPL-015 | 7 | P1 | Verify repository rejects personal/secret and oversized scenario payloads before writes | IMPL-002; privacy and storage contracts | Synthetic in-memory tests show email-bearing values, identity/credential keys, and payloads over 64 KB return errors before any database insert; live customer data is never used | Complete (2026-11-07; CI run #139 passed; runtime implementation unchanged and staging data untouched) |
| IMPL-016 | 7 | P1 | Verify schema install guard and table-definition contract with a mock database | Schema installer; WP test doubles | Unauthorized upgrades are rejected; all seven table DDLs are submitted and verified; failed verification prevents version advance; successful install saves version without autoload; current version is a no-op; do not touch live DB | Complete (2026-11-07; CI run #145 passed with dbDelta/wpdb doubles; live staging migration still pending) |
| IMPL-017 | 7 | P1 | Verify change-event redaction, option scope and idempotency contracts | IMPL-003; storage/repository; synthetic option fixtures | Untracked settings are ignored; secret/email/phone fields are excluded before fingerprinting; repeated same-context event inserts once; deletion records no old value; oversized projection is ignored; use in-memory database only | Complete (2026-11-07; CI run #150 passed; live hook/callback coverage remains pending) |
| IMPL-018 | 7 | P1 | Verify preserve-by-default and explicit uninstall erasure contracts | IMPL-002; uninstall.php; synthetic WP doubles | Default uninstall performs no table drops or option deletes; explicit opt-in drops exactly seven plugin tables and deletes only plugin schema/preference options; CI invokes both cases with no live DB | Complete (2026-11-07; CI run #155 passed) |
| IMPL-019 | 7 | P1 | Verify expired-run retention cleanup is bounded and access-controlled | IMPL-002; RetentionScheduler; synthetic wpdb | Unauthorized calls make no query; purge clamps to 1–500 runs, selects only expired IDs in expiry order, deletes observations/findings before parent runs, and performs no deletes when selection is empty; CI uses a synthetic DB | Complete (2026-11-07; CI run #164 passed) |
| IMPL-020 | 7 | P1 | Verify scenario REST routes are explicitly gated and limited to intended methods | IMPL-004; ScenarioController; REST contract | Collection exposes read/create, item exposes read/update/delete; every route uses the shared capability callback; no checkout or run-execution route is registered; CI uses route-registration stubs only | Complete (2026-11-07; CI run #173 passed) |
| IMPL-021 | 7 | P1 | Verify daily retention scheduling and deactivation cleanup | IMPL-002; RetentionScheduler; WordPress Cron doubles | Registers one delayed daily event, repeated registration is idempotent, and unschedule removes every duplicate event; CI uses in-memory Cron functions only | Complete (2026-11-07; CI run #182 passed) |
| IMPL-022 | 7 | P1 | Assert critical storage indexes and uniqueness contracts | IMPL-002; Schema; schema contract suite | Mocked schema tests verify unique scenario slug/version/revision/event keys and indexes for expiry, run-field, classification and severity; no live schema change | Complete (2026-11-07; CI run #189 passed) |
| REL-001 | 8 | P0 | Approve channel-specific free/paid boundary, licensing, naming and update ownership | Phase 7 release candidate; distribution research | Feature matrix provides useful non-trial free edition; separate marketplace submissions/update paths are compliant; price and vendor terms are reviewed | Not started |
| REL-002 | 8 | P0 | Prepare and submit the free WordPress.org edition | REL-001; Phase 7 quality evidence | Complete production ZIP, GPL-compatible file inventory, readme/changelog/source link and privacy/support info pass current directory review | Not started |
| REL-003 | 8 | P0 | Apply for Woo Marketplace vendor status and submit paid edition | REL-001; Phase 7 quality evidence | Vendor approval, separate paid submission, current QIT/compatibility/UX evidence, demo/test instructions and review approval are recorded | Not started |
| REL-004 | 8 | P0 | Finalize dual-channel packaging, updates and commercial operations | REL-001–003 | Free-to-paid install/upgrade/rollback behavior, update ownership, price parity, renewal/support and distribution checks are documented and verified | Not started |

### Task handoff format

When asking ChatGPT, Cursor, Claude, Gemini, or another assistant to work, provide:

- repository and product context;
- exact task ID and phase;
- required reading (`AGENTS.md`, README, this plan, relevant sources);
- scope and explicit out-of-scope items;
- dependencies and acceptance criteria;
- required output files or findings;
- instruction to update status only when the acceptance criteria are met;
- instruction to report sources, assumptions, blockers, and next task.

## Build and download a test package

The `Plugin CI and installable build` GitHub Actions workflow runs on pushes, pull requests and manual dispatch. On GitHub, open **Actions → Plugin CI and installable build → Run workflow**, select `main`, and open the successful run's `installable-plugin-v<version>` artifact. Extract the downloaded artifact archive to obtain the installable plugin ZIP and its SHA-256 checksum file. Upload the inner versioned plugin ZIP through **WordPress Admin → Plugins → Add Plugin → Upload Plugin**.

The workflow derives its package version from the main plugin header, generates Composer's optimized PSR-4 autoloader, runs validation/lint/pure comparison checks, validates ZIP integrity and expected package paths, then uploads the artifact for 30 days. Run #84 passed on 2026-11-07 and produced v0.2.0 artifact `13574267676`. This workflow packages a test build; it does not publish a marketplace release or add a product CLI.

## 10. Decisions and open questions

### Recorded decisions

- **D-001:** Position as checkout change-impact regression comparison across saved scenarios.
- **D-002:** The repository starts in pre-development planning; research and requirements precede full implementation.
- **D-003:** Use React for the planned admin interface.
- **D-004:** No product CLI or CLI commands before final release.
- **D-005:** PHP, WordPress, WooCommerce, WPML, and Polylang compatibility must be finalized as part of the plan before implementation scope is locked.
- **D-006:** README is the phase/status/progress/date tracker; this document contains detailed scope and backlog.
- **D-007:** Research Cart Lookout and ShopMonitor as initial competitors; verify current behavior before making claims.
- **D-008:** Evaluate shipping company APIs and payment integrations through an evidence-based compatibility and safety matrix.
- **D-009:** Competitor review shows that checkout testing, update awareness, and diagnostics already exist in the market. The product's multi-scenario before/after value-diff positioning is a hypothesis to validate, not a verified exclusive capability.
- **D-010:** Keep `AGENTS.md` canonical and provide tool-specific entry points. ChatGPT's general GitHub-connected chat is treated as manual-read unless the session demonstrably loads the repository rules.
- **D-011:** Target a free edition on WordPress.org and a paid edition on the WooCommerce.com Marketplace; marketplace submissions and vendor approval remain future release gates.
- **D-012:** Keep channel-specific packages and update ownership explicit. The WordPress.org edition must not behave as trialware or download/install the paid package from an external server; validate the free/paid split with both marketplaces before release.
- **D-013:** Close Phase 6 as a no-go/support-exclusion decision because runtime proof could not be run. No automatic checkout replay or checkout integration is supported until an exact matrix entry is proven and Phase 6 is reopened; this closure is not a runtime pass.
- **D-014:** The initial admin shell uses WordPress-bundled React (`wp.element`) and `wp-i18n` without a separate build tool or CLI. This is a source-level implementation choice, not a compatibility claim; verify it against the exact supported WordPress matrix before release.
- **D-015:** Accept versioned per-site custom tables for growing relational scenario/run history, small non-autoloaded options for configuration, no network-wide multisite support in the initial scope, 30-day default run retention configurable from 7–365 days, and preserve-on-uninstall unless an explicit erase option is selected. This is a planning decision; justify custom tables against post meta in implementation review, and keep implementation blocked until exact-version isolated migration/privacy runtime evidence passes.

- **D-016:** Monetary scenario expectations must be transmitted and stored as exact non-negative decimal strings (up to 16 integer and 6 fractional digits); reject floating-point JSON values and scientific notation to avoid precision ambiguity. Currency-specific decimal-scale validation remains part of adapter/domain normalization.

### Open questions for discovery

- Do any researched products already offer explicit multi-scenario baseline value diffs, even if not documented on their public pages?
- Which change types can be detected reliably across plugin updates, WooCommerce settings, and third-party extension settings?
- How can a scenario execute safely for classic checkout and Checkout Blocks without a real payment, customer email, order, or inventory mutation?
- Which state should be normalized and compared, and how should expected changes be approved?
- Which store configurations and integrations are in the initial supported release?
- Should test execution run on demand, after a detected change, on a schedule, or through a combination?
- What evidence may be retained and for how long?
- What severity/confidence model is useful without producing alert fatigue?
- Which shipping providers have official APIs and test environments that can be used safely?
- What requirements differ when WPML or Polylang changes language, currency, or localized checkout presentation?
- Which license and pricing packaging decisions belong to this add-on versus WPEssential, and do hosted runner costs support the proposed $119/year paid tier?
- Will both marketplaces approve the proposed free WordPress.org / paid WooCommerce.com presentation, package identity, paid add-on and update ownership?

## 11. Change control

- Add new scope only with an ID, user need, owning system, priority, dependencies, risk, and acceptance criteria.
- Update this plan and README when decisions or statuses change.
- Mark a phase complete only when its exit criteria have evidence.
- Keep dates honest: use TBD until estimated, then record actual dates when work starts/ends.
- Record significant technical decisions with context, alternatives considered, and consequences.
