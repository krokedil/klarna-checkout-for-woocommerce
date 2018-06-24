<?php
/**
 * Admin View: Page - Status Report.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<table class="wc_status_table widefat" cellspacing="0">
	<thead>
	<tr>
		<th colspan="3" data-export-label="Klarna Checkout">
			<h2><?php _e( 'Klarna Checkout', 'klarna-checkout-for-woocommerce' ); ?><?php echo wc_help_tip( __( 'Klarna Checkout System Status.', 'klarna-checkout-for-woocommerce' ) ); ?></h2>
		</th>
	</tr>
	</thead>
	<tbody>
	<tr>
		<td data-export-label="Orders created via API callback"><?php _e( 'Orders created via API callback', 'klarna-checkout-for-woocommerce' ); ?>:</td>
		<td class="help"><?php echo wc_help_tip( __( 'Displays the number of orders created via the API callback feature during the last month.', 'klarna-checkout-for-woocommerce' ) ); ?></td>
		<td>
			<?php
				$query = new WC_Order_Query( array(
			        'limit' => -1,
			        'orderby' => 'date',
			        'order' => 'DESC',
			        'return' => 'ids',
			        'payment_method' => 'kco',
			        'date_created' => '>' . ( time() - MONTH_IN_SECONDS )
			    ) );
			    $orders = $query->get_orders();
			    $amont_of_klarna_orders = count( $orders );
				$amont_of_api_callback_orders = 0;
			    foreach( $orders as $order_id ) {
					
			        if( 'klarna_checkout_backup_order_creation' == get_post_meta( $order_id, '_created_via', true ) ) {
			           $amont_of_api_callback_orders++;
			        }
                }
                if( $amont_of_api_callback_orders > 0 ) {
				    $percent_of_orders = round( ($amont_of_api_callback_orders/$amont_of_klarna_orders) * 100 );
                } else {
                    $percent_of_orders = 0;
                }
				if( $percent_of_orders >= 10 ) {
					$status = 'error';
				} else {
					$status = 'yes';
				}
				
				echo '<strong><mark class="' . $status . '">' . $percent_of_orders . '% (' . $amont_of_api_callback_orders . ' of ' . $amont_of_klarna_orders . ')</mark></strong> of all orders paid via Klarna Checkout were created via API callback during the last month. This is a fallback order creation feature. You should aim for 0%.';
		
			?>
		</td>
	</tr>
	</tbody>
</table>