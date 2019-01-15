<?php
/**
 * Klarna Checkout page
 *
 * Overrides /checkout/form-checkout.php.
 *
 * @package klarna-checkout-for-woocommerce
 */
wc_print_notices();

do_action( 'kco_wc_before_checkout_form' );

// If checkout registration is disabled and not logged in, the user cannot checkout.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}
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
