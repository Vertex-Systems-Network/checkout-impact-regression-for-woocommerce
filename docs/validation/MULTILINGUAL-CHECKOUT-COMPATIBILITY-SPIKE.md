# Multilingual checkout compatibility spike

**Task:** SPIKE-009  
**Phase:** 6 — Pre-development technical proof  
**Status:** Complete as source-backed planning; no runtime compatibility claim  
**Checked:** 2026-11-06

## Purpose

Define how saved checkout scenarios must represent language and currency, and which WPML/WCML and Polylang combinations need separate evidence before compatibility can be claimed. This spike reduces ambiguity in the test matrix; it does not verify the plugin against either multilingual stack.

## Evidence reviewed

- [WPML: WooCommerce Multilingual & Multicurrency](https://wpml.org/documentation/wpml-core-and-add-on-plugins/woocommerce-multilingual/) describes both classic shortcode checkout and WooCommerce Cart/Checkout blocks, multi-currency behavior, and translated shipping classes.
- [WPML: multi-currency and payment gateways](https://wpml.org/documentation/translating-your-contents/multi-currency-and-payment-gateways-for-woocommerce/) documents currency-specific gateway settings, location-based currency selection, and cart behavior when language or currency changes mid-session.
- [WPML WCML 5.6.1 release notes](https://wpml.org/version/woocommerce-multilingual-5-6-1/) specifically report a fix so block cart/checkout uses the shopper-selected currency. This demonstrates that version-specific behavior can affect comparisons.
- [Polylang: translating WooCommerce cart and checkout](https://polylang.pro/translate-woocommerce-cart-and-checkout-page-effortlessly/) describes classic and block checkout, and translation of shipping methods, payment gateways, and order confirmation.
- [Polylang for WooCommerce changelog](https://polylang.pro/polylang-for-woocommerce-changelog/) records changes to checkout-block compatibility, shipping/payment translation, and currency formatting.

These are vendor materials, not independent runtime evidence. The documented feature claims do not establish compatibility with this planned plugin or every third-party checkout extension.

## Scenario fingerprint additions

A comparison must bind observations to these dimensions whenever applicable:

| Dimension | Capture requirement | Why it affects comparability |
|---|---|---|
| Language | Active language/locale and language source (site language, URL, or switcher) | Translated labels/pages and language-specific settings may differ |
| Currency | Currency code, source (store default, manual selection, or location rule), and precision | Amounts and gateway availability can vary by currency |
| Checkout mode | Classic shortcode or Blocks | Separate rendering/data and payment-availability paths |
| Multilingual stack | Product and version; required add-ons and versions | Vendor fixes can alter checkout behavior |
| Destination | Country/region plus shipping zone/package identity | Localized shipping method selection may differ |
| Gateway context | Available method identifiers, filtered by currency/language where applicable | A cart response alone cannot prove checkout-visible payment availability |

If any required fingerprint dimension differs between baseline and observation, the result must be **not comparable/context changed**, not unchanged. Store only non-sensitive identifiers and redact customer data.

## Required proof matrix

At minimum, test each supported combination independently:

1. Single-language, single-currency control, Classic and Blocks.
2. WPML + WCML, Classic, default currency.
3. WPML + WCML, Blocks, default currency.
4. WPML + WCML, Classic and Blocks, secondary currency selected manually.
5. WPML + WCML, currency selected by visitor location, including gateway filtering.
6. WPML language switch with a non-empty cart and each configured cart-switch policy.
7. Polylang Pro + Polylang for WooCommerce, Classic and Blocks.
8. Polylang translated shipping methods/classes and payment gateway labels/availability where configured.
9. For every supported stack: one country-specific shipping-rate change, one missing method, one coupon/tax control, and one unchanged scenario.
10. For every run: verify no real charge, order/email/stock mutation, external fulfillment trigger, or retained test session; apply SPIKE-001/002 stop criteria.

Record exact versions, configuration, scenario fingerprint, raw redacted observation, normalized result, side-effect delta, and cleanup outcome in the [runtime proof worksheet](RUNTIME-PROOF-RUNBOOK.md).

## Fail-closed rules

- Do not infer a language or currency from display text; capture stable codes/context through supported APIs.
- Do not compare monetary values across currencies or different precision rules.
- Do not treat a translated shipping label as a distinct shipping method if the underlying method/rate identity is stable; retain both stable identity and display label.
- Do not claim payment availability from Cart API data alone.
- A missing integration/version/context signal produces unsupported or inconclusive, never a pass.
- A mismatch caused by currency/language/cart-switch context is not attributed to a plugin update without separate change-correlation evidence.

## Acceptance and boundary

**Accepted:** multilingual and multicurrency contexts are explicit scenario dimensions, and WPML/WCML and Polylang have separate versioned proof requirements across Classic and Blocks.

**Still unverified:** actual behavior of this product, exact supported version combinations, third-party plugin interactions, safe replay, and the entire runtime proof matrix. SPIKE-001 remains the gating blocker for automatic replay.

## Follow-up

When a disposable staging store is available, extend the runtime worksheet with the exact installed versions and run only combinations whose side effects can be contained. Publish supported combinations only after evidence passes the Phase 6 exit criteria.
