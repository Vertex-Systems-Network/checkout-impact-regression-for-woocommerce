# Change Log

The plugin records, automatically:

- **Plugin updates** – plugin name and new version.
- **WooCommerce settings changes** stored as `woocommerce_…_settings` options – this covers payment gateways and shipping method instances.

Only the option name and a fingerprint are stored, never the values. Secrets, emails, phone numbers and addresses are removed before the fingerprint is calculated.

A recorded change is a reminder to compare your scenarios; it does not prove checkout changed.

Developers can track more options with the `wpecir_tracked_option_names` filter (see [[Developer Reference]]).
