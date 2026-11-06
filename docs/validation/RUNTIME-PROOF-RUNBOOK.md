# Phase 6 — Runtime proof runbook and evidence worksheet

**Status:** Operator worksheet prepared; no runtime test has been run. Phase 6 is formally closed as a no-go for runtime support, not as a pass.  
**Task:** SPIKE-008  
**Date prepared:** 2026-11-06  
**Use with:** [Safe checkout proof protocol](PREDEVELOPMENT-SAFE-CHECKOUT-PROOF.md), [adapter spike](STORE-API-OBSERVATION-ADAPTER-SPIKE.md), and [canonicalization spike](STORE-API-CANONICALIZATION-SPIKE.md).

This document is a paper/evidence worksheet for a store operator with an isolated disposable WooCommerce staging clone. It contains no executable commands and does not authorize testing on a live store.

## 0. Environment provisioning gate

Do not begin runtime proof until every item below is true. This is a provisioning checklist, not a request to place credentials or customer data in this repository or chat.

| Gate | Required evidence before test |
|---|---|
| Disposable target | A staging clone or isolated test store that can be reset without affecting production |
| Access path | Authorized operator can install and inspect WordPress/WooCommerce and the exact checkout extensions; secrets remain in the operator's secret manager |
| Data safety | Synthetic products, addresses, coupons, and customer identity only; no copied live customer/order data |
| Payment safety | Test gateway credentials only; no live payment credentials or charge path |
| External effects | Email, webhooks, fulfillment, subscriptions, analytics, and carrier booking are disabled or routed to safe sinks |
| Recovery | Snapshot/backup and a tested reset procedure exist before the first case |
| Version lock | Exact PHP, WordPress, WooCommerce, checkout mode, theme, gateway, shipping, WPML/WCML or Polylang versions are recorded |
| Evidence | A reviewer can inspect redacted request/response and side-effect deltas without secrets or personal data |

If any gate fails, stop and record the matrix entry as **blocked**, not passed. Do not use a live store as a substitute.

**Environment finding for this planning session:** the workspace has no local PHP, Docker, WP-CLI, or WordPress project, and no WooCommerce staging store was supplied. A temporary WPRun sandbox booted WordPress 6.9.1 on PHP 8.4, but its installer marked WooCommerce 11.2.2 incompatible. The local plugin ZIP could not be transferred through the browser file picker, so plugin installation/activation and all test cases remain unexecuted. This admin boot is not runtime evidence for the plugin. A separate WordPress Playground attempt stopped at browser verification before WordPress loaded. Phase 6 remains closed under the no-go/support-exclusion decision; this checklist does not count as a runtime pass.

## 1. Run identity

| Field | Value |
|---|---|
| Run ID | |
| Operator / reviewer | |
| Test date and timezone | |
| Ticket or change reference | |
| Purpose | Safe checkout capture proof |
| Staging site identifier (non-secret) | |
| Disposable/reset owner | |
| Evidence folder/reference (redacted) | |

## 2. Exact environment fingerprint

Record exact versions, not “latest”. One row represents one test matrix entry.

| Component | Exact version / setting | Evidence source | Notes |
|---|---|---|---|
| PHP | | | |
| WordPress | | | |
| WooCommerce | | | |
| Active theme | | | |
| Checkout mode: Classic or Blocks | | | |
| HPOS or legacy order storage | | | |
| Database family/version | | | |
| Currency and minor-unit precision | | | |
| WPML/WCML or Polylang/add-on | | | |
| Shipping extensions and versions | | | |
| Payment gateways and versions | | | |
| Other checkout/cart extensions | | | |
| Cache/CDN/loopback configuration | | | |

Do not combine results from different checkout modes or materially different gateway/extension combinations into one “pass”.

## 3. Mandatory preflight — stop if any row is not PASS

| Gate | Required state | Result (PASS/FAIL) | Evidence / note |
|---|---|---|---|
| Environment | Disposable non-production clone; reset/destroy path assigned | | |
| Identity data | Synthetic products, addresses, customer and coupon only | | |
| Payment | No live credentials; sandbox or processing disabled | | |
| Email | Outbound mail disabled or proven controlled sink | | |
| Webhooks/fulfillment | Disabled or isolated sinks; no live label purchase | | |
| Analytics/CRM/ERP | External delivery disabled or test sinks | | |
| Inventory/subscriptions | Baseline values snapshotted; no real fulfillment | | |
| Scheduler | Action Scheduler / WP-Cron status known; failures visible | | |
| Access | Test operator has least-privilege staging account | | |
| Browser/session | Fresh synthetic session/token; no shopper cookies | | |
| Recovery | Snapshot/restore procedure tested before checkout probes | | |

