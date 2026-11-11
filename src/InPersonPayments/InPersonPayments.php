<?php
namespace Krokedil\KustomCheckout\InPersonPayments;

use Krokedil\KustomCheckout\InPersonPayments\Settings\Admin;

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
	 * The handling of sales rung up on a point of sale.
	 *
	 * @var PosSale|null
	 */
	public $pos_sale;

	/**
	 * The in-person details box on the order screen.
	 *
	 * @var OrderDetails|null
	 */
	public $order_details;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		if ( is_admin() ) {
			$this->admin = new Admin();
		}

		// Sessions already sent to a device still need their poll, webhook and waiting screen after the feature is switched off.
		$this->rest           = new Rest();
		$this->waiting_screen = new WaitingScreen();

		if ( ! self::is_enabled() ) {
			return;
		}

		$this->checkout = new Checkout();
		$this->pos_sale = new PosSale();

		if ( is_admin() ) {
			$this->order_details = new OrderDetails();
		}

		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
	}

	/**
	 * Whether in-person payments are switched on.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		// Read the option itself: this runs on plugins_loaded, and the settings defaults translate their labels, which is too early.
		$settings = get_option( 'woocommerce_kco_settings', array() );

		return wc_string_to_bool( is_array( $settings ) ? ( $settings['ipp_enabled'] ?? 'no' ) : 'no' );
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
