<?php
namespace Krokedil\KustomCheckout\ShippingAssistant;

use Krokedil\KustomCheckout\Utility\SettingsUtility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings class.
 *
 * Adds Kustom Shipping Assistant's settings to the Kustom Checkout settings page.
 */
class Settings {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'kco_wc_gateway_settings', array( $this, 'extend_settings' ) );
	}

	/**
	 * Given a settings array, extend it with the Shipping Assistant settings.
	 *
	 * @param array $settings A settings array.
	 * @return array
	 */
	public function extend_settings( $settings ) {
		$settings['ksa'] = array(
			'title' => 'Kustom Shipping Assistant',
			'type'  => 'krokedil_section_start',
			'id'    => 'ksa_section',
		);

		$settings['ksa_enabled'] = array(
			'title'       => __( 'Enable Kustom Shipping Assistant', 'klarna-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			'class'       => 'krokedil_conditional_toggler krokedil_toggler_ksa',
			'default'     => self::get_enabled_default(),
			'label'       => __( 'Let a TMS (transport management system) control shipping methods and pricing in the Kustom iframe.', 'klarna-checkout-for-woocommerce' ),
			'description' => __( 'Enable this if your store uses a TMS-based shipping integration. Adds a "Kustom Shipping Assistant" shipping method for use in your shipping zones.', 'klarna-checkout-for-woocommerce' ),
			'desc_tip'    => true,
		);

		$settings['ksa_enable_shipping_option_update_callback'] = array(
			'title'       => __( 'Enable shipping option update callback', 'klarna-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			'class'       => 'krokedil_conditional_setting krokedil_conditional_ksa',
			'label'       => __( 'Enable the shipping option update callback for WooCommerce to override shipping data from Kustom Shipping Assistant.', 'klarna-checkout-for-woocommerce' ),
			'default'     => 'no',
			'description' => __( 'Enabling this setting will allow WooCommerce to override shipping data from Kustom Shipping Assistant with data. For example if you need to override the tax rate used by shipping options in cases where the TMS does not provide the correct tax rate.', 'klarna-checkout-for-woocommerce' ),
		);

		$settings['ksa_section_end'] = array(
			'type' => 'krokedil_section_end',
		);

		return $settings;
	}

	/**
	 * Whether Kustom Shipping Assistant is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return wc_string_to_bool( SettingsUtility::get_setting( 'ksa_enabled', self::get_enabled_default() ) );
	}

	/**
	 * The default for the enable setting, until it has been saved.
	 *
	 * Enabled for stores already using the standalone plugin, which is detected by its shipping method
	 * being active in a zone. Every other store stays opt-in.
	 *
	 * @return string 'yes' or 'no'.
	 */
	public static function get_enabled_default() {
		static $default = null;

		if ( null !== $default ) {
			return $default;
		}

		// A saved value makes the default irrelevant, so skip the zone lookup.
		$saved = get_option( 'woocommerce_kco_settings', array() );
		if ( is_array( $saved ) && isset( $saved['ksa_enabled'] ) ) {
			$default = 'no';
			return $default;
		}

		$default = wc_bool_to_string( self::has_active_shipping_method() );
		return $default;
	}

	/**
	 * Whether any shipping zone, including "Rest of the world", has the KSA shipping method enabled.
	 *
	 * @return bool
	 */
	private static function has_active_shipping_method() {
		$data_store = \WC_Data_Store::load( 'shipping-zone' );
		$zone_ids   = array_merge( array( 0 ), wp_list_pluck( $data_store->get_zones(), 'zone_id' ) );

		foreach ( $zone_ids as $zone_id ) {
			foreach ( $data_store->get_methods( absint( $zone_id ), true ) as $method ) {
				if ( 'klarna_kss' === $method->method_id ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Whether the shipping option update callback is enabled.
	 *
	 * @return bool
	 */
	public static function is_shipping_option_update_callback_enabled() {
		return wc_string_to_bool( SettingsUtility::get_setting( 'ksa_enable_shipping_option_update_callback', 'no' ) );
	}
}
