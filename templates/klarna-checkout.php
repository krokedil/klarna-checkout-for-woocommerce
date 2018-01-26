<?php
/**
 * Klarna Checkout page
 *
 * Overrides /checkout/form-checkout.php.
 *
 * @package klarna-checkout-for-woocommerce
 */

do_action( 'kco_wc_before_checkout_form' );
?>

<form name="checkout" class="checkout woocommerce-checkout">
	<div id="kco-wrapper">
		<div id="kco-order-review">
			<?php do_action( 'kco_wc_before_order_review' ); ?>
			<?php woocommerce_order_review(); ?>
			<?php do_action( 'kco_wc_after_order_review' ); ?>
		</div>

		<div id="kco-iframe">
			<?php do_action( 'kco_wc_before_snippet' ); ?>
			<?php kco_wc_show_snippet(); ?>
			<?php do_action( 'kco_wc_after_snippet' ); ?>
		</div>
	</div>
</form>

<?php do_action( 'kco_wc_after_checkout_form' ); ?>
