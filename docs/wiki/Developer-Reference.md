# Developer Reference

## PHP hooks

| Hook | Type | Arguments | Purpose |
|---|---|---|---|
| `wpecir_loaded` | action | `$version` | Free plugin finished loading; add-ons boot after this |
| `wpecir_change_event_recorded` | action | `$event_id, $source, $object_type` | A plugin update or settings change was recorded |
| `wpecir_manual_report_saved` | action | `$run_id, $scenario_id` | A comparison report was saved |
| `wpecir_retention_purge_failed` | action | `WP_Error` | Daily purge failed |
| `wpecir_tracked_option_names` | filter | `string[]` | Extra option names to record in the change log |
| `wpecir_pro_active` | filter | `bool` | Add-ons return true to show the Pro badge |

## JavaScript filters (`wp.hooks`)

| Filter | Value | Context |
|---|---|---|
| `wpecir.adminTabs` | `[{ key, label, render(ctx) }]` | `{ h, apiFetch, scenarios, store, settings, go }` |
| `wpecir.reportActions` | `[ReactElement]` | `{ scenarioId, reports }` |

## REST API (`wpecir/v1`, capability `manage_woocommerce`)

| Route | Methods |
|---|---|
| `/scenarios` | GET, POST |
| `/scenarios/{id}` | GET, PUT (new version), DELETE |
| `/scenarios/{id}/versions` | GET |
| `/scenarios/{id}/baselines` | GET, POST (approve) |
| `/scenarios/{id}/comparisons` | GET |
| `/comparisons/preview` | POST |
| `/comparisons` | POST (save report) |
| `/change-events` | GET |
| `/settings` | GET, POST |

There is no route that runs checkout.

## Data

Seven tables (`wp_wpecir_scenarios`, `_scenario_versions`, `_baselines`, `_change_events`, `_runs`, `_observations`, `_findings`), schema version in option `wpecir_schema_version` (currently 2). Upgrades run automatically before any change-log read or write.
