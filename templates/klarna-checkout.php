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

<form class="checkout">
<div id="kco-wrapper">
		<p><a href="#" id="klarna-checkout-select-other">Select another payment method</a></p>

		<div id="kco-order-review">
			<?php woocommerce_order_review(); ?>
			<?php kco_wc_show_order_notes(); ?>
		</div>

		<div id="kco-iframe">
			<?php do_action( 'kco_wc_before_snippet' ); ?>
			<?php kco_wc_show_snippet(); ?>
			<?php do_action( 'kco_wc_after_snippet' ); ?>
		</div>
</div>
</form>
