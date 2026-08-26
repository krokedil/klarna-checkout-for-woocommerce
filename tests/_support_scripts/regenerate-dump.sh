#!/usr/bin/env bash
#
# Regenerate tests/Support/Data/dump.sql from a clean WordPress install.
#
# This is the documented wp-browser workflow (see `vendor/bin/codecept
# wp:db:export`) plus one extra post-processing step: every WP boot
# (including the one wp:db:export performs) schedules Action Scheduler
# actions whose `schedule` column stores a PHP-serialized object. The
# SQLite Database Integration plugin can't round-trip that data without
# truncating it at the first NUL byte (`\0` from protected-property
# prefixes), so importing the dump on a fresh suite run triggers
# `unserialize(): Error at offset N` and aborts WP load. We work around
# that by stripping the AS data rows from the dump after export, the
# schema stays, and Action Scheduler repopulates the tables at runtime
# (those runtime rows are not read back in the same request, so the
# corruption is harmless to test execution).
#
# Run from the plugin root:
#
#   bash tests/_support_scripts/regenerate-dump.sh
#
# Requires `wp` (WP-CLI) on PATH and the codecept binary in vendor/bin.
#
# Pre-conditions:
#   - `composer install` has run (tests/_wordpress exists with WC + KCO
#     symlinked in).
#   - `npm install && npm run build` has run so KCO's frontend assets are
#     present (the regen doesn't strictly need them, but a checkout
#     visit during dev verification will).

set -euo pipefail

# wp-cli refuses to run as root by default; let it through for CI/docker
# environments. Harmless when run as a normal user.
export WP_CLI_ALLOW_ROOT=1

WP_ROOT="tests/_wordpress"
DB_FILE="${WP_ROOT}/data/db.sqlite"
DB_SNAPSHOT="${WP_ROOT}/data/db.sqlite_snapshot"
DUMP_PATH="tests/Support/Data/dump.sql"
WP="wp --path=${WP_ROOT}"

echo "==> Stopping dev servers (best-effort)..."
vendor/bin/codecept dev:stop >/dev/null 2>&1 || true

echo "==> Wiping SQLite DB..."
rm -f "${DB_FILE}" "${DB_SNAPSHOT}"

# The admin password below is a placeholder on purpose, so no real credential
# ends up in the committed dump. tests/_mu-plugins/05-kustom-test-admin-password.php
# resets it to WORDPRESS_ADMIN_PASSWORD at request time.
echo "==> Installing WordPress..."
${WP} core install \
--url="http://localhost:5512" \
--title="Kustom Checkout Test" \
--admin_user="admin" \
--admin_email="admin@localhost.test" \
--admin_password="placeholder" \
--skip-email

echo "==> Setting permalinks..."
${WP} rewrite structure '/%postname%/' --hard

echo "==> Activating plugins (WooCommerce first, then KCO)..."
${WP} plugin activate woocommerce
${WP} plugin activate klarna-checkout-for-woocommerce

echo "==> Activating Storefront theme..."
${WP} theme activate storefront

echo "==> Setting WooCommerce defaults (SE / SEK, skip wizard)..."
${WP} option update woocommerce_default_country "SE"
${WP} option update woocommerce_currency "SEK"
${WP} option update woocommerce_store_address "Test Street 1"
${WP} option update woocommerce_store_city "Stockholm"
${WP} option update woocommerce_store_postcode "11122"
${WP} option update woocommerce_calc_taxes "yes"
${WP} transient delete _wc_activation_redirect || true
${WP} option update woocommerce_admin_install_timestamp "$(date +%s)" || true
${WP} option update woocommerce_terms_page_id 3

echo "==> Change the checkout page to only contain the [woocommerce_checkout] shortcode (removing the default content that confuses wp:db:export's search-and-replace) and make sure it's published..."
CHECKOUT_PAGE_ID=$(${WP} post list --post_type=page --post_status=publish --title="Checkout" --field=ID)
if [ -z "$CHECKOUT_PAGE_ID" ]; then
    echo "Checkout page not found. Aborting."
    exit 1
fi
${WP} post update "${CHECKOUT_PAGE_ID}" --post_content="[woocommerce_checkout]"


echo "==> Seeding one simple product so /shop has something to add to cart..."
PRODUCT_ID=$(${WP} post create \
    --post_type=product \
    --post_status=publish \
    --post_title="Test Product" \
    --post_name="test-product" \
--porcelain)
${WP} post meta add "${PRODUCT_ID}" _regular_price "100"
${WP} post meta add "${PRODUCT_ID}" _price "100"
${WP} post meta add "${PRODUCT_ID}" _stock_status "instock"
${WP} post meta add "${PRODUCT_ID}" _manage_stock "no"
${WP} post meta add "${PRODUCT_ID}" _virtual "no"
${WP} post meta add "${PRODUCT_ID}" _downloadable "no"
${WP} wc shop_coupon set_status >/dev/null 2>&1 || true # benign if absent

echo "==> Setting placeholder Kustom Checkout settings (enabled, test mode, SE)..."
# Credentials are not committed to the dump on purpose, they're injected
# at request time by tests/_mu-plugins/02-kustom-test-credentials.php from
# KUSTOM_TEST_MID_EU / KUSTOM_TEST_SECRET_EU. The placeholders here just
# guarantee the option row exists so the filter has something to merge into.
${WP} option update woocommerce_kco_settings \
'{"enabled":"yes","testmode":"yes", "logging":"yes", "allowed_customer_types":"B2C","test_merchant_id_eu":"placeholder","test_shared_secret_eu":"placeholder"}' \
--format=json

echo "==> Truncating Action Scheduler tables (must be empty in the dump, see header comment)..."
php -r "
\$pdo = new PDO('sqlite:${DB_FILE}');
\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach (['wp_actionscheduler_actions','wp_actionscheduler_claims','wp_actionscheduler_groups','wp_actionscheduler_logs'] as \$t) {
    try { \$pdo->exec(\"DELETE FROM \$t\"); } catch (Throwable \$e) {}
}
"

echo "==> Exporting dump via wp:db:export..."
vendor/bin/codecept wp:db:export "${WP_ROOT}" "${DUMP_PATH}"

echo "==> Stripping any AS data INSERTs that wp:db:export re-added during its own WP boot..."
php tests/_support_scripts/strip-actionscheduler-inserts.php "${DUMP_PATH}"

echo "==> Done. Dump: $(wc -c < "${DUMP_PATH}") bytes."
echo
echo "Verify with: vendor/bin/codecept run EndToEnd --steps"
