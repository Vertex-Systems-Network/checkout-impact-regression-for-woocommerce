# Checkout Impact options bank

**Status:** Research baseline reconciled with Phase 3 owner crosswalk; options remain candidates, not approved product scope.  
**Owner of scope decisions:** Product owner.  
**Research basis:** [Competitor research](COMPETITOR-RESEARCH.md), checked 2026-11-06.  
**Rule:** One user outcome gets one option ID and one accountable system owner. The Phase 3 owner crosswalk is in [Proposed architecture](../architecture/ARCHITECTURE.md). Market features are inventory evidence; they are not automatic requirements.

## Priority key

- **P0 discovery:** Must resolve before MVP scope/architecture approval.
- **P1 MVP candidate:** Strong fit with core product, still subject to technical/user validation.
- **P2 later:** Valuable only after core impact comparison works.
- **Defer:** Do not build without evidence of a distinct user need.

## Core product options

| ID | User outcome / option | Owner system | Priority / disposition | Dependencies | Primary risk | Acceptance criteria |
|---|---|---|---|---|---|---|
| OI-01 | Save a checkout scenario with cart/product, customer region/address attributes, currency, coupon and relevant checkout state | SYS-01 Scenario Catalog | P0 discovery → P1 MVP candidate | None | Scenario inputs may require personal data or extensions that cannot be normalized. | User can save, name, edit, duplicate and validate a scenario; sensitive/real customer data is not required by default; unsupported inputs are clear. |
| OI-02 | Capture an explicit, versioned, user-approved baseline per scenario | SYS-02 Baseline Registry | P0 discovery → P1 MVP candidate | OI-01 | A baseline may encode a bad result or become stale. | Baseline records normalized expected outcomes, environment/version context and approver/time; new runs never overwrite it silently. |
| OI-03 | Re-run saved scenarios after a supported plugin/configuration change or by authorized manual action | SYS-04 Run Orchestrator | P0 discovery → P1 MVP candidate | OI-01, OI-02, OI-10, OI-11 | Automated runs may create side effects, duplicate work, or run after an unsupported change. | Each run is linked to its trigger, scenario version and environment; duplicate triggers are idempotent; unsafe runs are blocked. |
| OI-04 | Normalize checkout observations: shipping method/availability/cost, payment availability, coupon/discount, tax/fees/total, validation and completion evidence | SYS-06 Observation Adapter Registry | P0 discovery → P1 MVP candidate | OI-01, OI-05 | Plugins expose inconsistent or ambiguous values (especially tax/currency). | Each value has stable field ID, currency/tax context, source and collection status; missing, unsupported and not-observed are distinct. |
| OI-05 | Show field-level expected-versus-observed diffs against approved baseline | SYS-07 Normalizer & Diff Engine | P0 discovery → P1 MVP candidate | OI-02, OI-04 | False deltas from normalization/context mismatch. | Report shows old/expected value, new/observed value, delta, scenario, time, and evidence; unchanged fields can be collapsed; no causal claim is made from timing alone. |
| OI-06 | Group results by scenario/customer cohort and identify affected versus unchanged control scenarios | SYS-10 Admin Experience | P0 discovery → P1 MVP candidate | OI-01, OI-05 | Unlike scenario contexts may be compared as if equivalent. | User can see which scenarios changed and which stayed stable; country/coupon/cart filters do not combine unlike scenarios. |
| OI-07 | Classify results as regression candidate, expected change, unchanged, flaky, unsupported or inconclusive | SYS-08 Impact Classifier | P0 discovery → P1 MVP candidate | OI-05, OI-08 | Overstated certainty or alert fatigue. | Classification includes reason and evidence; uncertainty is not labeled as a confirmed regression; user can mark expected with an audit record. |
| OI-08 | Link changes to plugin/theme version or selected settings-change context | SYS-03 Change Context Collector | P0 discovery → P1 MVP candidate | SYS-03 / platform hook research | Missed hooks and temporal coincidence may be mistaken for cause. | Report names observed change event and timestamp, indicates missing history, and labels attribution as correlation unless causality was reproduced. |
| OI-09 | Capture evidence for reproducing a difference | SYS-09 Evidence Store | P0 discovery → P1 MVP candidate | OI-03, OI-04, privacy/retention policy | Sensitive information or excessive retention in evidence. | Evidence includes redacted before/after values, test/environment context and run logs/screenshots only when justified; retention and access are configurable and documented. |
| OI-10 | Safe scenario execution without real charges or unintended customer/fulfilment effects | SYS-13 Privacy & Permission Guard | P0 blocker | OI-03, execution spike | Orders, messages, webhooks, payment, inventory, analytics, or third-party effects escape cleanup. | Execution mode is explicit; no live payment credentials are used; side effects such as orders, emails, stock, webhooks, analytics and third-party integrations have a tested prevention/cleanup strategy. Unsafe configurations are rejected or marked unsupported. |
| OI-11 | Detect platform/checkout capabilities and explain unsupported combinations | SYS-12 Compatibility Registry | P0 discovery → P1 MVP candidate | Compatibility research and test matrix | Incorrect support claims or silent false-green results. | PHP/WP/WooCommerce/checkout/HPOS and extension capabilities are checked against a sourced support matrix; the UI explains limitations without false green results. |
| OI-12 | Review or approve baseline updates and expected changes | SYS-02 Baseline Registry | P1 MVP candidate | OI-02, OI-13 | Unauthorized or silent baseline drift. | Changes require an authorized action, show exactly which fields/scenarios change, retain prior baseline, and record actor/time/reason. |
| OI-13 | Notify on material changes with deduplication and quieting controls | SYS-11 Alert Router | P1 MVP candidate | OI-07, OI-09 | Duplicate/noisy notifications or accidental leakage. | Alerts link to impact evidence; unchanged/flaky runs do not create repeated critical alerts; expected changes can be muted with expiry/review. |
| OI-14 | Localized, accessible React admin experience for scenarios, baselines, reports and history | SYS-10 Admin Experience | P1 MVP candidate | Approved requirements and API contracts | UI polish hides confusing or inaccessible states. | Keyboard/screen-reader support, translated labels, and complete loading/empty/error/unsupported states are designed and verified. |
| OI-15 | Export a support-ready impact report without unnecessary customer data | SYS-14 Reporting/Export | P2 later | OI-09, OI-14 | Export leaks store, user, or credential data. | Export reproduces the finding and source context while redacting credentials, personal data and unrelated store data. |
| OI-16 | Suggest scenario coverage or likely checks using AI | Deferred; no owner | Defer | OI-01, OI-04, privacy decision | Wrong suggestions, prompt/data leakage, or automation beyond user intent. | Only revisit after deterministic scenario/diff behavior works; suggestions are reviewable, never auto-run/auto-approve baselines, and meet privacy/cost constraints. |

