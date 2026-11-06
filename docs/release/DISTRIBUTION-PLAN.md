# Distribution and marketplace submission plan

**Status:** Target channels approved for planning; no marketplace application or submission has been made.  
**Checked:** 2026-11-06  
**Product:** WPEssential Checkout Impact Regression

## 1. Target release channels

| Edition | Target channel | Submission form | Release state |
|---|---|---|---|
| Free edition | WordPress.org Plugin Directory | Complete installable plugin ZIP for review; after approval, deploy stable releases through the directory's SVN repository. | Target; not submitted. |
| Paid edition | WooCommerce.com Marketplace | Marketplace vendor application/approval followed by a separate paid product submission and review. | Target; vendor application not submitted. |

The free edition must provide real, usable value on its own. It must not be a time-limited demo or a feature-locked trial. The paid edition can add distinct functionality, but WordPress.org's rules prohibit trialware and prohibit the directory plugin from downloading/installing a premium copy from an outside server. Therefore, do not build an in-plugin premium ZIP installer or external update channel into the WordPress.org edition. Keep user-directed links/upgrade notices contextual and non-intrusive. The exact package identity, paid add-on mechanism and update ownership must be agreed with both marketplaces before finalizing release packaging.

Woo Marketplace documentation says freemium products require separate free and paid submissions, later merged into a single product page. Treat the planned WordPress.org free listing and WooCommerce.com paid listing as separate channel submissions until Woo Marketplace confirms how it wants the cross-directory arrangement represented.

## 2. Edition boundary to decide before implementation freeze

The product owner must approve a feature-by-feature edition matrix before the first release candidate.

| Product capability | Free edition target | Paid edition target | Decision status |
|---|---|---|---|
| Scenario catalog and synthetic scenario editing | Useful core allowance; final limits TBD | Expanded allowance/configuration TBD | Not finalized |
| Baseline and before/after impact comparison | Core value must be genuinely usable; do not create a trial-only shell | Expanded history, workflows or scale may be considered | Not finalized |
| Change context and report review | Basic local report path | Advanced review/history/notification possibilities | Not finalized |
| Safe checkout replay | Only when a validated safe method exists | Same safety gate; paid tier cannot bypass safety | Blocked by Phase 6 proof |
| Multilingual, checkout mode and integration coverage | State exact tested matrix | No untested compatibility claims | Untested |
| Notifications, support export, site count, retention | Determine sustainable free scope | Final paid scope must justify price | Not finalized |
| AI suggestions or direct carrier APIs | Excluded until validated | No automatic inclusion in paid tier | Deferred |

The free edition cannot be a storefront shell that requires payment before the product's essential scenario comparison provides value. The directory review outcome is not guaranteed, so feature partitioning and upgrade prompts require a compliance review before package freeze.

## 3. WordPress.org submission gates

Before submitting:

1. Choose and document a GPL-compatible license for every distributed file; GPLv2-or-later is the preferred target unless legal review selects another compatible option.
2. Define a useful free product scope and verify it is not trialware, a quota-expiring demo, or a loader that installs/updates the premium package externally.
3. Prepare a complete, production-ready plugin ZIP with readable source, required compiled assets, plugin/readme metadata, changelog and public source/build-tool access.
4. Review all included libraries, code, assets, API terms, external requests, privacy disclosures and data flows.
5. Complete security, compatibility, activation, privacy/export/delete, accessibility and user-facing documentation review.
6. Submit through the WordPress.org Add Plugin process and address review feedback. Only the approved directory release is distributed from WordPress.org; use its SVN release repository for stable updates.
7. Confirm the plugin's directory slug, update path and naming do not conflict with the Woo Marketplace paid package.

WordPress.org currently requires a complete ready-to-use submission, GPL-compatible licensing, compliance with detailed guidelines and a stable directory-hosted version. Its guidelines prohibit trialware, external premium plugin installation and intrusive dashboard upsells. Verify the live requirements again at submission time.

## 4. WooCommerce.com Marketplace submission gates

Before submitting:

1. Apply for and obtain a Marketplace vendor/partner account; acceptance is not guaranteed.
2. Decide whether the product is a standalone extension or a service-backed product. The monetization model and revenue share depend on that classification; do not choose the final price from the current $119/year idea alone.
3. Prepare the paid package, product rationale, business details, documentation, demo links, critical-flow test instructions and support plan.
4. Pass current Marketplace review gates. The currently published requirements include security/UX review, compatibility with the two latest major WordPress and WooCommerce releases, HPOS compatibility for new extension submissions, PHP requirements, and Quality Insights Toolkit checks (API, E2E, activation, security, PHPCompatibility, malware and validation).
5. Prioritize Cart and Checkout Blocks; also prove any classic checkout compatibility that is claimed. Test compatibility with relevant popular extensions from the Marketplace list and publish limitations.
6. Submit the free and paid versions separately when using Woo Marketplace's freemium listing process. Resolve automated test failures and review feedback before approval.
7. Complete vendor agreement, listing, licensing, support, renewal and launch onboarding before publishing.

