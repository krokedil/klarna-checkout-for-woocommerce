#!/bin/sh

# Get the url from the ngrok API using the ngrok container.
# Wait for ngrok URL to become available
while [ -z "$NGROK_URL" ]; do
  echo "Waiting for ngrok URL to become available..."
  NGROK_URL=$(curl -s http://ngrok:4040/api/tunnels | jq -r '.tunnels[0].public_url')
  sleep 2
done

echo "NGROK_URL: $NGROK_URL"

wp core install --url=$NGROK_URL --title='Krokedil E2E Test' --admin_user='admin' --admin_password='password' --admin_email='e2e@krokedil.se' --skip-email --skip-plugins --skip-themes
wp rewrite structure '/%postname%/' --hard
if [ -z "${WC_VERSION}" ]; then
    wp plugin install woocommerce --activate
else
    wp plugin install woocommerce --version=${WC_VERSION} --activate
fi
wp plugin install wp-mail-logging --activate
wp theme install storefront --activate
wp plugin install https://github.com/WP-API/Basic-Auth/archive/master.zip --activate
wp plugin activate klarna-checkout-for-woocommerce
wp plugin install https://github.com/krokedil/klarna-order-management-for-woocommerce/archive/master.zip --activate
wp option update woocommerce_default_country SE
wp option update woocommerce_currency SEK
wp option update woocommerce_terms_page_id 3
