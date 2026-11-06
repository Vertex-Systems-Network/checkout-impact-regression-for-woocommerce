# Phase 2 — Requirements and compatibility matrix

**Status:** Planning requirements complete; compatibility remains **planned, not tested**.  
**Date:** 2026-11-06  
**Scope:** Requirements for the pre-development product plan. This document is not a claim that the plugin exists or has passed compatibility testing.

## 1. User journeys

| ID | Journey | Expected product outcome |
|---|---|---|
| J-01 | Store owner saves a Germany cart with a known shipping method and approved baseline. A shipping extension is updated; the scenario is run again. | Compare method presence and cost; show missing/changed values for Germany and preserve unaffected control results. |
| J-02 | Operator compares a UK delivery scenario before and after a shipping-setting change. | Show expected £/$ amount and observed amount in the store's currency, with tax/zone context and evidence. |
| J-03 | QA saves a US control scenario expected to remain unchanged. | Explicitly report unchanged, not merely omit the scenario. |
| J-04 | Operator applies `WELCOME10` to a saved cart before and after a coupon or checkout change. | Compare eligibility, applied amount, discount type, tax effects, and final total; avoid assuming the coupon must always succeed. |
| J-05 | Payment methods differ by country, currency, cart, or checkout type. | Report method availability and supported state; never initiate a real payment. |
| J-06 | A replay is blocked, unstable, unsupported, or produces inconsistent observations. | Mark it unsupported/flaky/inconclusive with reason; do not convert missing evidence into a regression claim. |
| J-07 | An authorized user reviews a difference and decides it is expected. | Record acknowledgement and baseline approval as an auditable action; do not silently overwrite the old baseline. |

Scenarios should be synthetic and reusable. A scenario may include country/region/postcode attributes, language, currency, guest/authenticated mode, product/variation/quantity, virtual or shippable items, shipping class, coupon, tax context, checkout experience, expected values, and explicit exclusions. Do not require a real shopper profile.

## 2. Functional requirements

Priority: P0 = required for the product hypothesis/safe operation; P1 = valuable after core comparison; P2 = deferred. Acceptance criteria are planning criteria, not implementation evidence.

| ID | Requirement and rationale | Priority | Dependencies | Acceptance criteria |
|---|---|---:|---|---|
| FR-001 | Create and validate reusable synthetic cart/customer-region scenarios so differences can be isolated by customer journey. | P0 | UX-001; scenario schema | Required fields are validated; missing prerequisites are explained; scenario is stored without real customer identity by default. |
| FR-002 | Capture an explicitly approved baseline with environment/version context; stores need a stable comparison reference. | P0 | FR-001; baseline schema | Baseline is versioned, timestamped and immutable; changes require a deliberate approval action; previous versions remain inspectable. |
| FR-003 | Capture normalized checkout observations for supported scenarios. | P0 | Safe execution proof; adapter contracts | Record shipping methods/costs, payment availability, coupon/discount, tax/fees/totals, validation/completion signals and capture limitations where supported. Each value has currency/context and provenance. |
| FR-004 | Compare before/after results field by field across a scenario suite. | P0 | FR-002, FR-003 | Report changed, unchanged, missing, unsupported, flaky or inconclusive values; include expected/observed values and affected scenario; never silently omit an attempted scenario. |
| FR-005 | Correlate observations with detected or manually recorded plugin/configuration changes. | P0 | Change-source spike; environment fingerprint | Store change timestamp, changed component/version or supported setting context; label temporal correlation as correlation, not proven causation. |
| FR-006 | Rank and explain impact without overclaiming certainty. | P0 | FR-004 | Findings carry severity/confidence and reason; an unobserved/unsupported value cannot be classified as a confirmed regression. |
| FR-007 | Let authorized users acknowledge expected differences, add a note, or approve a new baseline. | P0 | FR-002; capabilities/audit | Every action records actor, time, old/new baseline reference and reason; original evidence stays available. |
| FR-008 | Present an accessible React-based WordPress admin workflow for scenarios, comparison summary, detail and history. | P1 | UX-001 | Keyboard, focus, loading, empty, error and success states are specified; strings are localization-ready; no raw logs required to understand a finding. |
| FR-009 | Export a privacy-redacted report for support/QA handoff. | P1 | FR-003; privacy controls | Export includes scenario/run IDs, versions, expected/observed values and timestamps; excludes secrets and personal data by default. |
| FR-010 | Notify users about actionable findings with controls against duplicates/noise. | P1 | FR-006; notification design | Notification references affected scenario and evidence; expected/suppressed results are respected; delivery failure does not erase findings. |

