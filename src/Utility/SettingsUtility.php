<?php
namespace Krokedil\KustomCheckout\Utility;

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
		if ( self::$settings === null ) {
			self::$settings = get_option( 'woocommerce_kco_settings', array() );

			// Merge with default values, and ensure all settings are present.
			$defaults = self::get_default_values();
			self::$settings = wp_parse_args( self::$settings, $defaults );
		}

		return self::$settings;
	}

	/**
	 * Get the value of a specific setting.
	 *
	 * @param string $key
	 * @param mixed $default
	 *
	 * @return mixed
	 */
	public static function get_setting( $key, $default = null ) {
		$settings = self::get_settings();

		$value = $settings[ $key ] ?? $default;

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
