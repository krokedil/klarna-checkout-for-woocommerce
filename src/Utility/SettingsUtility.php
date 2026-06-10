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
	 * Check if Kustom Checkout is enabled, including the advanced demo mode check.
	 *
	 * If the advanced demo mode is enabled, Kustom Checkout is only considered enabled on the
	 * checkout page when the configured demo mode coupon has been applied to the cart.
	 *
	 * @return bool
	 */
	public static function is_enabled_with_demo_check() {
		$is_enabled = wc_string_to_bool( self::get_setting( 'enabled', 'no' ) );

		// Only check for demo mode if we are on the checkout page, and not on the order received page or the pay for order page.
		if ( ! is_checkout() || is_order_received_page() || is_wc_endpoint_url( 'order-pay' ) ) {
			return $is_enabled;
		}

		if ( ! $is_enabled || ! wc_string_to_bool( self::get_setting( 'demomode', 'no' ) ) ) {
			return $is_enabled;
		}

		// If the demo mode coupon is not set, the gateway should not be available.
		$demomode_coupon = self::get_setting( 'demomode_coupon', '' );
		if ( empty( $demomode_coupon ) ) {
			return false;
		}

		// The coupon code is case-sensitive and must match an applied coupon exactly.
		$applied_coupons = isset( WC()->cart ) ? WC()->cart->get_applied_coupons() : array();
		return in_array( $demomode_coupon, $applied_coupons, true );
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
