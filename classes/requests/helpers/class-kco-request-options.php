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
	public function get_options( $checkout_flow = 'embedded' ) {
		$options = array(
			'title_mandatory'                             => $this->get_title_mandatory(),
			'allow_separate_shipping_address'             => $this->get_allow_separate_shipping_address(),
			'date_of_birth_mandatory'                     => $this->get_dob_mandatory(),
			'national_identification_number_mandatory'    => $this->get_dob_mandatory(),
			'verify_national_identification_number'       => $this->get_nin_validation_mandatory(),
			'allowed_customer_types'                      => $this->get_allowed_customer_types(),
			'require_client_validation'                   => 'redirect' === $checkout_flow ? false : true,
			'require_client_validation_callback_response' => 'redirect' === $checkout_flow ? false : true,
			'phone_mandatory'                             => 'required' === get_option( 'woocommerce_checkout_phone_field', 'required' ),
			'show_subtotal_detail'                        => $this->show_subtotal_detail( $checkout_flow ),
		);

		if ( $this->get_iframe_colors() ) {
			$options = array_merge( $options, $this->get_iframe_colors() );
		}

		if ( $this->get_shipping_details() ) {
			$options['shipping_details'] = $this->get_shipping_details();
		}

		$additional_checkboxes = $this->additional_checkboxes();
		if ( ! empty( $additional_checkboxes ) ) {
			$options['additional_checkboxes'] = $additional_checkboxes;
		}

		return $options;
	}

	/**
	 * Gets date of birth mandatory option.
	 *
	 * @return bool
	 */
	private function get_title_mandatory() {
		$title_mandatory = isset( $this->settings ) && 'yes' === $this->settings['title_mandatory'];

		return $title_mandatory;
	}

	/**
	 * Gets allowed separate shipping details option.
	 *
	 * @return bool
	 */
	private function get_allow_separate_shipping_address() {
		$allow_separate_shipping = isset( $this->settings ) && 'yes' === $this->settings['allow_separate_shipping'];

		return $allow_separate_shipping;
	}

	/**
	 * Gets date of birth mandatory option.
	 *
	 * @return bool
	 */
	private function get_dob_mandatory() {
		$dob_mandatory = isset( $this->settings ) && 'yes' === $this->settings['dob_mandatory'];

		return $dob_mandatory;
	}

	/**
	 * Gets date of birth mandatory option.
	 *
	 * @return bool
	 */
	private function get_nin_validation_mandatory() {
		$nin_validation_mandatory = isset( $this->settings ) && isset( $this->settings['nin_validation_mandatory'] ) && 'yes' === $this->settings['nin_validation_mandatory'];

		return $nin_validation_mandatory;
	}

	/**
	 * Gets the allowed customer types
	 *
	 * @return string
	 */
	private function get_allowed_customer_types() {

		if ( in_array( $this->get_purchase_country(), array( 'SE', 'NO', 'FI' ), true ) && isset( $this->settings['allowed_customer_types'] ) ) {
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
			$color_settings['radius_border'] = $this->check_option_field( 'radius_border' );
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
		if ( isset( $this->settings ) ) {
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
		if ( isset( $this->settings ) && '' !== $this->settings[ $field ] ) {
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
		if ( '' !== $hex ) {
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

	/**
	 * Inserts a checkbox with description in the Klarna frame.
	 *
	 * @return array
	 */
	public function additional_checkboxes() {
		$additional_checkboxes = array();
		if ( isset( $this->settings['add_terms_and_conditions_checkbox'] ) && 'yes' === $this->settings['add_terms_and_conditions_checkbox'] ) {
			$additional_checkboxes[] = array(
				'id'       => 'terms_and_conditions',
				'text'     => wc_replace_policy_page_link_placeholders( wc_get_terms_and_conditions_checkbox_text() ),
				'checked'  => false,
				'required' => true,
			);
		}
		return apply_filters( 'kco_additional_checkboxes', $additional_checkboxes );
	}

	/**
	 * Gets the value for the show_subtotal_details argument.
	 *
	 * @param string $checkout_flow The checkout flow: redirect or embedded.
	 *
	 * @return bool
	 */
	public function show_subtotal_detail( $checkout_flow ) {
		if ( isset( $this->settings['show_subtotal_detail'] ) && in_array( $this->settings['show_subtotal_detail'], array( 'iframe', 'both' ), true ) && 'embedded' === $checkout_flow ) {
			return true;
		}
		return false;
	}
}