## Existing category options inventory and deduplication decisions

| Observed market option | Evidence in research | Bank disposition | Why |
|---|---|---|---|
| Scheduled browser checkout / test order | Cart Lookout, ShopMonitor, CheckView, Checkout Watch, CheckOO | Represent once as a runner capability under OI-03/OI-10 | Multiple brands implement the same user outcome; separate “scheduler”, “browser bot”, “test order” are systems/mechanisms, not duplicate product options. |
| Free/local health scan and config snapshot | Cobalt, Checkout Watch, Cart Lookout, WRN Store Monitor | Treat as diagnostic inputs and context; do not duplicate as separate monitoring dashboard scope | Product is judged by cross-scenario behavioral impact; local checks do not prove real customer completion. |
| Previous-run configuration comparison | Cobalt | Keep as evidence to compare selected configuration snapshots; not the core differentiator | Config diff alone is already publicly documented and does not equal observed checkout outcome diff. |
| Country/address-specific shipping preview | Qasim Checkout Test Lab; also configurable addresses in CheckView | Fold into scenario creation + observed shipping results (OI-01/OI-04) | A one-off shipping explanation is not a saved suite, approved baseline or change-linked before/after report. This is a close competitor capability and must be tested with users. |
| Plugin/theme/settings change history | Cart Lookout, UptimeStore, Sentrix | One Change Context Collector (OI-08) | Avoid separate timeline features per source; normalize event and provenance once. |
| Failure screenshots, logs, step replay/video | Cart Lookout, ShopMonitor, CheckView, Checkout Watch | One evidence capability (OI-09) | Different media are evidence formats, not independent product systems. Defer video if cost/privacy exceeds value. |
| Real order counts, gateway failure trends, store health score | UptimeStore, Sentrix, WRN Store Monitor | Defer unless user research proves needed for impact triage | These are operational monitoring/analytics capabilities and can broaden scope without improving scenario regression. |
| AI flow builder / AI diagnosis | ShopMonitor, CheckView, WRN Store Monitor | Defer (OI-16) | AI must not be a substitute for deterministic test state, transparent evidence, or explicit baseline approval. |
| Provider-specific shipping carrier API calls | Future API research | Defer to adapter study | Checkout shipping outcomes often come from WooCommerce shipping methods; direct carrier APIs should be added only for a validated user case and tested provider flow. |
| Email/Slack/webhook alerts | CheckView, ShopMonitor, Checkout Watch, WRN Store Monitor | One Alert Router (OI-13), start with a small validated set | Avoid separate alert subsystems per channel. Decide channels after MVP workflow validation. |
| Agency/multi-site management and white-label | ShopMonitor, CheckView | P2/defer | Relevant to agencies, but not needed to prove store-level before/after scenario impact. |

## Candidate MVP boundary for user validation

The narrow MVP hypothesis is: scenario profiles + approved baseline + safe runner for explicitly supported configurations + normalized shipping/payment/coupon/tax/total observations + before/after impact view + change context + audit trail.

Do not put direct shipping-carrier APIs, an AI test builder, broad store health/analytics, auto-fixes, multi-agency operations, or CLI commands into MVP without a documented user case and approved decision.

## Open validation tasks

- Verify whether Cobalt offers multiple scenario snapshots or only whole-store configuration snapshot comparisons.
- Compare Qasim's manual shipping preview and test checkout with a persistent expected-value baseline and update-linked reruns.
- Ask users whether they need a repeatable scenario suite or whether manually checking a shipping preview is enough.
- Test support across classic checkout, Checkout Blocks, HPOS, tax/currency plugins and shipping/payment extensions.
- Confirm whether stored expectations should be exact values, ranges/tolerances, allowed method sets, or user-defined assertions.


## Commercial packaging hypothesis (not approved)

| ID | Proposal | Dependencies | Risk | Acceptance criteria before decision |
|---|---|---|---|---|
| PRICE-01 | Free tier plus a proposed $119/year paid tier | Confirm runner architecture/cost, test allowance, evidence retention, support burden, target-buyer interviews and licensing model | Hosted-test costs could exceed annual revenue; a low plan may be unsustainable or a free tier may not demonstrate enough value | Compare plugin-only vs hosted/hybrid unit cost; define included sites/scenarios/runs/history/support; test willingness to pay with store owners/agencies; record tax/renewal/upgrade terms; approve only after a costed plan and evidence-based positioning. |

See [competitor pricing snapshot](COMPETITOR-RESEARCH.md#pricing-snapshot-public-pages-2026-11-06). Hosted subscriptions and plugin licensing are not like-for-like, so competitor price points are context, not a direct price formula.