## 3. Non-functional, privacy and safety requirements

| ID | Requirement | Priority | Dependencies | Acceptance criteria |
|---|---|---:|---|---|
| NFR-001 | Least privilege and WordPress security conventions. | P0 | WP admin architecture | Mutations require an appropriate capability and nonce; all input is validated/sanitized and output escaped; REST/AJAX routes have permission callbacks; no public unauthenticated scenario execution. |
| NFR-002 | Minimize stored data. | P0 | Scenario and evidence schema | Synthetic values are default; no real shopper PII, full addresses, card data, passwords, or gateway secrets in scenario reports/logs. Address attributes are reduced to the minimum needed for zone resolution. |
| NFR-003 | Protect secrets and sensitive evidence. | P0 | Storage design | Do not collect payment card data. Secrets are not returned to UI/export/logs; external transmission is off by default and requires explicit documented consent. Encryption-at-rest and key-management approach is decided before implementation. |
| NFR-004 | Prevent unsafe checkout side effects. | P0 | SPIKE-001; checkout modes | No real charge or live payment credential is used. A run is allowed only in a validated isolated sandbox/simulation path that contains unintended orders, customer emails, stock changes, webhooks, fulfillment, subscriptions and analytics effects. Checkout Store API GET creates a `checkout-draft` and is not read-only; any such artifact must be attributable and exact-version cleanup verified. Checkout PUT/POST and Place Order are prohibited in the proof. Cleanup alone is not proof that side effects were prevented. Otherwise report unsupported/inconclusive and do not run. |
| NFR-005 | Retention and deletion are merchant-controlled. | P0 | Evidence store | Define default retention before release; provide deletion/export behavior and uninstall policy; preserve approved baselines only according to user-selected retention and applicable platform behavior. |
| NFR-006 | Reliability and resource bounds. | P0 | Runner design | Jobs are bounded, idempotent, rate-limited, retryable with a cap, cancellable, and report timeout/failure distinctly; one bad scenario cannot block the queue indefinitely. |
| NFR-007 | Accessibility and localization. | P1 | React design system | Admin flows specify keyboard and screen-reader behavior, accessible status indicators, translatable strings, and locale/currency-aware value display. |
| NFR-008 | Performance and optimization evidence. | P1 | Measurement plan | Runs are asynchronous where appropriate; profiling records baseline and post-change measurements; optimization cannot weaken correctness, privacy, or compatibility. |
| NFR-009 | Auditability and recovery. | P0 | Baseline/action model | Baseline approval, run, acknowledgement, deletion and configuration actions have timestamps and actor/context; failed migrations or cleanup have recovery guidance. |

### Safe-run gate

Checkout replay is a high-risk unknown. Before any implementation enables it, the technical spike must demonstrate a disposable non-production isolated path, no live payment, and controlled handling of every side effect listed in NFR-004 for each supported checkout mode. WooCommerce documents that checkout Store API GET creates a draft order, so a GET probe is a mutation; checkout PUT mutates checkout state and POST processes checkout/payment. Draft cleanup must be observed and proven on the exact version, not assumed from documentation. If WooCommerce or an extension cannot provide the containment and cleanup evidence, that combination remains unsupported; do not advertise safe replay or automate it. See the [Phase 6 proof protocol](../validation/PREDEVELOPMENT-SAFE-CHECKOUT-PROOF.md).

