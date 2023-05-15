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
	 * @param bool $separate_sales_tax True if the sales tax should be separate.
	 * @return array
	 */
	public static function get_shipping_options( $separate_sales_tax ) {
		if ( ! WC()->cart->needs_shipping() ) {
			return array();
		}

		$shipping_options = array();
		$packages         = WC()->shipping->get_packages();
		foreach ( $packages as $i => $package ) {
			$chosen_method = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';
			foreach ( $package['rates'] as $method ) {
				$method_id   = $method->id;
				$method_name = $method->label;

				// Don't add the KSS shipping method as a shipping option. It should not be a valid fallback if it exists and the store uses a TMS system.
				if ( false !== strpos( $method_id, 'klarna_kss' ) ) {
					continue;
				}

				if ( $separate_sales_tax ) {
					$method_price = intval( round( $method->cost, 2 ) * 100 );
				} else {
					$method_price = intval( round( $method->cost + array_sum( $method->taxes ), 2 ) * 100 );
				}

				if ( array_sum( $method->taxes ) > 0 && ( ! $separate_sales_tax ) ) {
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
	}
}
