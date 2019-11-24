<?php
/**
 * Merchant data processor.
 *
 * @package Klarna_Checkout/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * KCO_Request_Options class.
 *
 * Class that gets the merchant data for the order.
 */
class KCO_Request_Options {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->settings = get_option( 'woocommerce_kco_settings' );
	}

	/**
	 * Gets merchant data for Klarna purchase.
	 *
	 * @return array
	 */
	public function get_options() {
		$options = array(
			'title_mandatory'                          => $this->get_title_mandatory(),
			'allow_separate_shipping_address'          => $this->get_allow_separate_shipping_address(),
			'date_of_birth_mandatory'                  => $this->get_dob_mandatory(),
			'national_identification_number_mandatory' => $this->get_dob_mandatory(),
			'allowed_customer_types'                   => $this->get_allowed_customer_types(),
			'require_client_validation'                => true,
			'phone_mandatory'                          => 'required' === get_option( 'woocommerce_checkout_phone_field', 'required' ),
		);

		if ( $this->get_iframe_colors() ) {
			$options = array_merge( $options, $this->get_iframe_colors() );
		}

		if ( $this->get_shipping_details() ) {
			$options['shipping_details'] = $this->get_shipping_details();
		}

		return $options;
	}

	/**
	 * Gets date of birth mandatory option.
	 *
	 * @return bool
	 */
	private function get_title_mandatory() {
		$title_mandatory = array_key_exists( 'title_mandatory', $this->settings ) && 'yes' === $this->settings['title_mandatory'];

		return $title_mandatory;
	}

	/**
	 * Gets allowed separate shipping details option.
	 *
	 * @return bool
	 */
	private function get_allow_separate_shipping_address() {
		$allow_separate_shipping = array_key_exists( 'allow_separate_shipping', $this->settings ) && 'yes' === $this->settings['allow_separate_shipping'];

		return $allow_separate_shipping;
	}

	/**
	 * Gets date of birth mandatory option.
	 *
	 * @return bool
	 */
	private function get_dob_mandatory() {
		$dob_mandatory = array_key_exists( 'dob_mandatory', $this->settings ) && 'yes' === $this->settings['dob_mandatory'];

		return $dob_mandatory;
	}

	/**
	 * Gets the allowed customer types
	 *
	 * @return string
	 */
	private function get_allowed_customer_types() {
		// Allow external payment method plugin to do its thing.
		// @TODO: Extract this into a hooked function.
		if ( in_array( $this->get_purchase_country(), array( 'SE', 'NO', 'FI' ), true ) ) {
			if ( isset( $this->settings['allowed_customer_types'] ) ) {
				$customer_types_setting = $this->settings['allowed_customer_types'];

				switch ( $customer_types_setting ) {
					case 'B2B':
						$allowed_customer_types = array( 'organization' );
						break;
					case 'B2BC':
						$allowed_customer_types = array( 'person', 'organization' );
						break;
					case 'B2CB':
						$allowed_customer_types = array( 'person', 'organization' );
						break;
					default:
						$allowed_customer_types = array( 'person' );
				}

				return $allowed_customer_types;
			}
		}
	}

	/**
	 * Gets iframe color settings.
	 *
	 * @return array|bool
	 */
	private function get_iframe_colors() {
		$color_settings = array();

		if ( $this->check_option_field( 'color_button' ) ) {
			$color_settings['color_button'] = self::add_hash_to_color( $this->check_option_field( 'color_button' ) );
		}

		if ( $this->check_option_field( 'color_button_text' ) ) {
			$color_settings['color_button_text'] = self::add_hash_to_color( $this->check_option_field( 'color_button_text' ) );
		}

		if ( $this->check_option_field( 'color_checkbox' ) ) {
			$color_settings['color_checkbox'] = self::add_hash_to_color( $this->check_option_field( 'color_checkbox' ) );
		}

		if ( $this->check_option_field( 'color_checkbox_checkmark' ) ) {
			$color_settings['color_checkbox_checkmark'] = self::add_hash_to_color( $this->check_option_field( 'color_checkbox_checkmark' ) );
		}

		if ( $this->check_option_field( 'color_header' ) ) {
			$color_settings['color_header'] = self::add_hash_to_color( $this->check_option_field( 'color_header' ) );
		}

		if ( $this->check_option_field( 'color_link' ) ) {
			$color_settings['color_link'] = self::add_hash_to_color( $this->check_option_field( 'color_link' ) );
		}

		if ( $this->check_option_field( 'radius_border' ) ) {
			$color_settings['radius_border'] = self::add_hash_to_color( $this->check_option_field( 'radius_border' ) );
		}

		if ( count( $color_settings ) > 0 ) {
			return $color_settings;
		}

		return false;
	}

	/**
	 * Gets shipping details note.
	 *
	 * @return bool
	 */
	private function get_shipping_details() {
		if ( array_key_exists( 'shipping_details', $this->settings ) ) {
			return $this->settings['shipping_details'];
		}

		return false;
	}

	/**
	 * Checks option fields.
	 *
	 * @param string $field Field name.
	 * @return array|bool
	 */
	private function check_option_field( $field ) {
		if ( array_key_exists( $field, $this->settings ) && '' !== $this->settings[ $field ] ) {
			return $this->settings[ $field ];
		}

		return false;
	}

	/**
	 * Adds hash to color hex.
	 *
	 * @param string $hex Hex color code.
	 * @return string
	 */
	private static function add_hash_to_color( $hex ) {
		if ( '' != $hex ) {
			$hex = str_replace( '#', '', $hex );
			$hex = '#' . $hex;
		}
		return $hex;
	}

	/**
	 * Gets country for Klarna purchase.
	 *
	 * @return string
	 */
	private function get_purchase_country() {
		// Try to use customer country if available.
		if ( ! empty( WC()->customer->get_billing_country() ) && strlen( WC()->customer->get_billing_country() ) === 2 ) {
			return WC()->customer->get_billing_country( 'edit' );
		}

		$base_location = wc_get_base_location();
		$country       = $base_location['country'];

		return $country;
	}
}
