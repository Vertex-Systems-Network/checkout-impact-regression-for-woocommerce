# WordPress.org submission checklist – free plugin

**Plugin:** Checkout Impact Regression for WooCommerce · **Slug:** `checkout-impact-regression-for-woocommerce` · **Version:** 1.0.0
**WordPress.org account:** `wpessential` (listed in `readme.txt` → Contributors)
**Plugin URI:** https://wpessential.org/plugins/checkout-impact-regression-for-woocommerce

## Ready in the repository (2026-11-12)

| Item | Where | State |
|---|---|---|
| Main file with full header (Name, URI, Version, Requires at least/PHP/Plugins, Author, License, Text Domain, WC headers) | `checkout-impact-regression-for-woocommerce.php` | Done |
| `readme.txt` generated from `wordpress-org/readme.md` by `tools/readme-to-txt.php` (WPEssential readme system; checks Stable tag, ≤5 tags, ≤150-char summary, required sections incl. External services) | `wordpress-org/readme.md` | Done |
| GPL-2.0-or-later licence file | `LICENSE` | Done |
| Text domain = slug, all strings translatable, `languages/` folder | code | Done |
| No external calls, no tracking, no remote assets, no locked features | code review | Done |
| Data sanitised, capability + nonce checks on every REST route | code + runtime test (anonymous/subscriber 403) | Done |
| Uninstall keeps data by default, opt-in erase removes everything | runtime test | Done |
| Privacy exporter/eraser + suggested policy text | runtime test | Done |
| Icon 128/256, banner 772×250 / 1544×500, screenshots 1–7, Live Preview blueprint | `wordpress-org/` | Done |
| User documentation | `docs/wiki/` → GitHub Wiki (enable Wiki in repo settings first) | Pages done |
| Package ZIP without dev files | `./bin/build.sh` or CI artifact **free-plugin-package** | Done |
| Plugin Check (official action) | CI job **WordPress.org Plugin Check** | Pass (CI run 43103077615) |

## Owner steps (need your WordPress.org login)

1. Merge the pull request, then download `checkout-impact-regression-for-woocommerce-1.0.0.zip` from the CI **free-plugin-package** artifact (or run `./bin/build.sh`).
2. Make sure the Plugin Check job is green. Fix any error it reports before uploading.
3. Log in as `wpessential` at <https://wordpress.org/plugins/developers/add/>, accept the guidelines and upload the ZIP. Confirm the slug shown is `checkout-impact-regression-for-woocommerce`.
4. Answer the review email from the Plugins Team within 7 days. Reply from the same account; re-upload a fixed ZIP from the same page if asked.
5. After approval, add repository secrets `SVN_USERNAME` (= `wpessential`) and `SVN_PASSWORD` (an SVN application password from your WordPress.org profile).
6. Publish GitHub release `v1.0.0`. The **Deploy free plugin to WordPress.org** workflow copies the code to SVN `trunk`, tags `1.0.0` and uploads `wordpress-org/` to `assets/`.
7. Create the page <https://wpessential.org/plugins/checkout-impact-regression-for-woocommerce> (the Plugin URI) with the description, the documentation from the Docs tab and a support link.

## Review notes to keep ready

- The plugin does not run checkout or create orders; it stores values the admin records.
- "WooCommerce" is used only in the "for WooCommerce" suffix; no WooCommerce logo in assets.
- The readme mentions the separate Pro add-on once; the free plugin contains no Pro code, upsell banners or license checks.
