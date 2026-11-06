# Scenario and comparison specification

**Phase:** 4 — Checkout scenario and comparison design  
**Status:** Design specification complete; schema/examples are not runtime-validated.  
**Date:** 2026-11-06  
**Related:** [Requirements](../requirements/REQUIREMENTS.md) · [Proposed architecture](../architecture/ARCHITECTURE.md) · [Integration matrix](../architecture/INTEGRATION-MATRIX.md)

## 1. Design contract

The comparison unit is one **versioned synthetic scenario + an approved baseline + a current observation with matching context**. A run answers: what fields changed for this exact cart/market/checkout context, and which were stable?

The product must not state that a plugin update caused a checkout difference based on timing alone. It reports correlation to the recorded change event. It must not call an observation “unchanged” when capture was incomplete, unsupported or from a different context.

This document defines a future data contract, not implemented behavior. Checkout replay remains blocked until the Phase 6 safe-run proof passes.

## 2. Scenario input schema (conceptual v1)

| Field | Type / required | Purpose and validation |
|---|---|---|
| `scenario_id`, `version` | stable ID + integer; required | Immutable identity/version. Edits create a new version. |
| `name`, `tags` | text/list; required name | Merchant-readable label and optional segmentation; no secrets in labels. |
| `cart.items[]` | product/variation ref, quantity, selected attributes; required | Positive integer quantity; resolve product/variation at run time and record product identity/version context. Deleted/unavailable product blocks the run. |
| `cart.context` | physical/virtual/mixed, shipping class/package expectations; required | Detect which fulfillment/shipping path applies; unsupported item types are explicit. |
| `destination` | country required; region/state/postcode/city optional by method requirement | Use ISO country/region values where available. Store only attributes needed to select the same zone/rate. Prefer synthetic test postcodes; never use a real customer address. Full synthetic postcode may be required by a shipping plugin. |
| `customer_mode` | guest or synthetic-account; required | No real shopper account. Authenticated behavior is out of scope unless a separate synthetic account is deliberately configured and approved. |
| `locale_currency` | language, currency code, decimal scale, source; required | Record store currency or explicit multi-currency context; a currency change makes baseline comparison context-sensitive. |
| `coupons[]` | code + expected eligibility/assertion; optional | Preserve the effective WooCommerce-normalized code; don't assume case folding. Never store coupon secrets in logs. |
| `tax_context` | inclusive/exclusive display, tax location basis, tax class/context fingerprint; required for tax comparisons | Minimize address data. If tax context cannot be made comparable, report context mismatch rather than a false delta. |
| `checkout_mode` | classic, blocks, other; required | Exactly one mode per scenario version. Third-party/custom checkout is unsupported until separately validated. |
| `assertions[]` | typed expected values + comparator; required for baseline creation | A scenario is not considered monitored until the merchant selects fields/acceptable values to compare. |
| `privacy_policy_ref` | policy/version reference; required | Captures consent/retention configuration version; never embeds credentials. |

### Example scenario definition (illustrative, not an executed test)

```json
{
  "scenario_id": "shipping-de-standard",
  "version": 1,
  "name": "Germany standard parcel",
  "tags": ["shipping", "DE", "control-suite-a"],
  "cart": {
    "items": [
      {
        "product_ref": "wc-product:481",
        "variation_ref": null,
        "quantity": 1,
        "attributes": {}
      }
    ],
    "context": "physical",
    "expected_package_count": 1
  },
  "destination": {
    "country": "DE",
    "region": null,
    "postcode": "10115",
    "synthetic": true
  },
  "customer_mode": "guest",
  "locale_currency": {
    "language": "de_DE",
    "currency": "EUR",
    "decimal_scale": 2
  },
  "coupons": [],
  "tax_context": {
    "price_display": "including_tax",
    "basis": "shipping_destination",
    "fingerprint": "merchant-approved-context-hash"
  },
  "checkout_mode": "blocks",
  "assertions": [
    {
      "field": "shipping.available_methods",
      "comparator": "contains_method_instance",
      "expected": {
        "method_id": "flat_rate",
        "instance_id": "3"
      }
    }
  ],
  "privacy_policy_ref": "privacy-policy-v1"
}
```

