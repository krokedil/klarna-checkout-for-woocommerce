<?php
/**
 * Klarna Checkout fallback order received page, used when WC checkout form submission fails.
 *
 * Overrides /checkout/thankyou.php.
 *
 * @package klarna-checkout-for-woocommerce
 */

if ( ! WC()->session->get( 'kco_wc_order_id' ) ) {
	return;
}

WC()->cart->empty_cart( true );

$klarna_order = KCO_WC()->api->get_order();
echo KCO_WC()->api->get_snippet( $klarna_order );
