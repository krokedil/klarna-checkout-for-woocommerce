<?php
namespace Krokedil\KustomCheckout\Blocks\InPersonPayments;

use Krokedil\KustomCheckout\InPersonPayments\Gateway;
use Krokedil\KustomCheckout\InPersonPayments\InPersonPayments;

defined( 'ABSPATH' ) || exit;

/**
 * Strips the block checkout down to what a walk-in sale has, the way
 * `InPersonPayments\Checkout` strips the shortcode checkout down.
 */
class BlockCheckout {

	/**
	 * The fields a walk-in customer is not asked for. `country` is left required: the
	 * block checkout derives the address format and the state list from it.
	 */
	public const OPTIONAL_FIELDS = array( 'email', 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'phone' );

	/**
	 * Class constructor.
	 */
	public function __construct() {
		// The server-side validation is relaxed by PosSale, which runs for the shortcode checkout too.
		add_filter( 'woocommerce_shared_settings', array( $this, 'relax_address_fields' ) );
	}

	/**
	 * Make the address fields optional in the settings the block checkout renders from.
	 *
	 * The block checkout reads the required fields once from the page settings, so they
	 * must be relaxed for every staff member, not only while the method is selected.
	 *
	 * @param array $settings The settings shared with the block scripts.
	 * @return array
	 */
	public function relax_address_fields( $settings ) {
		if ( ! self::should_relax_fields() ) {
			return $settings;
		}

		if ( isset( $settings['defaultFields'] ) && is_array( $settings['defaultFields'] ) ) {
			$settings['defaultFields'] = self::make_optional( $settings['defaultFields'] );
		}

		foreach ( (array) ( $settings['countryData'] ?? array() ) as $country_code => $country ) {
			if ( isset( $country['locale'] ) && is_array( $country['locale'] ) ) {
				$settings['countryData'][ $country_code ]['locale'] = self::make_optional( $country['locale'] );
			}
		}

		return $settings;
	}

	/**
	 * Record which method the salesperson picked on the block checkout, so that the
	 * cart knows an over-the-counter sale needs no shipping.
	 *
	 * This is what `woocommerce_cart_needs_shipping` reads to switch shipping off.
	 *
	 * @param bool $chosen Whether in-person payment is the method being checked out with.
	 * @return void
	 */
	public static function choose( $chosen ) {
		if ( ! InPersonPayments::is_enabled() || ! current_user_can( Gateway::required_capability() ) ) {
			return;
		}

		if ( ! WC()->session instanceof \WC_Session ) {
			return;
		}

		WC()->session->set( 'chosen_payment_method', $chosen ? Gateway::ID : '' );
	}

	/**
	 * Whether the address fields should be relaxed for this request.
	 *
	 * @return bool
	 */
	private static function should_relax_fields() {
		$relax = InPersonPayments::is_enabled()
			&& is_checkout()
			&& ! is_order_received_page()
			&& current_user_can( Gateway::required_capability() );

		/**
		 * Whether the block checkout's address fields are optional for this request.
		 *
		 * They are relaxed for every user who may take an in-person payment, because
		 * the block checkout reads the required fields once per page load.
		 *
		 * @since 2.22.0
		 *
		 * @param bool $relax Whether to relax the fields.
		 */
		return apply_filters( 'kco_ipp_relax_block_checkout_fields', $relax );
	}

	/**
	 * Clear the required flag on the fields a walk-in sale does not need.
	 *
	 * @param array $fields The field definitions, keyed by field name.
	 * @return array
	 */
	public static function make_optional( $fields ) {
		foreach ( self::OPTIONAL_FIELDS as $key ) {
			if ( isset( $fields[ $key ] ) ) {
				$fields[ $key ]['required'] = false;
			}
		}

		return $fields;
	}
}
