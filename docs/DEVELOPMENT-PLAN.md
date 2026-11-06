# Development plan

**Product:** WPEssential Checkout Impact Regression  
**Repository:** `Vertex-Systems-Network/wpessential-checkout-impact-regression`  
**Stage:** Pre-development planning  
**Plan last updated:** 2026-11-06

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

**Compatibility boundary:** Proposed minimums are planning targets only. The plugin has not been implemented or tested. Classic checkout, Checkout Blocks, HPOS, multilingual integrations and exact version combinations must each pass the specified tests before any compatibility claim. Safe checkout replay remains a Phase 6 technical proof blocker.

**Exit criteria met:** each requirement has an ID, rationale, priority, dependencies, and acceptance criteria; version claims cite official sources and are explicitly marked proposed/untested; the test strategy and unsupported-case behavior are defined.

### Phase 3 — Options bank and system architecture

**Status:** Complete for planning deliverables; 2026-11-06.

**Completed evidence:** The [options bank](research/OPTIONS-BANK.md) now uses a single SYS owner ID for every candidate; OI-16 is explicitly deferred with no owner. The [system inventory](architecture/SYSTEM-INVENTORY.md) remains the component responsibility map. [Proposed architecture](architecture/ARCHITECTURE.md) adds the option-to-owner crosswalk, two data-flow diagrams, conceptual domain records, boundaries, failure behavior, proposed decision records and a validation plan. The [shipping/payment integration matrix](architecture/INTEGRATION-MATRIX.md) records official API capabilities, authentication/onboarding, test environments, constraints and priority for WooCommerce's native interfaces and candidate carriers/gateways. ARCH-001 through ARCH-003 are complete for planning.

**Limits:** This is a source-linked desk study and architecture proposal. No carrier/gateway integration or checkout replay has been implemented or tested. Rate limits, regional eligibility and sandbox fidelity remain provider-specific where the official sources do not settle them. Safe execution remains a Phase 6 proof gate.

**Exit criteria met:** accepted/candidate options have one accountable owner; data inputs, conceptual state, permissions, retention, events and failures are mapped; integration facts cite official sources; critical unknowns have explicit validation tasks.

### Phase 4 — Checkout scenario and comparison design

**Status:** Complete for design deliverables; 2026-11-06.

**Completed evidence:** [Scenario and comparison specification](design/SCENARIO-AND-COMPARISON-SPEC.md) defines the conceptual scenario fields and versions, immutable baseline rules/context binding, typed observations, exact money representation, comparability constraints, run/field/finding states, deterministic V-01–V-07 vectors, a sample impact report, and the Phase 6 safe-run proof criteria. The schema covers shipping, payment availability, coupon/discount, tax/fees/totals, controls and unsupported/inconclusive cases.

**Phase boundary:** Phase 4 finalizes comparison semantics and expected vectors; it does not claim executable or runtime-tested behavior. WooCommerce's Store API checkout GET creates a draft order, and POST processes order/payment. Cart API operations can mutate session/cart state. The spec therefore forbids treating checkout endpoints as read-only probes and leaves actual safe replay to Phase 6 SPIKE-001. SCN-001 through SCN-003 are complete as design work.

**Exit criteria met:** representative expected outcomes and comparator behavior are specified deterministically; context mismatch and missing/unsupported data do not become false green results; report examples and safety boundaries are documented. Executable replay proof remains a later technical gate, not a Phase 4 completion claim.

### Phase 5 — UX and React-based admin interface

**Status:** Complete for UX/prototype deliverables; 2026-11-06.

**Completed evidence:** [Admin UX and React interface specification](design/ADMIN-UX-SPEC.md) defines information architecture, first-use/scenario/baseline/report/history/settings journeys, screen hierarchy, WordPress React component map, server/client responsibility boundary, visual language, responsive behavior and loading/empty/error/partial/unsupported/blocked states. The [interactive admin concept prototype](design/admin-interface-prototype.html) provides Overview, Scenario list, a four-step scenario builder, impact detail, Runs & history, and Settings screens with fictional data and prototype-only actions.

