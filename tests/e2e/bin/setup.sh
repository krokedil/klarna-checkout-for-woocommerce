#!/bin/bash
ls -la
wp core install --url=http://localhost:8000 --title="Klarna Checkout" --admin_user=admin --admin_password=password --admin_email=info@example.com --path=/var/www/html --skip-email;
wp rewrite structure /%postname%/;
wp rewrite flush;
wp plugin install wordpress-importer --activate;
wp plugin install woocommerce --activate;
wp theme install storefront --activate;
wp wc tool run install_pages --user=1;
wp plugin activate klarna-checkout-for-woocommerce
wp option update woocommerce_terms_page_id 3
wp option update woocommerce_currency 'SEK'
wp option update woocommerce_default_country 'SE'
wp option update woocommerce_calc_taxes 'yes'
wp option update woocommerce_store_address 'Hamngatan 2'
wp option update woocommerce_store_city 'Arvika'
wp option update woocommerce_store_postcode 67131
wp option update woocommerce_currency_pos 'right_space'
wp option update woocommerce_price_thousand_sep ','
wp option update woocommerce_price_decimal_sep '.'
wp option update woocommerce_price_num_decimals 2

# create customer with wp cli command : for instance wp customer create ...
