# Shipping and payment integration research matrix

**Phase:** 3 — Options bank and system architecture  
**Checked:** 2026-11-06  
**Status:** Official documentation desk research. These are candidates and API facts, not a promise of plugin support or an endorsement of provider popularity. No credentials or API calls were used.

## Integration strategy

1. **Primary source:** inspect the shipping methods and payment gateways WooCommerce actually registers and exposes for the current cart/checkout. Their availability is affected by store zones, rules, extensions, cart state and checkout mode.
2. **Provider API adapters:** consider only when a distinct user need exists. A carrier's direct rating API is not proof of the WooCommerce checkout quote because store rules, packaging, discounts, taxes and extension behavior may differ.
3. **Safe test boundary:** use provider sandbox/test environments only for separately approved integration tests. A provider sandbox does not prove that replaying the merchant's full checkout cannot create store-side orders, emails, inventory changes, webhooks, analytics, or fulfillment effects.
4. **Priority:** P0 is observe WooCommerce registered outcomes; P2 is provider-specific API research after user validation. No direct carrier/gateway API integration enters MVP by default.

## Shipping integration candidates

| Candidate | Official capability / regions | Auth, environment and limits | Product use / data flow | Priority and unresolved items |
|---|---|---|---|---|
| WooCommerce Shipping Method API | Extensions register shipping methods and add rates through `calculate_shipping()`; region/service behavior belongs to each method and its zone/rules. | Merchant's extension credentials/config may be provider-specific. WooCommerce API docs define the adapter interface; no common external sandbox/rate limit. | **Primary observation:** read method ID/label/rate/cost actually returned for a scenario. Avoid sending address/cart data outside the store unless a provider extension already does so. | **P0 core.** Need per-method/extension adapter coverage and classic/Blocks validation. |
| UPS Rating API | Rate/shop requests for UPS products, using UPS customer integration environment and production endpoint. | OAuth 2.0; customer integration environment at `wwwcie.ups.com/api`; production at `onlinetools.ups.com/api`. Account/product entitlement and service availability need validation; public quota not established in reviewed source. | Optional diagnostic adapter for UPS quote comparison only; origin/destination/package data and account-specific rate context leave the store if invoked. | **P2 defer.** Validate account setup, OAuth scopes, region/service and rate limits; do not equate quote with checkout rate. |
| FedEx Rates and Transit Times | Account-specific or list rate estimates and transit-time details for available package services/origin-destination pairs. | FedEx Developer Portal documents sandbox virtualization; account/service access and request limits vary and need provider confirmation. | Optional diagnostic comparison, never a replacement for captured WooCommerce rate. Use synthetic addresses/packages in test environment. | **P2 defer.** Confirm sandbox fidelity, entitlement, API changes and supported currencies/regions. |
| DHL Express MyDHL API | Global time-definite international Express rating, products, landed cost, shipping and other functions. | Active DHL Express customer account and API onboarding required; test base URL `https://express.api.dhl.com/mydhlapi/test`; published test quota 500 calls/day for credentials. Production endpoint performs real transactions. | If studied, isolate read-only rating/product calls; explicitly exclude shipment, pickup, label or production operations. | **P2 defer.** Account eligibility, supported markets, test data realism, scopes and quota management require validation. |
| USPS Domestic Prices API | Current domestic package postage prices, product eligibility, base/extra-service rates. | USPS OAuth via Customer Onboarding Portal; TEM testing endpoint `apis-tem.usps.com`; quotas/API product access can vary; some APIs require approval. Legacy Web Tools retired 2026-01-25. | Optional USPS quote diagnostic. Use TEM for API tests; no label/payment calls in checkout-regression runner. | **P2 defer.** Validate API product/quota, account entitlements, rate basis, address requirements and Woo extension interaction. |
| Royal Mail API | Royal Mail API catalog includes shipping and tracking; API Shipping V2 supports account-customer shipment/label workflows. | Account-manager/API onboarding for API Shipping; test and sandbox portals exist; exact product-specific limit and rate-quote test contract not established in reviewed source. | Not enough evidence to call it a checkout-rating adapter. Existing Woo extension outcomes can be observed through the primary Woo path. | **Defer.** Confirm a rate API/test contract and customer value before scope. |
| Canada Post / Australia Post / New Zealand Post / Purolator | WooCommerce's shipping-extension docs describe rate/quote extensions for these carriers (specific scope differs by extension). | Extension-specific account, currency, units, API access and sandbox details. | Integrate by observing the installed WooCommerce method first; don't create a second direct API implementation by default. | **P2 research.** Per-extension test and region matrix required before any named compatibility claim. |

### Shipping research findings

- WooCommerce's Shipping Method API invokes `calculate_shipping()` and lets a registered method add one or more rates. This is the best core abstraction for observing the store's configured result.
- WooCommerce's official shipping extension catalog documents provider extensions including Australia Post, Canada Post, FedEx, New Zealand Post, Purolator, Royal Mail, UPS and USPS; their requirements and capabilities are not interchangeable.
- USPS's current developer portal provides domestic pricing APIs and TEM. The old Web Tools platform is retired; new research or implementation must target the new USPS APIs.
- UPS, FedEx, DHL Express, USPS and Royal Mail are research candidates because first-party developer/API documentation was found—not because this review measured market share or proved demand.

## Payment integration candidates

