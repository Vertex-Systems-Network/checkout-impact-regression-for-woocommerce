# Admin UX and React interface specification

**Phase:** 5 — UX and React-based admin interface  
**Status:** UX specification and interactive concept prototype complete; prototype uses fictional data and is not a working plugin screen.  
**Date:** 2026-11-06  
**Related:** [Requirements](../requirements/REQUIREMENTS.md) · [Scenario/comparison spec](SCENARIO-AND-COMPARISON-SPEC.md) · [Architecture](../architecture/ARCHITECTURE.md) · [Interactive concept prototype](admin-interface-prototype.html)

## 1. Experience goals

1. Put affected scenarios first, with customer market/cart and exact before/after values visible without opening logs.
2. Preserve the difference between changed, unchanged, not observed, unsupported, blocked and inconclusive. Incomplete evidence must never receive a green status.
3. Make the next safe action clear: review evidence, inspect context, acknowledge an expected difference or approve a new baseline.
4. Explain that a nearby update is correlation context, not proof of cause.
5. Keep privacy and safety visible. Scenario forms ask for synthetic values; run controls remain disabled until safe execution is verified.
6. Fit naturally into wp-admin with a readable, product-specific interface rather than a generic analytics dashboard.

## 2. Information architecture

| Admin destination | Primary user question | Main content |
|---|---|---|
| Overview | What changed and what needs my attention? | Impact summary, affected scenarios, unchanged controls, incomplete/unsupported runs, recent change context, explicit correlation caveat. |
| Scenarios | Which customer/cart journeys are monitored? | Searchable list, market/cart/checkout metadata, baseline version/approval state, latest comparison status, create/edit/review actions. |
| Runs & history | What ran, why, and what evidence exists? | Trigger/change context, scenario coverage, run state, filters, timestamp, runner/capability context and details. |
| Settings | What can run safely and what data is retained? | Compatibility capability report, safe execution gate, retention, permissions, privacy/export controls and notification policy. |
| Scenario builder | What exact cart and market context should be compared? | Four-step form: Cart → Customer & checkout → Expected behavior → Review and save. |
| Impact detail | What differs, how certain is it, and what can I do? | Expected/current values, comparator and collection state, scenario/environment context, redacted evidence and review actions. |

Use a dedicated WooCommerce admin page with plugin navigation, not many single-purpose wp-admin menu pages. Keep WordPress global navigation visible. No product CLI, command palette or terminal-style interaction.

## 3. Primary user flows

### First scenario to first useful impact report

1. Overview empty state explains the workflow briefly and offers Create a scenario.
2. Builder collects a cart using product search, quantity/variation controls, synthetic destination, currency/language context and checkout mode.
3. Expected-behavior step lets the user choose fields: shipping method/price, payment availability, coupon/discount, tax/fees/total and selected validation fields. Explain unavailable fields.
4. Review summarizes exact inputs and privacy use. Merchant saves a draft scenario; no run is implied.
5. Baseline capture is a separate explicit action. Show environment/capability check; require review of captured expected values before approval.
6. After a supported change/context event, Overview shows affected scenarios first and stable control scenarios alongside them.
7. Detail compares values and context. Merchant may record Expected change with a reason or create a superseding baseline explicitly. Historical results stay unchanged.

### Run and unsupported flow

- Run comparison is actionable only after the server reports the exact environment/mode is eligible.
- If capability is absent, show a disabled control with the reason and link to compatibility/safety detail; do not offer a workaround.
- A blocked run gets a visible record with “Blocked before checkout” and reason. It is not a passed/unchanged result.
- A partial run separates observed fields from timeouts and unsupported fields.
- Every observation view carries the checkout mode and version context used.

### Expected-change review

1. User opens a finding and sees changed fields, the previous baseline and current observation.
2. Choose Mark expected or Review new baseline. These actions are separate; acknowledging a finding does not replace all scenario expectations.
3. Confirmation shows exactly which fields/scenarios change, optional reason, actor and audit effect.
4. Save confirmation returns to the report with Expected change label and a history entry.

## 4. Screen anatomy and content hierarchy

### Overview

