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

wc_empty_cart();
// Clear session storage to prevent error for customer in the future.
?>
	<script>sessionStorage.orderSubmitted = false</script>
<?php
kco_wc_show_snippet();
