=== Checkout Impact Regression for WooCommerce ===
Contributors: wpessential
Tags: woocommerce, checkout, regression testing, shipping, payment gateways
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.2
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See what changed at checkout after a plugin update or settings change. Compare shipping, coupons and payment methods against an approved baseline.

== Description ==

A plugin update or a small change to a shipping zone or payment gateway can quietly change what customers see at checkout: a shipping method disappears, a rate changes, a coupon stops applying or a payment method is no longer offered. Store owners usually find out from a customer.

**Checkout Impact Regression for WooCommerce** gives you a simple before-and-after check for the checkout journeys that matter most to your store.

= How it works =

1. **Save a scenario** – a customer journey: shipping country, currency, a test product and quantity, and what checkout should show (shipping method and cost, coupon and discount).
2. **Approve a baseline** – open checkout for that journey, record what you see and approve it as the known-good state.
3. **Compare after a change** – after an update or settings change, record checkout again. The plugin shows exactly which fields changed, disappeared, appeared or need review.

= Features =

* Scenario library with automatic version history – editing a scenario never overwrites the old version.
* Approved baselines with revisions, tied to the exact scenario version.
* Field-by-field comparison of shipping method, shipping cost, coupon code, coupon discount and available payment methods.
* Honest results: a value you did not record is reported as **Needs review**, never as unchanged.
* Exact money comparison (no floating-point rounding) in the scenario currency.
* Saved comparison reports with automatic deletion after 7–365 days (30 by default).
* Change log that records plugin updates and changes to WooCommerce payment and shipping settings, so you know when to compare. Setting values are never stored.
* Pickers for your store's countries, currencies, products, shipping rates and payment gateways.
* Built-in documentation page inside the plugin (WooCommerce → Checkout Impact → Docs).
* Works with High-Performance Order Storage and the Cart and Checkout blocks (it does not read or change orders).

= What this plugin does not do =

It never places orders, charges payments, sends emails to customers or opens checkout automatically. You record what checkout shows (ideally on a staging copy of your store); the plugin stores and compares it.

= Privacy =

* No data is sent to external services. No tracking, no ads.
* Data is stored in the plugin's own database tables on your site.
* Values that look like email addresses, passwords, tokens or API keys are rejected.
* Supports the WordPress personal data export and erase tools.
* Deleting the plugin keeps your data unless you turn on **Delete all Checkout Impact data when the plugin is deleted** in Settings.

= Pro add-on =

Checkout Impact Regression Pro (sold separately on the WooCommerce Marketplace) adds email alerts when a checkout-related change is recorded and CSV/JSON export of reports. Everything listed above is free and fully working without it.

= Documentation =

Open **WooCommerce → Checkout Impact → Docs** in your dashboard, or use the **Documentation** link on the Plugins screen. More information: [wpessential.org/plugins/checkout-impact-regression-for-woocommerce](https://wpessential.org/plugins/checkout-impact-regression-for-woocommerce)

== Installation ==

1. Make sure WooCommerce is installed and active.
2. Install the plugin from **Plugins → Add New** (search for "Checkout Impact Regression") or upload the ZIP file.
3. Activate it and open **WooCommerce → Checkout Impact**.
4. Follow the three steps on the Overview tab, or read the Docs tab.

== Frequently Asked Questions ==

= Does it run checkout or place test orders? =

No. You open checkout yourself and record what you see. The plugin never creates orders, carts, sessions or payments.

= Should I use it on my live store? =

You can. The plugin only stores scenarios and the values you record. For the checkout check itself we recommend a staging copy of your store or a test product.

= What is recorded in the change log? =

Plugin updates (plugin name and new version) and changes to WooCommerce options named `woocommerce_..._settings`, which include payment gateway and shipping method settings. Only the option name and a fingerprint are stored, not the values. Developers can add more option names with the `wpecir_tracked_option_names` filter.

= What happens to my data when I deactivate or delete the plugin? =

Deactivating never deletes data. Deleting keeps data too, unless you enabled the delete option under Settings.

= Who can use it? =

Users with the `manage_woocommerce` capability (Shop managers and Administrators).

= Is it compatible with HPOS and the checkout block? =

Yes. The plugin does not read or write orders and adds nothing to the storefront.

== Screenshots ==

1. Overview with the three-step workflow and recent changes.
2. Scenario builder with store countries, currencies and products, next to the scenario library.
3. Recording After values against an approved baseline.
4. Comparison result showing changed, missing and unchanged fields.
5. Saved reports with automatic retention.
6. Change log of plugin updates and settings changes.
7. Built-in documentation.

== Changelog ==

= 1.0.0 =
* First public release.
* Scenarios with version history, approved baselines, manual before/after comparison and saved reports.
* Change log with readable plugin and setting names (database schema version 2).
* Settings for report retention (7–365 days) and data removal on delete.
* New tabbed, full-width admin screen under WooCommerce, with built-in documentation.

== Upgrade Notice ==

= 1.0.0 =
First public release.
