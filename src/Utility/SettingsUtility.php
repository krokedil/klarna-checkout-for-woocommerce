<?php
namespace Krokedil\KustomCheckout\Utility;

/**
 * Utility class for helper functions related to plugin settings.
 */
class SettingsUtility {
	/**
	 * Holds the settings for the plugin.
	 *
	 * @var array|null
	 */
	private static $settings = null;

	/**
	 * Get the settings for Kustom Checkouts gateway.
	 *
	 * @return array
	 */
	public static function get_settings() {
		if ( null === self::$settings ) {
			self::$settings = get_option( 'woocommerce_kco_settings', array() );

			// Merge with default values, and ensure all settings are present.
			$defaults       = self::get_default_values();
			self::$settings = wp_parse_args( self::$settings, $defaults );
		}

		return self::$settings;
	}

	/**
	 * Get the value of a specific setting.
	 *
	 * @param string $key           The key of the setting to retrieve.
	 * @param mixed  $default_value The default value to return if the setting is not found.
	 *
	 * @return mixed
	 */
	public static function get_setting( $key, $default_value = null ) {
		$settings = self::get_settings();

		$value = $settings[ $key ] ?? $default_value;

		return $value;
	}

	/**
	 * Check if testmode is enabled or not.
	 *
	 * @return bool
	 */
	public static function is_testmode() {
		$testmode = self::get_setting( 'testmode', 'no' );

		return wc_string_to_bool( $testmode );
	}

	/**
	 * Check if Kustom Checkout should only be used when the cart or order needs payment.
	 *
	 * @return bool False if Kustom Checkout should be displayed on free orders.
	 */
	public static function check_if_needs_payment() {
		$display_on_free_orders = wc_string_to_bool( self::get_setting( 'display_on_free_orders', 'no' ) );

		/**
		 * Filters whether Kustom Checkout should only be used when the cart or order needs payment.
		 *
		 * @link https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#display-kustom-checkout-even-on-free-orders Display Kustom Checkout even on free orders
		 * @param bool $check_if_needs_payment Whether to check if payment is needed. Defaults to the inverse of the "Display on free orders" setting.
		 */
		return apply_filters( 'kco_check_if_needs_payment', ! $display_on_free_orders );
	}

	/**
	 * Get the default values for the settings.
	 *
	 * @return array
	 */
	private static function get_default_values() {
		// Get the WC Settings API definitions for the Kustom Gateway.
		$settings_api = \KCO_Fields::fields();

		$defaults = array();
		foreach ( $settings_api as $key => $field ) {
			if ( isset( $field['default'] ) ) {
				$defaults[ $key ] = $field['default'];
			}
		}

		return $defaults;
	}
}
