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
			'enabled'               => array(
				'title'       => __( 'Enable/Disable', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Enable Klarna Checkout', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'                 => array(
				'title'       => __( 'Title', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'klarna-checkout-for-woocommerce' ),
				'default'     => __( 'Klarna Checkout', 'klarna-checkout-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'           => array(
				'title'       => __( 'Description', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your website.', 'klarna-checkout-for-woocommerce' ),
				'default'     => __( 'Pay with Klarna Checkout.', 'klarna-checkout-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'logging'               => array(
				'title'       => __( 'Logging', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Log debug messages', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'testmode'              => array(
				'title'       => __( 'Test mode', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Enable Test Mode', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode using test API keys.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),

			// AT.
			'credentials_at'        => array(
				'title' => '<img src="' . plugins_url( 'assets/img/flags/at.svg', KLARNA_CHECKOUT_FOR_WOOCOMMERCE_MAIN_FILE ) . '" height="12" /> Austria',
				'type'  => 'title',
			),
			'title_at'              => array(
				'title'       => __( 'Title', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'If this option is used, default payment method title will be overriden for AT purchases.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'description_at'        => array(
				'title'       => __( 'Description', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'textarea',
				'description' => __( 'If this option is used, default payment method description will be overriden for AT purchases.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id_at'        => array(
				'title'       => __( 'Production Username', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for AT.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_at'      => array(
				'title'       => __( 'Production Password', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for AT.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_merchant_id_at'   => array(
				'title'       => __( 'Test Username', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for AT.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_at' => array(
				'title'       => __( 'Test Password', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for AT.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			// DK.
			'credentials_dk'        => array(
				'title' => '<img src="' . plugins_url( 'assets/img/flags/dk.svg', KLARNA_CHECKOUT_FOR_WOOCOMMERCE_MAIN_FILE ) . '" height="12" /> Denmark',
				'type'  => 'title',
			),
			'title_dk'              => array(
				'title'       => __( 'Title', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'If this option is used, default payment method title will be overriden for DK purchases.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'description_dk'        => array(
				'title'       => __( 'Description', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'textarea',
				'description' => __( 'If this option is used, default payment method description will be overriden for DK purchases.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id_dk'        => array(
				'title'       => __( 'Production Username', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for DK.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_dk'      => array(
				'title'       => __( 'Production Password', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for DK.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_merchant_id_dk'   => array(
				'title'       => __( 'Test Username', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for DK.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_dk' => array(
				'title'       => __( 'Test Password', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for DK.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			// FI.
			'credentials_fi'        => array(
				'title' => '<img src="' . plugins_url( 'assets/img/flags/fi.svg', KLARNA_CHECKOUT_FOR_WOOCOMMERCE_MAIN_FILE ) . '" height="12" /> Finland',
				'type'  => 'title',
			),
			'title_fi'              => array(
				'title'       => __( 'Title', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'If this option is used, default payment method title will be overriden for FI purchases.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'description_fi'        => array(
				'title'       => __( 'Description', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'textarea',
				'description' => __( 'If this option is used, default payment method description will be overriden for FI purchases.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id_fi'        => array(
				'title'       => __( 'Production Username', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for FI.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_fi'      => array(
				'title'       => __( 'Production Password', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for FI.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_merchant_id_fi'   => array(
				'title'       => __( 'Test Username', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for FI.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_fi' => array(
				'title'       => __( 'Test Password', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for FI.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			// DE.
			'credentials_de'        => array(
				'title' => '<img src="' . plugins_url( 'assets/img/flags/de.svg', KLARNA_CHECKOUT_FOR_WOOCOMMERCE_MAIN_FILE ) . '" height="12" /> Germany',
				'type'  => 'title',
			),
			'title_de'              => array(
				'title'       => __( 'Title', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'If this option is used, default payment method title will be overriden for DE purchases.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'description_de'        => array(
				'title'       => __( 'Description', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'textarea',
				'description' => __( 'If this option is used, default payment method description will be overriden for DE purchases.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id_de'        => array(
				'title'       => __( 'Production Username', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for DE.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_de'      => array(
				'title'       => __( 'Production Password', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for DE.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_merchant_id_de'   => array(
				'title'       => __( 'Test Username', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for DE.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_de' => array(
				'title'       => __( 'Test Password', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for DE.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			// NL.
			'credentials_nl'        => array(
				'title' => '<img src="' . plugins_url( 'assets/img/flags/nl.svg', KLARNA_CHECKOUT_FOR_WOOCOMMERCE_MAIN_FILE ) . '" height="12" /> Netherlands',
				'type'  => 'title',
			),
			'title_nl'              => array(
				'title'       => __( 'Title', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'If this option is used, default payment method title will be overriden for NL purchases.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'description_nl'        => array(
				'title'       => __( 'Description', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'textarea',
				'description' => __( 'If this option is used, default payment method description will be overriden for NL purchases.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id_nl'        => array(
				'title'       => __( 'Production Username', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for NL.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_nl'      => array(
				'title'       => __( 'Production Password', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for NL.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_merchant_id_nl'   => array(
				'title'       => __( 'Test Username', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for NL.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_nl' => array(
				'title'       => __( 'Test Password', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for NL.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			// NO.
			'credentials_no'        => array(
				'title' => '<img src="' . plugins_url( 'assets/img/flags/no.svg', KLARNA_CHECKOUT_FOR_WOOCOMMERCE_MAIN_FILE ) . '" height="12" /> Norway',
				'type'  => 'title',
			),
			'title_no'              => array(
				'title'       => __( 'Title', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'If this option is used, default payment method title will be overriden for NO purchases.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'description_no'        => array(
				'title'       => __( 'Description', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'textarea',
				'description' => __( 'If this option is used, default payment method description will be overriden for NO purchases.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id_no'        => array(
				'title'       => __( 'Production Username', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for NO.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_no'      => array(
				'title'       => __( 'Production Password', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for NO.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_merchant_id_no'   => array(
				'title'       => __( 'Test Username', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for NO.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_no' => array(
				'title'       => __( 'Test Password', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for NO.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			// SE.
			'credentials_se'        => array(
				'title' => '<img src="' . plugins_url( 'assets/img/flags/se.svg', KLARNA_CHECKOUT_FOR_WOOCOMMERCE_MAIN_FILE ) . '" height="12" /> Sweden',
				'type'  => 'title',
			),
			'description_se'        => array(
				'title'       => __( 'Description', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'textarea',
				'description' => __( 'If this option is used, default payment method description will be overriden for SE purchases.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'title_se'              => array(
				'title'       => __( 'Title', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'If this option is used, default payment method title will be overriden for SE purchases.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id_se'        => array(
				'title'       => __( 'Production Username', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for SE.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_se'      => array(
				'title'       => __( 'Production Password', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for SE.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_merchant_id_se'   => array(
				'title'       => __( 'Test Username', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for EU.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_se' => array(
				'title'       => __( 'Test Password', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for SE.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			// UK.
			'credentials_gb'        => array(
				'title' => '<img src="' . plugins_url( 'assets/img/flags/gb.svg', KLARNA_CHECKOUT_FOR_WOOCOMMERCE_MAIN_FILE ) . '" height="12" /> United Kingdom',
				'type'  => 'title',
			),
			'description_gb'        => array(
				'title'       => __( 'Description', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'If this option is used, default payment method description will be overriden for UK purchases.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'title_gb'              => array(
				'title'       => __( 'Title', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'textarea',
				'description' => __( 'If this option is used, default payment method title will be overriden for UK purchases.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id_gb'        => array(
				'title'       => __( 'Production Username', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for UK.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_gb'      => array(
				'title'       => __( 'Production Password', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for UK.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_merchant_id_gb'   => array(
				'title'       => __( 'Test Username', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for UK.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_gb' => array(
				'title'       => __( 'Test Password', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for UK.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			// US.
			'credentials_us'        => array(
				'title' => '<img src="' . plugins_url( 'assets/img/flags/us.svg', KLARNA_CHECKOUT_FOR_WOOCOMMERCE_MAIN_FILE ) . '" height="12" /> United States',
				'type'  => 'title',
			),
			'title_us'              => array(
				'title'       => __( 'Title', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'If this option is used, default payment method title will be overriden for US purchases.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'description_us'        => array(
				'title'       => __( 'Description', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'textarea',
				'description' => __( 'If this option is used, default payment method description will be overriden for US purchases.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id_us'        => array(
				'title'       => __( 'Production Username', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for US.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_us'      => array(
				'title'       => __( 'Production Password', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for US.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_merchant_id_us'   => array(
				'title'       => __( 'Test Username', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for US.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_us' => array(
				'title'       => __( 'Test Password', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for US.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			'color_settings_title'     => array(
				'title' => __( 'Color Settings', 'klarna-checkout-for-woocommerce' ),
				'type'  => 'title',
			),
			'color_button'             => array(
				'title'       => __( 'Checkout button color', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Checkout page button color', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true
			),
			'color_button_text'        => array(
				'title'       => __( 'Checkout button text color', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Checkout page button text color', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true
			),
			'color_checkbox'           => array(
				'title'       => __( 'Checkout checkbox color', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Checkout page checkbox color', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true
			),
			'color_checkbox_checkmark' => array(
				'title'       => __( 'Checkout checkbox checkmark color', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Checkout page checkbox checkmark color', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true
			),
			'color_header'             => array(
				'title'       => __( 'Checkout header color', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Checkout page header color', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true
			),
			'color_link'               => array(
				'title'       => __( 'Checkout link color', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Checkout page link color', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true
			),
			'radius_border'            => array(
				'title'       => __( 'Checkout radius border (px)', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'number',
				'description' => __( 'Checkout page radius border in pixels', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true
			),
		) );
	}

}
