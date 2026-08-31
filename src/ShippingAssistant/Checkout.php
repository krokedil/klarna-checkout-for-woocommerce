<?php
namespace Krokedil\KustomCheckout\ShippingAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checkout class.
 *
 * Hooks into the Kustom Checkout session lifecycle to keep TMS-controlled shipping in sync: clears stale
 * WooCommerce shipping caches, forces the `klarna_kss` shipping method as chosen, saves the TMS's
 * selected shipping option onto the order, and keeps the iframe visible for zero-total carts so the TMS
 * data still gets a chance to be saved.
 */
class Checkout {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'kco_wc_process_payment', array( $this, 'add_shipping_details_to_order' ), 10, 2 );
		add_action( 'kco_update_shipping_data', array( $this, 'clear_shipping_and_recalculate' ) );
		add_filter( 'kco_wc_chosen_shipping_method', array( $this, 'set_shipping_method' ) );
		add_filter( 'kco_check_if_needs_payment', array( $this, 'change_check_if_needs_payment' ) );
	}

	/**
	 * Returns the shipping method ID.
	 *
	 * @param array $chosen_shipping_methods WooCommerce shipping method ID.
	 * @return array The shipping method ID for this shipping method.
	 */
	public function set_shipping_method( $chosen_shipping_methods ) {
		$chosen           = $chosen_shipping_methods[0] ?? '';
		$shipping_methods = WC()->shipping->get_shipping_methods();

		// Only do this if we have Kustom Shipping Assistant active on the store, and the returned shipping method is NOT a real WooCommerce shipping method.
		if ( ! isset( $shipping_methods['klarna_kss'] ) || isset( $shipping_methods[ $chosen ] ) ) {
			return $chosen_shipping_methods;
		}

		// WooCommerce matches the chosen method against zone rate ids ('klarna_kss:<instance>'), so a
		// bare method id never matches and the zone silently falls back to its first available rate.
		$rate_id = $this->get_shipping_rate_id();

		return array( null !== $rate_id ? $rate_id : 'klarna_kss' );
	}

	/**
	 * Get the rate id of the Kustom Shipping Assistant method in the zone matching the customer.
	 *
	 * @return string|null The rate id, or null if the zone has no Kustom Shipping Assistant method.
	 */
	private function get_shipping_rate_id() {
		if ( ! WC()->cart ) {
			return null;
		}

		$packages = WC()->cart->get_shipping_packages();
		$package  = reset( $packages );
		if ( empty( $package ) ) {
			return null;
		}

		$zone = \WC_Shipping_Zones::get_zone_matching_package( $package );
		foreach ( $zone->get_shipping_methods( true ) as $method ) {
			if ( 'klarna_kss' === $method->id ) {
				return $method->get_rate_id();
			}
		}

		return null;
	}

	/**
	 * Adds the shipping details from Kustom Shipping Assistant to the WooCommerce order.
	 *
	 * @param int   $order_id The WooCommerce order id.
	 * @param array $klarna_order The Kustom order.
	 * @return void
	 */
	public function add_shipping_details_to_order( $order_id, $klarna_order ) {
		if ( isset( $klarna_order['selected_shipping_option'] ) ) {
			$kco_id = $klarna_order['id'];
			$order  = wc_get_order( $order_id );

			$shipping_details = $klarna_order['selected_shipping_option'];
			if ( isset( $shipping_details['tms_reference'] ) ) {
				$order->update_meta_data( '_kco_kss_reference', $shipping_details['tms_reference'] );
			}

			// Update the shipping details with the override data if it exists, since we want to save the overridden shipping details to the order, not the original ones from KSS.
			$override_data = get_transient( "kss_override_data_$kco_id" );
			if ( $override_data ) {
				$shipping_details['price']      = $override_data['price'] ?? $shipping_details['price'];
				$shipping_details['name']       = $override_data['name'] ?? $shipping_details['name'];
				$shipping_details['tax_rate']   = $override_data['tax_rate'] ?? $shipping_details['tax_rate'];
				$shipping_details['tax_amount'] = $override_data['tax_amount'] ?? $shipping_details['tax_amount'];
			}

			$order->update_meta_data( '_kco_kss_data', wp_json_encode( $shipping_details, JSON_UNESCAPED_UNICODE ) );
			$order->save();
			WC()->session->__unset( 'kco_kss_enabled' );

			// Clear the kss_override_data_{order_id} transient since we have now saved the shipping data to the order.
			delete_transient( "kss_override_data_$kco_id" );
		}
	}

	/**
	 * Clears the shipping calculations to prevent errors.
	 *
	 * @return void
	 */
	public function clear_shipping_and_recalculate() {
		if ( 'kco' === WC()->session->get( 'chosen_payment_method' ) ) {
			WC()->session->set( 'kco_kss_enabled', true );
		} elseif ( null !== WC()->session->get( 'kco_kss_enabled' ) ) {
			WC()->session->__unset( 'kco_kss_enabled' );
		}

		// Clear this customer's cached shipping rates so WooCommerce re-runs shipping on the next
		// calculation. We unset every 'shipping_for_package_*' session key (not just the main-cart packages).
		foreach ( array_keys( WC()->session->get_session_data() ) as $session_key ) {
			if ( 0 === strpos( $session_key, 'shipping_for_package_' ) ) {
				WC()->session->__unset( $session_key );
			}
		}
	}

	/**
	 * Make sure that the Kustom iframe is displayed in checkout even if order total is 0.
	 * This is needed so we can save the TMS data to the WooCommerce order.
	 *
	 * @param bool $needs_payment Whether or not the plugin should check if the Kustom checkout should be displayed. Defaults to true.
	 *
	 * @return bool
	 */
	public function change_check_if_needs_payment( $needs_payment ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- We need to have the $needs_payment parameter to be able to use this as a filter for 'kco_check_if_needs_payment'.
		// Always return false. We want to display the Kustom iframe even if order total is 0.
		return false;
	}
}
