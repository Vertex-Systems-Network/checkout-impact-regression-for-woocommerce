# Getting Started

1. Install and activate **WooCommerce**.
2. Install **Checkout Impact Regression for WooCommerce** from *Plugins → Add New* (or upload the ZIP) and activate it.
3. Open **WooCommerce → Checkout Impact**.

## Your first comparison

1. **Scenarios tab → Create a scenario.** Pick the shipping country, currency, a test product and quantity. Optionally add what checkout should show (shipping method such as `flat_rate:3`, shipping cost, coupon and discount). Save.
2. **Open checkout yourself** for that journey – a staging copy of your store is best.
3. **Compare tab.** Choose the scenario. In the **After** column, record each field as *Observed* (with the value) or *Confirmed missing*. Click **Approve After as baseline**.
4. **Make your change** – update a plugin, edit a shipping zone, switch a gateway.
5. Open checkout again, record the new values in **After**, click **Compare**. The result shows what changed. Click **Save report** to keep it.

Tip: start with your most important journey – your biggest shipping country with your best-selling product.
