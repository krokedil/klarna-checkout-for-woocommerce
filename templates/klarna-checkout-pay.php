<?php
/**
 * Klarna Checkout pay for order page.
 *
 * Overrides /checkout/form-pay.php.
 *
 * @package Klarna_Checkout/Templates
 */

?>
<div id="kco-iframe">
	<?php do_action( 'kco_wc_before_snippet' ); ?>
	<?php kco_wc_show_snippet( true ); ?>
	<?php do_action( 'kco_wc_after_snippet' ); ?>
</div>
