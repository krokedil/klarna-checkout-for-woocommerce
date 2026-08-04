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
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'kco_wc_gateway_settings', array( $this, 'extend_settings' ) );
		add_filter( 'woocommerce_generate_krokedil_subtitle_html', array( $this, 'generate_krokedil_subtitle_html' ), 10, 3 );
	}

	/**
	 * Given a settings array, extend it with the Elements settings.
	 *
	 * @param array $settings A settings array.
	 * @return array
	 */
	public function extend_settings( $settings ) {
		$settings['elements'] = array(
			'title' => __( 'Elements', 'klarna-checkout-for-woocommerce' ),
			'type'  => 'krokedil_section_start',
			'id'    => 'elements',
		);

		$settings['elements_intro'] = array(
			'type'        => 'krokedil_subtitle',
			// translators: %s: Kustom Portal link.
			'description' => sprintf(
				__( 'Kustom Elements are lightweight web components that display payment methods and delivery options directly on your store pages. The public API keys used here are separate from the API Username and Password used for the payment integration above — find them in the %s under Elements → Installation script → data-public-api-key.', 'klarna-checkout-for-woocommerce' ),
				'<a href="https://portal.kustom.com" target="_blank">' . __( 'Kustom Portal', 'klarna-checkout-for-woocommerce' ) . '</a>'
			),
		);

		$settings['elements_live_public_api_key'] = array(
			'title'             => __( 'Live public API key', 'klarna-checkout-for-woocommerce' ),
			'type'              => 'text',
			'description'       => __( 'The public API key used for live Kustom Elements.', 'klarna-checkout-for-woocommerce' ),
			'default'           => '',
			'desc_tip'          => true,
			'custom_attributes' => array(
				'autocomplete' => 'off',
			),
		);

		$settings['elements_playground_public_api_key'] = array(
			'title'             => __( 'Playground public API key', 'klarna-checkout-for-woocommerce' ),
			'type'              => 'text',
			'description'       => __( 'The public API key used for Kustom Elements in the playground/test environment.', 'klarna-checkout-for-woocommerce' ),
			'default'           => '',
			'desc_tip'          => true,
			'custom_attributes' => array(
				'autocomplete' => 'off',
			),
		);

		$settings['elements_payment_display_title'] = array(
			'title' => __( 'Payment Method Display', 'klarna-checkout-for-woocommerce' ),
			'type'  => 'krokedil_subtitle',
		);

		$settings['elements_payment_product_position'] = array(
			'title'    => __( 'Product page position', 'klarna-checkout-for-woocommerce' ),
			'type'     => 'select',
			'options'  => array(
				''                                         => __( 'Disabled', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_single_product_summary'       => __( 'After product summary (default)', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_before_single_product_summary' => __( 'Before product summary', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_after_single_product_summary' => __( 'After product tabs', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_before_add_to_cart_button'    => __( 'Before add to cart button', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_after_add_to_cart_button'     => __( 'After add to cart button', 'klarna-checkout-for-woocommerce' ),
			),
			'default'  => 'woocommerce_single_product_summary',
			'desc_tip' => false,
		);

		$settings['elements_payment_cart_position'] = array(
			'title'    => __( 'Cart page position', 'klarna-checkout-for-woocommerce' ),
			'type'     => 'select',
			'options'  => array(
				''                               => __( 'Disabled', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_cart_totals_after_order_total' => __( 'After order total (default)', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_before_cart_totals' => __( 'Before cart totals', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_after_cart_totals'  => __( 'After cart totals', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_cart_collaterals'   => __( 'Cart collaterals', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_after_cart_table'   => __( 'After cart table', 'klarna-checkout-for-woocommerce' ),
			),
			'default'  => 'woocommerce_cart_totals_after_order_total',
			'desc_tip' => false,
		);

		$settings['elements_payment_footer'] = array(
			'title'       => __( 'Add to footer', 'klarna-checkout-for-woocommerce' ),
			'label'       => __( 'Enable', 'klarna-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			// translators: 1: wp_footer code tag, 2: kustom_payment_element shortcode code tag.
			'description' => sprintf(
				__( 'Renders the element before the closing </body> tag via %1$s.<br>Elements are also available as a shortcode ( %2$s ) and as a block in the WordPress block editor.', 'klarna-checkout-for-woocommerce' ),
				'<code>wp_footer</code>',
				'<code>[kustom_payment_element]</code>'
			),
			'default'     => 'no',
		);

		$settings['elements_shipping_display_title'] = array(
			'title'       => __( 'Delivery Method Display', 'klarna-checkout-for-woocommerce' ),
			'type'        => 'krokedil_subtitle',
			'description' => __( 'Delivery Method Display requires Kustom Shipping Assistant (KSA) to be active and configured.', 'klarna-checkout-for-woocommerce' ),
		);

		$settings['elements_shipping_product_position'] = array(
			'title'    => __( 'Product page position', 'klarna-checkout-for-woocommerce' ),
			'type'     => 'select',
			'options'  => array(
				''                                         => __( 'Disabled', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_single_product_summary'       => __( 'After product summary (default)', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_before_single_product_summary' => __( 'Before product summary', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_after_single_product_summary' => __( 'After product tabs', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_before_add_to_cart_button'    => __( 'Before add to cart button', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_after_add_to_cart_button'     => __( 'After add to cart button', 'klarna-checkout-for-woocommerce' ),
			),
			'default'  => 'woocommerce_single_product_summary',
			'desc_tip' => false,
		);

		$settings['elements_shipping_cart_position'] = array(
			'title'    => __( 'Cart page position', 'klarna-checkout-for-woocommerce' ),
			'type'     => 'select',
			'options'  => array(
				''                               => __( 'Disabled', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_cart_totals_after_order_total' => __( 'After order total (default)', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_before_cart_totals' => __( 'Before cart totals', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_after_cart_totals'  => __( 'After cart totals', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_cart_collaterals'   => __( 'Cart collaterals', 'klarna-checkout-for-woocommerce' ),
				'woocommerce_after_cart_table'   => __( 'After cart table', 'klarna-checkout-for-woocommerce' ),
			),
			'default'  => 'woocommerce_cart_totals_after_order_total',
			'desc_tip' => false,
		);

		$settings['elements_shipping_footer'] = array(
			'title'       => __( 'Add to footer', 'klarna-checkout-for-woocommerce' ),
			'label'       => __( 'Enable', 'klarna-checkout-for-woocommerce' ),
			'type'        => 'checkbox',
			// translators: 1: wp_footer code tag, 2: kustom_shipping_element shortcode code tag.
			'description' => sprintf(
				__( 'Renders the element before the closing </body> tag via %1$s.<br>Elements are also available as a shortcode ( %2$s ) and as a block in the WordPress block editor.', 'klarna-checkout-for-woocommerce' ),
				'<code>wp_footer</code>',
				'<code>[kustom_shipping_element]</code>'
			),
			'default'     => 'no',
		);

		$settings['elements_end'] = array(
			'type' => 'krokedil_section_end',
		);

		return $settings;
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
