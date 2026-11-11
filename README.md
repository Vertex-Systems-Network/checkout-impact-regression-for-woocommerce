# Checkout Impact Regression for WooCommerce

By [WPEssential](https://wpessential.org) · Plugin page: <https://wpessential.org/plugins/checkout-impact-regression-for-woocommerce>

See what changed at checkout after a plugin update or settings change. Save checkout scenarios, approve a known-good baseline, and compare shipping method, shipping cost, coupon, discount and payment methods field by field.

> Example: after updating a shipping plugin, the Germany scenario shows *flat_rate:1 → missing*, shipping cost *EUR 7 → EUR 12*, coupon unchanged.

The plugin never places orders or runs checkout. You record what checkout shows (ideally on a staging copy) and the plugin stores and compares it.

## Status

**Version 1.0.0 – release candidate.** Last updated 2026-11-12.

| Phase | Status |
|---|---|
| 0–5 Brief, research, requirements, architecture, scenario design, UX | Complete (2026-11-06) |
| 6 Technical proof for *automatic* checkout replay | Closed as **NO-GO** – automatic mode deferred to v1.x |
| 7 Implementation (v1.0 manual comparison scope) | 93% – 26 of 28 rows complete; 2 partial (IMPL-010 failure UI, IMPL-011 MySQL/accessibility checks) |
| 8 Release (WordPress.org + WooCommerce Marketplace) | 25% – packages, readme, assets and checklists ready; submissions need the owner's accounts |

**Overall: 91%** (formula and row list in [the plan, §8.1–9](docs/DEVELOPMENT-PLAN.md)).

```
Overall  [██████████████████░░] 91%
Phase 7  [██████████████████▌░] 93%  (v1 scope)
Phase 8  [█████░░░░░░░░░░░░░░░] 25%
```

## Editions

| | Free – WordPress.org | Pro – WooCommerce Marketplace |
|---|---|---|
| Scenarios, versions, approved baselines | ✓ | ✓ |
| Before/after comparison and saved reports | ✓ | ✓ |
| Change log of plugin updates and settings changes | ✓ | ✓ |
| Retention and data-removal settings, privacy tools | ✓ | ✓ |
| In-plugin documentation (WooCommerce → Checkout Impact → Docs) | ✓ | ✓ |
| Email alerts when a change is recorded | – | ✓ |
| CSV / JSON report export | – | ✓ |

The free plugin has no locked or time-limited features. Pro is a separate plugin in [`pro/`](pro/checkout-impact-regression-pro) that requires the free one.

## Requirements

WordPress 6.9+, PHP 8.2+, WooCommerce 10.8+. Verified on WordPress 7.1.4, WooCommerce 11.2.2, PHP 8.3 (see [evidence](docs/validation/PHASE-7-TASK-EVIDENCE.md)).

## Development

```bash
composer install
for t in tests/php/*-contract.php tests/php/normalizer-diff.php tests/php/multilingual-context-dry-run.php; do php "$t" || exit 1; done
php tests/php/uninstall-contract.php erase
./bin/build.sh        # dist/checkout-impact-regression-for-woocommerce-<v>.zip and dist/checkout-impact-regression-pro-<v>.zip
```

CI (`.github/workflows/ci.yml`) runs lint and contract tests on PHP 8.2–8.4, builds both ZIPs and runs the official WordPress.org Plugin Check. Download packages from the run's **plugin-packages** artifact.

## Documentation

- [Development plan](docs/DEVELOPMENT-PLAN.md) – scope, decisions, backlog, progress formula
- [WordPress.org submission checklist](docs/release/WPORG-SUBMISSION.md)
- [WooCommerce Marketplace checklist](docs/release/WOO-MARKETPLACE-SUBMISSION.md)
- [Phase 7 evidence](docs/validation/PHASE-7-TASK-EVIDENCE.md)
- AI contributors: start with [AGENTS.md](AGENTS.md)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
