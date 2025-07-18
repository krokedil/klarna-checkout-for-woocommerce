<?php
namespace Krokedil\KustomCheckout\CheckoutFlow;

use Exception;

/**
 * Class for processing the embedded checkout flow on the blocks checkout page.
 */
class EmbeddedBlockFlow extends CheckoutFlow {
	/**
	 * Process the payment for the WooCommerce order.
	 *
	 * @param \WC_Order $order The WooCommerce order to be processed.
	 *
	 * @return array
	 * @throws Exception If there is an error during the payment processing.
	 */
	public function process( $order ) {
		$klarna_order_id = $this->get_klarna_order_id( $order );
		$klarna_order    = $this->get_klarna_order( $klarna_order_id );
		$order_number    = $this->get_order_number( $order );

		$this->debug_log_shipping( $klarna_order_id, $order );
		$this->save_order_metadata( $order, $klarna_order, 'embedded' );

		do_action( 'kco_wc_process_payment', $order->get_id(), $klarna_order );
		\KCO_Logger::log( "Order {$order_number} ({$klarna_order_id}) associated with [{$order->get_billing_email()}] was successfully processed." );

		return array(
			'result' => 'success',
		);
	}
}
