<?php
/**
 * Gets the shipping options for a KCO request.
 *
 * @package WC_Klarna_Checkout/Classes/Request/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Request Shipping Option class.
 */
class KCO_Request_Shipping_Options {
	/**
	 * Gets shipping options formatted for Klarna.
	 *
	 * @return array
	 */
	public static function get_shipping_options() {
		if ( WC()->cart->needs_shipping() ) {
			$shipping_options = array();
			$packages         = WC()->shipping->get_packages();
			$tax_display      = get_option( 'woocommerce_tax_display_cart' );

			foreach ( $packages as $i => $package ) {
				$chosen_method = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';
				foreach ( $package['rates'] as $method ) {
					$method_id   = $method->id;
					$method_name = $method->label;

					if ( $this->separate_sales_tax || 'excl' === $tax_display ) {
						$method_price = intval( round( $method->cost, 2 ) * 100 );
					} else {
						$method_price = intval( round( $method->cost + array_sum( $method->taxes ), 2 ) * 100 );
					}

					if ( array_sum( $method->taxes ) > 0 && ( ! $this->separate_sales_tax && 'excl' !== $tax_display ) ) {
						$method_tax_amount = intval( round( array_sum( $method->taxes ), 2 ) * 100 );
						$method_tax_rate   = intval( round( ( array_sum( $method->taxes ) / $method->cost ) * 100, 2 ) * 100 );
					} else {
						$method_tax_amount = 0;
						$method_tax_rate   = 0;
					}
					$method_selected    = $method->id === $chosen_method ? true : false;
					$shipping_options[] = array(
						'id'          => $method_id,
						'name'        => $method_name,
						'price'       => $method_price,
						'tax_amount'  => $method_tax_amount,
						'tax_rate'    => $method_tax_rate,
						'preselected' => $method_selected,
					);
				}
			}
			return apply_filters( 'kco_wc_shipping_options', $shipping_options );
		} else {
			return array();
		}
	}
}
