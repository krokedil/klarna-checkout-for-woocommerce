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
		$settings = array(
			'enabled'                      => array(
				'title'       => __( 'Enable/Disable', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Enable Klarna Checkout', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'                        => array(
				'title'       => __( 'Title', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Payment method title.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'Klarna',
				'desc_tip'    => true,
			),
			'description'                  => array(
				'title'       => __( 'Description', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'allow_separate_shipping'      => array(
				'title'       => __( 'Separate shipping address', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Allow separate shipping address', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'If this option is checked, customers will be able to enter shipping address different than their billing address in checkout.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'select_another_method_text'   => array(
				'title'       => __( 'Other payment method button text', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Customize the <em>Select another payment method</em> button text that is displayed in checkout if using other payment methods than Klarna Checkout. Leave blank to use the default (and translatable) text.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shipping_details'             => array(
				'title'       => __( 'Shipping details', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Shipping details note shown to customer', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Will be shown to customer in thank you page.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => false,
			),
			'allowed_customer_types'       => array(
				'title'       => __( 'Allowed Customer Types', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'select',
				'options'     => array(
					'B2C'  => __( 'B2C only', 'klarna-checkout-for-woocommerce' ),
					'B2B'  => __( 'B2B only', 'klarna-checkout-for-woocommerce' ),
					'B2CB' => __( 'B2C & B2B (defaults to B2C)', 'klarna-checkout-for-woocommerce' ),
					'B2BC' => __( 'B2B & B2C (defaults to B2B)', 'klarna-checkout-for-woocommerce' ),
				),
				'description' => sprintf( __( 'Select if you want to sell both to consumers and companies or only to one of them (available for SE, NO and FI). Learn more and <a href="%s" target="_blank">sign up for Klarna Checkout B2B here</a>.', 'klarna-checkout-for-woocommerce' ), 'https://www.klarna.com/se/foretag/klarna-checkout/klarna-checkout-foretag-form' ),
				'default'     => 'B2C',
				'desc_tip'    => false
			),
			'send_product_urls'            => array(
				'title'    => __( 'Product URLs', 'klarna-checkout-for-woocommerce' ),
				'type'     => 'checkbox',
				'label'    => __( 'Send product and product image URLs to Klarna', 'klarna-checkout-for-woocommerce' ),
				'default'  => 'yes',
				'desc_tip' => true,
			),
			'logging'                      => array(
				'title'       => __( 'Logging', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Log debug messages', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'testmode'                     => array(
				'title'       => __( 'Test mode', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Enable Test Mode', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode using test API keys.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'dob_mandatory'                => array(
				'title'       => __( 'Date of birth mandatory', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Make customer date of birth mandatory', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'If checked, the customer cannot skip date of birth.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			// EU.
			'credentials_eu'               => array(
				'title' => '<img src="' . plugins_url( 'assets/img/flags/eu.svg', KCO_WC_MAIN_FILE ) . '" height="12" /> API Credentials Europe',
				'type'  => 'title',
			),
			'merchant_id_eu'               => array(
				'title'       => __( 'Production Username (UID)', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Checkout merchant account for Europe.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_eu'             => array(
				'title'       => __( 'Production Password', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Checkout merchant account for Europe.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_merchant_id_eu'          => array(
				'title'       => __( 'Test Username (UID)', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Checkout merchant account for Europe.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_eu'        => array(
				'title'       => __( 'Test Password', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Checkout merchant account for Europe.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'title_mandatory'              => array(
				'title'       => __( 'Title mandatory (GB)', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Make customer title mandatory', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'If unchecked, title becomes optional. Only available for orders for country GB.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'prefill_consent'              => array(
				'title'   => __( 'Show prefill consent notice', 'klarna-checkout-for-woocommerce' ),
				'label'   => __( 'Only applicable for stores based in Germany and Austria', 'klarna-checkout-for-woocommerce' ),
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			// US.
			'credentials_us'               => array(
				'title' => '<img src="' . plugins_url( 'assets/img/flags/us.svg', KCO_WC_MAIN_FILE ) . '" height="12" /> API Credentials United States',
				'type'  => 'title',
			),
			'merchant_id_us'               => array(
				'title'       => __( 'Production Username (UID)', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Checkout merchant account for US.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_us'             => array(
				'title'       => __( 'Production Password', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Checkout merchant account for US.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_merchant_id_us'          => array(
				'title'       => __( 'Test Username (UID)', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Checkout merchant account for US.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_us'        => array(
				'title'       => __( 'Test Password', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Checkout merchant account for US.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			// Checkout iframe settings.
			'color_settings_title'         => array(
				'title' => __( 'Color Settings', 'klarna-checkout-for-woocommerce' ),
				'type'  => 'title',
			),
			'color_button'                 => array(
				'title'       => __( 'Checkout button color', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Checkout page button color', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_button_text'            => array(
				'title'       => __( 'Checkout button text color', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Checkout page button text color', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_checkbox'               => array(
				'title'       => __( 'Checkout checkbox color', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Checkout page checkbox color', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_checkbox_checkmark'     => array(
				'title'       => __( 'Checkout checkbox checkmark color', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Checkout page checkbox checkmark color', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_header'                 => array(
				'title'       => __( 'Checkout header color', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Checkout page header color', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_link'                   => array(
				'title'       => __( 'Checkout link color', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Checkout page link color', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'radius_border'                => array(
				'title'       => __( 'Checkout radius border (px)', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'number',
				'description' => __( 'Checkout page radius border in pixels', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
		);
		$wc_version = defined( 'WC_VERSION' ) && WC_VERSION ? WC_VERSION : null;

		if ( version_compare( $wc_version, '3.4', '>=' ) ) {
			$new_settings = array();
			foreach ( $settings as $key => $value ) {
				$new_settings[ $key ] = $value;
				if ( 'dob_mandatory' === $key ) {
					$new_settings['display_privacy_policy_text'] = array(
						'title'   => __( 'Checkout privacy policy text', 'klarna-checkout-for-woocommerce' ),
						'label'   => __( 'Select if you want to show the <em>Checkout privacy policy</em> text on the checkout page, and where you want to display it.', 'klarna-checkout-for-woocommerce' ),
						'type'    => 'select',
						'default' => 'no',
						'options' => array(
							'no'    => __( 'Do not display', 'klarna-checkout-for-woocommerce' ),
							'above' => __( 'Display above checkout', 'klarna-checkout-for-woocommerce' ),
							'below' => __( 'Display below checkout', 'klarna-checkout-for-woocommerce' ),
						),
					);
					$new_settings['add_terms_and_conditions_checkbox'] = array(
						'title'       => __( 'Terms and conditions checkbox', 'klarna-checkout-for-woocommerce' ),
						'label'       => __( 'Add a terms and conditions checkbox inside Klarna checkout iframe', 'klarna-checkout-for-woocommerce' ),
						'type'        => 'checkbox',
						'description' => __( 'To change the text navigate to → Appearance → Customize → WooCommerce → Checkout.', 'klarna-checkout-for-woocommerce' ),
						'default'     => 'no',
						'desc_tip'    => false,
					);
				}
			}
			$settings = $new_settings;
		}
		return apply_filters( 'kco_wc_gateway_settings', $settings );
	}
}
