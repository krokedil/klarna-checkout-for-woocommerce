<?php

class KCO_Test {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'update_klarna_order' ), 9999 );
	}

	public function update_klarna_order() {
		error_log( 1 );
		if ( ! is_checkout() ) {
			return;
		}

		if ( 'kco' !== WC()->session->get( 'chosen_payment_method' ) ) {
			return;
		}
		error_log( 2 );
		$klarna_order_id = WC()->session->get( 'kco_wc_order_id' );

		if ( empty( $klarna_order_id ) ) {
			return;
		}

		$klarna_order = KCO_WC()->api->get_klarna_order( $klarna_order_id );

		if ( 'checkout_incomplete' === $klarna_order['status'] ) {
			// If it is, update order.
			$klarna_order = KCO_WC()->api->update_klarna_order( $klarna_order_id );
		}
	}
} new KCO_Test();
