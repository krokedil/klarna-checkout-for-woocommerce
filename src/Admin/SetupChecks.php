<?php
namespace Krokedil\KustomCheckout\Admin;

use KrokedilKlarnaCheckoutDeps\Krokedil\SettingsPage\SettingsPage;
use KrokedilKlarnaCheckoutDeps\Krokedil\SettingsPage\SetupChecks\Checks;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the store settings Kustom Checkout depends on with the settings-page package, which
 * shows them in the settings sidebar, the system status report and as a non-dismissable notice.
 */
class SetupChecks {
	/**
	 * The documentation section that lists the required store settings.
	 *
	 * @var string
	 */
	const DOCS_URL = 'https://docs.krokedil.com/kustom-checkout-for-woocommerce/get-started/install-and-activate/#step-1-required-wordpresswoocommerce-settings';

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/**
	 * Registers the checks for the Kustom Checkout gateway.
	 *
	 * @return void
	 */
	public function register() {
		SettingsPage::get_instance()
			->add_setup_checks(
				'kco',
				$this->get_checks(),
				array(
					'plugin_name' => 'Kustom Checkout',
					'title'       => __( 'Required settings check', 'klarna-checkout-for-woocommerce' ),
					'docs_url'    => self::DOCS_URL,
					// The gateway is needed for the row notice in the payment methods list.
					'owner'       => function () {
						return WC()->payment_gateways()->payment_gateways()['kco'] ?? null;
					},
					'enabled'     => function () {
						$settings = get_option( 'woocommerce_kco_settings', array() );
						return wc_string_to_bool( $settings['enabled'] ?? 'no' );
					},
				)
			);
	}

	/**
	 * The checks, with descriptions that say why Kustom Checkout needs each setting.
	 *
	 * @return array
	 */
	private function get_checks() {
		return array(
			Checks::https(
				array( 'description' => __( 'The checkout must be served over a secure connection (HTTPS) for Kustom Checkout to work.', 'klarna-checkout-for-woocommerce' ) )
			),
			Checks::pretty_permalinks(
				array( 'description' => __( 'Pretty permalinks are required for callbacks from Kustom to reach your store.', 'klarna-checkout-for-woocommerce' ) )
			),
			Checks::terms_page(
				array( 'description' => __( 'Kustom Checkout displays a link to your terms and conditions page in the checkout. The page must be published and selected in WooCommerce.', 'klarna-checkout-for-woocommerce' ) )
			),
			Checks::price_decimals(
				2,
				array( 'description' => __( 'Displaying prices with 2 decimals is recommended to avoid rounding issues, so that the order total matches between WooCommerce and Kustom.', 'klarna-checkout-for-woocommerce' ) )
			),
		);
	}
}
