<?php
/**
 * Klarna Checkout page
 *
 * Overrides /checkout/form-checkout.php.
 *
 * @package klarna-checkout-for-woocommerce
 */

?>

<a href="#" id="klarna-checkout-select-other">Select another payment method</a>

<div id="kco-wrapper">
	<div id="kco-order-review">
		<?php woocommerce_order_review(); ?>
	</div>

	<div id="kco-iframe">
		<?php Klarna_Checkout_For_WooCommerce_API::show_iframe(); ?>
	</div>

</div>
