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
	 * Klarna purchase currency.
	 * Used for multi currency calculation in callbacks if Aelia plugin is installed.
	 *
	 * @var bool
	 */
	public $klarna_purchase_currency = false;

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
		// Make WC->cart & WC()->session available in backend.
		add_action( 'wp_loaded', array( $this, 'maybe_prepare_wc_session_for_server_side_callback' ), 1 );
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'maybe_prepare_wc_cart_for_server_side_callback' ), 1 );
	}


	/**
	 * Maybe set WC()->session if this is a Klarna callback.
	 * We do this to be able to retrieve WC()->cart in backend.
	 */
	public function maybe_prepare_wc_session_for_server_side_callback() {
		if ( isset( $_GET['kco_session_id'] ) && ( isset( $_GET['kco-action'] ) && ( 'validation' == $_GET['kco-action'] || 'push' == $_GET['kco-action'] ) ) ) {
			$session_id       = sanitize_key( $_GET['kco_session_id'] );
			$sessions_handler = new WC_Session_Handler();
			$session_data     = $sessions_handler->get_session( $session_id );

			if ( ! empty( $session_data ) ) {
				WC()->session = $sessions_handler;

				foreach ( $session_data as $key => $value ) {
					WC()->session->set( $key, maybe_unserialize( $value ) );
				}
			}
		}
	}

	/**
	 * Maybe set WC()->cart if this is a Klarna callback.
	 * We do this to be able to retrieve WC()->cart in backend.
	 */
	public function maybe_prepare_wc_cart_for_server_side_callback( $cart ) {
		if ( isset( $_GET['kco_session_id'] ) && ( isset( $_GET['kco-action'] ) && ( 'validation' == $_GET['kco-action'] || 'push' == $_GET['kco-action'] ) ) ) {
			WC()->cart = $cart;

			// If Aelia Currency Switcher plugin is used - set correct currency.
			add_filter( 'wc_aelia_cs_selected_currency', array( $this, 'wc_aelia_cs_selected_currency' ) );
		}
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

		// Let other plugins hook into the push notification.
		// Used by Klarna_Checkout_Subscription::handle_push_cb_for_payment_method_change().
		do_action( 'wc_klarna_push_cb', $klarna_order_id );

		$query_args = array(
			'fields'      => 'ids',
			'post_type'   => wc_get_order_types(),
			'post_status' => array_keys( wc_get_order_statuses() ),
			'meta_key'    => '_wc_klarna_order_id',
			'meta_value'  => $klarna_order_id,
		);

		$orders = get_posts( $query_args );

		// If zero matching orders were found, create backup order.
		if ( empty( $orders ) ) {
			// Backup order creation.
			$this->backup_order_creation( $klarna_order_id );
			return;
		}

		$order_id = $orders[0];
		$order    = wc_get_order( $order_id );

		if ( $order ) {
			// Get the Klarna order data.
			$response     = KCO_WC()->api->request_post_get_order( $klarna_order_id );
			$klarna_order = apply_filters( 'kco_wc_api_callbacks_push_klarna_order', json_decode( $response['body'] ) );

			// The Woo order was already created. Check if order status was set (in process_payment_handler).
			if ( ! $order->has_status( array( 'on-hold', 'processing', 'completed' ) ) ) {

				krokedil_log_events( $order_id, 'Klarna push callback. Updating order status.', $klarna_order );

				if ( 'ACCEPTED' === $klarna_order->fraud_status ) {
					$order->payment_complete( $klarna_order_id );
					// translators: Klarna order ID.
					$note = sprintf( __( 'Payment via Klarna Checkout, order ID: %s', 'klarna-checkout-for-woocommerce' ), sanitize_key( $klarna_order->order_id ) );
					$order->add_order_note( $note );
				} elseif ( 'REJECTED' === $klarna_order->fraud_status ) {
					$order->update_status( 'on-hold', __( 'Klarna Checkout order was rejected.', 'klarna-checkout-for-woocommerce' ) );
				} elseif ( 'PENDING' === $klarna_order->fraud_status ) {
					// translators: Klarna order ID.
					$note = sprintf( __( 'Klarna order is under review, order ID: %s.', 'klarna-checkout-for-woocommerce' ), sanitize_key( $klarna_order->order_id ) );
					$order->update_status( 'on-hold', $note );
				}
			} else {
				krokedil_log_events( $order_id, 'Klarna push callback. Order status already set to On hold/Processing/Completed.', $klarna_order );
			}

			// Acknowledge order in Klarna.
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
			krokedil_log_events( $order_id, 'Klarna notification callback data', $data );
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
		$checkout  = WC()->checkout();

		// Set currency if multi currency plugins needs this when calculating cart.
		$this->klarna_purchase_currency = sanitize_text_field( $data['purchase_currency'] );

		if ( is_array( $data ) ) {
			$log_order                 = $data;
			$log_order['html_snippet'] = '';
			krokedil_log_events( null, 'Klarna validation callback data', $log_order );
			KCO_WC()->logger->log( 'Klarna validation callback data: ' . stripslashes_deep( json_encode( $log_order ) ) );
		}

		// Country.
		WC()->customer->set_billing_country( strtoupper( sanitize_text_field( $data['billing_address']['country'] ) ) );
		WC()->customer->set_shipping_country( strtoupper( sanitize_text_field( $data['shipping_address']['country'] ) ) );

		// County/State.
		if ( isset( $data['billing_address']['region'] ) ) {
			WC()->customer->set_billing_state( sanitize_text_field( $data['billing_address']['region'] ) );
			WC()->customer->set_shipping_state( sanitize_text_field( $data['shipping_address']['region'] ) );
		}

		// Postcode.
		WC()->customer->set_billing_postcode( sanitize_text_field( $data['billing_address']['postal_code'] ) );
		WC()->customer->set_shipping_postcode( sanitize_text_field( $data['shipping_address']['postal_code'] ) );

		WC()->cart->calculate_totals();

		$all_in_stock     = true;
		$shipping_valid   = true;
		$shipping_chosen  = false;
		$needs_shipping   = false;
		$coupon_valid     = true;
		$has_subscription = false;
		$needs_login      = false;
		$email_exists     = false;
		$totals_match     = true;

		$session_id = $_GET['kco_session_id'];
		$session    = $this->get_session_from_id( $session_id );
		if ( is_array( $data ) ) {
			$log_session                             = $session;
			$log_session['_krokedil_events_session'] = '';
			krokedil_log_events( null, 'Klarna validation callback session data', $log_session );
			KCO_WC()->logger->log( 'Klarna validation callback session data: ' . stripslashes_deep( json_encode( $log_session ) ) );
		}

		// Check stock for each item and shipping method and if subscription.
		$cart_items = $data['order_lines'];
		foreach ( $cart_items as $cart_item ) {
			if ( 'physical' === $cart_item['type'] || 'digital' === $cart_item['type'] ) {

				// Get product by SKU or ID.
				if ( wc_get_product_id_by_sku( $cart_item['reference'] ) ) {
					$cart_item_product = wc_get_product( wc_get_product_id_by_sku( $cart_item['reference'] ) );
				} else {
					$cart_item_product = wc_get_product( $cart_item['reference'] );
				}

				if ( $cart_item_product ) {
					if ( ! $cart_item_product->has_enough_stock( $cart_item['quantity'] ) || 'outofstock' === $cart_item_product->get_stock_status() ) {
						$all_in_stock = false;
					}
					if ( ! $cart_item_product->is_virtual() ) {
						if ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $cart_item_product ) ) {
							if ( 0 === WC_Subscriptions_Product::get_trial_length( $cart_item_product ) ) {
								$needs_shipping = true;
							} else {
								$needs_shipping = false;
							}
						} else {
							$needs_shipping = true;
						}
					}
				}

				if ( class_exists( 'WC_Subscriptions_Cart' ) ) {
					$has_subscription = ( WC_Subscriptions_Product::is_subscription( $cart_item_product ) === true ) ? true : $has_subscription;
				}
			} elseif ( 'shipping_fee' === $cart_item['type'] ) {
				$shipping_chosen = true;
			}
		}

		if ( $needs_shipping ) {
			$shipping_valid = $shipping_chosen;
		}

		// Validate any potential coupons.
		if ( ! empty( json_decode( $data['merchant_data'] )->coupons ) ) {
			$coupons  = json_decode( $data['merchant_data'] )->coupons;
			$emails[] = $data['billing_address']['email'];
			foreach ( $coupons as $coupon ) {
				$wc_coupon = new WC_Coupon( $coupon );

				$limit_per_user = $wc_coupon->get_usage_limit_per_user();
				if ( 0 < $limit_per_user ) {
					$used_by         = $wc_coupon->get_used_by();
					$usage_count     = 0;
					$user_id_matches = array( get_current_user_id() );
					// Check usage against emails.
					foreach ( $emails as $email ) {
						WC()->customer->set_email( $email );
						$usage_count      += count( array_keys( $used_by, $email, true ) );
						$user              = get_user_by( 'email', $email );
						$user_id_matches[] = $user ? $user->ID : 0;
					}
					// Check against billing emails of existing users.
					$users_query     = new WP_User_Query(
						array(
							'fields'     => 'ID',
							'meta_query' => array(
								array(
									'key'     => '_billing_email',
									'value'   => $email,
									'compare' => 'IN',
								),
							),
						)
					); // WPCS: slow query ok.
					$user_id_matches = array_unique( array_filter( array_merge( $user_id_matches, $users_query->get_results() ) ) );
					foreach ( $user_id_matches as $user_id ) {
						$usage_count += count( array_keys( $used_by, (string) $user_id, true ) );
					}
					if ( $usage_count >= $wc_coupon->get_usage_limit_per_user() ) {
						$coupon_valid = false;
					}
				}
			}
		}

		if ( ! empty( json_decode( $data['merchant_data'] )->is_user_logged_in ) ) {
			$is_user_logged_in = json_decode( $data['merchant_data'] )->is_user_logged_in;
		}
		// Check if any product is subscription product.
		if ( class_exists( 'WC_Subscriptions_Cart' ) && $has_subscription ) {
			if ( ! $checkout->is_registration_enabled() && ! $is_user_logged_in ) {
				$needs_login = true;
			}
			// If customer has account but isn't logged in - tell them to login.
			if ( email_exists( $data['billing_address']['email'] ) && ! $is_user_logged_in ) {
				$email_exists = true;
			}
		}

		// Check if registration is required, customer is not logged in but have an existing account.
		if ( $checkout->is_registration_required() && ! $is_user_logged_in && email_exists( $data['billing_address']['email'] ) ) {
			$needs_login = true;
		}

		// Check cart totals.
		$klarna_total = $data['order_amount'];
		$wc_total     = intval( round( WC()->cart->get_total( 'edit' ) * 100 ) );
		if ( $klarna_total !== $wc_total ) {
			// Do another check against raw session data if the first fails.
			$wc_total = intval( round( maybe_unserialize( $session['cart_totals'] )['total'] * 100 ) );
			if ( $klarna_total !== $wc_total ) {
				$totals_match = false;
				KCO_WC()->logger->log( 'Cart totals does not match in validation callback. Klarna_total: ' . $klarna_total . ' WC_total: ' . $wc_total . ' Cart Totals: ' . maybe_unserialize( $session['cart_totals'] ) );
			}
		}

		do_action( 'kco_validate_checkout', $data, $all_in_stock, $shipping_chosen );

		if ( $all_in_stock && $shipping_valid && $coupon_valid && $totals_match && ! $needs_login && ! $email_exists ) {
			header( 'HTTP/1.0 200 OK' );
		} else {
			header( 'HTTP/1.0 303 See Other' );
			if ( ! $all_in_stock ) {
				$logger = new WC_Logger();
				$logger->add( 'klarna-checkout-for-woocommerce', 'Stock validation failed for SKU ' . $cart_item['reference'] );
				header( 'Location: ' . wc_get_cart_url() . '?stock_validate_failed' );
			} elseif ( ! $shipping_valid ) {
				header( 'Location: ' . wc_get_checkout_url() . '?no_shipping' );
			} elseif ( ! $coupon_valid ) {
				header( 'Location: ' . wc_get_checkout_url() . '?invalid_coupon' );
			} elseif ( $needs_login ) {
				header( 'Location: ' . wc_get_checkout_url() . '?needs_login' );
			} elseif ( $email_exists ) {
				header( 'Location: ' . wc_get_checkout_url() . '?email_exists' );
			} elseif ( ! $totals_match ) {
				header( 'Location: ' . wc_get_checkout_url() . '?totals_dont_match' );
			} else {
				header( 'Location: ' . wc_get_checkout_url() . '?unable_to_process' );
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
		// Send back order amount, order tax amount, order lines, purchase currency and status 200.
		$post_body = file_get_contents( 'php://input' );
		$data      = json_decode( $post_body, true );
		do_action( 'wc_klarna_shipping_option_update_cb', $data );

		header( 'HTTP/1.0 200 OK' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT );
		die();
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
		KCO_WC()->logger->log( 'Starting backup order creation for Klarna order ID ' . $klarna_order_id );
		$response = KCO_WC()->api->request_post_get_order( $klarna_order_id );

		if ( ! is_wp_error( $response ) && ( $response['response']['code'] >= 200 && $response['response']['code'] <= 299 ) ) {
			// Retrieve Klarna order.
			$klarna_order = json_decode( $response['body'] );

			// Process customer data.
			$this->process_customer_data( $klarna_order );

			// Process order.
			$this->process_order( $klarna_order );

		} else {
			// Retrieve error.
			$error = KCO_WC()->api->extract_error_messages( $response );
			KCO_WC()->logger->log( 'ERROR when requesting Klarna order in backup_order_creation (' . stripslashes_deep( json_encode( $error ) ) . ') ' . stripslashes_deep( json_encode( $response ) ) );
			return false;
		}
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

		if ( 'organization' === $klarna_order->customer->type ) {
			WC()->customer->set_billing_company( sanitize_text_field( $klarna_order->billing_address->organization_name ) );
			WC()->customer->set_shipping_company( sanitize_text_field( $klarna_order->shipping_address->organization_name ) );
		}

		WC()->customer->save();
	}

	/**
	 * Processes WooCommerce order on backup order creation.
	 *
	 * @param Klarna_Checkout_Order $klarna_order Klarna order.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	private function process_order( $klarna_order ) {
		$billing_first_name  = isset( $klarna_order->billing_address->given_name ) ? $klarna_order->billing_address->given_name : '.';
		$billing_last_name   = isset( $klarna_order->billing_address->family_name ) ? $klarna_order->billing_address->family_name : '.';
		$billing_address     = isset( $klarna_order->billing_address->street_address ) ? $klarna_order->billing_address->street_address : '.';
		$billing_address2    = isset( $klarna_order->billing_address->street_address2 ) ? $klarna_order->billing_address->street_address2 : '';
		$billing_postal_code = isset( $klarna_order->billing_address->postal_code ) ? $klarna_order->billing_address->postal_code : $fallback_postcode;
		$billing_city        = isset( $klarna_order->billing_address->city ) ? $klarna_order->billing_address->city : '.';
		$billing_region      = isset( $klarna_order->billing_address->region ) ? $klarna_order->billing_address->region : '';
		$billing_country     = isset( $klarna_order->billing_address->country ) ? $klarna_order->billing_address->country : WC()->countries->get_base_country();
		$billing_phone       = isset( $klarna_order->billing_address->phone ) ? $klarna_order->billing_address->phone : '.';
		$billing_email       = isset( $klarna_order->billing_address->email ) ? $klarna_order->billing_address->email : '.';

		$shipping_first_name  = isset( $klarna_order->shipping_address->given_name ) ? $klarna_order->shipping_address->given_name : '.';
		$shipping_last_name   = isset( $klarna_order->shipping_address->family_name ) ? $klarna_order->shipping_address->family_name : '.';
		$shipping_address     = isset( $klarna_order->shipping_address->street_address ) ? $klarna_order->shipping_address->street_address : '.';
		$shipping_address2    = isset( $klarna_order->shipping_address->street_address2 ) ? $klarna_order->shipping_address->street_address2 : '';
		$shipping_postal_code = isset( $klarna_order->shipping_address->postal_code ) ? $klarna_order->shipping_address->postal_code : '.';
		$shipping_city        = isset( $klarna_order->shipping_address->city ) ? $klarna_order->shipping_address->city : '.';
		$shipping_region      = isset( $klarna_order->shipping_address->region ) ? $klarna_order->shipping_address->region : '';
		$shipping_country     = isset( $klarna_order->shipping_address->country ) ? $klarna_order->shipping_address->country : WC()->countries->get_base_country();
		$shipping_phone       = isset( $klarna_order->shipping_address->phone ) ? $klarna_order->shipping_address->phone : '.';
		$shipping_email       = isset( $klarna_order->shipping_address->email ) ? $klarna_order->shipping_address->email : '.';

		$billing_company  = isset( $klarna_order->billing_address->organization_name ) ? $klarna_order->billing_address->organization_name : '.';
		$shipping_company = isset( $klarna_order->shipping_address->organization_name ) ? $klarna_order->shipping_address->organization_name : '.';
		$org_nr           = isset( $klarna_order->customer->organization_registration_id ) ? $klarna_order->customer->organization_registration_id : '.';

		try {
			$order = wc_create_order( array( 'status' => 'pending' ) );

			$order->set_billing_first_name( sanitize_text_field( $billing_first_name ) );
			$order->set_billing_last_name( sanitize_text_field( $billing_last_name ) );
			$order->set_billing_country( sanitize_text_field( $billing_country ) );
			$order->set_billing_address_1( sanitize_text_field( $billing_address ) );
			$order->set_billing_address_2( sanitize_text_field( $billing_address2 ) );
			$order->set_billing_city( sanitize_text_field( $billing_city ) );
			$order->set_billing_state( sanitize_text_field( $billing_region ) );
			$order->set_billing_postcode( sanitize_text_field( $billing_postal_code ) );
			$order->set_billing_phone( sanitize_text_field( $billing_phone ) );
			$order->set_billing_email( sanitize_text_field( $billing_email ) );

			$order->set_shipping_first_name( sanitize_text_field( $shipping_first_name ) );
			$order->set_shipping_last_name( sanitize_text_field( $shipping_last_name ) );
			$order->set_shipping_country( sanitize_text_field( $shipping_country ) );
			$order->set_shipping_address_1( sanitize_text_field( $shipping_address ) );
			$order->set_shipping_address_2( sanitize_text_field( $shipping_address2 ) );
			$order->set_shipping_city( sanitize_text_field( $shipping_city ) );
			$order->set_shipping_state( sanitize_text_field( $shipping_region ) );
			$order->set_shipping_postcode( sanitize_text_field( $shipping_postal_code ) );
			update_post_meta( $order->get_id(), '_shipping_phone', sanitize_text_field( $shipping_phone ) );
			update_post_meta( $order->get_id(), '_shipping_email', sanitize_text_field( $shipping_email ) );

			if ( 'organization' === $klarna_order->customer->type ) {
				$order->set_billing_company( sanitize_text_field( $billing_company ) );
				$order->set_shipping_company( sanitize_text_field( $shipping_company ) );
				// update_post_meta( $order->get_id(), '_kco_org_nr', $org_nr );
			}

			$order->set_created_via( 'klarna_checkout_backup_order_creation' );
			$order->set_currency( sanitize_text_field( $klarna_order->purchase_currency ) );
			$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );

			$available_gateways = WC()->payment_gateways->payment_gateways();
			$payment_method     = $available_gateways['kco'];
			$order->set_payment_method( $payment_method );

			// Add recurring token to order via Checkout API.
			$response_data = KCO_WC()->api->request_pre_get_order( $klarna_order->order_id );
			if ( ! is_wp_error( $response_data ) && ( $response_data['response']['code'] >= 200 && $response_data['response']['code'] <= 299 ) ) {
				$klarna_order_data = json_decode( $response_data['body'] );
				if ( isset( $klarna_order_data->recurring_token ) && ! empty( $klarna_order_data->recurring_token ) ) {
					$recurring_token = $klarna_order_data->recurring_token;
					update_post_meta( $order->get_id(), '_kco_recurring_token', $recurring_token );
				}
			} else {
				// Retrieve error.
				$error = KCO_WC()->api->extract_error_messages( $response_data );
				KCO_WC()->logger->log( 'ERROR when requesting Klarna order via Checkout API (' . stripslashes_deep( json_encode( $error ) ) . ') ' . stripslashes_deep( json_encode( $response_data ) ) );
			}

			// Process cart with data from Klarna.
			// Only do this if we where unable to create the cart object from session ID.
			if ( WC()->cart->is_empty() ) {

				// Apply coupons if it has been used.
				// @todo - fix so that original price and the discounted amount is displayed in the order.
				if ( isset( $klarna_order->merchant_data ) ) {
					$merchant_data = json_decode( $klarna_order->merchant_data );
					if ( isset( $merchant_data->coupons ) && ! empty( $merchant_data->coupons ) ) {
						$coupons = $merchant_data->coupons;
						foreach ( $coupons as $coupon ) {
							$order->apply_coupon( $coupon );
						}
					}
				}

				$this->process_order_lines( $klarna_order, $order );
				$order->set_shipping_total( self::get_shipping_total( $klarna_order ) );
				$order->set_cart_tax( self::get_cart_contents_tax( $klarna_order ) );
				$order->set_shipping_tax( self::get_shipping_tax_total( $klarna_order ) );
				$order->set_total( $klarna_order->order_amount / 100 );
				$order->calculate_totals();
			} else {

				// Set currency if multi currency plugins needs this when calculating cart.
				$this->klarna_purchase_currency = sanitize_text_field( $klarna_order->purchase_currency );

				WC()->cart->calculate_totals();
				$order->set_shipping_total( WC()->cart->get_shipping_total() );
				$order->set_discount_total( WC()->cart->get_discount_total() );
				$order->set_discount_tax( WC()->cart->get_discount_tax() );
				$order->set_cart_tax( WC()->cart->get_cart_contents_tax() + WC()->cart->get_fee_tax() );
				$order->set_shipping_tax( WC()->cart->get_shipping_tax() );
				$order->set_total( WC()->cart->get_total( 'edit' ) );

				KCO_WC()->logger->log( 'Processing order lines (from WC cart) during backup order creation for Klarna order ID ' . $klarna_order->order_id );
				krokedil_log_events( $order->get_id(), 'Processing order lines (from WC cart) during backup order creation.', array() );
				WC()->checkout()->create_order_line_items( $order, WC()->cart );
				WC()->checkout()->create_order_fee_lines( $order, WC()->cart );
				WC()->checkout()->create_order_shipping_lines( $order, WC()->session->get( 'chosen_shipping_methods' ), WC()->shipping()->get_packages() );
				WC()->checkout()->create_order_tax_lines( $order, WC()->cart );
				WC()->checkout()->create_order_coupon_lines( $order, WC()->cart );

				// Check order totals.
				if ( ! $this->order_totals_match( $order, $klarna_order ) ) {
					$this->update_order_line_prices( $order, $klarna_order );
				}
			}

			/**
			 * Added to simulate WCs own order creation.
			 *
			 * TODO: Add the order content into a $data variable and pass as second parameter to the hook.
			 */
			do_action( 'woocommerce_checkout_create_order', $order, array() );

			// Save the order
			$order_id = $order->save();

			/**
			 * Added to simulate WCs own order creation.
			 *
			 * TODO: Add the order content into a $data variable and pass as second parameter to the hook.
			 */
			do_action( 'woocommerce_checkout_update_order_meta', $order_id, array() );

			$order->add_order_note( __( 'Order created via Klarna Checkout API callback. Please verify the order in Klarnas system.', 'klarna-checkout-for-woocommerce' ) );

			if ( 'ACCEPTED' === $klarna_order->fraud_status ) {
				$order->payment_complete( $klarna_order->order_id );
				// translators: Klarna order ID.
				$note = sprintf( __( 'Payment via Klarna Checkout, order ID: %s', 'klarna-checkout-for-woocommerce' ), sanitize_key( $klarna_order->order_id ) );
				$order->add_order_note( $note );
			} elseif ( 'REJECTED' === $klarna_order->fraud_status ) {
				$order->update_status( 'on-hold', __( 'Klarna Checkout order was rejected.', 'klarna-checkout-for-woocommerce' ) );
			} elseif ( 'PENDING' === $klarna_order->fraud_status ) {
				// translators: Klarna order ID.
				$note = sprintf( __( 'Klarna order is under review, order ID: %s.', 'klarna-checkout-for-woocommerce' ), sanitize_key( $klarna_order->order_id ) );
				$order->update_status( 'on-hold', $note );
			}
			KCO_WC()->api->request_post_acknowledge_order( $klarna_order->order_id );
			KCO_WC()->api->request_post_set_merchant_reference(
				$klarna_order->order_id,
				array(
					'merchant_reference1' => $order->get_order_number(),
					'merchant_reference2' => $order->get_id(),
				)
			);

			if ( ! $this->order_totals_match( $order, $klarna_order ) ) {
				$order->update_status( 'on-hold', sprintf( __( 'Order needs manual review, WooCommerce total and Klarna total do not match. Klarna order total: %s.', 'klarna-checkout-for-woocommerce' ), $klarna_order->order_amount ) );
			}
		} catch ( Exception $e ) {
			$logger = new WC_Logger();
			$logger->add( 'klarna-checkout-for-woocommerce', 'Backup order creation error: ' . $e->getCode() . ' - ' . $e->getMessage() );
		}
	}

	/**
	 * Processes cart contents on backup order creation.
	 *
	 * @param Klarna_Checkout_Order $klarna_order Klarna order.
	 * @param WooCommerce_Order     $order WooCommerce order.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	private function process_order_lines( $klarna_order, $order ) {
		KCO_WC()->logger->log( 'Processing order lines (from Klarna order) during backup order creation for Klarna order ID ' . $klarna_order->order_id );
		krokedil_log_events( $order->get_id(), 'Processing order lines (from Klarna order) during backup order creation.', array() );
		foreach ( $klarna_order->order_lines as $cart_item ) {
			if ( 'physical' === $cart_item->type || 'digital' === $cart_item->type ) {
				if ( wc_get_product_id_by_sku( $cart_item->reference ) ) {
					$id = wc_get_product_id_by_sku( $cart_item->reference );
				} else {
					$id = $cart_item->reference;
				}

				try {
					$product = wc_get_product( $id );
					$args    = array(
						'name'         => $product->get_name(),
						'tax_class'    => $product->get_tax_class(),
						'product_id'   => $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id(),
						'variation_id' => $product->is_type( 'variation' ) ? $product->get_id() : 0,
						'variation'    => $product->is_type( 'variation' ) ? $product->get_attributes() : array(),
						'subtotal'     => ( $cart_item->total_amount - $cart_item->total_tax_amount ) / 100,
						'total'        => ( $cart_item->total_amount - $cart_item->total_tax_amount ) / 100,
						'quantity'     => $cart_item->quantity,
					);
					$item    = new WC_Order_Item_Product();
					$item->set_props( $args );
					$item->set_backorder_meta();
					$item->set_order_id( $order->get_id() );
					$item->save();
					$order->add_item( $item );

				} catch ( Exception $e ) {
					$logger = new WC_Logger();
					$logger->add( 'klarna-checkout-for-woocommerce', 'Backup order creation error add to cart error: ' . $e->getCode() . ' - ' . $e->getMessage() );
				}
			}

			if ( 'shipping_fee' === $cart_item->type ) {
				try {
					$method_id   = substr( $cart_item->reference, 0, strpos( $cart_item->reference, ':' ) );
					$instance_id = substr( $cart_item->reference, strpos( $cart_item->reference, ':' ) + 1 );
					$rate        = new WC_Shipping_Rate( $cart_item->reference, $cart_item->name, ( $cart_item->total_amount - $cart_item->total_tax_amount ) / 100, array(), $method_id, $instance_id );
					$item        = new WC_Order_Item_Shipping();
					$item->set_props(
						array(
							'method_title' => $rate->label,
							'method_id'    => $rate->id,
							'total'        => wc_format_decimal( $rate->cost ),
							'taxes'        => $rate->taxes,
							'meta_data'    => $rate->get_meta_data(),
						)
					);
					$order->add_item( $item );
				} catch ( Exception $e ) {
					$logger = new WC_Logger();
					$logger->add( 'klarna-checkout-for-woocommerce', 'Backup order creation error add shipping error: ' . $e->getCode() . ' - ' . $e->getMessage() );
				}
			}

			if ( 'surcharge' === $cart_item->type ) {
				$tax_class = '';
				if ( isset( $cart_item->merchant_data ) ) {
					$merchant_data = json_decode( $cart_item->merchant_data );
					$tax_class     = $merchant_data->tax_class;
				}
				try {
					$args = array(
						'name'      => $cart_item->name,
						'tax_class' => $tax_class,
						'subtotal'  => ( $cart_item->total_amount - $cart_item->total_tax_amount ) / 100,
						'total'     => ( $cart_item->total_amount - $cart_item->total_tax_amount ) / 100,
						'quantity'  => $cart_item->quantity,
					);
					$fee  = new WC_Order_Item_Fee();
					$fee->set_props( $args );
					$order->add_item( $fee );
				} catch ( Exception $e ) {
					$logger = new WC_Logger();
					$logger->add( 'klarna-checkout-for-woocommerce', 'Backup order creation error add fee error: ' . $e->getCode() . ' - ' . $e->getMessage() );
				}
			}
		}
	}

	/**
	 * Update order line item prices on backup order creation.
	 *
	 * @param WooCommerce_Order     $order WooCommerce order.
	 * @param Klarna_Checkout_Order $klarna_order Klarna order.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	public function update_order_line_prices( $order, $klarna_order ) {
		$old_order_total = $order->get_total();
		// Loop through Order items ("line_item" type).
		foreach ( $order->get_items() as $item_id => $item ) {
			$product          = $order->get_product_from_item( $item );
			$klarna_reference = KCO_WC()->order_lines->get_item_reference( $product );
			$klarna_line_item = $this->get_klarna_line_item( $klarna_reference, $klarna_order );

			if ( ! empty( $klarna_line_item ) ) {
				$new_total           = ( $klarna_line_item->total_amount - $klarna_line_item->total_tax_amount ) / 100;
				$tax_for_calculation = ( $klarna_line_item->tax_rate / 10000 ) + 1;
				$new_sub_total       = ( ( $klarna_line_item->unit_price * $klarna_line_item->quantity ) / $tax_for_calculation ) / 100;

				$item->set_subtotal( $new_sub_total );
				$item->set_total( $new_total );

				// Make new taxes calculations.
				$item->calculate_taxes();
				// Save line item data.
				$item->save();
			}
		}
		$order->set_total( $klarna_order->order_amount / 100 );
		$order->calculate_totals();

		$note = 'Order lines adjusted for order id ' . $order->get_id() . ' (KCO order ' . $klarna_order->order_id . '). Old order total: ' . $old_order_total . '. New order total: ' . $order->get_total();
		KCO_WC()->logger->log( $note );
		krokedil_log_events( $order->get_id(), 'Order line prices adjusted during backup order creation', $note );
	}

	/**
	 * Get a specific line item from Klarna order based on the passed reference.
	 *
	 * @param string                $klarna_reference Product ID/SKU retrieved from order_lines->get_item_reference.
	 * @param Klarna_Checkout_Order $klarna_order Klarna order.
	 *
	 * @return bool
	 */
	public function get_klarna_line_item( $klarna_reference, $klarna_order ) {
		$klarna_line_item = null;

		foreach ( $klarna_order->order_lines as $order_line ) {
			if ( $klarna_reference === $order_line->reference ) {
				$klarna_line_item = $order_line;
				break;
			}
		}
		return $klarna_line_item;
	}

	private function get_session_from_id( $session_id ) {
		$sessions_handler = new WC_Session_Handler();
		$session          = $sessions_handler->get_session( $session_id );

		return $session;
	}

	private static function get_shipping_total( $klarna_order ) {
		$shipping_total = 0;
		foreach ( $klarna_order->order_lines as $cart_item ) {
			if ( 'shipping_fee' === $cart_item->type ) {
				$shipping_total += $cart_item->total_amount;
			}
		}
		if ( $shipping_total > 0 ) {
			$shipping_total = $shipping_total / 100;
		}

		return $shipping_total;
	}

	private static function get_shipping_tax_total( $klarna_order ) {
		$shipping_tax_total = 0;
		foreach ( $klarna_order->order_lines as $cart_item ) {
			if ( 'shipping_fee' === $cart_item->type ) {
				$shipping_tax_total += $cart_item->total_tax_amount;
			}
		}
		if ( $shipping_tax_total > 0 ) {
			$shipping_tax_total = $shipping_tax_total / 100;
		}

		return $shipping_tax_total;
	}

	private static function get_cart_contents_tax( $klarna_order ) {
		$cart_contents_tax = 0;
		foreach ( $klarna_order->order_lines as $cart_item ) {
			if ( 'physical' === $cart_item->type || 'digital' === $cart_item->type ) {
				$cart_contents_tax += $cart_item->total_tax_amount;
			}
		}
		if ( $cart_contents_tax > 0 ) {
			$cart_contents_tax = $cart_contents_tax / 100;
		}

		return $cart_contents_tax;
	}

	/**
	 * Set selected currency if Aelia Currency Switcher is used.
	 * Used in validate and push notification callback.
	 *
	 * @param string $currency Currency used for calculation.
	 */
	public function wc_aelia_cs_selected_currency( $currency ) {
		if ( $this->klarna_purchase_currency ) {
			$currency = $this->klarna_purchase_currency;
		}
		return $currency;
	}

	/**
	 * Compare order totals between Woo & Klarna order.
	 *
	 * @param WooCommerce_Order     $order WooCommerce order.
	 * @param Klarna_Checkout_Order $klarna_order Klarna order.
	 *
	 * @return bool
	 */
	public function order_totals_match( $order, $klarna_order ) {
		if ( (int) round( $order->get_total() * 100 ) !== (int) $klarna_order->order_amount ) {
			return false;
		}
		return true;
	}
}

Klarna_Checkout_For_WooCommerce_API_Callbacks::get_instance();
