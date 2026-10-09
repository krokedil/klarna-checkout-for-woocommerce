<?php
namespace Krokedil\KustomCheckout\Blocks\Express;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Krokedil\KustomCheckout\Express\Express;

defined( 'ABSPATH' ) || exit;

/**
 * Class ExpressPaymentMethod
 *
 * Registers the Kustom express buttons in the express area of the cart and checkout blocks.
 */
class ExpressPaymentMethod extends AbstractPaymentMethodType {
	/**
	 * Payment method name.
	 *
	 * @var string
	 */
	protected $name = 'kco_express';

	/**
	 * Register the payment method script.
	 *
	 * @return void
	 */
	public function initialize() {
		$asset_file = KCO_WC_PLUGIN_PATH . '/blocks/build/express.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$assets = include $asset_file;
		wp_register_script( 'kco-express-block', plugins_url( 'blocks/build/express.js', KCO_WC_MAIN_FILE ), $assets['dependencies'], $assets['version'], true );
	}

	/**
	 * Checks if the payment method is active or not.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return Express::is_available();
	}

	/**
	 * Loads the payment method scripts.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		return array( 'kco-express-block' );
	}

	/**
	 * Returns an array of supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features() {
		return array( 'products' );
	}

	/**
	 * Gets the payment method data to load into the frontend.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return Express::get_script_config();
	}
}
