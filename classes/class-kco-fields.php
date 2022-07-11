<?php
/**
 * Class for Klarna Checkout gateway settings.
 *
 * @package Klarna_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * KCO_Fields class.
 *
 * Klarna Checkout for WooCommerce settings fields.
 */
class KCO_Fields {

	/**
	 * Returns the fields.
	 */
	public static function fields() {
		$settings = array(
			'enabled'                    => array(
				'title'       => __( 'Enable/Disable', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Enable Klarna Checkout', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'                      => array(
				'title'       => __( 'Title', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Payment method title.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'Klarna',
				'desc_tip'    => true,
			),
			'description'                => array(
				'title'       => __( 'Description', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'select_another_method_text' => array(
				'title'             => __( 'Other payment method button text', 'klarna-checkout-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Customize the <em>Select another payment method</em> button text that is displayed in checkout if using other payment methods than Klarna Checkout. Leave blank to use the default (and translatable) text.', 'klarna-checkout-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'add_to_email'               => array(
				'title'    => __( 'Add Klarna Post Purchase info to order email', 'klarna-checkout-for-woocommerce' ),
				'type'     => 'checkbox',
				'label'    => __( 'This will add Klarnas Post Purchase information to the order emails that are sent. You can read more about this here: ', 'klarna-checkout-for-woocommerce' ) . '<a href="https://docs.klarna.com/guidelines/klarna-checkout-best-practices/post-purchase/order-confirmation/" target="_blank">Klarna URLs</a>',
				'default'  => 'no',
				'desc_tip' => true,
			),
			'testmode'                   => array(
				'title'       => __( 'Test mode', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Enable Test Mode', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode using test API keys.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'logging'                    => array(
				'title'       => __( 'Logging', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Log debug messages', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'checkout_layout'            => array(
				'title'    => __( 'Checkout layout', 'klarna-checkout-for-woocommerce' ),
				'type'     => 'select',
				'options'  => array(
					'one_column_checkout' => __( 'One column checkout', 'klarna-checkout-for-woocommerce' ),
					'two_column_right'    => __( 'Two column checkout (Klarna Checkout in right column)', 'klarna-checkout-for-woocommerce' ),
					'two_column_left'     => __( 'Two column checkout (Klarna Checkout in left column)', 'klarna-checkout-for-woocommerce' ),
					'two_column_left_sf'  => __( 'Two column checkout (Klarna Checkout in left column) - Storefront light', 'klarna-checkout-for-woocommerce' ),
				),
				'default'  => 'two_column_right',
				'desc_tip' => false,
			),
			// EU.
			'credentials_eu'             => array(
				'title' => '<img src="' . KCO_WC_PLUGIN_URL . '/assets/img/flags/eu.svg height="12" /> API Credentials Europe',
				'type'  => 'title',
			),
			'merchant_id_eu'             => array(
				'title'             => __( 'Production Klarna API Username', 'klarna-checkout-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Use API username and API password you downloaded in the Klarna Merchant Portal. Don’t use your email address.', 'klarna-checkout-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => false,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'shared_secret_eu'           => array(
				'title'             => __( 'Production Klarna API Password', 'klarna-checkout-for-woocommerce' ),
				'type'              => 'password',
				'description'       => __( 'Use API username and API password you downloaded in the Klarna Merchant Portal. Don’t use your email address.', 'klarna-checkout-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => false,
				'custom_attributes' => array(
					'autocomplete' => 'new-password',
				),
			),
			'test_merchant_id_eu'        => array(
				'title'             => __( 'Test Klarna API Username', 'klarna-checkout-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Use API username and API password you downloaded in the Klarna Merchant Portal. Don’t use your email address.', 'klarna-checkout-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => false,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'test_shared_secret_eu'      => array(
				'title'             => __( 'Test Klarna API Password', 'klarna-checkout-for-woocommerce' ),
				'type'              => 'password',
				'description'       => __( 'Use API username and API password you downloaded in the Klarna Merchant Portal. Don’t use your email address.', 'klarna-checkout-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => false,
				'custom_attributes' => array(
					'autocomplete' => 'new-password',
				),
			),
			// US.
			'credentials_us'             => array(
				'title' => '<img src="' . KCO_WC_PLUGIN_URL . '/assets/img/flags/us.svg height="12" /> API Credentials United States',
				'type'  => 'title',
			),
			'merchant_id_us'             => array(
				'title'             => __( 'Production Klarna API Username', 'klarna-checkout-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Use API username and API password you downloaded in the Klarna Merchant Portal. Don’t use your email address.', 'klarna-checkout-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => false,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'shared_secret_us'           => array(
				'title'             => __( 'Production Klarna API Password', 'klarna-checkout-for-woocommerce' ),
				'type'              => 'password',
				'description'       => __( 'Use API username and API password you downloaded in the Klarna Merchant Portal. Don’t use your email address.', 'klarna-checkout-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => false,
				'custom_attributes' => array(
					'autocomplete' => 'new-password',
				),
			),
			'test_merchant_id_us'        => array(
				'title'             => __( 'Test Klarna API Username', 'klarna-checkout-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Use API username and API password you downloaded in the Klarna Merchant Portal. Don’t use your email address.', 'klarna-checkout-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => false,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'test_shared_secret_us'      => array(
				'title'             => __( 'Test Klarna API Password', 'klarna-checkout-for-woocommerce' ),
				'type'              => 'password',
				'description'       => __( 'Use API username and API password you downloaded in the Klarna Merchant Portal. Don’t use your email address.', 'klarna-checkout-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => false,
				'custom_attributes' => array(
					'autocomplete' => 'new-password',
				),
			),
			// Shipping.
			'shipping_section'           => array(
				'title' => __( 'Shipping settings', 'klarna-checkout-for-woocommerce' ),
				'type'  => 'title',
			),
			'allow_separate_shipping'    => array(
				'title'       => __( 'Separate shipping address', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Allow separate shipping address', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'If this option is checked, customers will be able to enter shipping address different than their billing address in checkout.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'shipping_methods_in_iframe' => array(
				'title'       => __( 'Shipping methods in iframe', 'klarna-checkout-for-woocommerce' ),
				'label'       => sprintf( __( 'Display Shipping methods in Klarna iframe.  If you are using the <b>Klarna Shipping Assistant</b> plugin, please follow the steps explained in the plugin documentation under the sections titled %1$s and %2$s', 'klarna-checkout-for-woocommerce' ), ( '<a href="https://docs.krokedil.com/klarna-for-woocommerce/additional-klarna-plugins/klarna-shipping-assistant/#configuration" target="_blank">configuration</a>' ), ( '<a href="https://docs.krokedil.com/klarna-for-woocommerce/additional-klarna-plugins/klarna-shipping-assistant/#tax-settings" target="_blank">tax settings</a>' ) ),
				'type'        => 'checkbox',
				'description' => __( 'If this option is checked, selection of shipping methods is done in Klarna iframe. Shipping price and name of the selected shipping method will still be displayed in WooCommerce order review.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'shipping_details'           => array(
				'title'             => __( 'Shipping details', 'klarna-checkout-for-woocommerce' ),
				'label'             => __( 'Shipping details note shown to customer', 'klarna-checkout-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Will be shown to customer in thank you page.', 'klarna-checkout-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => false,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			// Checkout.
			'checkout_section'           => array(
				'title' => __( 'Checkout settings', 'klarna-checkout-for-woocommerce' ),
				'type'  => 'title',
			),
			'send_product_urls'          => array(
				'title'    => __( 'Product URLs', 'klarna-checkout-for-woocommerce' ),
				'type'     => 'checkbox',
				'label'    => __( 'Send product and product image URLs to Klarna', 'klarna-checkout-for-woocommerce' ),
				'default'  => 'yes',
				'desc_tip' => true,
			),
			'dob_mandatory'              => array(
				'title'       => __( 'Date of birth mandatory', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Make customer date of birth mandatory', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'If checked, the customer cannot skip date of birth.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'nin_validation_mandatory'   => array(
				'title'       => __( 'National identification number mandatory', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Makes the validation of national identification numbers mandatory for customers.', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'If checked, the customer will have to complete a validation process for the national identification number ( SE, NO, FI and DK only ). This will also make national identification numbers mandatory.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'allowed_customer_types'     => array(
				'title'       => __( 'Allowed Customer Types', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'select',
				'options'     => array(
					'B2C'  => __( 'B2C only', 'klarna-checkout-for-woocommerce' ),
					'B2B'  => __( 'B2B only', 'klarna-checkout-for-woocommerce' ),
					'B2CB' => __( 'B2C & B2B (defaults to B2C)', 'klarna-checkout-for-woocommerce' ),
					'B2BC' => __( 'B2B & B2C (defaults to B2B)', 'klarna-checkout-for-woocommerce' ),
				),
				'description' => __( 'Select if you want to sell both to consumers and companies or only to one of them (available for SE, NO and FI). You need to contact Klarna directly to activate this with your account.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'B2C',
				'desc_tip'    => false,
			),
			'title_mandatory'            => array(
				'title'       => __( 'Title mandatory (GB)', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Make customer title mandatory', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'If unchecked, title becomes optional. Only available for orders for country GB.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'prefill_consent'            => array(
				'title'   => __( 'Show prefill consent notice', 'klarna-checkout-for-woocommerce' ),
				'label'   => __( 'Only applicable for stores based in Germany and Austria', 'klarna-checkout-for-woocommerce' ),
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			'quantity_fields'            => array(
				'title'       => __( 'Display quantity fields', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Display the quantity selection fields on the checkout page', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'If this option is checked, the customer will be able to change the quantity of a product purchased on the checkout page as they would on the cart page.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'show_subtotal_detail'       => array(
				'title'       => __( 'Display subtotal details', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'select',
				'options'     => array(
					'woo'    => __( 'In the WooCommerce order review', 'klarna-checkout-for-woocommerce' ),
					'iframe' => __( 'In the Klarna Checkout iFrame', 'klarna-checkout-for-woocommerce' ),
					'both'   => __( 'In both', 'klarna-checkout-for-woocommerce' ),
				),
				'description' => __( 'Select how you want to show the subtotal details on the checkout page.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'woo',
				'desc_tip'    => false,
			),
			// Checkout iframe settings.
			'color_settings_title'       => array(
				'title' => __( 'Color Settings', 'klarna-checkout-for-woocommerce' ),
				'type'  => 'title',
			),
			'color_button'               => array(
				'title'       => __( 'Checkout button color', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Checkout page button color', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_button_text'          => array(
				'title'       => __( 'Checkout button text color', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Checkout page button text color', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_checkbox'             => array(
				'title'       => __( 'Checkout checkbox color', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Checkout page checkbox color', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_checkbox_checkmark'   => array(
				'title'       => __( 'Checkout checkbox checkmark color', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Checkout page checkbox checkmark color', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_header'               => array(
				'title'       => __( 'Checkout header color', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Checkout page header color', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_link'                 => array(
				'title'       => __( 'Checkout link color', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Checkout page link color', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'radius_border'              => array(
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
				if ( 'nin_validation_mandatory' === $key ) {
					$new_settings['display_privacy_policy_text']       = array(
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
