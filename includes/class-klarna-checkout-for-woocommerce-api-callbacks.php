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

		// Rest
		add_action( 'rest_api_init', array( $this, 'rest_routes' ) );
	}

	/**
	 * Registers REST API routes.
	 */
	public function rest_routes() {
		register_rest_route( 'kcowc/v1', '/address/(?P<id>[a-zA-Z0-9-]+)', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'rest_address_cb' ),
		) );

	}

	public function rest_address_cb( $request ) {
		$request_order_id = $request->get_param( 'id' );
		$request_body     = json_decode( $request->get_body() );

		$cart = WC()->cart;
		$cart->add_to_cart( 70 );
		$cart->calculate_shipping();
		$cart->calculate_totals();

		$response_body = array(
			'purchase_currency' => 'GBP',
			'order_amount'      => 7770,
			'order_tax_amount'  => 693,
			'order_lines'       => array(
				array(
					'reference'             => '70',
					'name'                  => 'Flying Ninja',
					'quantity'              => 3,
					'unit_price'            => 1025,
					'tax_rate'              => 0,
					'total_amount'          => 3077,
					'total_tax_amount'      => 0,
					'total_discount_amount' => 0,
					'product_url'           => 'http://krokedil.klarna.ngrok.io/product/flying-ninja/',
					'image_url'             => 'http://krokedil.klarna.ngrok.io/wp-content/uploads/2013/06/poster_2_up-180x180.jpg'
				),
				array(
					'type'             => 'shipping_fee',
					'reference'        => 'flat_rate:3',
					'name'             => 'Flat Rate',
					'quantity'         => 1,
					'unit_price'       => 4000,
					'tax_rate'         => 0,
					'total_amount'     => 4000,
					'total_tax_amount' => 0
				),
				array(
					'type'                  => 'sales_tax',
					'reference'             => 'Sales Tax',
					'name'                  => 'Sales Tax',
					'quantity'              => 1,
					'unit_price'            => 693,
					'tax_rate'              => 0,
					'total_amount'          => 693,
					'total_discount_amount' => 0,
					'total_tax_amount'      => 0
				)
			)
		);

		// header( 'HTTP/1.0 200 OK' );
		// header('Content-Type: application/json');
		// return json_encode( $response_body );

		$response = new WP_REST_Response( $response_body );
		$response->set_status( 200 );
		$response->header( 'Content-Type', 'application/json' );

		return $response;
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

		$klarna_order_id = $_GET['kco_wc_order_id'];

		$query_args = array(
			'post_type'   => wc_get_order_types(),
			'post_status' => array_keys( wc_get_order_statuses() ),
			'meta_key'    => '_wc_klarna_order_id',
			'meta_value'  => $klarna_order_id,
		);

		$orders   = get_posts( $query_args );
		$order_id = $orders[0]->ID;
		$order    = wc_get_order( $order_id );

		$response     = KCO_WC()->api->request_post_get_order( $klarna_order_id );
		$klarna_order = json_decode( $response['body'] );

		if ( 'ACCEPTED' === $klarna_order->fraud_status ) {
			$order->payment_complete( $klarna_order_id );
			$order->add_order_note( 'Payment via Klarna Checkout, order ID: ' . $klarna_order->order_id );
		} elseif ( 'REJECTED' === $klarna_order->fraud_status ) {
			$order->update_status( 'on-hold', 'Klarna Checkout order was rejected.' );
		}

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
				$logger->add( 'klarna', 'Stock validation failed for SKU ' . $cart_item['reference'] );
				header( 'Location: ' . wc_get_cart_url() . '?stock_validate_failed' );
			} elseif ( ! $shipping_chosen ) {
				header( 'Location: ' . wc_get_checkout_url() . '?no_shipping' );
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
		header( 'HTTP/1.0 200 OK' );
		header( 'Content-Type: application/json' );
		echo '{
	    "order_amount": 5000,
	    "order_tax_amount": 500,
	    "order_lines": [
	        {
	            "type": "physical",
	            "reference": "19-402-USA",
	            "name": "Red T-Shirt",
	            "quantity": 5,
	            "quantity_unit": "pcs",
	            "unit_price": 1000,
	            "tax_rate": 1000,
	            "total_amount": 5000,
	            "total_discount_amount": 0,
	            "total_tax_amount": 500,
	            "merchant_data": "{\"marketplace_seller_info\":[{\"product_category\":\"Women\'s Fashion\",\"product_name\":\"Women Sweatshirt\"}]}",
	            "product_url": "https://www.estore.com/products/f2a8d7e34",
	            "image_url": "https://www.exampleobjects.com/logo.png"
	        }
	    ],
	    "purchase_currency": "USD"
		}';

		// $post_body = file_get_contents( 'php://input' );
		// Convert post body into native object.
		// $data = json_decode( $post_body, true );

		// @TODO: Continue here

		/*
		header( 'HTTP/1.0 200 OK' );
		echo '{
	    "order_amount": 0,
	    "order_tax_amount": 0,
	    "order_lines": [
	        {
	            "type": "physical",
	            "reference": "19-402-USA",
	            "name": "Red T-Shirt",
	            "quantity": 5,
	            "quantity_unit": "pcs",
	            "unit_price": 10000,
	            "tax_rate": 1000,
	            "total_amount": 50000,
	            "total_discount_amount": 0,
	            "total_tax_amount": 5000,
	            "merchant_data": "{\"marketplace_seller_info\":[{\"product_category\":\"Women\'s Fashion\",\"product_name\":\"Women Sweatshirt\"}]}",
	            "product_url": "https://www.estore.com/products/f2a8d7e34",
	            "image_url": "https://www.exampleobjects.com/logo.png"
	        }
	    ],
	    "purchase_currency": "USD"
		}';
		*/
	}

}

Klarna_Checkout_For_WooCommerce_API_Callbacks::get_instance();
