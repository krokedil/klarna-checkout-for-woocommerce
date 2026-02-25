<?php
/**
 * Class for the KOM settings.
 *
 * @package WC_Klarna_Order_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to add settings to the Klarna Add-ons page.
 */
class Settings {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'wc_gateway_klarna_payments_settings', array( $this, 'extend_settings' ) );
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
				'kom_auto_capture'       => 'yes',
				'kom_auto_cancel'        => 'yes',
				'kom_auto_update'        => 'yes',
				'kom_auto_order_sync'    => 'yes',
				'kom_force_full_capture' => 'no',
				'kom_debug_log'          => 'yes',
			)
		);

		$settings['kom'] = array(
			'title' => 'Klarna Order Management',
			'type'  => 'title',
		);

		$settings['kom_auto_capture'] = array(
			'title'   => 'On order completion',
			'type'    => 'checkbox',
			'default' => $default_values['kom_auto_capture'],
			'label'   => __( 'Activate Klarna order automatically when WooCommerce order is marked complete.', 'klarna-checkout-for-woocommerce' ),
		);

		$settings['kom_auto_cancel'] = array(
			'title'   => 'On order cancel',
			'type'    => 'checkbox',
			'default' => $default_values['kom_auto_cancel'],
			'label'   => __( 'Cancel Klarna order automatically when WooCommerce order is marked canceled.', 'klarna-checkout-for-woocommerce' ),
		);

		$settings['kom_auto_update'] = array(
			'title'   => 'On order update',
			'type'    => 'checkbox',
			'default' => $default_values['kom_auto_update'],
			'label'   => __( 'Update Klarna order automatically when WooCommerce order is updated.', 'klarna-checkout-for-woocommerce' ),
		);

		$settings['kom_auto_order_sync'] = array(
			'title'   => 'On order creation ( manual )',
			'type'    => 'checkbox',
			'default' => $default_values['kom_auto_order_sync'],
			'label'   => __( 'Gets the customer information from Klarna when creating a manual admin order and adding a Klarna order id as a transaction id.', 'klarna-checkout-for-woocommerce' ),
		);

		$settings['kom_force_full_capture'] = array(
			'title'   => 'Force capture full order',
			'type'    => 'checkbox',
			'default' => $default_values['kom_force_full_capture'],
			'label'   => __( 'Force capture full order. Useful if the Klarna order has been updated by an ERP system.', 'klarna-checkout-for-woocommerce' ),
		);

		$settings['kom_debug_log'] = array(
			'title'   => 'Debug log',
			'type'    => 'checkbox',
			'default' => $default_values['kom_debug_log'],
			'label'   => __( 'Enable the debug log.', 'klarna-checkout-for-woocommerce' ),
		);

		return $settings;
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
			return get_option( 'woocommerce_kco_settings' );
		} elseif ( 'klarna_payments' === $payment_method ) {
			return get_option( 'woocommerce_klarna_payments_settings' );
		} else {
			return get_option( 'kom_settings' );
		}
	}
}
