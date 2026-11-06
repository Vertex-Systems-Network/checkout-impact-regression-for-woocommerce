# Phase 6 — Runtime proof runbook and evidence worksheet

**Status:** Operator worksheet prepared; no runtime test has been run.  
**Task:** SPIKE-008  
**Date prepared:** 2026-11-06  
**Use with:** [Safe checkout proof protocol](PREDEVELOPMENT-SAFE-CHECKOUT-PROOF.md), [adapter spike](STORE-API-OBSERVATION-ADAPTER-SPIKE.md), and [canonicalization spike](STORE-API-CANONICALIZATION-SPIKE.md).

This document is a paper/evidence worksheet for a store operator with an isolated disposable WooCommerce staging clone. It contains no executable commands and does not authorize testing on a live store.

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
- Staging environment: not provided.
- Local PHP/WordPress/Docker runtime: unavailable in the current workspace.
- Browser Playground: did not launch because the site requested browser verification.
- Checkout cases, side-effect audit and cleanup: not executed.
- Phase 6: remains in progress; no runtime pass is claimed.
