<?php
/**
 * Klarna Checkout page
 *
 * Overrides /checkout/form-checkout.php.
 *
 * @package klarna-checkout-for-woocommerce
 */

?>

<?php WC()->session->set( 'chosen_payment_method', 'klarna_checkout_for_woocommerce' ); ?>

<a href="#" id="klarna-checkout-select-other">Select another payment method</a>

<div id="kco-wrapper">
	<div id="kco-order-review">
		<form class="checkout">
		<?php
			// echo do_shortcode( '[woocommerce_cart]' );
			woocommerce_order_review();
		?>
		</form>
	</div>

	<div id="kco-iframe">
		<?php do_action( 'kco_wc_before_snippet' ); ?>
		<?php kco_wc_show_snippet(); ?>
		<?php do_action( 'kco_wc_after_snippet' ); ?>
	</div>

</div>
