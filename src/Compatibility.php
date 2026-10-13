<?php
namespace Krokedil\KustomCheckout;

use Krokedil\KustomCheckout\Compatibility\YithProductAddons;

defined( 'ABSPATH' ) || exit;

/**
 * Compatibility class.
 * Handles compatibility with third-party plugins.
 */
class Compatibility {

	/**
	 * Register compatibility integrations.
	 *
	 * Must run once all plugins have been loaded, since the integrations check whether the plugin they
	 * integrate with is active.
	 *
	 * @return void
	 */
	public static function register() {
		// Initialize YITH WooCommerce Product Add-ons compatibility.
		YithProductAddons::init();
	}
}
