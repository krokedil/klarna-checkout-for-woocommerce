<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Klarna_Checkout_For_WooCommerce_API_Callbacks class.
 *
 * Class that handles KCO API callbacks.
 */
class Klarna_Checkout_For_WooCommerce_API_Callbacks {

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return self::$instance The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Klarna_Checkout_For_WooCommerce_API_Callbacks constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_api_kco_wc_push', array( $this, 'push_cb' ) );
		add_action( 'woocommerce_api_kco_wc_notification', array( $this, 'notification_cb' ) );
		add_action( 'woocommerce_api_kco_wc_country_change', array( $this, 'country_change_cb' ) );
		add_action( 'woocommerce_api_kco_wc_validation', array( $this, 'validation_cb' ) );
		add_action( 'woocommerce_api_kco_wc_shipping_option_update', array( $this, 'shipping_option_update_cb' ) );
		add_action( 'woocommerce_api_kco_wc_address_update', array( $this, 'address_update_cb' ) );
	}

	/**
	 * Push callback function.
	 */
	public function push_cb() {
		/**
		 * 1. Handle POST request
		 * 2. Request the order from Klarna
		 * 3. Backup order creation
		 * 4. Acknowledge the order
		 * 5. Send merchant_reference1
		 */

		$klarna_order_id = $_GET['klarna_order_id'];
		$query_args = array(
			'post_type' => wc_get_order_types(),
			'post_status' => array_keys( wc_get_order_statuses() ),
			'meta_key' => '_klarna_order_id',
			'meta_value' => $klarna_order_id,
		);
		$orders = get_posts( $query_args );
		$order_id = $orders[0]->ID;
		$order = wc_get_order( $order_id );

		$klarna_order = KCO_WC()->api->request_post_get_order( $klarna_order_id );

		KCO_WC()->api->request_post_acknowledge_order( $klarna_order_id );
		KCO_WC()->api->request_post_set_merchant_reference(
			$klarna_order_id,
			array(
				'merchant_reference1' => $order->get_order_number(),
				'merchant_reference2' => $order->get_id(),
			)
		);
	}

	/**
	 * Notification callback function, used for pending orders.
	 */
	public function notification_cb() {

	}

	/**
	 * Country change callback function.
	 * Used in KCO Global only.
	 *
	 * @link https://developers.klarna.com/en/us/kco-v3/checkout/additional-features/kco-global/callbacks
	 */
	public function country_change_cb() {

	}

	/**
	 * Order validation callback function.
	 * Response must be sent to Klarna API.
	 *
	 * @link https://developers.klarna.com/api/#checkout-api-callbacks-order-validation
	 */
	public function validation_cb() {

	}

	/**
	 * Shipping option update callback function.
	 * Response must be sent to Klarna API.
	 *
	 * @link https://developers.klarna.com/api/#checkout-api-callbacks-shipping-option-update
	 */
	public function shipping_option_update_cb() {

	}

	/**
	 * Address update callback function.
	 * Response must be sent to Klarna API.
	 *
	 * @link https://developers.klarna.com/api/#checkout-api-callbacks-address-update
	 */
	public function address_update_cb() {
		$post_body = file_get_contents( 'php://input' );
		// Convert post body into native object.
		$data = json_decode( $post_body, true );

		// @TODO: Continue here

		header( 'HTTP/1.0 200 OK' );
	}

}

Klarna_Checkout_For_WooCommerce_API_Callbacks::get_instance();
