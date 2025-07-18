<?php
namespace Krokedil\KustomCheckout\Blocks\Checkout;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Krokedil\KustomCheckout\Utility\SettingsUtility;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutBlock
 */
class CheckoutBlock extends AbstractPaymentMethodType {
	/**
	 * Payment method name. Matches gateway ID.
	 *
	 * @var string
	 */
	protected $name = 'kco';

	/**
	 * Initializes the settings for the plugin.
	 *
	 * @return void
	 */
	public function initialize() {
		$this->settings = SettingsUtility::get_settings();

		// Ensure the assets file exists.
		if ( ! file_exists( KCO_WC_PLUGIN_PATH . '/blocks/build/checkout.asset.php' ) ) {
			return;
		}

		$assets = include KCO_WC_PLUGIN_PATH . '/blocks/build/checkout.asset.php';
		$url    = plugins_url( 'blocks/build/checkout.js', KCO_WC_MAIN_FILE );

		wp_register_script( 'kco-checkout-block', $url, $assets['dependencies'], $assets['version'], true );
	}

	/**
	 * Checks if the payment method is active or not.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return 'yes' === $this->get_setting( 'enabled', 'no' );
	}

	/**
	 * Loads the payment method scripts.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		return array( 'kco-checkout-block' );
	}

	/**
	 * Checks if we are currently on the admin pages when loading the blocks.
	 *
	 * @return boolean
	 */
	public function is_admin() {
		// If we are on the block render endpoint, then this is an admin request.
		$is_edit_context = isset( $_GET['action'] ) && 'edit' === $_GET['action']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_admin        = $is_edit_context;

		return $is_admin;
	}

	/**
	 * Gets the payment method data to load into the frontend.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		if ( is_order_received_page() ) {
			return array();
		}

		return $this->get_data();
	}

	/**
	 * Returns an array of the data for the payment method that should be passed to the frontend.
	 *
	 * @return array<string, mixed>
	 */
	private function get_data() {
		$icon_url = plugins_url( 'assets/img/kustom_logo_primary.png', KCO_WC_MAIN_FILE );
		if ( $this->is_admin() ) {
			return array(
				'title'            => $this->get_setting( 'title' ),
				'description'      => $this->get_setting( 'description' ),
				'error'            => false,
				'snippet'          => false,
				'shippingInIframe' => 'yes' === $this->settings['shipping_methods_in_iframe'],
				'countryCodes'     => kco_get_country_codes(),
				'iconUrl'          => $icon_url,
				'supports'         => $this->get_supported_features(),
			);
		}

		$error   = false;
		$snippet = false;

		$klarna_order = kco_create_or_update_order();

		if ( is_wp_error( $klarna_order ) ) {
			$error = $klarna_order->get_error_message();
		} else {
			$snippet = $klarna_order['html_snippet'];
		}

		return array(
			'title'            => $this->get_setting( 'title' ),
			'description'      => $this->get_setting( 'description' ),
			'error'            => $error,
			'snippet'          => $snippet,
			'shippingInIframe' => 'yes' === $this->settings['shipping_methods_in_iframe'],
			'countryCodes'     => kco_get_country_codes(),
			'iconUrl'          => $icon_url,
			'features '        => $this->get_supported_features(),
		);
	}
}
