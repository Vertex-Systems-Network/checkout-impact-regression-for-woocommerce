# Candidate system inventory

**Status:** Phase 1 research artifact; boundaries are a proposed design, not approved implementation architecture.  
**Last reviewed:** 2026-11-06  
**Options source:** [Options bank](../research/OPTIONS-BANK.md)

## System map and ownership

| ID | System | Single responsibility | Owns | Inputs → outputs | Dependencies | Key failure mode |
|---|---|---|---|---|---|---|
| SYS-01 | Scenario Catalog | Store reusable, validated customer/cart journey definitions | Scenario IDs, versions, normalized inputs and metadata | Admin scenario form → immutable scenario version | | WooCommerce products/cart API; admin capability checks | Invalid or stale scenario input; sensitive identity data stored.
| SYS-02 | Baseline Registry | Preserve approved expected results per scenario | Baseline ID/version, normalized expectation, approval record | Accepted comparison result + authorized approval → immutable baseline version | | SYS-01; authorized user action | Silent baseline overwrite or baseline for mismatched environment.
| SYS-03 | Change Context Collector | Record supported updates/settings-change events for correlation | Normalized event records and source provenance | WordPress/WooCommerce events → timestamped change context | | WordPress/WooCommerce hooks and selected plugin APIs | Change event missed; secret setting value captured; timing misattributed.
| SYS-04 | Run Orchestrator | Decide what scenario version runs, when, and with safe/idempotent scheduling | Run lifecycle state, idempotency keys, retry policy | Trigger + scenario reference → run jobs/status | | SYS-01, SYS-03, SYS-12, SYS-13; Action Scheduler/WP-Cron decision | Duplicate, delayed, or unsupported run executes.
| SYS-05 | Execution Adapter | Execute one scenario through an approved safe test mechanism | Temporary execution context only | Scenario + capability profile → raw checkout observations/errors | | SYS-01, SYS-04, SYS-12, SYS-13; validated test mode | Live payment or unintended side effects; environment flakiness.
| SYS-06 | Observation Adapter Registry | Collect values exposed by WooCommerce core and explicitly supported extensions | Adapter contracts/version support; no baseline ownership | Raw execution result or supported read-only path → typed observations with source | | SYS-05; documented WooCommerce/extension APIs | Inconsistent adapter semantics or missing/partial fields.
| SYS-07 | Normalizer & Diff Engine | Normalize compatible observations and calculate deterministic deltas | Normalization rules and diff schema versions | Baseline + current typed observations → field-level differences | | SYS-02, SYS-06; versioned currency/tax semantics | False comparison across incompatible contexts.
| SYS-08 | Impact Classifier | Apply explicit policy to results without confusing correlation with causation | Classification rules and confidence/reason codes | Diff + run health + change context → regression candidate/unchanged/expected/flaky/unsupported/inconclusive | | SYS-03, SYS-07; reviewed classification policy | Correlation misreported as causality or unknown marked pass.
| SYS-09 | Evidence Store | Protect and retain the minimal evidence needed to explain/reproduce a result | Evidence references, redaction state and retention metadata | Run/diff outputs → access-controlled redacted evidence | | SYS-04, SYS-07; secure storage and retention policy | Sensitive artifacts exposed or retained beyond policy.
| SYS-10 | Admin Experience | Provide React-based scenario, baseline, report and history workflows | UI state only; calls permission-checked application APIs | Typed service responses → accessible views and user actions | | SYS-01, SYS-02, SYS-08, SYS-09; permission-checked service API | UI state hides errors or sends invalid/unauthorized action.
| SYS-11 | Alert Router | Deliver deduplicated, user-configured notifications | Delivery policy, dedupe keys and delivery status | Material classified finding → approved channel payload | | SYS-08, SYS-09; email/provider policy | Duplicate/noisy alerts or unintended disclosure.
| SYS-12 | Compatibility Registry | Determine supported platform and extension capabilities | Tested compatibility facts and capability declarations | Environment fingerprint → supported/limited/unsupported status | | Platform/version probes and tested compatibility evidence | False support/unsupported result.
| SYS-13 | Privacy & Permission Guard (cross-cutting) | Enforce capability checks, nonces, redaction, secret handling and retention policy | Security/privacy policy | Every request and stored/exported field → allow/deny/redact/audit | | WordPress capabilities/nonces, privacy/export/erasure hooks, secrets policy | Bypass, over-collection, or redaction failure.
| SYS-14 | Reporting/Export (later) | Produce reproducible, minimized support exports | Export schema and redaction policy | Authorized filters → report file | | SYS-09, SYS-13; export schema | Export includes unrelated/personal/secrets data.

