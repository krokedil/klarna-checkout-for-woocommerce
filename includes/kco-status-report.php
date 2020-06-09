<?php
/**
 * Admin View: Page - Status Report.
 *
 * @package Klarna_Checkout/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<table class="wc_status_table widefat" cellspacing="0">
	<thead>
	<tr>
		<th colspan="3" data-export-label="Klarna Checkout">
			<h2><?php esc_html_e( 'Klarna Checkout', 'klarna-checkout-for-woocommerce' ); ?><?php echo wc_help_tip( __( 'Klarna Checkout System Status.', 'klarna-checkout-for-woocommerce' ) );  // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped ?></h2>
		</th>
	</tr>
	</thead>
	<tbody>
	<tr>

	</tr>
	</tbody>
</table>
