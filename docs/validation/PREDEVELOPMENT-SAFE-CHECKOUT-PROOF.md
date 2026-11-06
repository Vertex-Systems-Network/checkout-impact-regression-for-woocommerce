# Phase 6 — Safe checkout proof protocol

**Status:** Protocol defined; runtime proof blocked pending an isolated WooCommerce staging store.  
**Date:** 2026-11-06  
**Evidence class:** Official documentation review and test-plan design only. No plugin code or checkout scenario has been executed.

## Purpose

Decide, for each exact checkout mode and extension combination, whether the product can observe saved checkout scenarios without causing an unsafe customer, payment, order, inventory, email, webhook, analytics, subscription, or fulfillment effect.

This document is a proof protocol, not evidence that replay is safe. A documented test plan does not count as a successful runtime spike.

## Verified WooCommerce behavior that affects the design

- The Store API checkout **GET** returns checkout data and creates a draft order with the `checkout-draft` status. It is not a read-only probe.
- Checkout **PUT** updates checkout state and may recalculate totals; treat it as a mutation.
- Checkout **POST** processes checkout and may process an order/payment. It is prohibited in this proof.
- Checkout Blocks invoke checkout processing when a shopper places an order; payment method availability is extension-specific and can depend on cart, totals, address and other runtime conditions.
- WooCommerce's testing guidance warns that test orders may still affect emails, analytics, extensions and other integrations; staging and explicit side-effect controls are required.
- The reviewed public docs do not establish a current cleanup guarantee for draft orders. The spike must observe and prove cleanup for the exact tested version; do not rely on an assumed time-to-live.

Sources: [Store API Checkout](https://developer.woocommerce.com/docs/apis/store-api/resources-endpoints/checkout/), [How the Checkout block processes an order](https://developer.woocommerce.com/docs/block-development/getting-started/how-checkout-block-processes-an-order/), [Payment method integration for Checkout Blocks](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/checkout-payment-methods/payment-method-integration/), [Testing orders](https://woocommerce.com/document/testing-orders/), [WooCommerce update guide](https://woocommerce.com/document/update-woocommerce/).

## Mandatory isolated test environment

Do not begin runtime probing until the test owner records all of the following:

- Disposable, non-production WordPress/WooCommerce clone with an exact environment fingerprint: PHP, WordPress, WooCommerce, database, theme, checkout mode, HPOS mode and active extension versions.
- Synthetic products, addresses, customer identity and coupon data. No production shopper records.
- No live payment credentials. Use a gateway sandbox or disable payment processing entirely; never submit Place Order or call checkout POST.
- Outbound email disabled or routed to a controlled sink. Verify the sink before each run.
- Webhooks, fulfillment, label purchase, subscriptions, analytics forwarding, CRM/ERP sync and other external integrations disabled or routed to isolated test sinks.
- Known initial snapshots for orders (drafts separately from all other statuses), gateway transactions, stock, coupon usage counts, Action Scheduler tasks, email sink, webhook sink, analytics sink, subscriptions and fulfillment.
- Reset or destroy procedure after the experiment, with an owner and evidence that the reset completed.

If any integration cannot be disabled or contained, the checkout/integration combination is unsupported for automatic replay.

## Probe matrix

Run each row separately for Classic shortcode checkout and Checkout Blocks. Third-party/custom checkout is a separate unsupported target until explicitly added and proven.

| ID | Probe | What to capture | Mutation / stop rule |
|---|---|---|---|
| P-01 | Load cart and update a synthetic destination | Shipping packages/rates, totals, taxes, payment availability, network requests | Cart/session state may mutate. Stop if external or persistent effects escape the test boundary. |
| P-02 | Apply a synthetic coupon | Eligibility, discount, coupon usage count, totals | Stop if usage count or external state changes unexpectedly; restore only from verified disposable snapshot. |
| P-03 | Load checkout page / Checkout Store API data | Request paths, draft order IDs/status, observed checkout fields | Checkout GET may create a draft. Record each draft artifact; do not call PUT or POST. |
| P-04 | Repeat identical observation | Field-level output and side-effect snapshots | Require stable observations or classify the capture as flaky/inconclusive. |
| P-05 | Cleanup and reset | Remaining drafts, jobs, email/webhook/analytics sinks, stock and counters | Cleanup is proven only when all recorded artifacts are gone or explicitly retained with safe documented semantics. |

Never click Place Order, call checkout POST, capture/void/refund a payment, buy a label, or run against a live store. A draft order is a persistent test artifact, even when it is not a placed order; it must be counted and its cleanup verified. If the product's proposed runner cannot reliably identify and clean up its own draft artifacts, checkout GET is not an acceptable observation mechanism.

## Evidence checklist per run

Record a run ID and before/after evidence for:

1. Environment and capability fingerprint.
2. Draft orders, separately from pending, processing, completed, failed, cancelled or refunded orders.
3. Gateway sandbox/transaction log and proof that no live gateway was configured.
4. Product stock, backorders and reservation state.
5. Coupon usage counters.
6. WooCommerce emails and controlled SMTP sink.
7. Webhook deliveries and Action Scheduler jobs.
8. Analytics, subscriptions, fulfillment and shipping-label systems.
9. Cart/session/customer state left behind.
10. Cleanup actions, remaining artifacts, reset result and reviewer.

Keep raw logs redacted; store no card data, secrets or real personal data in the evidence package.

## Pass / fail criteria

A specific checkout-mode + extension-matrix entry passes only when repeated runs demonstrate all of the following:

- Same environment and scenario context produce deterministic normalized observations.
- No real charge or live gateway transaction occurs.
- No non-draft order is created and no Place Order / checkout POST path is used.
- No email escapes the controlled sink.
- No unintended stock, coupon counter, webhook, scheduled action, analytics, subscription, fulfillment, label, or external customer-state effect remains.
- Any checkout-draft artifact is counted, attributable to the run, and reliably cleaned up; cleanup behavior is verified on the exact WooCommerce version.
- The environment can be restored and a second run produces the same result.
- Every assertion has retained evidence and a reviewer.

Any failed assertion, unknown side effect, non-repeatable result, unavailable sink, or unverified cleanup means **Fail / Unsupported** for that matrix entry. Do not soften a failed safety gate into “best effort”.

## Current execution record

| Item | Status | Evidence |
|---|---|---|
| Official behavior research | Complete (desk research) | Sources listed above; checked 2026-11-06 |
| Safe-run protocol | Complete (design) | This document |
| Disposable staging environment | Blocked / not provided | No store connection or environment credentials available in this work session |
| Classic checkout runtime proof | Not run | Requires isolated staging environment |
| Checkout Blocks runtime proof | Not run | Requires isolated staging environment |
| Draft cleanup proof | Not run | Current public docs reviewed do not establish a cleanup guarantee |
| Plugin implementation | Not started | Phase 7 remains out of scope |
| CLI | Not added | Explicitly deferred until final release decision |

## Next action

Provide access to a disposable staging clone or have a store operator execute this protocol and share redacted evidence. Then complete SPIKE-001 by recording exact pass/fail results per supported mode and gateway/extension class. Until then, automatic checkout replay remains blocked; product behavior must not be represented as runtime-validated.
