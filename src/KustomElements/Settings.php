<?php

namespace Krokedil\KustomCheckout\KustomElements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings class for Kustom Elements.
 *
 * Extends the KCO gateway settings page with a dedicated Kustom Elements section.
 */
class Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'kco_wc_gateway_settings', array( $this, 'extend_settings' ) );
	}

	/**
	 * Extends the KCO gateway settings with Kustom Elements fields.
	 *
	 * @param array $settings Existing settings fields.
	 * @return array
	 */
	public function extend_settings( $settings ) {
		$kco_settings = get_option( 'woocommerce_kco_settings', array() );

		$settings['ke_section_title'] = array(
			'title' => __( 'Kustom Elements', 'klarna-checkout-for-woocommerce' ),
			'type'  => 'title',
		);

		$settings['ke_enabled'] = array(
			'title'   => __( 'Enable Kustom Elements', 'klarna-checkout-for-woocommerce' ),
			'type'    => 'checkbox',
			'label'   => __( 'Display Kustom payment method information on your storefront.', 'klarna-checkout-for-woocommerce' ),
			'default' => $kco_settings['ke_enabled'] ?? 'no',
		);

		$settings['ke_script'] = array(
			'title'       => __( 'Elements script', 'klarna-checkout-for-woocommerce' ),
			'type'        => 'textarea',
			'description' => __( 'Paste the &lt;script&gt; tag provided in the Kustom Portal. It will be output in &lt;head&gt; on pages where Elements are active.', 'klarna-checkout-for-woocommerce' ),
			'default'     => $kco_settings['ke_script'] ?? '',
		);

		// Product page placement.
		$settings['ke_product_title'] = array(
			'title' => __( 'Product page', 'klarna-checkout-for-woocommerce' ),
			'type'  => 'title',
		);

		$settings['ke_product_enabled'] = array(
			'title'   => __( 'Enable on product page', 'klarna-checkout-for-woocommerce' ),
			'type'    => 'checkbox',
			'label'   => __( 'Show a Kustom Element on single product pages.', 'klarna-checkout-for-woocommerce' ),
			'default' => $kco_settings['ke_product_enabled'] ?? 'no',
		);

		$settings['ke_product_hook'] = array(
			'title'   => __( 'Position', 'klarna-checkout-for-woocommerce' ),
			'type'    => 'select',
			'options' => array(
				'woocommerce_single_product_summary'          => __( 'After product summary (default)', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_before_single_product_summary'   => __( 'Before product summary', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_after_single_product_summary'    => __( 'After product tabs', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_before_add_to_cart_button'       => __( 'Before add to cart button', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_after_add_to_cart_button'        => __( 'After add to cart button', 'klarna-checkout-for-woocommerce' ),
			),
			'default' => $kco_settings['ke_product_hook'] ?? 'woocommerce_single_product_summary',
		);

		$settings['ke_product_priority'] = array(
			'title'   => __( 'Priority', 'klarna-checkout-for-woocommerce' ),
			'type'    => 'number',
			'default' => $kco_settings['ke_product_priority'] ?? '25',
		);

		$settings['ke_product_data_key'] = array(
			'title'       => __( 'Data key', 'klarna-checkout-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'The data-key attribute for the product page element (provided by Kustom).', 'klarna-checkout-for-woocommerce' ),
			'default'     => $kco_settings['ke_product_data_key'] ?? '',
		);

		// Cart placement.
		$settings['ke_cart_title'] = array(
			'title' => __( 'Cart page', 'klarna-checkout-for-woocommerce' ),
			'type'  => 'title',
		);

		$settings['ke_cart_enabled'] = array(
			'title'   => __( 'Enable on cart page', 'klarna-checkout-for-woocommerce' ),
			'type'    => 'checkbox',
			'label'   => __( 'Show a Kustom Element on the cart page.', 'klarna-checkout-for-woocommerce' ),
			'default' => $kco_settings['ke_cart_enabled'] ?? 'no',
		);

		$settings['ke_cart_hook'] = array(
			'title'   => __( 'Position', 'klarna-checkout-for-woocommerce' ),
			'type'    => 'select',
			'options' => array(
				'woocommerce_cart_totals_after_order_total' => __( 'After order total (default)', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_before_cart_totals'            => __( 'Before cart totals', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_after_cart_totals'             => __( 'After cart totals', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_cart_collaterals'              => __( 'Cart collaterals', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_after_cart_table'              => __( 'After cart table', 'klarna-checkout-for-woocommerce' ),
			),
			'default' => $kco_settings['ke_cart_hook'] ?? 'woocommerce_cart_totals_after_order_total',
		);

		$settings['ke_cart_priority'] = array(
			'title'   => __( 'Priority', 'klarna-checkout-for-woocommerce' ),
			'type'    => 'number',
			'default' => $kco_settings['ke_cart_priority'] ?? '10',
		);

		$settings['ke_cart_data_key'] = array(
			'title'       => __( 'Data key', 'klarna-checkout-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'The data-key attribute for the cart page element (provided by Kustom).', 'klarna-checkout-for-woocommerce' ),
			'default'     => $kco_settings['ke_cart_data_key'] ?? '',
		);

		return $settings;
	}

	/**
	 * Returns a single KCO setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public static function get( $key, $default = '' ) {
		$settings = get_option( 'woocommerce_kco_settings', array() );
		return $settings[ $key ] ?? $default;
	}

	/**
	 * Whether Kustom Elements is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return 'yes' === self::get( 'ke_enabled', 'no' );
	}
}