Product references and shipping method IDs are store-local references, not universal identifiers. Their values must be resolved and fingerprinted at run time. This example is a schema illustration, not proof the named method exists.

## 3. Baseline/version rules

1. **Baseline is explicit.** A merchant reviews a captured result and explicitly approves it as expected behavior. The system never turns the latest successful observation into a baseline automatically.
2. **Immutable versions.** Each approval creates a new baseline version linked to one scenario version, environment fingerprint, runner/adapter versions, capture time and approver.
3. **Context binding.** Baseline comparison requires compatible product/variation, destination, currency/decimal scale, tax settings context, checkout mode, installed extension capability set and relevant environment fingerprint.
4. **Version drift.** Changes to scenario input, expected assertions, checkout mode or material context create a new scenario/baseline version. Show the old/new version and require approval.
5. **No retroactive rewriting.** Correcting a wrong baseline creates a superseding baseline with reason; historical runs continue referencing the original.
6. **Partial baseline.** A baseline may mark fields unsupported/not selected. It cannot imply that those fields were unchanged.
7. **Retention.** Baseline/evidence lifecycle follows the merchant's configured retention and product privacy policy. The exact default duration remains a release decision, not set by this schema document.

### Context fingerprint (conceptual)

Include only comparison-relevant, non-secret facts: WordPress/WooCommerce/PHP/plugin versions; active checkout type; currency and decimal precision; relevant shipping/tax/coupon configuration hashes with secret fields excluded; adapter/capability versions; locale; and a safe-run profile ID. Never store raw API keys, gateway settings secrets, card data or full customer addresses in the fingerprint.

## 4. Observation schema and normalization

Each observation is typed and independent. A run is not one boolean.

| Observation group | Canonical fields | Normalization / comparison rule |
|---|---|---|
| Shipping | package fingerprint, method ID, instance ID, rate ID, label, available/selected state, cost, shipping tax, collection status | Method/instance identity and availability are primary. Label is descriptive and may change independently. Compare costs only for same package, destination, currency, tax context and selected/unselected meaning. Package mapping uncertainty makes the group incomparable. |
| Payment | gateway ID, display label, configured/enabled state if known, checkout-visible/available state, requirements/capability context, collection status | Compare availability for the same cart, destination, currency and checkout mode. Keep “configured” separate from “currently available”. Do not attempt payment or submit Place Order. Unknown reason stays unknown. |
| Coupon/discount | normalized code, eligibility state, reason code if exposed, discount type, discount amount, discount tax, affected subtotal/final total | Compare a coupon's observed effect and applicability in equivalent cart/customer context. Do not infer “discount changed” from final total alone when taxes/shipping/fees also changed. |
| Totals | currency, decimal scale, subtotal, discount, shipping, fee, tax groups/tax total, final total | Store amounts as exact decimal strings or integer minor-unit strings with currency and Woo decimal scale; never use binary floating-point as the canonical diff value. Compare only identical currency, scale, display-tax mode and context. |
| Validation | stable field/error code, severity, checkout mode, collection status | Compare stable machine codes where exposed; redact entered field values and personal data. Human-facing message text is evidence, not stable identity. |
| Completion | `not_attempted` by default; future supported result only after safety proof | No order submission is part of this design's default observation. Do not claim checkout completion from cart totals/payment-method visibility. |

WooCommerce's Store API distinguishes cart operations from checkout order processing. The cart API documents state changes for customer address, coupons and shipping-rate selection; these are session/cart mutations and may invoke extension behavior. More critically, the checkout data GET creates a draft order, and POST processes the order/payment. Therefore the runner must not call Store API checkout GET/PUT/POST as a read-only probe. A future runner may use mutating cart operations only inside a proven isolated test session and must account for hooks/side effects; until Phase 6 proves this, execution remains blocked.

### Money representation

Store each monetary value with `currency_code`, `decimal_scale`, and a canonical exact value. For a two-decimal store currency, 12.34 may be represented as `minor_units: "1234"`; for other precision, record the scale explicitly. Keep the original normalized Woo value string for evidence. The comparator defaults to exact equality at the store's configured precision. Any tolerance must be an explicit per-field assertion with a reason and approved baseline; never add a global silent rounding tolerance.

### Collection-state vocabulary

