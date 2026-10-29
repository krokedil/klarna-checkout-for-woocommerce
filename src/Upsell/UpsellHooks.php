<?php
namespace Krokedil\KustomCheckout\Upsell;

/**
 * Hooks for upsell functionality.
 */
class UpsellHooks {
	/**
	 * UpsellHooks constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		// Get the plugin settings.
		$settings = get_option( 'woocommerce_kco_settings', array() );

		// Only add hooks if upsell is enabled in the settings.
		if ( wc_string_to_bool( $settings['enable_upsell'] ?? 'no' ) ) {
			add_filter( 'kco_wc_merchant_urls', array( $this, 'add_upsell_urls' ) );
			add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'add_hidden_order_itemmeta' ) );
			add_action( 'woocommerce_product_options_related', array( $this, 'output_kustom_upsells_field' ) );
			add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_kustom_upsells' ) );
			new UpsellPendingDisplay();
		}
	}

	/**
	 * Output the Kustom upsells product search field in the Linked Products tab.
	 *
	 * @return void
	 */
	public function output_kustom_upsells_field() {
		global $product_object;
		?>
		<p class="form-field">
			<label for="kco_upsell_ids"><?php esc_html_e( 'Kustom upsells', 'klarna-checkout-for-woocommerce' ); ?></label>
			<select class="wc-product-search" multiple="multiple" style="width: 50%;" id="kco_upsell_ids" name="kco_upsell_ids[]" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'woocommerce' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-exclude="<?php echo intval( $product_object->get_id() ); ?>">
				<?php
				foreach ( UpsellRecommendations::get_kustom_upsell_ids( $product_object ) as $product_id ) {
					$product = wc_get_product( $product_id );
					if ( $product ) {
						echo '<option value="' . intval( $product_id ) . '" selected="selected">' . esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ) . '</option>';
					}
				}
				?>
			</select> <?php echo wc_help_tip( __( 'Products recommended in the Kustom Checkout confirmation page when the upsell recommendations setting is set to Kustom upsells.', 'klarna-checkout-for-woocommerce' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_help_tip escapes the tip. ?>
		</p>
		<?php
	}

	/**
	 * Save the Kustom upsells of a product.
	 *
	 * @param \WC_Product $product The product being saved.
	 *
	 * @return void
	 */
	public function save_kustom_upsells( $product ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the product meta box nonce before this action.
		$ids = isset( $_POST['kco_upsell_ids'] ) ? array_filter( array_map( 'absint', (array) wp_unslash( $_POST['kco_upsell_ids'] ) ) ) : array();
		$product->update_meta_data( UpsellRecommendations::KUSTOM_UPSELLS_META_KEY, array_values( $ids ) );
	}

	/**
	 * Add the upsell validation URL, and the upsell recommendations URL when recommendations are enabled, to the list of merchant URLs.
	 *
	 * @param array $merchant_urls The list of merchant URLs.
	 *
	 * @return array
	 */
	public function add_upsell_urls( $merchant_urls ) {
		$merchant_urls['upsell_validation'] = home_url( '/wc-api/KCO_WC_Upsell_Validation/?kco_wc_order_id={checkout.order.id}' );

		$settings = get_option( 'woocommerce_kco_settings', array() );
		if ( 'none' !== ( $settings['upsell_recommendations'] ?? 'none' ) ) {
			$merchant_urls['upsell'] = home_url( '/wc-api/KCO_WC_Upsell/?kco_wc_order_id={checkout.order.id}' );
		}

		return $merchant_urls;
	}

	/**
	 * Add the order line metadata for upsell data to the list of hidden order item meta.
	 *
	 * @param array $hidden_order_itemmeta The list of hidden meta data.
	 * @return array
	 */
	public function add_hidden_order_itemmeta( $hidden_order_itemmeta ) {
		// If the query parameter "debug" is set, return early.
		if ( isset( $_GET['debug'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $hidden_order_itemmeta;
		}

		$hidden_order_itemmeta[] = '_kco_is_upsell';
		$hidden_order_itemmeta[] = '_kco_upsell_reference';

		return $hidden_order_itemmeta;
	}
}
