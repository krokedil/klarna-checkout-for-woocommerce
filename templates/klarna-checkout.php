<?php
/**
 * Klarna Checkout page
 *
 * Overrides /checkout/form-checkout.php.
 *
 * @package klarna-checkout-for-woocommerce
 */

WC()->cart->calculate_fees();
WC()->cart->calculate_shipping();
WC()->cart->calculate_totals();

$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
?>

<?php if ( count( $available_gateways ) > 1 ) { ?>
	<p><a class="checkout-button button alt" href="#" id="klarna-checkout-select-other">Select another payment method</a></p>
<?php } ?>

<?php woocommerce_checkout_coupon_form(); ?>

<form name="checkout" class="checkout woocommerce-checkout">
	<div id="kco-wrapper">
		<div id="kco-order-review">
			<?php woocommerce_order_review(); ?>
			<?php kco_wc_show_extra_fields(); ?>
		</div>

		<div id="kco-iframe">
			<?php do_action( 'kco_wc_before_snippet' ); ?>
			<?php kco_wc_show_snippet(); ?>
			<?php do_action( 'kco_wc_after_snippet' ); ?>
		</div>
	</div>
</form>
