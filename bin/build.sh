#!/usr/bin/env bash
# Builds dist/checkout-impact-regression-for-woocommerce-<version>.zip (WordPress.org).
# With PRO_DIR=<path to a checkout of checkout-impact-regression-pro-for-woocommerce>
# it also builds the Pro ZIP into the same dist/, so both packages always ship together.
# Only allowlisted runtime files are packaged; tests, docs and AI files never ship.
set -euo pipefail
cd "$(dirname "$0")/.."

SLUG=checkout-impact-regression-for-woocommerce
version() { grep -m1 -E '^\s*\*\s*Version:' "$1" | sed -E 's/.*Version:\s*//' | tr -d '[:space:]'; }
VERSION=$(version "$SLUG.php")
CONST=$(grep -oE "WPECIR_VERSION', '[^']+'" "$SLUG.php" | grep -oE "[0-9][^']*")
[ "$VERSION" = "$CONST" ] || { echo "WPECIR_VERSION ($CONST) != plugin Version ($VERSION)"; exit 1; }

# readme.txt is generated from wordpress-org/readme.md (WPEssential readme system).
php tools/readme-to-txt.php --check

composer install --no-interaction --no-dev --optimize-autoloader --quiet

rm -rf build/$SLUG && mkdir -p build/$SLUG dist
rm -f dist/$SLUG-*.zip dist/$SLUG-*.zip.sha256
cp -R "$SLUG.php" uninstall.php readme.txt LICENSE composer.json assets includes vendor build/$SLUG/
find build -name '.DS_Store' -delete

(cd build && zip -qr ../dist/$SLUG-$VERSION.zip $SLUG)
unzip -tq dist/$SLUG-$VERSION.zip >/dev/null
(cd dist && sha256sum $SLUG-$VERSION.zip > $SLUG-$VERSION.zip.sha256)
for required in "$SLUG.php" vendor/autoload.php readme.txt; do
	unzip -Z1 dist/$SLUG-$VERSION.zip | grep -qx "$SLUG/$required" || { echo "missing $required"; exit 1; }
done
if unzip -Z1 dist/$SLUG-$VERSION.zip | grep -qE '/(tests|docs|tools|\.github)/|AGENTS\.md|CLAUDE\.md|/pro/'; then echo "dev or Pro files leaked into the free ZIP"; exit 1; fi

if [ -n "${PRO_DIR:-}" ]; then
	PRO_DIR=$(realpath "$PRO_DIR")
	FREE_DIR="$PWD" "$PRO_DIR/bin/build.sh" --pro-only
	cp "$PRO_DIR"/dist/checkout-impact-regression-pro-for-woocommerce-*.zip* dist/
fi
ls -lh dist
