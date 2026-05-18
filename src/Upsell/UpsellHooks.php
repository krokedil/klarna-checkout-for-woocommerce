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
		}
	}

	/**
	 * Add the upsell and upsell validation URLs to the list of merchant URLs.
	 *
	 * @param array $merchant_urls The list of merchant URLs.
	 *
	 * @return array
	 */
	public function add_upsell_urls( $merchant_urls ) {
		$merchant_urls['upsell_validation'] = home_url( '/wc-api/KCO_WC_Upsell_Validation/?kco_wc_order_id={checkout.order.id}' );

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
