<?php
namespace Krokedil\KustomCheckout\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings class.
 *
 * Adds the "Elements" settings section to the Kustom Checkout settings page.
 */
class Settings {
	/**
	 * The hook that the product page placements are attached to. The placement setting holds the priority.
	 *
	 * @var string
	 */
	const PRODUCT_HOOK = 'woocommerce_single_product_summary';

	/**
	 * The allowed product page placement priorities on the product summary hook.
	 *
	 * @var string[]
	 */
	const PRODUCT_PRIORITIES = array( '4', '7', '15', '25', '35', '45', '55' );

	/**
	 * The allowed cart page placement hooks, sorted as they appear on the page.
	 *
	 * @var string[]
	 */
	const CART_HOOKS = array(
		'woocommerce_cart_collaterals',
		'woocommerce_before_cart_totals',
		'woocommerce_proceed_to_checkout',
		'woocommerce_after_cart_totals',
		'woocommerce_after_cart',
	);

	/**
	 * The public API key settings.
	 *
	 * @var string[]
	 */
	const PUBLIC_API_KEY_FIELDS = array( 'elements_live_public_api_key', 'elements_playground_public_api_key' );

	/**
	 * The gateway credentials that must never be used as a public API key.
	 *
	 * @var string[]
	 */
	const CREDENTIAL_FIELDS = array(
		'merchant_id',
		'shared_secret',
		'test_merchant_id',
		'test_shared_secret',
		'merchant_id_eu',
		'shared_secret_eu',
		'test_merchant_id_eu',
		'test_shared_secret_eu',
		'merchant_id_us',
		'shared_secret_us',
		'test_merchant_id_us',
		'test_shared_secret_us',
	);

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'kco_wc_gateway_settings', array( $this, 'extend_settings' ) );
		add_filter( 'woocommerce_generate_krokedil_subtitle_html', array( $this, 'generate_krokedil_subtitle_html' ), 10, 3 );
		add_filter( 'woocommerce_settings_api_sanitized_fields_kco', array( $this, 'sanitize_public_api_keys' ) );
	}

	/**
	 * The product page placement options. The key is the priority on the woocommerce_single_product_summary hook.
	 *
	 * @return array
	 */
	public static function get_product_placement_options() {
		return array(
			''   => __( 'Disabled', 'klarna-checkout-for-woocommerce' ),
			'4'  => __( 'Above Title', 'klarna-checkout-for-woocommerce' ),
			'7'  => __( 'Between Title and Price', 'klarna-checkout-for-woocommerce' ),
			'15' => __( 'Between Price and Excerpt', 'klarna-checkout-for-woocommerce' ),
			'25' => __( 'Between Excerpt and Add to cart button', 'klarna-checkout-for-woocommerce' ),
			'35' => __( 'Between Add to cart button and Product meta', 'klarna-checkout-for-woocommerce' ),
			'45' => __( 'Between Product meta and Product sharing buttons', 'klarna-checkout-for-woocommerce' ),
			'55' => __( 'After Product sharing buttons', 'klarna-checkout-for-woocommerce' ),
		);
	}

	/**
	 * The cart page placement options, sorted as they appear on the page. The key is the hook name.
	 *
	 * @return array
	 */
	public static function get_cart_placement_options() {
		return array(
			''                                => __( 'Disabled', 'klarna-checkout-for-woocommerce' ),
			'woocommerce_cart_collaterals'    => __( 'Above cross-sell', 'klarna-checkout-for-woocommerce' ),
			'woocommerce_before_cart_totals'  => __( 'Above cart totals', 'klarna-checkout-for-woocommerce' ),
			'woocommerce_proceed_to_checkout' => __( 'Between cart totals and proceed to checkout button', 'klarna-checkout-for-woocommerce' ),
			'woocommerce_after_cart_totals'   => __( 'After proceed to checkout button', 'klarna-checkout-for-woocommerce' ),
			'woocommerce_after_cart'          => __( 'Bottom of the page', 'klarna-checkout-for-woocommerce' ),
		);
	}

	/**
	 * Given a settings array, extend it with the Elements settings.
	 *
	 * @param array $settings A settings array.
	 * @return array
	 */
	public function extend_settings( $settings ) {
		$settings['elements'] = array(
			'title'       => __( 'Elements', 'klarna-checkout-for-woocommerce' ),
			'type'        => 'krokedil_section_start',
			'id'          => 'elements',
			'description' => __( 'Display Kustom payment methods and delivery options on your store pages with Kustom Elements.', 'klarna-checkout-for-woocommerce' ),
		);

		$settings['elements_live_public_api_key'] = array(
			'title'             => __( 'Production public API key', 'klarna-checkout-for-woocommerce' ),
			'type'              => 'text',
			'description'       => sprintf(
				// translators: %s: Kustom Portal link.
				__( 'The public API keys used here are separate from the API Username and Password used for the payment integration above — find them in the %s under Elements → Installation script → data-public-api-key.', 'klarna-checkout-for-woocommerce' ),
				'<a href="https://portal.kustom.com" target="_blank">' . __( 'Kustom Portal', 'klarna-checkout-for-woocommerce' ) . '</a>'
			),
			'desc_tip'          => __( 'The public API key used for Kustom Elements when test mode is disabled.', 'klarna-checkout-for-woocommerce' ),
			'default'           => '',
			'custom_attributes' => array(
				'autocomplete' => 'off',
			),
		);

		$settings['elements_playground_public_api_key'] = array(
			'title'             => __( 'Test public API key', 'klarna-checkout-for-woocommerce' ),
			'type'              => 'text',
			'description'       => __( 'Used when test mode is enabled.', 'klarna-checkout-for-woocommerce' ),
			'default'           => '',
			'custom_attributes' => array(
				'autocomplete' => 'off',
			),
		);

		$settings['elements_payment_display_title'] = array(
			'title'       => __( 'Payment Method Display', 'klarna-checkout-for-woocommerce' ),
			'type'        => 'krokedil_subtitle',
			'description' => __( 'Presents the available Kustom payment methods on your store pages. You can also display it with the shortcode [kustom_payment_element] or block Kustom Payment Element.', 'klarna-checkout-for-woocommerce' ),
		);

		$settings['elements_payment_product_position'] = $this->get_product_placement_field( __( 'Payment Method Display placement on product pages', 'klarna-checkout-for-woocommerce' ) );
		$settings['elements_payment_cart_position']    = $this->get_cart_placement_field( __( 'Payment Method Display placement on cart page', 'klarna-checkout-for-woocommerce' ) );

		$settings['elements_shipping_display_title'] = array(
			'title'       => __( 'Delivery Method Display', 'klarna-checkout-for-woocommerce' ),
			'type'        => 'krokedil_subtitle',
			'description' => __( 'Presents the available delivery options on your store pages. Requires Kustom Shipping Assistant (KSA) to be active and configured. You can also display it with the shortcode [kustom_delivery_element] or block Kustom Delivery Element.', 'klarna-checkout-for-woocommerce' ),
		);

		$settings['elements_shipping_product_position'] = $this->get_product_placement_field( __( 'Delivery Method Display placement on product pages', 'klarna-checkout-for-woocommerce' ) );
		$settings['elements_shipping_cart_position']    = $this->get_cart_placement_field( __( 'Delivery Method Display placement on cart page', 'klarna-checkout-for-woocommerce' ) );

		$settings['elements_end'] = array(
			'type' => 'krokedil_section_end',
		);

		return $settings;
	}

	/**
	 * Get a product page placement select field.
	 *
	 * @param string $title The field title.
	 * @return array
	 */
	private function get_product_placement_field( $title ) {
		return array(
			'title'    => $title,
			'type'     => 'select',
			'options'  => self::get_product_placement_options(),
			'default'  => '15',
			// translators: %s: The product page hook name.
			'desc_tip' => sprintf( __( 'Where on the product page the element is displayed. Uses the %s hook.', 'klarna-checkout-for-woocommerce' ), self::PRODUCT_HOOK ),
		);
	}

	/**
	 * Get a cart page placement select field.
	 *
	 * @param string $title The field title.
	 * @return array
	 */
	private function get_cart_placement_field( $title ) {
		return array(
			'title'    => $title,
			'type'     => 'select',
			'options'  => self::get_cart_placement_options(),
			'default'  => 'woocommerce_cart_collaterals',
			'desc_tip' => __( 'Where on the cart page the element is displayed. Each option uses a WooCommerce cart hook, from woocommerce_cart_collaterals (top) to woocommerce_after_cart (bottom).', 'klarna-checkout-for-woocommerce' ),
		);
	}

	/**
	 * Clear public API keys that are obviously not public API keys, e.g. the gateway's merchant ID or shared secret.
	 *
	 * @param array $settings The sanitized settings about to be saved.
	 * @return array
	 */
	public function sanitize_public_api_keys( $settings ) {
		$credentials = array();
		foreach ( self::CREDENTIAL_FIELDS as $field ) {
			if ( ! empty( $settings[ $field ] ) ) {
				$credentials[] = trim( $settings[ $field ] );
			}
		}

		foreach ( self::PUBLIC_API_KEY_FIELDS as $field ) {
			if ( empty( $settings[ $field ] ) ) {
				continue;
			}

			$value = trim( sanitize_text_field( $settings[ $field ] ) );
			if ( preg_match( '/\s/', $value ) || in_array( $value, $credentials, true ) ) {
				$settings[ $field ] = '';
				$this->add_invalid_key_error( $field );
				continue;
			}

			$settings[ $field ] = $value;
		}

		return $settings;
	}

	/**
	 * Show an admin error for a cleared public API key.
	 *
	 * @param string $field The public API key setting.
	 */
	private function add_invalid_key_error( $field ) {
		if ( ! class_exists( 'WC_Admin_Settings' ) ) {
			return;
		}

		$label = 'elements_live_public_api_key' === $field
			? __( 'production', 'klarna-checkout-for-woocommerce' )
			: __( 'test', 'klarna-checkout-for-woocommerce' );

		\WC_Admin_Settings::add_error(
			sprintf(
				// translators: %s: The environment (production or test).
				__( 'The %s public API key for Kustom Elements is invalid and has been cleared. Use the data-public-api-key from the Elements installation script in the Kustom Portal, not the API Username or Password.', 'klarna-checkout-for-woocommerce' ),
				$label
			)
		);
	}

	/**
	 * Render a plain sub-heading/info row inside a settings section.
	 *
	 * @param string $html Unused (default filter argument).
	 * @param string $key  The field key.
	 * @param array  $data The field arguments.
	 * @return string
	 */
	public function generate_krokedil_subtitle_html( $html, $key, $data ) {
		$title       = $data['title'] ?? '';
		$description = $data['description'] ?? '';

		ob_start();
		?>
		<tr valign="top">
			<td colspan="2" class="kco-settings-subtitle">
				<?php if ( ! empty( $title ) ) : ?>
					<h4><?php echo wp_kses_post( $title ); ?></h4>
				<?php endif; ?>
				<?php if ( ! empty( $description ) ) : ?>
					<p class="description"><?php echo wp_kses_post( $description ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}
}
