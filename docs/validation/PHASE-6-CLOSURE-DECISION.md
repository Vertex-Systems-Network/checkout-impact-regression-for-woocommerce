# Phase 6 closure decision — no-go for runtime support

**Decision date:** 2026-11-06  
**Phase:** 6 — Pre-development technical proof  
**Status:** Closed as an explicit no-go/support-exclusion decision. No runtime pass is claimed.

## Decision

Phase 6 is closed for planning with **no automatic checkout replay mode approved**. Every checkout observation or integration combination that depends on unproven runtime behavior is explicitly unsupported. This closes the phase by applying its “formally unsupported” exit path; it does not mean the technical risk was runtime-tested or resolved.

The product's before/after regression concept remains the target. Phase 7 must not enable or advertise automatic checkout replay until the relevant mode has passed a reopened, version-specific runtime proof. No product CLI is added.

## Explicit exclusions

| Mode or integration | Phase 6 decision | Evidence needed before support |
|---|---|---|
| Store API cart mutation and scenario capture | Not runtime-supported | CART-01–06 in an isolated environment; token/session isolation, capture completeness and cleanup |
| Store API Checkout GET and draft lifecycle | Not runtime-supported | Exact-version fresh-session and retry-path behavior; order artifact attribution and cleanup |
| Classic checkout page | Unsupported | Versioned read-only page capture, hooks, mail, stock and order side-effect audit |
| Checkout Blocks | Unsupported | Versioned block/cart/checkout capture and payment-availability behavior, with no Place Order |
| Payment gateways and checkout field extensions | Unsupported | Per-gateway sandbox proof; no charge, external request, order, email or unexpected state change |
| Shipping providers/extensions and carrier APIs | Unsupported | Per-provider sandbox proof; rate correctness, label/booking and webhook containment |
| WPML/WCML and Polylang | Unsupported | Exact language/currency/Classic/Blocks matrix in isolated stores |
| HPOS and database-specific behavior | Unsupported | Exact schema/storage-mode test and cleanup evidence |

A cart/session identifier is not a side-effect firewall. A Cart API response by itself does not prove checkout-visible payment availability. Incomplete observations must remain unsupported or inconclusive rather than pass.

## What was completed

- Source-based risk review and API/version discrepancy documentation.
- Adapter, canonicalization, change-event, queue, multilingual, and impact-vector specifications.
- Safety protocol, environment provisioning checklist, and operator worksheet for the V-01–V-07 report vectors.
- Explicit scope exclusion for every unproven automatic replay mode.

## What was not completed

- No WordPress/WooCommerce checkout test ran.
- No order draft, cleanup, payment, email, inventory, coupon, webhook, shipping, or fulfillment side-effect behavior was measured.
- No runtime compatibility claim is made for PHP, WordPress, WooCommerce, Classic, Blocks, HPOS, WPML/WCML, Polylang, gateways, or providers.

The workspace has no local PHP, Docker, WP-CLI, or WordPress installation. No WooCommerce staging store was supplied. On 2026-11-06, a temporary WPRun sandbox booted WordPress 6.9.1 on PHP 8.4, but the Plugin Directory installer marked WooCommerce 11.2.2 incompatible. The local plugin ZIP could not be transferred through the browser file picker, so the plugin was not installed or activated. This is not plugin runtime evidence. A fresh cloud-browser WordPress Playground attempt showed “Checking your browser” before WordPress loaded; no verification was solved or bypassed.

## Reopen gate

Before enabling any excluded mode:

1. Provision a resettable, non-production WooCommerce store with synthetic data, test-only payment configuration, safe email/webhook sinks, and no live carrier/fulfillment credentials.
2. Record exact PHP, WordPress, WooCommerce, database, checkout mode, theme and extension versions.
3. Execute the related cases in the [runtime proof runbook](RUNTIME-PROOF-RUNBOOK.md), including the V-01–V-07 impact vectors where applicable.
4. Capture before/after state, side-effect deltas, residual artifacts, cleanup evidence and independent review.
5. Reopen Phase 6 for that exact combination; change its status only from recorded evidence. Keep all other untested combinations unsupported.

## Downstream consequence

Phase 7 has started with only a read-only bootstrap/admin shell source slice. It has not been runtime-tested; no scenario data is stored and no checkout request is made. Implementation tasks that require automatic checkout capture/replay remain gated and must fail closed. No report may claim live checkout observation or compatibility.