- `observed`: adapter returned a value with known semantics.
- `missing`: expected value is absent and the adapter completed a valid capture that can establish absence.
- `not_applicable`: field does not apply to this scenario (e.g. shipping for virtual-only cart).
- `not_observed`: capture did not request or return this field.
- `unsupported`: checkout/extension combination has no validated adapter.
- `collection_error`: adapter failed or timed out.
- `context_mismatch`: baseline/current context is not comparable.

These states are not interchangeable. Only `observed` and validated `missing` can produce an ordinary value comparison.

## 5. Diff model and classification

### Field-level diff

| Diff state | Definition |
|---|---|
| `unchanged` | Comparable canonical values are exactly equal, or satisfy the field's explicitly approved comparator. |
| `changed` | Comparable canonical values differ. Store both values, delta if meaningful, comparator and context. |
| `added` | Current capture has a valid value for a field absent from the approved baseline. |
| `missing` | Baseline expected a value and a complete supported capture proves it is absent. |
| `not_comparable` | Context mismatch, unsupported field, incomplete capture, or different semantics prevent a valid comparison. |

Do not conflate a missing shipping method with a failed shipping-rate service: the first requires a completed supported observation; the second is an execution/collection failure.

### Run state vs finding classification

**Run state** describes execution: `queued`, `running`, `complete`, `partial`, `blocked`, `failed`, `timed_out`, `cancelled`.

**Finding classification** describes interpretation: `regression_candidate`, `expected_change`, `unchanged`, `flaky`, `unsupported`, `inconclusive`.

- `regression_candidate`: material field delta violates an approved assertion; requires evidence and comparable context. This is not proof of causation.
- `expected_change`: an authorized user acknowledged the difference or approved a superseding baseline; include actor/time/reason.
- `unchanged`: relevant fields were successfully captured and comparable with no differences.
- `flaky`: repeated captures of the same scenario/context disagree beyond the approved comparator. One timeout alone is a failed/inconclusive run, not “flaky”.
- `unsupported`: mode/integration has no validated capability.
- `inconclusive`: partial or context-mismatched data, transient errors, or unresolved semantics prevent a finding.

A suite summary uses the worst relevant status while retaining per-scenario/per-field results. A blocked/unsupported scenario cannot make the suite green. Classification must not infer severity from price delta alone; configurable thresholds and confidence policy require later product validation.

### Pseudocode contract

```text
if safety_gate != allow:
    run = blocked
    finding = unsupported_or_inconclusive_with_reason
else:
    capture observations with per-field collection_state
    if context_fingerprint is incompatible:
        finding = inconclusive(context_mismatch)
    else:
        compare only observed or validated-missing fields
        if any approved assertion is violated:
            finding = regression_candidate
        else if all monitored fields were comparable:
            finding = unchanged
        else:
            finding = unsupported_or_inconclusive
```

## 6. Deterministic design vectors (expected report examples)

These vectors specify desired interpretation; they were not executed against WooCommerce.

| Vector | Approved baseline | Current observation | Expected report |
|---|---|---|---|
| V-01 Germany shipping disappearance | DE/EUR, physical parcel, method `flat_rate:3` present at EUR 5.00; supported adapter and matching context | Same scenario/context, complete supported capture reports no `flat_rate:3`; other fields observed | `regression_candidate`: “Germany — expected shipping option missing”; show exact method ID, prior presence, current absence, run/version context. |
| V-02 UK shipping price change | GB/GBP, package fingerprint P1, `flat_rate:1` cost GBP 7.00 | Same scenario/context and method; current rate GBP 12.00 | `regression_candidate`: GBP +5.00 (+71.43% as presentation-only derived value); base values/control remain visible. |
| V-03 US unchanged control | US/USD, same cart/checkout fingerprint; expected method and totals recorded | All monitored fields observed and equal | `unchanged`: explicitly show the control scenario remained stable. |
| V-04 WELCOME10 discount change | USD 100.00 eligible subtotal, coupon applied, 10% = USD 10.00 discount before recorded tax behavior | Same context, coupon still accepted but observed discount is USD 5.00 | `regression_candidate`: compare coupon state/type/discount/tax separately; do not attribute unrelated total delta to the coupon. |
| V-05 Incomplete execution | Supported baseline with shipping and payment fields | Shipping observation succeeds; payment adapter times out | Run `partial`; payment is `inconclusive`/collection_error; no “payment unavailable” claim. |
| V-06 Unsupported checkout mode | Classic-checkout baseline | Current store uses unvalidated third-party checkout | Run `blocked` or `partial`; mark mode `unsupported`; do not compare as pass. |
| V-07 Currency/tax context changed | GBP, tax-inclusive, scale 2 | EUR or tax-exclusive capture | `inconclusive`/context_mismatch; no cross-currency arithmetic. |