**Preflight decision:** ☐ Continue  ☐ Stop and mark unsupported  
**Reviewer and timestamp:** ______________________________

## 4. Before-state side-effect snapshot

Capture values immediately before each isolated test batch. Keep raw evidence inside the staging environment; attach only redacted extracts.

| State surface | Before value / count | Capture location | Redacted evidence reference |
|---|---|---|---|
| Orders: checkout-draft | | | |
| Orders: pending/processing/completed/failed/other | | | |
| Gateway sandbox transactions | | | |
| Product stock/backorders/reservations | | | |
| Coupon usage counts | | | |
| WooCommerce email sink | | | |
| Webhook / Action Scheduler queue | | | |
| Analytics / subscriptions / fulfillment | | | |
| Shipping labels / carrier actions | | | |
| Cart token/session state | | | |

## 5. Scenario capture worksheet

Run each case once per checkout mode and environment matrix entry. Never click Place Order and never submit checkout POST.

| Case ID | Scenario / action | Observed response and canonical fields | State changed? (Y/N) | Error/uncertainty | Evidence reference |
|---|---|---|---|---|---|
| CART-01 | Two distinct Cart Tokens, same synthetic cart | | | | |
| CART-02 | Add item, set destination, collect rates/totals | | | | |
| CART-03 | Apply coupon; record discount and usage counter | | | | |
| CART-04 | Blocks checkout payment availability; no Place Order | | | | |
| CART-05 | Classic checkout review update; no Place Order | | | | |
| CART-06 | Invalid/expired/cross-run token fails closed | | | | |
| P-03 | Checkout data GET; record response order ID/status and session | | | | |
| NORM-01 | Amount strings and currency precision | | | | |
| NORM-02 | Same numeric amount, changed currency/precision | | | | |
| NORM-03 | Empty rates while shipping calculation incomplete | | | | |
| NORM-04 | Same rate identity, translated display label changed | | | | |
| NORM-05 | Multiple discounts/fees and coupon attribution | | | | |
| NORM-06 | Unknown extension fields/address redaction | | | | |

For each case, record the WooCommerce version-specific checkout GET path. New-session and existing failed-payment/retry states must not be conflated. If the case unexpectedly reaches order/payment processing or invokes a non-isolated integration, stop immediately and fail that matrix entry.

## 6. Before/after impact vector execution matrix

These cases translate design vectors V-01–V-07 from the [scenario and comparison specification](../design/SCENARIO-AND-COMPARISON-SPEC.md) into operator evidence. They validate expected interpretation; they do not mean the product runner exists or has produced these results. Record the same exact environment fingerprint and side-effect snapshots for baseline and current observations.

| Case | Baseline → current setup | Required observations | Expected classification | Safety / stop condition |
|---|---|---|---|---|
| V-01 Germany shipping disappearance | DE/EUR physical cart with a known rate → same cart, destination, currency and checkout mode after the controlled shipping configuration change | Complete rate collection; stable method/instance/rate IDs; package fingerprint; tax and currency context | Regression candidate only when a completed supported capture omits the baseline method | If rate collection is incomplete or service fails, mark inconclusive; never call it a missing method |
| V-02 UK shipping price change | GB/GBP same package and method at GBP 7.00 → GBP 12.00 | Exact amount, currency/scale, rate identity, tax inclusion and package context | Regression candidate; primary delta GBP 5.00 | If identity, package, tax mode or currency differs, mark context mismatch |
| V-03 US unchanged control | US/USD baseline with expected method and totals → same scenario after the same controlled change window | All asserted fields captured completely in both runs | Unchanged only when every monitored field compares equal | Missing fields or unsupported capture cannot count as unchanged |
| V-04 Coupon discount change | Eligible USD 100.00 subtotal with coupon WELCOME10 at 10% → same context with observed USD 5.00 discount | Coupon eligibility, type, amount, tax, subtotal, fees, shipping and total | Regression candidate if coupon effect changes in a complete comparable capture | Do not infer coupon regression from total alone |
| V-05 Partial collection | Supported baseline includes shipping and payment observations → current run has complete shipping data but payment adapter returns a controlled timeout | Per-field collection state and timeout evidence | Partial run; payment is inconclusive/collection error | Do not claim a gateway is unavailable from timeout |
| V-06 Unsupported checkout | Classic baseline → current environment uses an unvalidated third-party checkout mode | Capability/mode detection and supported adapter list | Blocked or partial; checkout mode unsupported | Do not issue a green result or attempt an alternate checkout submission |
| V-07 Context mismatch | GBP/tax-inclusive approved baseline → EUR or tax-exclusive current capture | Currency, precision, tax-display mode and environment fingerprints | Inconclusive/context mismatch; no arithmetic comparison | Never convert or normalize across currencies to force a match |

