#!/usr/bin/env bash
wait-for-it --host=localhost --port=8000 -t 20
echo "Installing wordpress..."
wp core install \
	--url="http://localhost:8000" \
	--title="${WP_SITE_TITLE}" \
	--admin_user=${WP_ADMIN_USER} \
	--admin_password=${WP_ADMIN_PASS} \
	--admin_email=${WP_EMAIL} \
	--skip-email
wp plugin install woocommerce \
	--version=$WC_VERSION \
	--activate
wp theme install storefront \
	--activate
wp plugin activate ${PLUGIN_NAME}
wp db import ./wp-content/plugins/klarna-checkout-for-woocommerce/tests/e2e/bin/data.sql
wp search-replace "$(wp option get siteurl)" 'http://localhost:8000'
wp user update 1 --user_pass=${WP_ADMIN_PASS}
wp option update blogdescription "${SITE_DESC}"

