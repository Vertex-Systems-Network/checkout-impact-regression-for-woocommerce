# Phase 6 — Safe checkout proof protocol

**Status:** Protocol defined; Phase 6 closed as no-go for runtime support. No checkout test ran and no runtime pass is claimed.  
**Date:** 2026-11-06  
**Evidence class:** Official documentation review and test-plan design only. No plugin code or checkout scenario has been executed.

## Purpose

Decide, for each exact checkout mode and extension combination, whether the product can observe saved checkout scenarios without causing an unsafe customer, payment, order, inventory, email, webhook, analytics, subscription, or fulfillment effect.

This document is a proof protocol, not evidence that replay is safe. A documented test plan does not count as a successful runtime spike.

## Verified WooCommerce behavior that affects the design

- **Version-sensitive GET behavior:** the Store API Checkout reference currently says GET returns a persisted `checkout-draft` order. WooCommerce 10.9 release notes say persisted draft creation is deferred later for new sessions; current WooCommerce source returns a no-order response for fresh checkout form interactions and defers draft creation to place-order POST. The public API reference has not caught up with that behavior. A failed-payment retry/session that already has an order can take a different path. Because this project's proposed floor is WooCommerce 10.8+, inspect the exact release tag and verify behavior per patch version; do not generalize 10.9+ behavior to 10.8.
- Checkout **GET** is still not assumed side-effect-free: it can read/update session-associated checkout state or follow a retry path. Treat the endpoint as stateful and do not call it in automatic replay until the pinned version and extension hooks are tested.
- Checkout **PUT** persists checkout fields/session state and may recalculate totals; treat it as a mutation.
- Checkout **POST** processes checkout and may create the draft/order and process payment. It is prohibited in this proof.
- Checkout Blocks invoke checkout processing when a shopper places an order; payment method availability is extension-specific and can depend on cart, totals, address and other runtime conditions.
- WooCommerce's testing guidance warns that test orders may still affect emails, analytics, extensions and other integrations; staging and explicit side-effect controls are required.
- Runtime evidence must record any persisted draft order if one is created through a retry or extension path; no cleanup guarantee is assumed. Verify exact-version cleanup rather than relying on an assumed time-to-live.

Sources: [Store API Checkout reference](https://developer.woocommerce.com/docs/apis/store-api/resources-endpoints/checkout/) (currently stale on new-session draft persistence), [WooCommerce 10.9 release notes](https://developer.woocommerce.com/2026/06/23/woocommerce-10-9/) (draft creation deferred later), [current Checkout route source](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/StoreApi/Routes/V1/Checkout.php), [Checkout Block order processing](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/how-checkout-processes-an-order/), [Payment method integration for Checkout Blocks](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/checkout-payment-methods/payment-method-integration/), [Testing orders](https://woocommerce.com/document/testing-orders/).

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
| P-03 | Load checkout page / Checkout Store API data | Request paths, response `order_id`/status, session changes, any persisted order IDs, observed fields | Run only after exact version pinning in disposable staging. Compare the fresh-session and existing failed-payment/retry path; never assume GET creates or cannot create a persisted order. Do not call PUT or POST. |
| P-04 | Repeat identical observation | Field-level output and side-effect snapshots | Require stable observations or classify the capture as flaky/inconclusive. |
| P-05 | Cleanup and reset | Remaining drafts, jobs, email/webhook/analytics sinks, stock and counters | Cleanup is proven only when all recorded artifacts are gone or explicitly retained with safe documented semantics. |

Never click Place Order, call checkout POST, capture/void/refund a payment, buy a label, or run against a live store. Any persisted draft order is a test artifact and must be counted and its cleanup verified. Even when no persisted order is expected for a fresh session on a newer version, checkout GET remains stateful until exact-version and extension behavior is proven. If the runner cannot reliably identify and clean up artifacts it creates, checkout GET is not an acceptable observation mechanism.

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
| Local runtime availability | Unavailable | PHP, Docker and WP-CLI executables are not installed in this workspace; no local WordPress/WooCommerce runtime can be started here |
| Browser-based Playground | Blocked | WordPress Playground displayed a browser verification challenge after one retry; no WordPress/WooCommerce site started and no verification bypass was attempted |
| Classic checkout runtime proof | Not run | Requires isolated staging environment |
| Checkout Blocks runtime proof | Not run | Requires isolated staging environment |
| Draft/retry-path cleanup proof | Not run | API reference and newer release/source behavior differ; exact-version runtime validation required |
| Plugin implementation | Not started | Phase 7 remains out of scope |
| CLI | Not added | Explicitly deferred until final release decision |

## Source-audit conclusion (desk research)

The checkout API reference and newer release/source behavior disagree about whether a fresh checkout GET persists a draft order. WooCommerce 10.9 release notes describe deferred draft creation for new sessions; the current source route also has a no-order fresh-session response and a separate retry path. This is strong enough to reject a version-agnostic endpoint assumption, but it is not runtime validation and does not establish extension side effects or the exact WooCommerce 10.8 floor behavior.

## Related adapter design

The [Store API observation adapter spike](STORE-API-OBSERVATION-ADAPTER-SPIKE.md) defines the cart-versus-payment capture boundary and adds CART-01 through CART-06 runtime cases. Those cases are required inputs to SPIKE-001; they have not been executed. Use the [runtime proof runbook](RUNTIME-PROOF-RUNBOOK.md) to record environment, preflight, results, side-effect deltas, cleanup and sign-off.

## Phase 6 closure and future reopening

Phase 6 is closed under the [no-go/support-exclusion decision](PHASE-6-CLOSURE-DECISION.md): no automatic checkout replay mode is approved. This is a planning closure, not runtime proof. To support any excluded mode in a later release, provision the isolated environment in the [runtime proof runbook](RUNTIME-PROOF-RUNBOOK.md), execute the exact mode/version matrix, record side-effect and cleanup evidence, and reopen Phase 6 for that entry. Until then, product behavior must not be represented as runtime-validated.
