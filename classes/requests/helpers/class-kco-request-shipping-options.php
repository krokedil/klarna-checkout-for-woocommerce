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
	 * Gets shipping options formatted for Kustom
	 *
	 * @param bool $separate_sales_tax True if the sales tax should be separate.
	 * @return array
	 */
	public static function get_shipping_options( $separate_sales_tax ) {
		if ( ! WC()->cart->needs_shipping() ) {
			return array();
		}

		$packages = WC()->shipping->get_packages();
		foreach ( $packages as $index => $package ) {
			// Remove shipping package for free trials. See WC_Subscriptions_Cart::set_cart_shipping_packages().
			foreach ( $package['contents'] as $cart_item_key => $cart_item ) {
				if ( class_exists( 'WC_Subscriptions_Product' ) && \WC_Subscriptions_Product::get_trial_length( $cart_item['data'] ) > 0 ) {
					unset( $packages[ $index ]['contents'][ $cart_item_key ] );
				}
			}

			if ( empty( $packages[ $index ]['contents'] ) ) {
				unset( $packages[ $index ] );
			}

			// Skip shipping lines for free trials.
			if ( class_exists( 'WC_Subscriptions_Cart' ) && \WC_Subscriptions_Cart::cart_contains_subscription() ) {
				$pattern = '/_after_a_\d+_\w+_trial/';
				if ( preg_match( $pattern, $index ) ) {
					unset( $packages[ $index ] );
				}
			}
		}

		$shipping_options = array();
		foreach ( $packages as $i => $package ) {
			$chosen_method = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';
			foreach ( $package['rates'] as $method ) {
				$method_id   = $method->id;
				$method_name = $method->label;
				$method_cost = kco_ensure_numeric( $method->cost );

				// Don't add the KSS shipping method as a shipping option. It should not be a valid fallback if it exists and the store uses a TMS system.
				if ( false !== strpos( $method_id, 'klarna_kss' ) ) {
					continue;
				}

				if ( $separate_sales_tax ) {
					$method_price = intval( round( $method_cost, 2 ) * 100 );
				} else {
					$method_price = intval( round( $method_cost + array_sum( $method->taxes ), 2 ) * 100 );
				}

				if ( array_sum( $method->taxes ) > 0 && ( ! $separate_sales_tax ) ) {
					$method_tax_amount = intval( round( array_sum( $method->taxes ), 2 ) * 100 );
					$method_tax_rate   = intval( round( ( array_sum( $method->taxes ) / $method_cost ) * 100, 2 ) * 100 );
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
