<?php
namespace Krokedil\KustomCheckout\OrderManagement;

use Krokedil\KustomCheckout\OrderManagement\Webhooks\Webhooks;
use Krokedil\KustomCheckout\Utility\SettingsUtility;

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
			)
		);

		$settings['kom'] = array(
			'title' => 'Kustom Order Management',
			'type'  => 'title',
		);

		$settings['kom_enabled'] = array(
			'title'       => __( 'Enable order management', 'klarna-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			'class'       => 'krokedil_conditional_toggler krokedil_toggler_kom',
			'default'     => $default_values['kom_enabled'],
			'label'       => __( 'Let WooCommerce activate, cancel, update and credit Kustom orders.', 'klarna-checkout-for-woocommerce' ),
			'description' => __( 'Disable this if you manage orders in the Kustom portal. Refunds must then be registered in WooCommerce with "Refund manually". The manual actions on individual orders remain available.', 'klarna-checkout-for-woocommerce' ),
			'desc_tip'    => true,
		);

		$settings['kom_auto_capture'] = array(
			'title'   => 'On order completion',
			'type'    => 'checkbox',
			'class'   => 'krokedil_conditional_setting krokedil_conditional_kom',
			'default' => $default_values['kom_auto_capture'],
			'label'   => __( 'Activate Kustom order automatically when WooCommerce order is marked complete.', 'klarna-checkout-for-woocommerce' ),
		);

		$settings['kom_auto_cancel'] = array(
			'title'   => 'On order cancel',
			'type'    => 'checkbox',
			'class'   => 'krokedil_conditional_setting krokedil_conditional_kom',
			'default' => $default_values['kom_auto_cancel'],
			'label'   => __( 'Cancel Kustom order automatically when WooCommerce order is marked canceled.', 'klarna-checkout-for-woocommerce' ),
		);

		$settings['kom_auto_update'] = array(
			'title'   => 'On order update',
			'type'    => 'checkbox',
			'class'   => 'krokedil_conditional_setting krokedil_conditional_kom',
			'default' => $default_values['kom_auto_update'],
			'label'   => __( 'Update Kustom order automatically when WooCommerce order is updated.', 'klarna-checkout-for-woocommerce' ),
		);

		$settings['kom_auto_order_sync'] = array(
			'title'   => 'On order creation ( manual )',
			'type'    => 'checkbox',
			'class'   => 'krokedil_conditional_setting krokedil_conditional_kom',
			'default' => $default_values['kom_auto_order_sync'],
			'label'   => __( 'Gets the customer information from Kustom when creating a manual admin order and adding a Kustom order id as a transaction id.', 'klarna-checkout-for-woocommerce' ),
		);

		$settings['kom_force_full_capture'] = array(
			'title'   => 'Force capture full order',
			'type'    => 'checkbox',
			'class'   => 'krokedil_conditional_setting krokedil_conditional_kom',
			'default' => $default_values['kom_force_full_capture'],
			'label'   => __( 'Force capture full order. Useful if the Kustom order has been updated by an ERP system.', 'klarna-checkout-for-woocommerce' ),
		);

		$settings['kom_webhooks'] = array(
			'title'       => __( 'Kustom webhooks', 'klarna-checkout-for-woocommerce' ),
			'type'        => 'title',
			'description' => $this->get_webhooks_description(),
		);

		$settings['kom_webhook_capture_sync'] = array(
			'title'       => __( 'Sync captures from Kustom', 'klarna-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			'default'     => 'no',
			'label'       => __( 'Register captures made in the Kustom Portal on the WooCommerce order.', 'klarna-checkout-for-woocommerce' ),
			'description' => __( 'A fully captured order is set to Completed. Captures made from WooCommerce are recognized and not registered twice.', 'klarna-checkout-for-woocommerce' ),
			'desc_tip'    => true,
		);

		$settings['kom_webhook_signing_secret'] = array(
			'title'       => __( 'Production webhook signing secret', 'klarna-checkout-for-woocommerce' ),
			'type'        => 'password',
			'default'     => '',
			'description' => __( 'The signing secret (whsec_...) of the webhook in the production Kustom Portal.', 'klarna-checkout-for-woocommerce' ),
			'desc_tip'    => true,
		);

		$settings['kom_webhook_test_signing_secret'] = array(
			'title'       => __( 'Test webhook signing secret', 'klarna-checkout-for-woocommerce' ),
			'type'        => 'password',
			'default'     => '',
			'description' => __( 'The signing secret (whsec_...) of the webhook in the test Kustom Portal.', 'klarna-checkout-for-woocommerce' ),
			'desc_tip'    => true,
		);

		return $settings;
	}

	/**
	 * Get the description of the webhooks section, with the URL to enter in the Kustom Portal.
	 *
	 * @return string
	 */
	protected function get_webhooks_description() {
		$description = __( 'In the Kustom Portal, go to Integrations → Webhooks, add an endpoint with the URL below and subscribe to capture.created. Then copy the signing secret here. Production and test have separate secrets.', 'klarna-checkout-for-woocommerce' );

		// The REST URL needs the rewrite rules, which aren't set up if the settings are read very early.
		if ( empty( $GLOBALS['wp_rewrite'] ) ) {
			return $description;
		}

		return $description . '<br><code>' . esc_html( Webhooks::get_url() ) . '</code>';
	}

	/**
	 * Whether captures made outside of WooCommerce should be registered from webhooks.
	 *
	 * @return bool
	 */
	public static function is_capture_sync_enabled() {
		return wc_string_to_bool( SettingsUtility::get_setting( 'kom_webhook_capture_sync', 'no' ) );
	}

	/**
	 * Get the configured webhook signing secrets.
	 *
	 * Both are returned, so a webhook is accepted regardless of which environment the order was placed in.
	 *
	 * @return string[]
	 */
	public static function get_webhook_secrets() {
		return array(
			SettingsUtility::get_setting( 'kom_webhook_signing_secret', '' ),
			SettingsUtility::get_setting( 'kom_webhook_test_signing_secret', '' ),
		);
	}

	/**
	 * Whether order management is enabled.
	 *
	 * Defaults to enabled so stores that never saw this setting are unaffected.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return wc_string_to_bool( SettingsUtility::get_setting( 'kom_enabled', 'yes' ) );
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
