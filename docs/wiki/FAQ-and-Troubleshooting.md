# FAQ and Troubleshooting

**Does it place test orders?** No. It never runs checkout, creates orders or charges payments.

**The Compare tab says the baseline belongs to an older version.** You edited the scenario. Record After values again and approve a new baseline.

**"Approve After as baseline" is disabled.** Every After field must be *Observed* (with a value) or *Confirmed missing*.

**A money value is rejected.** Use a plain number like `7.50` – no currency sign, no thousands separator, at most 6 decimals.

**Nothing appears in the Change log.** Only plugin updates and `woocommerce_…_settings` options are recorded. Shipping-zone membership changes are stored elsewhere by WooCommerce and are not recorded.

**Pro alerts are not arriving.** Check that your site can send email (an SMTP plugin helps). After three failed attempts the Alerts tab shows the time of the last failure.

**"Checkout Impact Regression for WooCommerce could not load its files."** The ZIP was incomplete. Reinstall the full package from WordPress.org.