For V-05, use a controlled test harness or isolated fault injection only after the adapter exists; do not impair a real payment gateway. For V-01/V-02/V-04, use reversible test configuration changes in the disposable store and snapshot/restore between cases. Do not run extension updates that trigger external services unless those services are disabled or safely stubbed.

For every vector, preserve a per-field expected/current table, evidence reference, exact change event, repeatability result, and after-state cleanup audit. The resulting operator evidence can validate WooCommerce observations and safety; validating the product’s regression classification still requires an implemented runner and diff engine in Phase 7.

## 6. Repeatability

| Check | First observation | Repeat observation | Match? | Notes |
|---|---|---|---|---|
| Cart/totals | | | | |
| Shipping method identity and cost | | | | |
| Coupon state and discount | | | | |
| Checkout-visible payment methods | | | | |
| Context/environment fingerprint | | | | |
| Side-effect deltas | | | | |

A mismatch that cannot be explained by an explicit environment/context change is flaky or inconclusive, not unchanged.

## 7. After-state side-effect audit

Use the same sources and scopes as the before-state snapshot.

| State surface | Before | After | Delta explained? | Residual artifact / cleanup evidence |
|---|---|---|---|---|
| Orders: checkout-draft | | | | |
| Orders: pending/processing/completed/failed/other | | | | |
| Gateway sandbox transactions | | | | |
| Product stock/backorders/reservations | | | | |
| Coupon usage counts | | | | |
| WooCommerce email sink | | | | |
| Webhook / Action Scheduler queue | | | | |
| Analytics / subscriptions / fulfillment | | | | |
| Shipping labels / carrier actions | | | | |
| Cart token/session state | | | | |

Cleanup is not successful until every run-created artifact is attributed and either removed or retained under an explicit, safe documented policy. No external side effect may be waived because the order was only a test.

## 8. Matrix decision and sign-off

| Criterion | PASS/FAIL | Evidence |
|---|---|---|
| No live payment credential or real charge | | |
| No Place Order or checkout POST | | |
| No non-draft order created | | |
| No email escaped controlled sink | | |
| No unplanned inventory/coupon/webhook/analytics/fulfillment effect | | |
| Any persisted draft is attributable and cleanup is exact-version proven | | |
| Repeated results are deterministic for the same context | | |
| Redaction, permissions and evidence retention are acceptable | | |
| Run is recoverable/cancellable and reports queue delays correctly | | |

**Matrix entry:** ☐ PASS  ☐ FAIL / UNSUPPORTED  ☐ INCONCLUSIVE  
**Supported exact mode/version/extensions:** ______________________________  
**Known exclusions:** ______________________________  
**Operator:** ____________________  **Date:** __________  
**Independent reviewer:** ____________________  **Date:** __________

A PASS is limited to the exact recorded environment and test cases. It is not a blanket WooCommerce, gateway, carrier, WPML, Polylang or marketplace compatibility claim.

## 9. Current execution status

- Worksheet: prepared.
- Temporary WordPress admin runtime: WPRun WordPress 6.9.1 / PHP 8.4 booted.
- WooCommerce: not installed; the installer marked the available 11.2.2 release incompatible with WordPress 6.9.1.
- Plugin ZIP: present locally in the workspace, but the browser file picker did not expose a usable local-file transfer, so remote installation/activation did not complete.
- Local runtime: PHP, WordPress, WP-CLI, Docker, database binaries and cached install packages are absent. An apt package-index attempt failed in the managed environment before dependencies could be acquired.
- Plugin activation, admin UI behavior, capability cases, checkout cases, side-effect audit and cleanup: not executed.
- Browser Playground: blocked at browser verification before WordPress loaded.
- User-provided `/el-test/` site: the login route presented an invisible reCAPTCHA robot-verification challenge before the login form; credentials were not submitted and the challenge was not solved or bypassed.
- Phase 6: closed as an explicit no-go/support-exclusion decision; no runtime pass is claimed. Read [closure decision](PHASE-6-CLOSURE-DECISION.md).
