<?php
namespace Krokedil\KustomCheckout\OrderManagement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Settings class.
 *
 * Class to add settings to the settings page and to retrieve the settings values.
 */
class Settings {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'kco_wc_gateway_settings', array( $this, 'extend_settings' ) );
	}

	/**
	 * Given a settings array, they will be extended by KOM's settings.
	 *
	 * @param array $settings A settings array.
	 * @return array
	 */
	public function extend_settings( $settings ) {
		$default_values = wp_parse_args(
			get_option( 'kom_settings', array() ),
			array(
				'kom_enabled'            => 'yes',
				'kom_auto_capture'       => 'yes',
				'kom_auto_cancel'        => 'yes',
				'kom_auto_update'        => 'yes',
				'kom_auto_order_sync'    => 'yes',
				'kom_force_full_capture' => 'no',
				'kom_enable_refunds'     => 'yes',
			)
		);

		$settings['kom'] = array(
			'title' => 'Kustom Order Management',
			'type'  => 'title',
		);

		$settings['kom_enabled'] = array(
			'title'   => 'Enable order management',
			'type'    => 'checkbox',
			'default' => $default_values['kom_enabled'],
			'label'   => __( 'Manage Kustom orders from WooCommerce. Disable this to turn off every setting below, so that captures, cancellations and refunds are handled in the Kustom portal instead. The manual actions in the order metabox remain available.', 'klarna-checkout-for-woocommerce' ),
		);

		$settings['kom_auto_capture'] = array(
			'title'   => 'On order completion',
			'type'    => 'checkbox',
			'default' => $default_values['kom_auto_capture'],
			'label'   => __( 'Activate Kustom order automatically when WooCommerce order is marked complete.', 'klarna-checkout-for-woocommerce' ),
		);

		$settings['kom_auto_cancel'] = array(
			'title'   => 'On order cancel',
			'type'    => 'checkbox',
			'default' => $default_values['kom_auto_cancel'],
			'label'   => __( 'Cancel Kustom order automatically when WooCommerce order is marked canceled.', 'klarna-checkout-for-woocommerce' ),
		);

		$settings['kom_auto_update'] = array(
			'title'   => 'On order update',
			'type'    => 'checkbox',
			'default' => $default_values['kom_auto_update'],
			'label'   => __( 'Update Kustom order automatically when WooCommerce order is updated.', 'klarna-checkout-for-woocommerce' ),
		);

		$settings['kom_auto_order_sync'] = array(
			'title'   => 'On order creation ( manual )',
			'type'    => 'checkbox',
			'default' => $default_values['kom_auto_order_sync'],
			'label'   => __( 'Gets the customer information from Kustom when creating a manual admin order and adding a Kustom order id as a transaction id.', 'klarna-checkout-for-woocommerce' ),
		);

		$settings['kom_force_full_capture'] = array(
			'title'   => 'Force capture full order',
			'type'    => 'checkbox',
			'default' => $default_values['kom_force_full_capture'],
			'label'   => __( 'Force capture full order. Useful if the Kustom order has been updated by an ERP system.', 'klarna-checkout-for-woocommerce' ),
		);

		$settings['kom_enable_refunds'] = array(
			'title'   => 'Refunds',
			'type'    => 'checkbox',
			'default' => $default_values['kom_enable_refunds'],
			'label'   => __( 'Send refunds from WooCommerce to Kustom. Disable this if you refund in the Kustom portal instead, and WooCommerce will only offer to refund manually.', 'klarna-checkout-for-woocommerce' ),
		);

		return $settings;
	}

	/**
	 * Read one of the order management switches that are not order specific.
	 *
	 * Deliberately reads the option directly instead of going through SettingsUtility: the switches
	 * are read while the plugin is still bootstrapping on plugins_loaded, and SettingsUtility builds
	 * its defaults from \KCO_Fields::fields(), which would trigger translation loading before init.
	 *
	 * The fields are saved as gateway settings, so "woocommerce_kco_settings" is the option to read,
	 * not the legacy "kom_settings" that get_settings() falls back to.
	 *
	 * @param string $key           The setting to read.
	 * @param string $default_value The value to use when the merchant has never saved the setting.
	 * @return string
	 */
	private function get_global_setting( $key, $default_value = 'yes' ) {
		$settings = get_option( 'woocommerce_kco_settings', array() );

		return $settings[ $key ] ?? $default_value;
	}

	/**
	 * Whether order management is enabled at all.
	 *
	 * This is the master switch for the whole section. When disabled, no Kustom order is captured,
	 * cancelled, updated or refunded from WooCommerce, and every other setting in the section is
	 * ignored. The manual actions in the order metabox are still available.
	 *
	 * @return bool
	 */
	public function is_om_enabled() {
		return 'no' !== $this->get_global_setting( 'kom_enabled' );
	}

	/**
	 * Whether refunds should be sent to Kustom from WooCommerce.
	 *
	 * @return bool
	 */
	public function is_refunds_enabled() {
		return $this->is_om_enabled() && 'no' !== $this->get_global_setting( 'kom_enable_refunds' );
	}

	/**
	 * Whether one of the per order settings in the section is enabled for an order.
	 *
	 * A missing value counts as enabled for backwards compatibility. Always false when the master
	 * switch is off.
	 *
	 * @param string $key      The setting to check, e.g. "kom_auto_capture".
	 * @param int    $order_id WooCommerce order ID.
	 * @return bool
	 */
	public function is_enabled( $key, $order_id = 0 ) {
		if ( ! $this->is_om_enabled() ) {
			return false;
		}

		$options = $this->get_settings( $order_id );

		return ! isset( $options[ $key ] ) || 'yes' === $options[ $key ];
	}

	/**
	 * Retrieve the plugin settings.
	 *
	 * If the plugin's settings could not be found, we'll default to KP's or KCO's settings depending on the payment method.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array|false
	 */
	public function get_settings( $order_id ) {
		if ( empty( $order_id ) ) {
			/* If "kom_settings" is not available, use default values. */
			return get_option(
				'kom_settings',
				array_map(
					function ( $setting ) {
						if ( 'title' === $setting['type'] || ! isset( $setting['default'] ) ) {
							return null;
						}

						return $setting['default'];
					},
					$this->extend_settings( array() )
				)
			);
		}

		$order          = wc_get_order( $order_id );
		$payment_method = $order->get_payment_method();

		if ( 'kco' === $payment_method ) {
			return get_option( 'woocommerce_kco_settings', array() );
		} else {
			return get_option( 'kom_settings', array() );
		}
	}
}
