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
	 * All locales supported by kustom-payment-method-display.
	 *
	 * @var array<string,string>
	 */
	private static $supported_locales = array(
		'cs-CZ' => 'Czech (Czechia)',
		'da-DK' => 'Danish (Denmark)',
		'de-CH' => 'German (Switzerland)',
		'de-DE' => 'German (Germany)',
		'en-AT' => 'English (Austria)',
		'en-AU' => 'English (Australia)',
		'en-BE' => 'English (Belgium)',
		'en-CA' => 'English (Canada)',
		'en-DE' => 'English (Germany)',
		'en-DK' => 'English (Denmark)',
		'en-FI' => 'English (Finland)',
		'en-FR' => 'English (France)',
		'en-GB' => 'English (United Kingdom)',
		'en-NL' => 'English (Netherlands)',
		'en-NO' => 'English (Norway)',
		'en-SE' => 'English (Sweden)',
		'en-US' => 'English (United States)',
		'es-ES' => 'Spanish (Spain)',
		'es-US' => 'Spanish (United States)',
		'fi-FI' => 'Finnish (Finland)',
		'fr-BE' => 'French (Belgium)',
		'fr-CA' => 'French (Canada)',
		'fr-CH' => 'French (Switzerland)',
		'fr-FR' => 'French (France)',
		'it-IT' => 'Italian (Italy)',
		'nb-NO' => 'Norwegian (Norway)',
		'nl-BE' => 'Dutch (Belgium)',
		'nl-NL' => 'Dutch (Netherlands)',
		'pl-PL' => 'Polish (Poland)',
		'pt-PT' => 'Portuguese (Portugal)',
		'sv-FI' => 'Swedish (Finland)',
		'sv-SE' => 'Swedish (Sweden)',
	);

	/**
	 * All payment method identifiers supported by kustom-payment-method-display.
	 *
	 * @var array<string,string>
	 */
	private static $supported_methods = array(
		'affirm'          => 'Affirm',
		'aktia'           => 'Aktia',
		'alandsbanken'    => 'Ålandsbanken',
		'alipay'          => 'Alipay',
		'amazonpay'       => 'Amazon Pay',
		'amex'            => 'American Express',
		'applepay'        => 'Apple Pay',
		'bancontact'      => 'Bancontact',
		'bank'            => 'Bank',
		'billie'          => 'Billie',
		'bitcoin'         => 'Bitcoin',
		'bitcoincash'     => 'Bitcoin Cash',
		'bitpay'          => 'BitPay',
		'blik'            => 'BLIK',
		'card'            => 'Card',
		'cartes-bancaires' => 'Cartes Bancaires',
		'citadele'        => 'Citadele',
		'danskebank'      => 'Danske Bank',
		'dinersclub'      => 'Diners Club',
		'directdebit'     => 'Direct Debit',
		'discover'        => 'Discover',
		'dnb'             => 'DNB',
		'elo'             => 'Elo',
		'eps'             => 'EPS',
		'forbrugsforeningen' => 'Forbrugsforeningen',
		'giropay'         => 'Giropay',
		'googlepay'       => 'Google Pay',
		'ideal'           => 'iDEAL',
		'interac'         => 'Interac',
		'jcb'             => 'JCB',
		'klarna'          => 'Klarna',
		'maestro'         => 'Maestro',
		'mastercard'      => 'Mastercard',
		'mbway'           => 'MB Way',
		'mobilepay'       => 'MobilePay',
		'nordea'          => 'Nordea',
		'omasp'           => 'Oma Säästöpankki',
		'op'              => 'OP',
		'p24'             => 'Przelewy24',
		'payoneer'        => 'Payoneer',
		'paypal'          => 'PayPal',
		'paysafe'         => 'Paysafe',
		'poppankki'       => 'POP Pankki',
		'saastopankki'    => 'Säästöpankki',
		'satispay'        => 'Satispay',
		'seb'             => 'SEB',
		'sepa'            => 'SEPA',
		'skrill'          => 'Skrill',
		'sofort'          => 'Sofort',
		'swish'           => 'Swish',
		'twint'           => 'TWINT',
		'unionpay'        => 'UnionPay',
		'venmo'           => 'Venmo',
		'vipps'           => 'Vipps',
		'visa'            => 'Visa',
		'visaelectron'    => 'Visa Electron',
		'wechat'          => 'WeChat Pay',
	);

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

		$settings['ke_snippet'] = array(
			'title'       => __( 'Installation snippet', 'klarna-checkout-for-woocommerce' ),
			'type'        => 'textarea',
			'description' => __( 'Paste the full installation snippet from the Kustom Portal. The API key is extracted automatically and the script is generated correctly for playground or production based on the Test mode setting above.', 'klarna-checkout-for-woocommerce' ),
			'default'     => $kco_settings['ke_snippet'] ?? '',
		);

		$settings['ke_locale'] = array(
			'title'       => __( 'Locale', 'klarna-checkout-for-woocommerce' ),
			'type'        => 'select',
			'description' => __( 'Market and language for the element. Must match the market configured in the Kustom Portal.', 'klarna-checkout-for-woocommerce' ),
			'options'     => self::$supported_locales,
			'default'     => $kco_settings['ke_locale'] ?? self::default_locale(),
		);

		$settings['ke_include'] = array(
			'title'       => __( 'Include payment methods', 'klarna-checkout-for-woocommerce' ),
			'type'        => 'multiselect',
			'description' => __( 'Optional. Payment methods to always show, even if not returned by the API. Hold Ctrl/Cmd to select multiple.', 'klarna-checkout-for-woocommerce' ),
			'options'     => self::$supported_methods,
			'default'     => $kco_settings['ke_include'] ?? array(),
		);

		$settings['ke_exclude'] = array(
			'title'       => __( 'Exclude payment methods', 'klarna-checkout-for-woocommerce' ),
			'type'        => 'multiselect',
			'description' => __( 'Optional. Payment methods to hide from the list. Hold Ctrl/Cmd to select multiple.', 'klarna-checkout-for-woocommerce' ),
			'options'     => self::$supported_methods,
			'default'     => $kco_settings['ke_exclude'] ?? array(),
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
				'woocommerce_single_product_summary'        => __( 'After product summary (default)', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_before_single_product_summary' => __( 'Before product summary', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_after_single_product_summary'  => __( 'After product tabs', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_before_add_to_cart_button'     => __( 'Before add to cart button', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_after_add_to_cart_button'      => __( 'After add to cart button', 'klarna-checkout-for-woocommerce' ),
			),
			'default' => $kco_settings['ke_product_hook'] ?? 'woocommerce_single_product_summary',
		);

		$settings['ke_product_priority'] = array(
			'title'   => __( 'Priority', 'klarna-checkout-for-woocommerce' ),
			'type'    => 'number',
			'default' => $kco_settings['ke_product_priority'] ?? '25',
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

	/**
	 * Extracts the public API key from the stored installation snippet.
	 *
	 * Looks for data-public-api-key="..." in whatever the merchant pasted.
	 *
	 * @return string The extracted API key, or empty string if not found.
	 */
	public static function get_api_key() {
		$snippet = (string) self::get( 'ke_snippet', '' );
		if ( empty( $snippet ) ) {
			return '';
		}
		if ( preg_match( '/data-public-api-key=["\']([^"\']+)["\']/', $snippet, $matches ) ) {
			return sanitize_text_field( $matches[1] );
		}
		return '';
	}

	/**
	 * Whether KCO is running in test/playground mode.
	 *
	 * @return bool
	 */
	public static function is_test_mode() {
		return 'yes' === self::get( 'testmode', 'no' );
	}

	/**
	 * Returns the Kustom Elements JS URL for the current environment.
	 *
	 * @return string
	 */
	public static function get_script_url() {
		if ( self::is_test_mode() ) {
			return 'https://js.playground.kustom.co/kustom-elements/v1/pre-load.js';
		}
		return 'https://js.kustom.co/kustom-elements/v1/pre-load.js';
	}

	/**
	 * Returns the selected locale as a string.
	 *
	 * @return string
	 */
	public static function get_locale() {
		return (string) self::get( 'ke_locale', self::default_locale() );
	}

	/**
	 * Returns the include list as a comma-separated string for the element attribute.
	 *
	 * Multiselect values are stored as arrays by WooCommerce.
	 *
	 * @return string
	 */
	public static function get_include() {
		$value = self::get( 'ke_include', array() );
		return is_array( $value ) ? implode( ',', $value ) : (string) $value;
	}

	/**
	 * Returns the exclude list as a comma-separated string for the element attribute.
	 *
	 * @return string
	 */
	public static function get_exclude() {
		$value = self::get( 'ke_exclude', array() );
		return is_array( $value ) ? implode( ',', $value ) : (string) $value;
	}

	/**
	 * Returns a best-guess locale derived from the WP site locale.
	 *
	 * Converts WP locale format (sv_SE) to Kustom format (sv-SE) and
	 * returns it if it exists in the supported list, otherwise falls back to en-GB.
	 *
	 * @return string
	 */
	public static function default_locale() {
		$candidate = str_replace( '_', '-', get_locale() );
		return isset( self::$supported_locales[ $candidate ] ) ? $candidate : 'en-GB';
	}
}