## 4. Explicit exclusions for the initial product plan

- Order management, RMA/returns, fulfillment, or inventory management.
- Live card testing, real charges, real customer checkout or customer surveillance.
- Automatic changes to shipping/payment configuration, automatic fixes, or silent baseline refresh.
- General uptime monitoring as the primary product purpose.
- Direct carrier API integrations or named-provider guarantees before provider-specific research, sandbox verification and a separate decision.
- Automatic detection of every third-party plugin setting; only validated change sources can be claimed.
- Cloud transmission of store/customer data without explicit consent and a documented data-processing/security design.
- Product CLI or CLI setup/commands before the final release.
- Compatibility claims for versions or extension combinations that have not been tested.

## 5. Compatibility plan

### Proposed minimum environment

The proposal uses the current WooCommerce 10.8 server recommendations as its planning floor; WordPress.org's database recommendation is stricter for MariaDB. Minimum is a future product target, **not a tested compatibility claim**.

| Component | Proposed minimum / required dependency | Planned validation | Current evidence/status |
|---|---|---|---|
| PHP | 8.3+ | Test PHP 8.3 and 8.4; add newer versions only after Woo/WP compatibility review. | WooCommerce 10.8 recommends 8.3+, tested up to 8.4. Plugin not implemented/tested. |
| WordPress | 6.9+ | Test selected minimum and latest stable at release time. | WooCommerce 10.8 server recommendation is WP 6.9+; WC 10.8 requires WP 6.9+. |
| WooCommerce | 10.8+ planning floor; latest stable supported only after validation. | Test minimum and latest stable; test upgrades/change correlation separately. | WooCommerce 10.8 current requirement source checked 2026-11-06; plugin not implemented/tested. |
| Database | MySQL 8.0+ or MariaDB 10.11+ proposed floor. | Validate schema/migrations on both database families. | WordPress recommends MySQL 8.0+ or MariaDB 10.11+; Woo 10.8 recommends MariaDB 10.6+, so this plan adopts the stricter WP recommendation. |
| HTTPS / memory | HTTPS required; WordPress memory limit 256 MB+ recommended. | Include environment diagnostics and graceful unsupported warning. | WooCommerce 10.8 server recommendations. |
| WPML | Optional integration: WPML core plus **WooCommerce Multilingual & Multicurrency (WCML)** for Woo translations/multicurrency. | At minimum test supported WP/Woo floor with current stable WPML/WCML; cover translated checkout and currency changes. | WPML core min WP 6.0/PHP 7.4/MySQL 5.6 or MariaDB 10.1 and 128 MB (256 MB recommended); WCML changelog 5.8.0 reports WooCommerce 11.3 custom-field work and was released 2026-11-04. Exact complete package compatibility still requires matrix testing. |
| Polylang | Optional integration: Polylang or Polylang Pro **and Polylang for WooCommerce** add-on. | Test supported WP/Woo floor with current stable versions; cover translated pages/products/shipping labels. | Polylang for WooCommerce 2.2 requires WP 6.5+, WooCommerce 9.2+, Polylang 3.7+. Do not claim currency behavior without verifying the selected configuration. |
| Checkout experience | Classic shortcode checkout and Checkout Blocks are separate compatibility targets. Neither is declared supported until tested. | Separate scenario coverage for each mode; list unsupported extensions/hooks. | WooCommerce says not all classic hooks work with blocks and extensions must declare block compatibility only after testing. |
| HPOS | Test HPOS enabled and legacy order storage; no support declaration before testing. | Verify no direct post/postmeta order assumptions and test both storage modes. | WooCommerce requires extensions to establish HPOS compatibility; compatibility mode can help transition but is not proof for this product. |
| Multisite | Not in initial commitment. | Defer until explicit requirements and isolation/permissions design. | Not validated. |

