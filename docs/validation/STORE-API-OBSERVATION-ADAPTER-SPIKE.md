# Phase 6 — Store API observation adapter spike

**Status:** Interface boundary specified from official documentation; runtime behavior not tested.  
**Date:** 2026-11-06  
**Task:** SPIKE-004  
**Evidence class:** Source/documentation review and proposed adapter contract. This is not an implementation or safety pass.

## Finding

The Store API Cart response is a plausible source for cart-level observations: items, coupons, address, shipping rates, and totals are returned together. Cart Tokens can identify a headless cart without relying on browser cookies and can be used with Cart and Checkout routes. A separate token per saved scenario provides a cleaner cart/session boundary than reusing a live shopper session.

A token is **not** a side-effect firewall. Cart mutations still execute WooCommerce and extension logic in that session. The API documents POST operations for cart item changes, coupon apply/remove, customer/address updates, and shipping-rate selection. These operations must be tested in a disposable environment before automatic execution is allowed.

Payment method availability is a different observation. The Cart API contract does not document a payment availability list. Checkout Blocks determines availability through registered payment integrations' **canMakePayment** callbacks, which receive current cart totals, shipping state and addresses; callbacks may be asynchronous. Classic shortcode checkout uses a different frontend/AJAX lifecycle. A cart response alone cannot establish that a customer can see or select a payment option.

## Proposed adapter boundary

| Adapter | Capture responsibility | Execution posture |
|---|---|---|
| CartObservationAdapter | Cart items, coupon state, shipping packages/rates, tax and totals, currency, collection status | Candidate for per-scenario Cart Token session. Uses POST mutations to construct a scenario. Runtime side effects are unproven. |
| BlocksCheckoutObservationAdapter | Checkout-visible payment availability and supported validation signals | Requires exact Blocks checkout page/runtime; inspect registered availability result without submitting Place Order. No support claim until verified against gateway extensions. |
| ClassicCheckoutObservationAdapter | Classic checkout visible shipping/payment state and stable validation signals | Requires classic checkout page and its update-order-review lifecycle. Hooks and third-party checkout customizations must be inventoried per exact version. |
| NormalizedObservationAdapter | Convert adapter-specific values to shared typed shipping/payment/coupon/totals fields | Must retain source adapter/version and collection state; no fallback from missing API fields to “unavailable”. |

Adapters own capture mechanics only. The shared normalizer/comparator owns canonical values and diff rules. If an adapter cannot distinguish missing, not_observed, unsupported, and collection_error, it must not produce a green/unchanged result.

## Candidate isolated scenario flow

1. Create a unique Cart Token in a disposable test environment and associate it with one scenario/run ID.
2. Add only the configured synthetic products/quantities.
3. Set synthetic destination attributes; collect cart response and shipping/tax/total state.
4. Apply the synthetic coupon if requested; collect response and coupon usage state before/after.
5. Select a shipping rate only when selection-dependent totals must be compared; record the mutation.
6. Capture checkout-visible payment methods with the mode-specific adapter. Never submit Place Order or call checkout POST.
7. Tear down/reset the test session and compare all external/persistent side-effect snapshots.

This sequence is a test candidate, not approved production behavior. Cart-token creation, session persistence, add-to-cart hooks, coupon handling, rate selection, gateway availability callbacks, and teardown all need runtime evidence.

## Runtime proof cases added to SPIKE-001

| Case | Scenario | Expected contract |
|---|---|---|
| CART-01 | Two runs with distinct Cart Tokens and same synthetic scenario | State does not cross between tokens; normalized cart observations match when environment is unchanged. |
| CART-02 | Same token: add product, set destination, collect rates/totals | Values match the actual Store API response; no order submission occurs; side effects are inventoried. |
| CART-03 | Apply coupon and compare usage counters | Discount/coupon values are observed; no unexpected counter/usage effect remains after reset. |
| CART-04 | Blocks payment availability after address/cart update | Capture result of gateway availability evaluation; distinguish hidden/unavailable from not evaluated or async failure. |
| CART-05 | Classic checkout payment and shipping update | Capture visible methods/rates after classic update lifecycle; classify custom checkout overrides separately. |
| CART-06 | Invalid, expired, or cross-run Cart Token | Fail closed; do not attach results to the wrong scenario or silently fall back to a live cookie session. |

## Safety and support rules

- Never use one of the store's browser shopper cookies for an automated scenario.
- Never infer payment availability from a cart-only response.
- A Cart Token isolates the session identity only; it does not guarantee that extensions avoid external calls, inventory reservations, coupon effects, analytics or other side effects.
- Every POST cart mutation is recorded with request type, scenario ID, token fingerprint (never raw token), response digest and side-effect audit.
- If a mode-specific checkout surface or gateway callback cannot be observed deterministically, report unsupported or inconclusive; do not approximate with the other adapter.
- Classic and Blocks are separate support declarations. A pass for one does not imply a pass for the other.

## Sources reviewed 2026-11-06

- [WooCommerce Store API Cart](https://developer.woocommerce.com/docs/apis/store-api/resources-endpoints/cart/) — Cart response and POST mutation routes.
- [WooCommerce Cart Tokens](https://developer.woocommerce.com/docs/apis/store-api/cart-tokens/) — token-based Cart/Checkout session identification.
- [WooCommerce Store API](https://developer.woocommerce.com/docs/apis/store-api/) — session-scoped API model and route list.
- [Payment method integration for Checkout Blocks](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/checkout-payment-methods/payment-method-integration/) — canMakePayment inputs and asynchronous availability.
- [Payment Store](https://developer.woocommerce.com/docs/block-development/reference/data-store/payment/) — Blocks payment availability state.
- [Classic checkout AJAX reference](https://woocommerce.github.io/code-reference/classes/WC-AJAX.html) — classic checkout update-order-review path.

## Completion boundary

SPIKE-004 is complete as a documentation-backed adapter-boundary proposal. Runtime safety, token isolation, cart-session teardown, payment availability and extension side effects remain unverified. Do not mark SPIKE-001 or Phase 6 complete based on this artifact.
