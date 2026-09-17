<?php
namespace Krokedil\KustomCheckout\InPersonPayments;

use Krokedil\KustomCheckout\InPersonPayments\Settings\Admin;
use Krokedil\KustomCheckout\Utility\SettingsUtility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kustom In-Person Payments.
 */
class InPersonPayments {

	/**
	 * The settings page handler.
	 *
	 * @var Admin|null
	 */
	public $admin;

	/**
	 * The checkout field handler.
	 *
	 * @var Checkout|null
	 */
	public $checkout;

	/**
	 * The REST routes the waiting screen and Kustom call.
	 *
	 * @var Rest|null
	 */
	public $rest;

	/**
	 * The waiting screen shown while the customer pays.
	 *
	 * @var WaitingScreen|null
	 */
	public $waiting_screen;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		if ( is_admin() ) {
			$this->admin = new Admin();
		}

		if ( ! self::is_enabled() ) {
			return;
		}

		$this->checkout       = new Checkout();
		$this->rest           = new Rest();
		$this->waiting_screen = new WaitingScreen();

		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
	}

	/**
	 * Whether in-person payments are switched on.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return wc_string_to_bool( SettingsUtility::get_setting( 'ipp_enabled', 'no' ) );
	}

	/**
	 * Register the in-person payment method.
	 *
	 * @param array $methods The registered payment methods.
	 * @return array
	 */
	public function add_gateway( $methods ) {
		$methods[] = Gateway::class;

		return $methods;
	}
}