The currently published Woo Marketplace page describes a vendor revenue-share model (70% to vendor), requires a competitive price at or below equivalent sales in other environments, and uses a single-site paid license option for standalone plugins. These commercial terms may change; re-check and model net revenue/support/hosting costs before approving the proposed $119/year retail price.

## 5. Shared source and channel-specific packaging

- Maintain one canonical source repository and clearly documented build provenance.
- Generate separate, reproducible WordPress.org free and Woo Marketplace paid artifacts from reviewed release tags.
- Record edition, version, source commit, build dependencies and checksums for each package.
- Never ship development tools, fixtures, secrets, sample customer data or local test credentials in release ZIPs.
- Keep the WordPress.org SVN repository release-only; it is not a development branch.
- Define how users move from free to paid without automatic external ZIP installation, data loss, duplicate plugin activation, conflicting update ownership or silent edition downgrade.
- Keep migration/upgrade behavior backwards-compatible; test free → paid, paid update, rollback and uninstall data policy before release.
- Use marketplace-specific names, slugs, readme/listing copy and support channels as approved; do not imply either marketplace has accepted the product.
- Keep pricing statements consistent across channels if Woo Marketplace's price-parity rule applies. Annual term, renewal, tax, refunds, support response and multisite entitlement need explicit terms.

## 6. Release evidence matrix

| Gate | WordPress.org | WooCommerce.com | Evidence needed |
|---|---|---|---|
| Licensing | GPL-compatible complete package | Vendor/license model and package compliance | License inventory, third-party license notices, approved license decision |
| Product scope | Useful free edition; not trialware | Free/paid separate submissions for freemium, if applicable | Approved edition matrix and review notes |
| Platform support | Exact tested claims in readme | Latest-two-major WP/Woo support and current PHP expectations | Reproducible compatibility matrix and artifacts |
| Checkout experience | Claims backed by tests; no unsafe replay | Blocks prioritized; HPOS required for new submissions | Checkout Blocks/classic evidence; HPOS test report |
| Security/privacy | Detailed guideline and privacy compliance | Security review and QIT security/malware/validation | Threat model, permissions review, data-flow/privacy docs |
| Quality | Complete production ZIP, readable source, required assets | QIT API/E2E/activation/security/PHPCompatibility/malware/validation | Passing reports for release artifact |
| UX/accessibility | Non-intrusive admin experience and clear docs | Critical flows tested, UX review, accessibility standards | Keyboard/assistive tech results, demo and test instructions |
| Operations | SVN stable-release updates, readme/changelog, support | Vendor account, listing, customer support, billing/update onboarding | Release runbooks, support policy, contact details |
| Commercial | Free tier scope and permitted contextual upgrade path | Revenue share, price parity, single-site licensing, renewal/support model | Costed pricing decision and sales/support model |

Do not mark a submission gate complete merely because a package builds. Only the official marketplace review and published listing establish approval.

## 7. Open decisions

- What specific free capabilities create standalone merchant value while complying with WordPress.org's no-trialware rule?
- Does the Woo Marketplace approve the proposed cross-directory free/paid presentation and package/update model?
- Should the paid edition be one extension package or an explicitly separate add-on package?
- What is the current Woo Marketplace vendor agreement, payment cadence, refund/renewal policy and revenue-share treatment at application time?
- Does $119/year meet Woo's then-current price-parity expectation, support cost and user willingness-to-pay?
- What exact support, privacy policy, data processing, retention and incident contact details are required by both listings?
- Which free/paid capabilities depend on safe runner proof, hosting cost or external service? No tier may bypass the safety gate.

## 8. Official sources checked 2026-11-06

- [WordPress.org Plugin Directory overview](https://developer.wordpress.org/plugins/wordpress-org/)
- [WordPress.org Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [WordPress.org Plugin Developer FAQ](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/)
- [WordPress.org planning/submitting/maintaining plugins](https://developer.wordpress.org/plugins/wordpress-org/planning-submitting-and-maintaining-plugins/)
- [Woo Marketplace submission guidelines](https://developer.woocommerce.com/docs/woo-marketplace/submitting-your-product)
- [Woo Marketplace monetization expectations](https://developer.woocommerce.com/docs/woo-marketplace/monetization-expectations)
- [Woo Marketplace vendor getting started](https://developer.woocommerce.com/docs/woo-marketplace/getting-started/)
- [WooCommerce extension development best practices](https://developer.woocommerce.com/docs/extensions/best-practices-extensions/extension-development-best-practices)