**React boundary:** The HTML prototype is a visual/interaction review artifact, not product code. The production interface remains React within wp-admin using WordPress runtime/components; PHP remains authoritative for permissions and comparison behavior. Accessibility criteria are designed but have not been tested with assistive technology. Runtime React implementation and verification remain Phase 7.

**Exit criteria met for design:** users can understand an impact difference through expected/current values and evidence without reading raw logs; keyboard, responsive, loading/empty/error/unsupported states and component ownership are specified. No screen implies safe checkout replay or live payment.

### Phase 6 — Pre-development technical proof

**Status:** Not started

**Deliverables:** small technical spikes for the highest-risk WordPress/WooCommerce hooks, checkout modes, safe execution, normalization, and extension points; implementation-ready specifications.

**Exit criteria:** architecture risks are validated or explicitly accepted; no unresolved blocker is hidden by assumptions; implementation backlog is sequenced.

### Phase 7 — Plugin implementation and verification

**Status:** Not started

**Deliverables:** implementation in approved slices; automated/manual verification artifacts; compatibility results; security and performance evidence; docs.

**Exit criteria:** agreed acceptance criteria pass for the supported matrix; unsupported cases are surfaced safely; no CLI is added before final release.

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
| REQ-003 | 2 | P0 | Define privacy, permissions, retention, and safe test constraints | REQ-001 | Data inventory, access model, retention, redaction, and no-real-charge constraints have acceptance criteria | Complete (2026-11-06; safe-run proof remains Phase 6) |
| ARCH-001 | 3 | P0 | Map WooCommerce shipping/payment extension and carrier API options | REQ-002, PLAN-005 | Official docs and sandbox/API facts are recorded; region, credential, rate-limit, and dependency data included | Complete (2026-11-06; integration matrix; provider support untested) |
| ARCH-002 | 3 | P0 | Propose end-to-end architecture and data flow | PLAN-004, PLAN-005, REQ-001–003 | Diagram covers change trigger through report, data stores, permissions, retries, redaction, and failure paths | Complete (2026-11-06; proposed architecture; technical proof pending) |
| ARCH-003 | 3 | P0 | Define baseline, normalized result, and diff schemas | ARCH-002 | Schemas distinguish missing, changed, unchanged, unsupported, flaky, and inconclusive values with versioning rules | Complete (2026-11-06; conceptual contracts; Phase 4 schema design pending) |
| SCN-001 | 4 | P0 | Specify scenario inputs, versions and baseline context binding | REQ-001, ARCH-002 | Schema captures synthetic cart/destination/locale/currency/tax/checkout mode and validates immutable scenario/baseline rules | Complete (2026-11-06; scenario spec) |
| SCN-002 | 4 | P0 | Define normalized observations, comparability and field diffs | SCN-001, ARCH-003 | Money, shipping, payment, coupon, tax and validation fields have normalization, context matching and explicit missing/unsupported states | Complete (2026-11-06; scenario spec; no runtime claim) |
| SCN-003 | 4 | P0 | Specify impact report vectors and safe-run boundary | SCN-002, REQ-003 | Deterministic expected vectors cover region shipping, price shift, unchanged control, coupon, partial, unsupported and context mismatch; safe-run proof is assigned to SPIKE-001 | Complete (2026-11-06; design examples; replay untested) |
| SPIKE-001 | 6 | P0 | Validate safe checkout replay for supported checkout modes | REQ-002, REQ-003, ARCH-001–003 | Evidence proves or rejects safe repeatable scenarios without real charges or unintended side effects | Not started |
| UX-001 | 5 | P1 | Define admin information architecture and interactive scenario/report prototype | REQ-001, PLAN-004, SCN-001–003 | Prototype covers setup, market/cart, expected values, before/after impact, evidence, history and prototype-only safety blocks; React component/API ownership is documented | Complete (2026-11-06; design artifact only) |
| UX-002 | 5 | P1 | Specify accessible interaction and async/error/unsupported states | UX-001, REQ-008 | Keyboard/focus, labels, announcements, contrast, responsive behavior and empty/loading/error/partial/blocked/unsupported states are explicitly designed | Complete (2026-11-06; not assistive-technology tested) |
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
