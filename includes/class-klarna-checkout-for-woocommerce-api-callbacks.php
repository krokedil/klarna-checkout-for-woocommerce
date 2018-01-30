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
		// add_action( 'woocommerce_api_kco_wc_country_change', array( $this, 'country_change_cb' ) );
		add_action( 'woocommerce_api_kco_wc_validation', array( $this, 'validation_cb' ) );
		add_action( 'woocommerce_api_kco_wc_shipping_option_update', array( $this, 'shipping_option_update_cb' ) );
		add_action( 'woocommerce_api_kco_wc_address_update', array( $this, 'address_update_cb' ) );
		add_action( 'kco_wc_punted_notification', array( $this, 'kco_wc_punted_notification_cb' ), 10, 2 );
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

		// Do nothing if there's no Klarna Checkout order ID.
		if ( ! $_GET['kco_wc_order_id'] ) {
			return;
		}

		$klarna_order_id = sanitize_key( $_GET['kco_wc_order_id'] );

		$query_args = array(
			'fields'      => 'ids',
			'post_type'   => wc_get_order_types(),
			'post_status' => array_keys( wc_get_order_statuses() ),
			'meta_key'    => '_wc_klarna_order_id',
			'meta_value'  => $klarna_order_id,
		);

		$orders = get_posts( $query_args );

		// If zero matching orders were found, return.
		if ( empty( $orders ) ) {
			// Backup order creation.
			$this->backup_order_creation( $klarna_order_id );
			return;
		}

		$order_id = $orders[0];
		$order    = wc_get_order( $order_id );

		if ( $order ) {
			// If the order was already created, send merchant reference.
			$response     = KCO_WC()->api->request_post_get_order( $klarna_order_id );
			$klarna_order = json_decode( $response['body'] );

			if ( 'ACCEPTED' === $klarna_order->fraud_status ) {
				$order->payment_complete( $klarna_order_id );
				$order->add_order_note( 'Payment via Klarna Checkout, order ID: ' . sanitize_key( $klarna_order->order_id ) );
			} elseif ( 'REJECTED' === $klarna_order->fraud_status ) {
				$order->update_status( 'on-hold', 'Klarna Checkout order was rejected.' );
			} elseif ( 'PENDING' === $klarna_order->fraud_status ) {
				$order->update_status( 'on-hold', 'Klarna order is under review, order ID: ' . $klarna_order->order_id );
			}

			KCO_WC()->api->request_post_acknowledge_order( $klarna_order_id );
			KCO_WC()->api->request_post_set_merchant_reference(
				$klarna_order_id,
				array(
					'merchant_reference1' => $order->get_order_number(),
					'merchant_reference2' => $order->get_id(),
				)
			);
		} else {
			// Backup order creation.
			$this->backup_order_creation( $klarna_order_id );
		}
	}

	/**
	 * Notification callback function, used for pending orders.
	 */
	public function notification_cb() {
		/**
		 * Notification callback URL has Klarna Order ID (kco_wc_order_id) in it.
		 *
		 * 1. Get Klarna Order ID
		 * 2. Try to find matching WooCommerce order, to see if it was created
		 * 3. If WooCommerce order does not exist, that means regular creation failed AND confirmation callback
		 *    either hasn't happened yet or failed. In this case, schedule a single event, 5 minutes from now
		 *    and try to get WooCommerce order then.
		 * 4. If WooCommerce order does exist, fire the hook.
		 */


		$order_id = '';

		if ( $_GET['kco_wc_order_id'] ) { // KCO.
			$klarna_order_id = sanitize_key( $_GET['kco_wc_order_id'] );
			$query_args      = array(
				'fields'      => 'ids',
				'post_type'   => wc_get_order_types(),
				'post_status' => array_keys( wc_get_order_statuses() ),
				'meta_key'    => '_wc_klarna_order_id',
				'meta_value'  => $klarna_order_id,
			);

			$orders = get_posts( $query_args );

			// If zero matching orders were found, return.
			if ( ! empty( $orders ) ) {
				$order_id = $orders[0];
			}
		}

		if ( '' !== $order_id ) {
			do_action( 'wc_klarna_notification_listener' );
		} else {
			$post_body = file_get_contents( 'php://input' );
			$data      = json_decode( $post_body, true );

			wp_schedule_single_event( time() + 300, 'kco_wc_punted_notification', array( $klarna_order_id, $data ) );
		}
	}

	/**
	 * Punted notification callback.
	 *
	 * @param string $klarna_order_id Klarna order ID.
	 */
	public function kco_wc_punted_notification_cb( $klarna_order_id, $data ) {
		do_action( 'wc_klarna_notification_listener', $klarna_order_id, $data );
	}

	/**
	 * Order validation callback function.
	 * Response must be sent to Klarna API.
	 *
	 * @link https://developers.klarna.com/api/#checkout-api-callbacks-order-validation
	 */
	public function validation_cb() {
		$post_body = file_get_contents( 'php://input' );
		$data      = json_decode( $post_body, true );

		$all_in_stock    = true;
		$shipping_chosen = false;

		// Check stock for each item and shipping method.
		$cart_items = $data['order_lines'];

		foreach ( $cart_items as $cart_item ) {
			if ( 'physical' === $cart_item['type'] ) {
				// Get product by SKU or ID.
				if ( wc_get_product_id_by_sku( $cart_item['reference'] ) ) {
					$cart_item_product = wc_get_product( wc_get_product_id_by_sku( $cart_item['reference'] ) );
				} else {
					$cart_item_product = wc_get_product( $cart_item['reference'] );
				}

				if ( $cart_item_product ) {
					if ( ! $cart_item_product->has_enough_stock( $cart_item['quantity'] ) ) {
						$all_in_stock = false;
					}
				}
			} elseif ( 'shipping_fee' === $cart_item['type'] ) {
				$shipping_chosen = true;
			}
		}

		if ( $all_in_stock && $shipping_chosen ) {
			header( 'HTTP/1.0 200 OK' );
		} else {
			header( 'HTTP/1.0 303 See Other' );

			if ( ! $all_in_stock ) {
				$logger = new WC_Logger();
				$logger->add( 'klarna-checkout-for-woocommerce', 'Stock validation failed for SKU ' . $cart_item['reference'] );
				header( 'Location: ' . wc_get_cart_url() . '?stock_validate_failed' );
			} elseif ( ! $shipping_chosen ) {
				header( 'Location: ' . wc_get_checkout_url() . '?no_shipping' );
			} elseif ( $email_exists ) {
				header( 'Location: ' . wc_get_checkout_url() . '?login_required=yes' );
			}
		}
	}

	/**
	 * Shipping option update callback function.
	 * Response must be sent to Klarna API.
	 *
	 * @link https://developers.klarna.com/api/#checkout-api-callbacks-shipping-option-update
	 */
	public function shipping_option_update_cb() {
		// Send back order amount, order tax amount, order lines, purchase currency and status 200
	}

	/**
	 * Address update callback function.
	 * Response must be sent to Klarna API.
	 *
	 * @link https://developers.klarna.com/api/#checkout-api-callbacks-address-update
	 * @ref  https://github.com/mmartche/coach/blob/30022c266089fc7499c54e149883e951c288dc9f/catalog/controller/extension/payment/klarna_checkout.php#L509
	 */
	public function address_update_cb() {
		// Currently disabled, because of how response body needs to be calculated in WooCommerce.
	}

	/**
	 * Backup order creation, in case checkout process failed.
	 *
	 * @param string $klarna_order_id Klarna order ID.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	public function backup_order_creation( $klarna_order_id ) {
		$response     = KCO_WC()->api->request_post_get_order( $klarna_order_id );
		$klarna_order = json_decode( $response['body'] );

		// Process customer data.
		$this->process_customer_data( $klarna_order );

		// Process customer data.
		$this->process_cart( $klarna_order );

		// Process order.
		$this->process_order( $klarna_order );
	}

	/**
	 * Processes customer data on backup order creation.
	 *
	 * @param Klarna_Checkout_Order $klarna_order Klarna order.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	private function process_customer_data( $klarna_order ) {
		// First name.
		WC()->customer->set_billing_first_name( sanitize_text_field( $klarna_order->billing_address->given_name ) );
		WC()->customer->set_shipping_first_name( sanitize_text_field( $klarna_order->shipping_address->given_name ) );

		// Last name.
		WC()->customer->set_billing_last_name( sanitize_text_field( $klarna_order->billing_address->family_name ) );
		WC()->customer->set_shipping_last_name( sanitize_text_field( $klarna_order->shipping_address->family_name ) );

		// Country.
		WC()->customer->set_billing_country( sanitize_text_field( $klarna_order->billing_address->country ) );
		WC()->customer->set_shipping_country( sanitize_text_field( $klarna_order->shipping_address->country ) );

		// Street address 1.
		WC()->customer->set_billing_address_1( sanitize_text_field( $klarna_order->billing_address->street_address ) );
		WC()->customer->set_shipping_address_1( sanitize_text_field( $klarna_order->shipping_address->street_address ) );

		// Street address 2.
		WC()->customer->set_billing_address_2( sanitize_text_field( $klarna_order->billing_address->street_address2 ) );
		WC()->customer->set_shipping_address_2( sanitize_text_field( $klarna_order->shipping_address->street_address2 ) );

		// City.
		WC()->customer->set_billing_city( sanitize_text_field( $klarna_order->billing_address->city ) );
		WC()->customer->set_shipping_city( sanitize_text_field( $klarna_order->shipping_address->city ) );

		// County/State.
		WC()->customer->set_billing_state( sanitize_text_field( $klarna_order->billing_address->region ) );
		WC()->customer->set_shipping_state( sanitize_text_field( $klarna_order->shipping_address->region ) );

		// Postcode.
		WC()->customer->set_billing_postcode( sanitize_text_field( $klarna_order->billing_address->postal_code ) );
		WC()->customer->set_shipping_postcode( sanitize_text_field( $klarna_order->shipping_address->postal_code ) );

		// Phone.
		WC()->customer->set_billing_phone( sanitize_text_field( $klarna_order->billing_address->phone ) );

		// Email.
		WC()->customer->set_billing_email( sanitize_text_field( $klarna_order->billing_address->email ) );

		WC()->customer->save();
	}


	/**
	 * Processes cart contents on backup order creation.
	 *
	 * @param Klarna_Checkout_Order $klarna_order Klarna order.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	private function process_cart( $klarna_order ) {
		WC()->cart->empty_cart();

		foreach ( $klarna_order->order_lines as $cart_item ) {
			if ( 'physical' === $cart_item->type ) {
				if ( wc_get_product_id_by_sku( $cart_item->reference ) ) {
					$id = wc_get_product_id_by_sku( $cart_item->reference );
				} else {
					$id = $cart_item->reference;
				}

				try {
					WC()->cart->add_to_cart( $id, $cart_item->quantity );
				} catch ( Exception $e ) {
					$logger = new WC_Logger();
					$logger->add( 'klarna-checkout-for-woocommerce', 'Backup order creation error add to cart error: ' . $e->getMessage() );
				}
			}
		}

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_fees();
		WC()->cart->calculate_totals();

		// Check cart items (quantity, coupon validity etc).
		if ( ! WC()->cart->check_cart_items() ) {
			return;
		}

		WC()->cart->check_cart_coupons();
	}

	/**
	 * Processes WooCommerce order on backup order creation.
	 *
	 * @param Klarna_Checkout_Order $klarna_order Klarna order.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	private function process_order( $klarna_order ) {
		try {
			$order = new WC_Order();

			$order->set_billing_first_name( sanitize_text_field( $klarna_order->billing_address->given_name ) );
			$order->set_billing_last_name( sanitize_text_field( $klarna_order->billing_address->family_name ) );
			$order->set_billing_country( sanitize_text_field( $klarna_order->billing_address->country ) );
			$order->set_billing_address_1( sanitize_text_field( $klarna_order->billing_address->street_address ) );
			$order->set_billing_address_2( sanitize_text_field( $klarna_order->billing_address->street_address2 ) );
			$order->set_billing_city( sanitize_text_field( $klarna_order->billing_address->city ) );
			$order->set_billing_state( sanitize_text_field( $klarna_order->billing_address->region ) );
			$order->set_billing_postcode( sanitize_text_field( $klarna_order->billing_address->postal_code ) );
			$order->set_billing_phone( sanitize_text_field( $klarna_order->billing_address->phone ) );
			$order->set_billing_email( sanitize_text_field( $klarna_order->billing_address->email ) );

			$order->set_shipping_first_name( sanitize_text_field( $klarna_order->shipping_address->given_name ) );
			$order->set_shipping_last_name( sanitize_text_field( $klarna_order->shipping_address->family_name ) );
			$order->set_shipping_country( sanitize_text_field( $klarna_order->shipping_address->country ) );
			$order->set_shipping_address_1( sanitize_text_field( $klarna_order->shipping_address->street_address ) );
			$order->set_shipping_address_2( sanitize_text_field( $klarna_order->shipping_address->street_address2 ) );
			$order->set_shipping_city( sanitize_text_field( $klarna_order->shipping_address->city ) );
			$order->set_shipping_state( sanitize_text_field( $klarna_order->shipping_address->region ) );
			$order->set_shipping_postcode( sanitize_text_field( $klarna_order->shipping_address->postal_code ) );

			$order->set_created_via( 'klarna_checkout_backup_order_creation' );
			$order->set_currency( sanitize_text_field( $klarna_order->purchase_currency ) );
			$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
			$order->set_payment_method( 'kco' );

			$order->set_shipping_total( WC()->cart->get_shipping_total() );
			$order->set_discount_total( WC()->cart->get_discount_total() );
			$order->set_discount_tax( WC()->cart->get_discount_tax() );
			$order->set_cart_tax( WC()->cart->get_cart_contents_tax() + WC()->cart->get_fee_tax() );
			$order->set_shipping_tax( WC()->cart->get_shipping_tax() );
			$order->set_total( WC()->cart->get_total( 'edit' ) );

			WC()->checkout()->create_order_line_items( $order, WC()->cart );
			WC()->checkout()->create_order_fee_lines( $order, WC()->cart );
			WC()->checkout()->create_order_shipping_lines( $order, WC()->session->get( 'chosen_shipping_methods' ), WC()->shipping->get_packages() );
			WC()->checkout()->create_order_tax_lines( $order, WC()->cart );
			WC()->checkout()->create_order_coupon_lines( $order, WC()->cart );

			$order->save();
			if ( 'ACCEPTED' === $klarna_order->fraud_status ) {
				$order->payment_complete( $klarna_order->order_id );
				$order->add_order_note( 'Payment via Klarna Checkout, order ID: ' . sanitize_key( $klarna_order->order_id ) );
			} elseif ( 'REJECTED' === $klarna_order->fraud_status ) {
				$order->update_status( 'on-hold', 'Klarna Checkout order was rejected.' );
			}

			KCO_WC()->api->request_post_acknowledge_order( $klarna_order->order_id );
			KCO_WC()->api->request_post_set_merchant_reference(
				$klarna_order->order_id,
				array(
					'merchant_reference1' => $order->get_order_number(),
					'merchant_reference2' => $order->get_id(),
				)
			);
		} catch ( Exception $e ) {
			$logger = new WC_Logger();
			$logger->add( 'klarna-checkout-for-woocommerce', 'Backup order creation error: ' . $e->getMessage() );
		}
	}

}

Klarna_Checkout_For_WooCommerce_API_Callbacks::get_instance();
