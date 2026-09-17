<?php
namespace Krokedil\KustomCheckout\Blocks\InPersonPayments;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Krokedil\KustomCheckout\InPersonPayments\Devices;
use Krokedil\KustomCheckout\InPersonPayments\Gateway;
use Krokedil\KustomCheckout\InPersonPayments\InPersonPayments;
use Krokedil\KustomCheckout\Utility\SettingsUtility;

defined( 'ABSPATH' ) || exit;

/**
 * The in-person payment method on the block checkout.
 *
 * The React side only picks a device and hands it back as payment method data, which
 * the Store API puts in `$_POST` before calling the gateway, so the order is placed by
 * the same `Gateway::process_payment()` the shortcode checkout uses.
 */
class CheckoutBlock extends AbstractPaymentMethodType {

	/**
	 * The script handle registered for the payment method.
	 */
	const SCRIPT_HANDLE = 'kco-ipp-checkout-block';

	/**
	 * Payment method name. Matches the gateway id.
	 *
	 * @var string
	 */
	protected $name = Gateway::ID;

	/**
	 * Register the script the payment method renders from.
	 *
	 * @return void
	 */
	public function initialize() {
		$this->settings = SettingsUtility::get_settings();

		$assets_file = KCO_WC_PLUGIN_PATH . '/blocks/build/inpersonpayments.asset.php';
		if ( ! file_exists( $assets_file ) ) {
			return;
		}

		$assets = include $assets_file;

		wp_register_script(
			self::SCRIPT_HANDLE,
			plugins_url( 'blocks/build/inpersonpayments.js', KCO_WC_MAIN_FILE ),
			$assets['dependencies'],
			$assets['version'],
			true
		);
	}

	/**
	 * Whether in-person payments are switched on at all.
	 *
	 * @return bool
	 */
	public function is_active() {
		return InPersonPayments::is_enabled();
	}

	/**
	 * The scripts the payment method loads.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		return array( self::SCRIPT_HANDLE );
	}

	/**
	 * The features the payment method supports. An in-person sale is never recurring.
	 *
	 * @return string[]
	 */
	public function get_supported_features() {
		return array( 'products' );
	}

	/**
	 * The data the React side renders from.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'           => SettingsUtility::get_setting( 'ipp_title', __( 'In-Person Payment', 'klarna-checkout-for-woocommerce' ) ),
			'deviceLabel'     => __( 'Device', 'klarna-checkout-for-woocommerce' ),
			'placeOrderLabel' => __( 'Take payment on device', 'klarna-checkout-for-woocommerce' ),
			'noDeviceMessage' => __( 'Please choose a Kustom POS device to take the payment on.', 'klarna-checkout-for-woocommerce' ),
			'devices'         => $this->get_devices(),
			'features'        => $this->get_supported_features(),
		);
	}

	/**
	 * The devices the salesperson may send the sale to.
	 *
	 * Only the id and the name are exposed: the payment method data is printed into the
	 * checkout page, and nothing on the React side needs the rest of the device record.
	 *
	 * @return array
	 */
	private function get_devices() {
		// The editor and the order received page have no sale to send anywhere, and an
		// administrator editing the checkout page would otherwise cost a round trip.
		if ( is_admin() || is_order_received_page() || ! current_user_can( Gateway::required_capability() ) ) {
			return array();
		}

		$devices = Devices::all();
		if ( is_wp_error( $devices ) ) {
			return array();
		}

		return array_values(
			array_map(
				function ( $device ) {
					return array(
						'id'   => $device['id'] ?? '',
						'name' => $device['name'] ?? ( $device['id'] ?? '' ),
					);
				},
				array_filter(
					$devices,
					function ( $device ) {
						return ! empty( $device['id'] );
					}
				)
			)
		);
	}
}