The WPML source currently lists WCML 5.8.0 and the WPML compatibility directory lists WooCommerce tested through 11.2; the current WC server-recommendations page is for WooCommerce 10.8. This is a reminder to test the *intersection* of exact package versions at release time rather than combining each vendor's latest-version claims into a blanket guarantee.

### Dependency handling

- If WooCommerce is absent or below the planned floor, do not initialize checkout-dependent code; show a clear admin dependency notice.
- If WPML/WCML or Polylang integration is absent, retain core single-language behavior and mark language/currency-specific comparisons unavailable; do not fatal or silently substitute values.
- If a checkout mode, storage mode, gateway, shipping method, or extension is unverified, mark that scenario unsupported/inconclusive and show the detected context.
- Publish tested compatibility only after reproducible automated/manual evidence exists for the exact matrix entry.

## 6. Compatibility verification strategy

1. Define a release matrix pinned to exact WordPress, WooCommerce, PHP, DB, WPML/WCML, Polylang/add-on versions.
2. Run static/lint checks and plugin activation/dependency checks per supported PHP/WP combination.
3. Exercise scenario create/edit/delete, baseline approval/version restore, run capture, normalized diff, export/redaction, retention deletion, and permission boundaries.
4. Run fixtures for missing shipping method, shipping amount change, unaffected control, coupon discount change, unavailable payment method, and inconclusive/timeout outcomes.
5. Repeat checkout behavior coverage separately for classic checkout and Checkout Blocks, then HPOS and legacy storage.
6. Add WPML/WCML language/currency and Polylang translated-checkout cases only where installed package/version is in the release matrix.
7. Validate safe-run gate with non-production store fixtures and assert no live charge, unintended order, email, stock mutation, webhook, or fulfillment side effect. Any failed assertion blocks that matrix entry.
8. Record exact environment, result, artifact/evidence, known limitation and test date. “Untested” stays visible in README/product metadata until evidence exists.

## 7. Source register (official sources checked 2026-11-06)

- [WordPress server requirements](https://wordpress.org/about/requirements/) — recommended PHP/database/HTTPS.
- [WooCommerce server recommendations](https://woocommerce.com/document/server-requirements/) — WooCommerce 10.8 server recommendations.
- [WooCommerce PHP/WordPress update requirements](https://woocommerce.com/document/update-php-wordpress/) — WooCommerce version minimums and legacy vs recommended versions.
- [WooCommerce 10.8 release notes](https://developer.woocommerce.com/2026/05/26/woocommerce-10-8-0-release/) — WP 6.9 minimum for 10.8.
- [WPML minimum requirements](https://wpml.org/home/minimum-requirements/) — WPML core environment requirements.
- [WPML WCML changelog](https://wpml.org/version/wpml-multilingual-multicurrency-for-woocommerce/) — WCML version/release notes.
- [WPML WooCommerce compatibility](https://wpml.org/plugin/woocommerce-2/) — tested WooCommerce version and WCML dependency.
- [Polylang for WooCommerce changelog](https://polylang.pro/polylang-for-woocommerce-changelog/) — add-on version requirements.
- [Polylang for WooCommerce setup](https://polylang.pro/documentation/support/guides/polylang-wc-installation/) — required add-on and setup.
- [WooCommerce Cart/Checkout blocks extensibility](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/) — block compatibility declaration and hook limitations.
- [WooCommerce extension compatibility guidance](https://developer.woocommerce.com/docs/best-practices/compatibility/) — latest core, blocks and HPOS compatibility expectations.
- [WooCommerce HPOS developer documentation](https://developer.woocommerce.com/docs/features/orders/high-performance-order-storage/) — separate order storage model and compatibility concerns.

## Completion boundary

Phase 2 is complete as a **requirements and proposed compatibility plan**: scenarios, prioritized requirements, safety/privacy boundaries, exclusions, source-linked platform targets, dependency behavior and a reproducible verification strategy are specified. Product compatibility is not tested or released; safe checkout execution remains a Phase 6 technical proof blocker.