- Page title “Checkout impact” and one-line explanation.
- Safety/compatibility notice above summary cards whenever action is blocked or unsupported.
- Plain summary labels: Changed, Unchanged, Need review, Scenarios monitored. Avoid unexplained health scores.
- Primary table columns: scenario/market, latest result, short reason, time, action.
- Sort: regression candidates → partial/inconclusive → unsupported/blocked → unchanged. Do not bury unresolved results under a global green score.
- Change context panel shows source and time plus “This timing does not prove the update caused the difference.”
- When no scenarios/baselines exist, show a useful setup step rather than empty charts.

### Scenario builder

Four short, saveable steps with persistent progress. Keep back/next controls available and preserve entered fields on validation errors. Use search for large product lists. Required and optional fields are explicit; helper text explains why postcode, currency or checkout mode matters. Never put API credentials or payment details in this form.

### Impact detail

- Header: scenario, market, checkout mode, baseline version, run ID and finding label.
- First content card: changed value with Expected / Current labels, units, currency and comparator.
- Show collection state and context match near each value.
- Show unchanged controls from the same comparison window to prevent an all-store failure interpretation.
- Evidence is minimized and redacted. Raw logs are secondary support details, not the first explanation.
- Place safe review actions after evidence, not adjacent to the primary changed value.

### Runs & history

Filter by scenario, trigger, date and run state. Keep run state separate from finding classification. A blocked/failed run is never rendered as unchanged. Details show requested/attempted/completed counts so missing scenarios cannot disappear.

## 5. React component and application structure

Production target: React rendered within a WooCommerce wp-admin page. Use WordPress-provided React/runtime and stable @wordpress/components patterns where they fit. WordPress documents these components for dashboard interfaces and provides React through wp-element. Avoid @wordpress/ui for production-critical surfaces while it is documented as experimental; revisit only if its stability/support policy becomes suitable.

| React area | Responsibility | Example subcomponents |
|---|---|---|
| App shell | Plugin navigation, page title, route boundary, notices | AdminApp, PluginNav, PageHeader, CapabilityBanner |
| Scenario catalog | Search/filter/list and lifecycle controls | ScenarioTable, ScenarioStatus, ScenarioFilters |
| Scenario wizard | Local form state/validation; typed save action to PHP | CartStep, MarketStep, AssertionsStep, ReviewStep |
| Comparison/report | Present server-provided findings; no client-side diff/classification | ImpactSummary, DiffRow, ContextPanel, EvidenceList |
| Run history | Run state, scenario coverage and history filters | RunTable, RunStateBadge, RunDetail |
| Settings/capabilities | Explain version/mode support, privacy and retention choices | CompatibilityPanel, PrivacySettings, RetentionControl |
| Shared feedback | Async, empty, blocked, warning and confirmation states | LoadingRegion, EmptyState, ErrorNotice, ConfirmDialog, ScreenReaderStatus |

### State and API boundaries

- PHP/WordPress services remain authoritative for permissions, capability checks, validation, persistence, comparison and classification.
- React sends bounded requests through WordPress apiFetch/REST patterns with nonce and permission checks; hiding a button is never authorization.
- React may own temporary form/navigation state. It must not infer regressions or update baselines from the last successful run.
- API responses carry run state, collection state, finding classification, provenance and context-fingerprint status.
- No browser request submits checkout, calls a provider's live API, or changes merchant WooCommerce configuration.
- Use WordPress script dependencies for wp-element, wp-components and relevant packages instead of bundling another React copy unless production build/compatibility decision explicitly requires it.

## 6. Visual language and interaction system

- **Canvas:** WordPress admin neutral background, white content surfaces, thin borders and generous spacing between sections.
- **Primary action:** WordPress admin blue for create/save/review. High-risk actions require a separate confirmation and are never the default emphasis.
- **Status:** pair text with icon/shape and color. Red = material difference requiring review; amber = partial/unsupported/inconclusive/blocked; green = verified unchanged. Labels must work in monochrome.
- **Typography:** familiar dashboard hierarchy with distinct page title, section title, field label and helper copy. Avoid tiny all-caps headings and dense card grids.
- **Numbers:** align monetary values and show currency code, tax basis and expected/current labels. Do not rely on color/arrows alone.
- **Tables:** semantic headers, clear row action names, visible focus; use responsive labeled rows at narrow widths if clearer than horizontal scrolling.
- **Motion:** no critical information conveyed only through animation. Respect reduced-motion settings.
- **Copy:** plain language, one concept per label. Use Regression candidate until evidence supports stronger wording. Say Not observed for missing capture; say missing only when a complete supported capture proves absence.
- **Density:** one primary action per screen. Avoid summary cards that duplicate table columns or repeat actions.

