<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klarna_Checkout_For_WooCommerce_Fields class.
 *
 * Klarna Checkout for WooCommerce settings fields.
 */
class Klarna_Checkout_For_WooCommerce_Fields {

	/**
	 * Returns the fields.
	 */
	public static function fields() {
		return apply_filters( 'klarna_checkout_for_woocommerce_gateway_settings', array(
			'enabled'                  => array(
				'title'       => __( 'Enable/Disable', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Enable Klarna Checkout', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'                    => array(
				'title'       => __( 'Title', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'klarna-checkout-for-woocommerce' ),
				'default'     => __( 'Klarna Checkout', 'klarna-checkout-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'              => array(
				'title'       => __( 'Description', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your website.', 'klarna-checkout-for-woocommerce' ),
				'default'     => __( 'Pay with Klarna Checkout.', 'klarna-checkout-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'test_merchant_id_us'      => array(
				'title'       => __( 'Test username (US)', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_us'    => array(
				'title'       => __( 'Test password (US)', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id_us'           => array(
				'title'       => __( 'Production username (US)', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_us'         => array(
				'title'       => __( 'Production password (US)', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_merchant_id_uk'      => array(
				'title'       => __( 'Test username (UK)', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_uk'    => array(
				'title'       => __( 'Test password (UK)', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id_uk'           => array(
				'title'       => __( 'Production username (UK)', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_uk'         => array(
				'title'       => __( 'Production password (UK)', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_merchant_id_nl'      => array(
				'title'       => __( 'Test username (NL)', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_nl'    => array(
				'title'       => __( 'Test password (NL)', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id_nl'           => array(
				'title'       => __( 'Production username (NL)', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_nl'         => array(
				'title'       => __( 'Production password (NL)', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'testmode'                 => array(
				'title'       => __( 'Test mode', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Enable Test Mode', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode using test API keys.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'logging'                  => array(
				'title'       => __( 'Logging', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Log debug messages', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			)
		) );
	}

}
