#!/usr/bin/env bash
# Builds the installable ZIPs:
#   dist/checkout-impact-regression-for-woocommerce-<version>.zip  (WordPress.org, free)
#   dist/checkout-impact-regression-pro-<version>.zip              (WooCommerce Marketplace, Pro)
# Only whitelisted runtime files are packaged; tests, docs and AI files never ship.
set -euo pipefail
cd "$(dirname "$0")/.."

FREE_SLUG=checkout-impact-regression-for-woocommerce
PRO_SLUG=checkout-impact-regression-pro
version() { grep -m1 -E '^\s*\*\s*Version:' "$1" | sed -E 's/.*Version:\s*//' | tr -d '[:space:]'; }
FREE_VERSION=$(version "$FREE_SLUG.php")
PRO_VERSION=$(version "pro/$PRO_SLUG/$PRO_SLUG.php")
STABLE=$(grep -m1 '^Stable tag:' readme.txt | sed -E 's/Stable tag:\s*//' | tr -d '[:space:]')
CONST=$(grep -oE "WPECIR_VERSION', '[^']+'" "$FREE_SLUG.php" | grep -oE "[0-9][^']*")
[ "$FREE_VERSION" = "$STABLE" ] || { echo "readme Stable tag ($STABLE) != plugin Version ($FREE_VERSION)"; exit 1; }
[ "$FREE_VERSION" = "$CONST" ] || { echo "WPECIR_VERSION ($CONST) != plugin Version ($FREE_VERSION)"; exit 1; }

composer install --no-interaction --no-dev --optimize-autoloader --quiet

rm -rf dist build && mkdir -p dist build/$FREE_SLUG build/$PRO_SLUG
cp -R "$FREE_SLUG.php" uninstall.php readme.txt LICENSE assets includes languages vendor build/$FREE_SLUG/
cp -R pro/$PRO_SLUG/. build/$PRO_SLUG/
cp LICENSE build/$PRO_SLUG/
find build -name '.gitkeep' -delete
find build -name '.DS_Store' -delete

(cd build && zip -qr ../dist/$FREE_SLUG-$FREE_VERSION.zip $FREE_SLUG && zip -qr ../dist/$PRO_SLUG-$PRO_VERSION.zip $PRO_SLUG)
for z in dist/*.zip; do
	unzip -tq "$z" >/dev/null
	(cd dist && sha256sum "$(basename "$z")" > "$(basename "$z").sha256")
done
unzip -Z1 dist/$FREE_SLUG-$FREE_VERSION.zip | grep -qx "$FREE_SLUG/$FREE_SLUG.php"
unzip -Z1 dist/$FREE_SLUG-$FREE_VERSION.zip | grep -qx "$FREE_SLUG/vendor/autoload.php"
unzip -Z1 dist/$FREE_SLUG-$FREE_VERSION.zip | grep -qx "$FREE_SLUG/readme.txt"
if unzip -Z1 dist/$FREE_SLUG-$FREE_VERSION.zip | grep -qE '/(tests|docs|pro|\.github)/|AGENTS\.md|CLAUDE\.md'; then echo "dev files leaked into free ZIP"; exit 1; fi
ls -lh dist
