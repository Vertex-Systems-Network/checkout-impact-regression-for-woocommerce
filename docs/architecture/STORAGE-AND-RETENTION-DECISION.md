# Storage and retention decision

**Status:** Accepted design; v0.2.0 schema scaffold is present in source, but runtime migration, record access, privacy/retention behavior, and compatibility remain unverified.  
**Date:** 2026-11-06  
**Owner:** Product/engineering (planning authorization recorded)  
**Related:** [Architecture](ARCHITECTURE.md), [Requirements](../requirements/REQUIREMENTS.md), [Phase 6 closure](../validation/PHASE-6-CLOSURE-DECISION.md)

## Accepted planning choice

Use versioned, per-site custom database tables for scenario versions, approved baseline versions, change events, run records, normalized observations, and findings. Keep small configuration values in non-autoloaded WordPress options. Do not store growing run history or serialized scenario suites in autoloaded options. This choice is accepted for planning; it does not authorize skipping the runtime proof gates below.

This recommendation is based on the product's related versioned records, indexed history queries, retention cleanup, and explicit access/deletion lifecycle. WordPress guidance says to prefer post meta where practical; the implementation review must document why this relational history and lifecycle are not practical as post meta. If the implementation cannot justify that boundary, reconsider private custom post types plus metadata.

Use `$wpdb->prefix` for site-scoped table names and WordPress-supported schema versioning/upgrade routines. Do not support network-wide multisite operation in the initial release. A multisite network may use per-site plugin activation only after per-site isolation, upgrade, cleanup, and permissions are proven; network activation remains unsupported initially.

## Planning lifecycle decision

- Scenario definitions and approved baselines are versioned and retained until an authorized store administrator deletes them.
- Run details, observations, and findings use a proposed default retention of 30 days, configurable from 7 to 365 days. Purge expired records in bounded background batches; preserve referential integrity and surface cleanup failures.
- On uninstall, preserve data by default. Offer an explicit administrator-controlled erase option and use the WordPress uninstall path to remove all plugin tables/options only when that choice is enabled.
- Deactivation does not delete merchant records.
- Store only synthetic/minimized destination attributes required for zone resolution. Never store card data, gateway credentials, real shopper names, email addresses, or full street addresses in scenario/run records.
- Register WordPress privacy exporter/eraser support for any personal data that could enter supported fields despite the synthetic-data default; redact before export and honor deletion requests.
- The first implementation stores no payment or carrier API credentials. If a later release adds provider credentials, require a separate secret-storage decision; do not assume database storage is encrypted by the plugin.

## Acceptance gates before storage is usable or supported

1. Exact supported WordPress/database versions are chosen from the compatibility plan.
2. Disposable runtime migration evidence covers fresh install, repeat activation, upgrade from prior schema, failed/partial migration recovery, per-site prefixing, uninstall preserve/delete, bounded retention cleanup, and privacy export/erasure.
3. Security review confirms prepared queries, capability checks, nonces on mutations, output escaping, least-privilege table access, and no secrets/PII in logs.
4. Performance evidence bounds indexes, query patterns, cleanup batch size, and storage growth.

A v0.2.0 source scaffold now declares the planned per-site tables and guarded schema-version migration because implementation work has started. The schema has not been applied on the test site, no scenario record API or UI exists, and no scenario data is currently stored. Do not treat the scaffold as supported storage or enable record writes until all gates above pass. This does not lift the Phase 6 checkout runtime block.

## Primary references

- [WordPress Plugin Handbook: Creating Tables with Plugins](https://developer.wordpress.org/plugins/creating-tables-with-plugins/) — use the site prefix, schema versioning and `dbDelta`; consider post meta first where practical.
- [WordPress Plugin Handbook: Activation/Deactivation Hooks](https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/) — distinguishes temporary deactivation cleanup from permanent uninstall deletion.
- [WordPress Privacy Handbook: Personal Data Exporter and Eraser](https://developer.wordpress.org/plugins/privacy/adding-the-personal-data-exporter-to-your-plugin/) — document and implement applicable export/erasure handlers.