## Non-overlapping responsibilities

- **Change event is not a checkout result.** SYS-03 says what changed and when; it cannot assert that the change caused a diff.
- **Scenario is not baseline.** SYS-01 defines test inputs; SYS-02 stores approved expected outputs.
- **Adapter is not classifier.** SYS-06 captures values and provenance; SYS-07 calculates deterministic differences; SYS-08 assigns user-facing meaning.
- **Evidence is not a second result database.** SYS-09 stores minimized artifacts referenced by run/diff records.
- **React UI does not own business rules.** SYS-10 renders service state and sends authorized actions; normalization and severity stay server-side.
- **Carrier API adapters and checkout observation adapters are distinct.** A shipping provider API quote may not equal the method/rate a WooCommerce customer actually sees. Do not substitute one for the other without a product decision.

## Candidate data flow

1. SYS-03 records an available change event, or a user starts a run.
2. SYS-04 resolves the approved scenario version and checks compatibility through SYS-12 and permissions/safety through SYS-13.
3. SYS-05 executes only the validated path. If safe behavior cannot be guaranteed, mark unsupported/inconclusive and do not proceed.
4. SYS-06 returns typed observations with environment, source, and collection status.
5. SYS-07 compares compatible observations with SYS-02 baseline.
6. SYS-08 classifies with confidence and links nearby change events as correlation evidence.
7. SYS-09 stores only permitted, redacted evidence; SYS-10 presents impact per scenario.
8. SYS-11 notifies only according to user policy and deduplication rules.
9. An authorized user may approve an expected change or new baseline through SYS-02; prior baselines remain auditable.

## Shared record contracts (conceptual)

### Scenario version
`scenario_id`, `scenario_version`, `label`, `cart_definition`, `region/address attributes`, `currency`, `coupon/assertions`, `checkout mode`, `created_at`, `created_by`, `privacy flags`.

Do not place real payment credentials in a scenario. Use generated/synthetic identity data where the execution path needs contact fields. Credentials for service connections are separate secrets and must not enter reports.

### Run record
`run_id`, `scenario_id/version`, `trigger_type`, `change_event_ids[]`, `environment fingerprint`, `started_at`, `completed_at`, `status`, `runner_version`, `capability profile`, `result reference`.

### Observation
`field_id`, `value`, `unit/currency/tax context`, `source adapter`, `observed_at`, `collection status`, `redaction metadata`.

### Difference
`baseline_id/version`, `run_id`, `scenario_id/version`, `field_id`, `expected`, `observed`, `delta`, `classification`, `confidence/reason`, `evidence references`.

These are discovery contracts; schema and persistence design wait for platform, privacy and runner validation.

## Failure and safety boundaries

- Change event unavailable: show incomplete history; never imply no change happened.
- Scenario cannot reproduce or adapter missing: classify unsupported/inconclusive, do not compare incompatible values.
- Run timeout or transient environment issue: classify as execution failure/flaky candidate; do not call it a product regression without repeat/evidence.
- Partial data: compare fields only when semantics, currency and tax context match; surface skipped fields.
- Unsafe payment/order side effects: block the run or require a safe test method; never fall back to a live charge.
- Evidence redaction/retention failure: do not deliver evidence externally; report storage/retention issue.
- Duplicate trigger: idempotent run handling; do not multiply test orders or alerts.
- Baseline update: explicit authorized review; never automatic from latest success.
- UI requests: capability/nonce validation and server-side validation apply even if the React UI hides an action.
- External service or carrier API: no secrets in logs/reports; request scope and data flow must be documented before adapter approval.

## Phase 2/3 validation gates

- Confirm core/extension hooks can capture relevant changes without storing secrets or excessive values.
- Determine whether checkout observation requires a real browser, a local safe simulation, or both for distinct findings.
- Prove order/email/webhook/inventory/analytics side effects can be prevented or bounded in the supported runner.
- Verify behavior for classic and block checkout, HPOS, shipping zones, coupons, tax, currency, WPML and Polylang.
- Confirm scheduling behavior and reliability under WP-Cron/Action Scheduler constraints.
- Set retention, access, uninstall, privacy export/erasure, performance limits and support bundle policy.
- Keep UI and implementation out of scope until the necessary decisions have evidence and owners.
