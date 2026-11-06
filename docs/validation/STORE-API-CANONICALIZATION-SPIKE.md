# Phase 6 — Store API canonicalization spike

**Status:** Canonical field mapping specified from official response examples; runtime/schema compatibility not tested.  
**Date:** 2026-11-06  
**Task:** SPIKE-005  
**Evidence class:** Documentation-backed data contract. No parser or plugin code has been implemented.

## Goal

Define how supported WooCommerce Store API observations map into the product's common diff model without rounding money, treating display labels as stable identifiers, or converting incomplete data into a false regression.

## Monetary representation

The Cart API examples return checkout amounts as integer strings in the currency's minor unit and include currency metadata such as currency code and minor-unit precision. For example, the string "5500" with currency_code USD and currency_minor_unit 2 represents USD 55.00. Store API product raw_prices include a separate precision (example: 6); they are not interchangeable with checkout totals.

Canonical amount contract:

- Parse the amount as a base-10 integer string. Do not convert through binary float.
- Carry currency_code, currency_minor_unit, API field path, and raw response string beside each canonical amount.
- Compare only when currency code and minor-unit precision match the approved baseline context.
- Use currency symbol, separators, prefix and suffix for display only. They do not establish amount identity.
- Do not recompute total price by summing line and tax fields: WooCommerce may round line/tax aggregates independently. Compare each authoritative field and aggregate as returned.
- Treat missing currency metadata, invalid integer strings, precision mismatch, or mixed-currency response as context_mismatch/collection_error, not zero.

## Field mapping

| Store API field family | Canonical observation | Identity/comparison guidance |
|---|---|---|
| totals.total_items, total_items_tax | Items subtotal and tax | Exact minor-unit strings; keep tax separate. |
| totals.total_fees, total_fees_tax | Fees and fee tax | Compare only with same fee-producing extension context; detail fee rows where a stable provider key exists. |
| totals.total_discount, total_discount_tax | Aggregate discount and discount tax | Do not infer a specific coupon's effect from aggregate alone when multiple discounts/fees exist. |
| totals.total_shipping, total_shipping_tax | Aggregate shipping and tax | Detail against package/rate observations; aggregate equality does not prove the same method was offered. |
| totals.total_price, total_tax, tax_lines[] | Final total and tax breakdown | Compare exact returned fields; tax-line labels may be localized. Use a stable tax-rate identity only when exposed and validated. |
| shipping_rates[] | Available shipping methods per package | Prefer package_id, rate_id, method_id, instance_id, selected flag, price and taxes. Treat display name/description/delivery text as descriptive evidence, not stable identity. |
| coupons[] | Applied coupon code, discount type and per-coupon discount totals | Normalize code only by documented Woo semantics; do not assume case folding. Keep coupon and aggregate discount observations separate. |
| has_calculated_shipping, needs_shipping | Completeness/applicability guards | If shipping is not calculated, an empty rate list is not proof that a method is missing. If cart does not need shipping, shipping is not_applicable. |
| extensions | Namespaced third-party data | Capture only allowlisted, documented extension namespaces with schema/version identity. Unknown extension payloads stay unobserved; do not persist arbitrary payloads. |
| billing_address, shipping_address | Comparison context only | Retain only minimum zone-resolution attributes (country/region/postcode as needed); redact names, street, phone and email. |

## Collection-state guards

1. Validate the response shape and route/version before mapping any value.
2. Record a response as complete only if the request succeeded and required response groups are present.
3. An empty shipping_rates list can map to a validated missing-method finding only if:
   - shipping is required;
   - shipping calculation completed;
   - package/destination/cart context matches;
   - the mode-specific capture completed without timeout/error.
4. An applied coupon row proves application in that capture; an absent coupon can be reported missing only after a supported, complete coupon attempt with equivalent context.
5. Payment method availability is not inferred from Cart API fields. It requires a separate mode-specific checkout adapter.
6. A response with a different currency, currency precision, destination, package identity, checkout mode, or relevant extension context is context_mismatch, not a value delta.
7. Store the endpoint, adapter/version, capture time, environment fingerprint and exact canonical field provenance for every compared value.

## Illustrative conversions (not runtime tests)

| API value | Currency metadata | Canonical value | Display |
|---|---|---|---|
| "5500" | USD, minor unit 2 | USD minor units "5500" | $55.00 |
| "0" | JPY, minor unit 0 | JPY minor units "0" | ¥0 |
| "1234" | KWD, minor unit 3 | KWD minor units "1234" | KWD 1.234 |

These examples demonstrate the data contract only. A real store's currency and extension output must be captured and tested.

## Runtime acceptance cases

Add the following to SPIKE-001's staging run:

- **NORM-01:** confirm raw monetary strings and currency precision across item, coupon, rate and cart-total fields.
- **NORM-02:** compare values with same numeric amount but different currency code or minor precision; the result must be context_mismatch.
- **NORM-03:** return an empty shipping rate list with has_calculated_shipping false; no missing-method regression may be emitted.
- **NORM-04:** compare rate IDs/method instance IDs while changing only translated display name; identity and label diffs must remain distinct.
- **NORM-05:** apply multiple discounts/fees and verify the report does not attribute aggregate totals to one coupon.
- **NORM-06:** verify unknown extension fields and address PII are excluded from retained evidence.

## Sources reviewed 2026-11-06

- [WooCommerce Cart API](https://developer.woocommerce.com/docs/apis/store-api/resources-endpoints/cart/) — cart response, money fields, rates, coupons, totals and currency precision.
- [WooCommerce Store API available extensible endpoints](https://developer.woocommerce.com/docs/apis/store-api/extending-store-api/available-endpoints-to-extend) — namespaced extension payloads.
- [WooCommerce Store API formatters](https://developer.woocommerce.com/docs/apis/store-api/extending-store-api/extend-store-api-formatters) — money/currency formatting and currency minor-unit metadata.
- [Payment method integration for Checkout Blocks](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/checkout-payment-methods/payment-method-integration/) — payment availability is a separate gateway callback.

## Completion boundary

SPIKE-005 is complete as a canonicalization design. Money conversion, provider extensions, localized rates/tax lines, rounding behavior, and redaction still require fixture and staging verification before the canonical adapter can be implemented or advertised as compatible.
