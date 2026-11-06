# Phase 6 — Change-source hooks spike

**Status:** Hook responsibilities and limits mapped from official references; implementation/runtime coverage not tested.  
**Date:** 2026-11-06  
**Task:** SPIKE-006  
**Evidence class:** Source-backed event-capture proposal. No update or settings event was executed.

## Objective

Define how the product can record that a plugin update or supported checkout-related setting change happened, without claiming that the event caused a later checkout difference.

## Plugin update event

WordPress documents the upgrader_process_complete action with an upgrader object and hook_extra payload. For plugin update flows, hook_extra can identify update action, plugin type, bulk status and plugin basenames. This is a useful update-event signal but not a complete change detector.

Proposed handling:

1. Subscribe to upgrader_process_complete and ignore non-plugin actions/types.
2. Normalize the updated plugin basename list and record an event timestamp, bulk flag, actor/context if safely available, and observed before/after versions.
3. Use a separate environment fingerprint after the update completes; do not infer the final active version solely from hook payload.
4. Queue a comparison job only after the store is stable and safety gates permit it. Do not run checkout observations inside the upgrader callback.
5. Treat this as temporal correlation. If an update flow bypasses the WordPress upgrader, a scheduled fingerprint may detect version drift later but cannot prove the exact update time or actor.
6. If the plugin itself is being upgraded, WordPress warns the callback may execute from the old plugin version. The event writer must be backward-tolerant or defer minimal event data to a compatible follow-up request.

## Settings-change event

WordPress exposes updated_option after a successful option change, and update_option_{option} for an individual option. WooCommerce settings flows also expose settings page/section update actions and component-specific WC_Settings_API save behavior.

Proposed handling:

- Maintain an explicit allowlist of checkout-impacting option keys and setting owners. Do not persist every updated_option payload.
- For array-valued options, compare only allowlisted non-secret subkeys and canonicalize before/after values; exclude credentials, tokens, passwords, personal data and opaque secrets.
- Use WooCommerce settings hooks as context metadata where available; use option-level changes as the source of normalized value changes. Avoid treating a broad settings-page action as proof that a particular value changed.
- Support direct WC REST settings changes only when the same allowlisted option change is observed. Do not store raw request bodies.
- Treat custom tables, direct database writes, extension-specific stores and unrecognized settings as unknown until a provider-specific adapter or fingerprint detects them. A changed fingerprint without a known event is “configuration drift; source unknown.”
- Collapse multiple option writes within one settings save into one logical change group; preserve per-option safe fingerprints for evidence.

## Security, performance and causality boundaries

- Event capture should do bounded local normalization only; no network calls, checkout run, or expensive full-site fingerprinting in synchronous hooks.
- Use a queue/outbox with idempotency key from event type, entity, safe value fingerprint and event window.
- Avoid logging raw values. For secrets, report “changed” only if a supported safe comparator exists; otherwise mark context changed/incomparable.
- Debounce bulk updates and multi-option saves; store the affected plugin basenames/option IDs without exposing secret values.
- A run linked to a change event is “associated with” or “after” that event. The report must not say the event caused the regression unless a controlled reproduction establishes causation.
- Any unsupported event path should result in environment drift or unknown source, never a false claim that no changes occurred.

## Candidate event contract

| Field | Meaning |
|---|---|
| event_id | Stable idempotency key; not a secret or raw setting value. |
| event_type | plugin_updated, setting_changed, environment_drift, or unknown_change. |
| entity_ref | Plugin basename or allowlisted option key; redact unknown identifiers where needed. |
| occurred_at | WordPress timestamp with timezone/UTC normalization. |
| source | upgrader_hook, woocommerce_settings_hook, option_hook, or fingerprint_scan. |
| old_version/new_version | Plugin versions when reliably observed; nullable with explicit reason. |
| safe_old_hash/safe_new_hash | Keyed/canonical digest only for allowlisted non-secret comparable fields; never a hash of low-entropy secret values. |
| group_id | Correlates multiple option/plugin changes from one bulk action/settings save. |
| confidence | observed, inferred_from_fingerprint, or unknown; not causal confidence. |
| redaction_profile | Version of field allowlist and redaction policy used. |

## Runtime acceptance cases

Add these to the Phase 6 test plan:

- **CHG-01:** single plugin update produces one normalized event with the correct plugin identity and resulting version.
- **CHG-02:** bulk plugin update records every affected plugin, deduplicates duplicate hook observations, and never runs comparisons during the upgrader callback.
- **CHG-03:** auto-update flow produces an event or is detected later as version drift, with source/time uncertainty visible.
- **CHG-04:** allowlisted WooCommerce settings change produces a safe before/after fingerprint without raw option leakage.
- **CHG-05:** unchanged option does not produce a semantic change event.
- **CHG-06:** secret-valued setting changes reveal only a changed/unknown state and never expose raw value or a reusable plain hash.
- **CHG-07:** settings changes stored outside WordPress options are not falsely reported unchanged; unsupported/source-unknown classification is visible.
- **CHG-08:** correlated checkout result remains “after change” unless a controlled reproduction independently demonstrates causality.

## Sources reviewed 2026-11-06

- [WordPress upgrader_process_complete](https://developer.wordpress.org/reference/hooks/upgrader_process_complete/) — upgrader completion hook and update context.
- [WordPress updated_option](https://developer.wordpress.org/reference/hooks/updated_option/) — successful option updates and before/after values.
- [WordPress update_option](https://developer.wordpress.org/reference/functions/update_option/) — option lifecycle hooks.
- [WooCommerce Settings API](https://developer.woocommerce.com/docs/extensions/settings-and-config/settings-api) — extension settings save flow.
- [WooCommerce settings pages](https://developer.woocommerce.com/docs/extensions/settings-and-config/extend-wc-settings-page) — page/section save hooks.

## Completion boundary

SPIKE-006 is complete as a change-source contract and limitation map. Update automation, raw setting storage, provider-specific settings, skipped-upgrader changes and causal attribution remain unvalidated; event detection must stay conservative until runtime tests pass.
