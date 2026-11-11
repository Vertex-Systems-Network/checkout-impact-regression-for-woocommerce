# Scenarios

A scenario is one customer journey:

| Field | Example | Notes |
|---|---|---|
| Name | Germany — T-shirt — WELCOME10 | Your label |
| Shipping country | DE | Picked from WooCommerce countries |
| Currency | EUR | Picked from WooCommerce currencies |
| Test product + quantity | Organic T-shirt × 1 | Up to 200 published products are offered; quantity 1–100 |
| Expected shipping method | `flat_rate:3` | WooCommerce rate ID (method:instance) |
| Expected shipping cost | 7.50 | Plain number, up to 6 decimals |
| Coupon + expected discount | WELCOME10 / 5 | Discount needs a coupon |

## Versions

Editing a scenario never overwrites it: it saves a **new version** and keeps the old ones (Scenarios → *Versions*). A baseline belongs to one version, so after editing you approve a new baseline.

## Deleting

Deleting a scenario removes its versions, baselines and reports. This cannot be undone.