| Candidate | Official capability / regions | Auth, sandbox and constraints | Product use / data flow | Priority and unresolved items |
|---|---|---|---|---|
| WooCommerce Payment Gateway API | Gateway plugins expose gateway classes/settings and process checkout. Gateway types include redirect/form, iframe, direct and offline. | Each gateway owns credentials and sandbox settings; no single WooCommerce-wide provider sandbox. Direct gateways can entail PCI/security requirements. | **Primary observation:** compare registered gateway IDs/availability and supported state for the scenario without calling `process_payment()`. Never collect card fields/tokens. | **P0 core.** Availability semantics differ by classic checkout and Checkout Blocks and must be tested. |
| WooCommerce Checkout Blocks payment integration | Block payment methods register through the Blocks registry and server integration; `canMakePayment` can depend on cart/address/selected shipping. | Gateway implementation-specific; Checkout Blocks availability is separate from classic gateway registration. | Test whether a method is present/eligible using a verified non-submitting observation path. | **P0 compatibility target, untested.** Do not click Place Order as part of observation. |
| Stripe (via installed Woo gateway) | Stripe test mode/API test keys provide test objects and simulated responses. Automated UI/API testing is constrained: Stripe documents rate limits and security protections on Stripe Checkout/Payment Element. | Test keys separate from live keys; API testing remains subject to rate limits and the installed gateway's implementation. | Prefer the WooCommerce gateway's safe availability data. Mock/simulate provider responses for plugin adapter tests; no Stripe.js confirmation or live transaction from scenario runner. | **P2 provider validation.** Need exact gateway package, mode detection, checkout-block behavior and side-effect proof. |
| PayPal (via installed Woo gateway) | PayPal sandbox is a self-contained simulation using sandbox accounts/endpoints; mock transactions do not use live accounts/funds. | Separate sandbox credentials/endpoints. Not all PayPal features behave identically in sandbox; installed Woo gateway configuration still matters. | Use sandbox only for approved gateway integration tests. Availability observation should not submit an order or invoke capture. | **P2 provider validation.** Validate exact plugin/version, redirects, callbacks, locale/currency and block support. |
| Offline methods (e.g. bank transfer, cheque) | WooCommerce gateway methods may not depend on a remote payment API. | No provider sandbox may exist; store settings and eligibility rules still matter. | Compare visibility/eligibility without selecting a method that submits the order. | **P1 coverage via Woo core path.** Need safe read-only capture contract. |

A payment provider's sandbox and a store's safe checkout runner are separate concerns. Sandbox money simulation alone does not guarantee no WooCommerce order, notification, inventory reservation, webhook, analytics or fulfillment side effect.

## Data/credential policy

- Use existing WooCommerce extension execution as the source of checkout behavior; do not duplicate or export saved provider credentials.
- Any future direct provider adapter must document data fields sent, purpose, OAuth scopes, credential storage, account requirements, rate limits, deletion/rotation and supported regions before approval.
- Never call live rating/shipping operations that purchase a label, create shipment/pickup, capture payment, refund, or fulfill an order from the regression workflow.
- Rate/shipping or payment data leaving the store must require an explicit merchant-enabled adapter and a clear disclosure.
- Default test traffic should use synthetic addresses and non-sensitive package/cart attributes.
- Provider errors, throttles and timeouts are diagnostic/inconclusive states, not checkout regressions.
- No vendor rate limit is guessed; where official docs do not state a quota, this matrix says so and assigns a validation task.

## Proposed sequencing

1. Implement nothing provider-specific in Phase 3.
2. In Phase 4, define observation contracts for registered WooCommerce methods/gateways and note when a field cannot be observed.
3. In Phase 6, validate the safe observation/execution mechanism against Woo's classic and block checkout interfaces.
4. Prioritize one shipping extension and one payment gateway only after target-buyer interviews and test-environment review.
5. Add direct carrier APIs only if the Woo-native observation misses a documented user requirement.

## Official source register checked 2026-11-06

- [WooCommerce Shipping Method API](https://developer.woocommerce.com/docs/features/shipping/shipping-method-api/)
- [WooCommerce official shipping extensions catalog](https://woocommerce.com/documentation/woocommerce/shipping/shipping-extensions/)
- [UPS Developer Portal — Rating API](https://developer.ups.com/tag/Rating?loc=en_US)
- [FedEx Developer Portal — Comprehensive Rates and Transit Times](https://developer.fedex.com/api/en-us/catalog/comprehensive-rate/docs.html)
- [DHL Developer Portal — MyDHL API](https://developer.dhl.com/api-reference/dhl-express-mydhl-api)
- [USPS Developer Portal](https://developers.usps.com/)
- [USPS Getting Started / TEM](https://developers.usps.com/getting-started)
- [USPS Web Tools retirement notice](https://www.usps.com/business/web-tools-apis/documentation-updates.htm)
- [Royal Mail Developer Portal — API Shipping V2](https://pp.developer.royalmail.net/product/7986/api/283)
- [Royal Mail API onboarding](https://developer.royalmail.net/index.php/start)
- [WooCommerce Payment Gateway API](https://developer.woocommerce.com/docs/features/payments/payment-gateway-api)
- [WooCommerce Checkout Block payment integration](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/checkout-payment-methods/payment-method-integration)
- [Stripe Automated Testing](https://docs.stripe.com/automated-testing)
- [PayPal Sandbox Testing](https://developer.paypal.com/sandbox-testing/overview/)