## 7. Required UI states

| State | User-facing treatment | Action available |
|---|---|---|
| First-use empty | Explain scenario → baseline → compare in three steps. | Create scenario; read privacy/safety explanation. |
| Loading | Stable page frame, specific status text and meaningful progress. | Cancel a supported async job only if cancellation is safe. |
| Save validation error | Identify field, preserve entries, focus/announce first error, explain correction. | Edit and retry. |
| Network/server error | Say whether a result was saved/confirmed; include safe retry and support reference. | Retry only idempotent action; no duplicate run. |
| No baseline | Explain comparison is unavailable until a reviewed baseline exists. | Review first captured values and explicitly approve. |
| Changed/regression candidate | Show exact expected/current values, context and evidence. | Investigate, acknowledge expected or review baseline. |
| Unchanged | State which monitored fields were captured and compared. | View details/history. |
| Partial/inconclusive | Identify failed/mismatched fields and comparable fields separately. | Inspect capability or retry only when safe. |
| Unsupported | Name exact checkout mode/integration/version gap. | View compatibility detail; no run action until support is verified. |
| Blocked by safety gate | Say “Run blocked before checkout” and give safe reason. | View safety requirements; no bypass action. |
| Permission denied | Explain insufficient access without exposing hidden data. | Ask an authorized administrator. |
| Retention/deletion pending | Explain which data remains and deletion state. | View status; do not silently dismiss. |

## 8. Accessibility and responsive behavior

WordPress's accessibility standard for ecosystem code is WCAG 2.2 AA. Design acceptance criteria:

- Semantic page landmarks and heading order; every input has visible programmatic label and associated help/error text.
- Keyboard-only navigation for navigation, filters, tables, wizard and dialogs; visible focus; logical focus restoration after dialog close or route change.
- Status changes and async completion/errors announced with suitable live regions; avoid unexpected focus movement.
- Regression/unchanged/blocked state uses text plus icon/shape and color, with sufficient contrast.
- Comparison tables have clear row/column headers; screen-reader users can identify expected/current fields and units.
- Confirmation dialogs manage focus while open, have title and cancel action, and return focus to the initiating control.
- At narrow widths, preserve reading order and actions; no hover-only controls or mandatory horizontal scrolling for primary findings.
- Respect reduced motion and browser zoom. Avoid color-only maps, tiny text, icon-only unnamed buttons and auto-advancing content.

## 9. Prototype and review scope

[Interactive concept prototype](admin-interface-prototype.html) contains Overview, Scenarios, a four-step builder entry, impact detail, Runs & history, and Settings examples. Its figures are fictional. It does not connect to WordPress, call Store API, save settings, create scenarios or execute checkout. The Run a comparison affordance explicitly reports blocked pending safety validation.

This is a visual/interaction artifact, not production code. The production interface remains React using WordPress packages after the design and REST contracts are approved.

## 10. Phase 5 acceptance criteria

- A store operator can understand the overview, create/review a scenario, inspect before/after results and navigate history using the prototype.
- Interaction design distinguishes run status from finding status and never conflates incomplete/unsupported with unchanged.
- Every visible action has a defined permission/data effect or is clearly marked prototype-only.
- Loading, empty, error, validation, partial, blocked, unsupported and success states are specified.
- React component map identifies concern ownership; PHP retains authorization and comparison authority.
- Accessibility and responsive criteria are explicit and source-backed.
- README and development plan reflect Phase 5 completion only as UX/prototype deliverables; runtime implementation/testing remain Phase 7.

## Sources checked 2026-11-06

- [WordPress components package](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-components/) — reusable UI patterns.
- [WordPress React components for plugin pages](https://developer.wordpress.org/news/2024/03/how-to-use-wordpress-react-components-for-plugin-pages/) — React admin-page approach.
- [WordPress accessibility coding standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/accessibility/) — WCAG 2.2 AA expectation.
- [WordPress accessibility testing guide](https://developer.wordpress.org/block-editor/contributors/accessibility-testing/) — keyboard and assistive-technology review guidance.
- [WordPress @wordpress/ui package](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-ui/) — package is experimental; it is not selected as the default production dependency.
