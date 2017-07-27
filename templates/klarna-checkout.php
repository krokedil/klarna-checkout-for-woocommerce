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
		<?php kco_wc_show_snippet(); ?>
	</div>

</div>