The percent in V-02 is a display aid only; the primary delta is exact currency amount. The product must preserve merchant currency and not relabel the concept example's dollar values as universal.

## 7. Sample impact report layout

| Scenario | Change context | Field | Baseline | Current | Status | Evidence |
|---|---|---|---|---|---|---|
| Germany standard parcel | Shipping extension updated 2.1 → 2.2 (correlated) | Shipping method `flat_rate:3` | Present · EUR 5.00 | Missing (capture complete) | Regression candidate | run ID, scenario/baseline versions, checkout mode, time |
| UK standard parcel | Shipping settings change recorded | Selected method cost | GBP 7.00 | GBP 12.00 | Regression candidate | normalized values + environment fingerprint |
| US control | Same change window | Monitored shipping/totals | USD 8.00 | USD 8.00 | Unchanged | comparator and capture status |
| Welcome coupon cart | Coupon/configuration change recorded | `WELCOME10` discount | USD 10.00 | USD 5.00 | Regression candidate | coupon state, subtotal and tax context |
| Payment check | Gateway adapter timeout | Availability | Available | Not observed | Inconclusive | timeout code; no claim of missing payment method |

“Change context” is correlation metadata. User-facing copy must not say an update caused the outcome unless a controlled reproduction establishes that separately.

## 8. Safe execution boundary and technical handoff

This phase finalizes the **design** but cannot prove safe replay without a technical spike. WooCommerce documents that GET checkout data creates a draft order; POST checkout processes orders and payments. Store API cart updates also mutate session/cart state. Therefore:

- Do not use checkout GET/PUT/POST for baseline or regression observation.
- Do not submit Place Order, click payment controls, capture/void/refund payments, create labels/shipments, or test with live provider credentials.
- Any future cart/session mutation must run in a disposable, isolated test environment/session and be reviewed for plugin hooks, emails, stock, webhooks, analytics, subscriptions, fulfillment and persistent customer state.
- If isolation/side-effect prevention is not demonstrated for an exact checkout/integration combination, mark it unsupported and disable automatic replay.
- Completion evidence remains `not_attempted` until the safe-run spike proves an alternative path and defines what “complete” means without a real order.

**Phase 6 proof criteria:** identify the exact endpoints/hooks; trace every persistent side effect; demonstrate prevention/containment in a disposable store; assert no real charge, live order, customer email, inventory mutation, webhook, analytics or fulfillment effect; clean up and verify residual state; repeat for each supported checkout mode and gateway class. Any failed assertion blocks that matrix entry.

## 9. Official technical sources checked 2026-11-06

- [WooCommerce Store API Cart](https://developer.woocommerce.com/docs/apis/store-api/resources-endpoints/cart/) — cart, coupon, address and shipping-rate operations.
- [WooCommerce Store API Checkout](https://developer.woocommerce.com/docs/apis/store-api/resources-endpoints/checkout/) — checkout GET draft-order creation and POST order/payment processing.
- [WooCommerce client/server data flow](https://developer.woocommerce.com/docs/block-development/reference/overview-of-data-flow/) — server is source of truth for cart totals, shipping rates and coupons.
- [WooCommerce payment method integration for Checkout Blocks](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/checkout-payment-methods/payment-method-integration/) — block gateway availability interface.
- [WooCommerce price formatting code reference](https://woocommerce.github.io/code-reference/files/woocommerce-includes-wc-formatting-functions.html) — price precision and decimal handling.

## Completion boundary

Phase 4 is complete as a scenario/baseline/observation/diff/report design. V-01–V-07 are deterministic expected vectors, not test results. Safe checkout replay is deliberately a Phase 6 proof requirement; no production checkout or order operation is claimed safe by this document.
