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
			// The order was already created. Check if order status was set (in thankyou page).
			if ( ! $order->has_status( array( 'on-hold', 'processing', 'completed' ) ) ) {

				$response     = KCO_WC()->api->request_post_get_order( $klarna_order_id );
				$klarna_order = apply_filters( 'kco_wc_api_callbacks_push_klarna_order', json_decode( $response['body'] ) );

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
				KCO_WC()->api->request_post_acknowledge_order( $klarna_order_id );
				KCO_WC()->api->request_post_set_merchant_reference(
					$klarna_order_id,
					array(
						'merchant_reference1' => $order->get_order_number(),
						'merchant_reference2' => $order->get_id(),
					)
				);

			} else {
				krokedil_log_events( $order_id, 'Klarna push callback. Order status already set to On hold/Processing/Completed.', $klarna_order );
			}
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
		krokedil_log_events( null, 'Klarna validation callback data', $data );
		$all_in_stock     = true;
		$shipping_chosen  = false;
		$shipping_valid   = true;
		$coupon_valid     = true;
		$has_subscription = false;
		$needs_login      = false;
		$email_exists     = false;
		$totals_match     = true;

		$session_id = $_GET['kco_session_id'];
		$session    = $this->get_session_from_id( $session_id );

		$form_data = false;
		if ( isset( $session['kco_checkout_form'] ) ) {
			$form_data = unserialize( $session['kco_checkout_form'] );
		}
		$has_required_data     = true;
		$failed_required_check = array();
		if ( false !== $form_data ) {
			foreach ( $form_data as $form_row ) {
				if ( 'true' === $form_row['required'] && '' === $form_row['value'] ) {
					$has_required_data       = false;
					$failed_required_check[] = $form_row['name'];
				}
			}
		}

		// Check stock for each item and shipping method and if subscription.
		$cart_items = $data['order_lines'];
		foreach ( $cart_items as $cart_item ) {
			if ( 'physical' === $cart_item['type'] || 'digital' === $cart_item['type'] ) {
				$needs_shipping = false;
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
			if ( $needs_shipping ) {
				$shipping_valid = $shipping_chosen;
			}
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
		$wc_total     = intval( round( maybe_unserialize( $session['cart_totals'] )['total'] * 100 ) );
		if ( $klarna_total !== $wc_total ) {
			$totals_match = false;
		}

		do_action( 'kco_validate_checkout', $data, $all_in_stock, $shipping_chosen );

		if ( $all_in_stock && $shipping_valid && $has_required_data && $coupon_valid && $totals_match && ! $needs_login && ! $email_exists ) {
			header( 'HTTP/1.0 200 OK' );
		} else {
			header( 'HTTP/1.0 303 See Other' );
			if ( ! $all_in_stock ) {
				$logger = new WC_Logger();
				$logger->add( 'klarna-checkout-for-woocommerce', 'Stock validation failed for SKU ' . $cart_item['reference'] );
				header( 'Location: ' . wc_get_cart_url() . '?stock_validate_failed' );
			} elseif ( ! $shipping_valid ) {
				header( 'Location: ' . wc_get_checkout_url() . '?no_shipping' );
			} elseif ( ! $has_required_data ) {
				$validation_hash = base64_encode( json_encode( $failed_required_check ) );
				header( 'Location: ' . wc_get_checkout_url() . '?required_fields=' . $validation_hash );
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
		$response = KCO_WC()->api->request_post_get_order( $klarna_order_id );

		if ( ! is_wp_error( $response ) && ( $response['response']['code'] >= 200 && $response['response']['code'] <= 299 ) ) {
			// Retrieve Klarna order.
			$klarna_order = json_decode( $response['body'] );

			// Process customer data.
			$this->process_customer_data( $klarna_order );

			// Process cart with data from Klarna.
			// Only do this if we where unable to create the cart object from session ID.
			if ( WC()->cart->is_empty() ) {
				$this->process_cart( $klarna_order );
			}

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
			if ( 'physical' === $cart_item->type || 'digital' === $cart_item->type ) {
				if ( wc_get_product_id_by_sku( $cart_item->reference ) ) {
					$id = wc_get_product_id_by_sku( $cart_item->reference );
				} else {
					$id = $cart_item->reference;
				}

				try {
					WC()->cart->add_to_cart( $id, $cart_item->quantity );
				} catch ( Exception $e ) {
					$logger = new WC_Logger();
					$logger->add( 'klarna-checkout-for-woocommerce', 'Backup order creation error add to cart error: ' . $e->getCode() . ' - ' . $e->getMessage() );
				}
			}
		}

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

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_fees();
		WC()->cart->calculate_totals();

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
			WC()->checkout()->create_order_shipping_lines( $order, WC()->session->get( 'chosen_shipping_methods' ), WC()->shipping()->get_packages() );
			WC()->checkout()->create_order_tax_lines( $order, WC()->cart );
			WC()->checkout()->create_order_coupon_lines( $order, WC()->cart );

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

			if ( (int) round( $order->get_total() * 100 ) !== (int) $klarna_order->order_amount ) {
				$order->update_status( 'on-hold', sprintf( __( 'Order needs manual review, WooCommerce total and Klarna total do not match. Klarna order total: %s.', 'klarna-checkout-for-woocommerce' ), $klarna_order->order_amount ) );
			}
		} catch ( Exception $e ) {
			$logger = new WC_Logger();
			$logger->add( 'klarna-checkout-for-woocommerce', 'Backup order creation error: ' . $e->getCode() . ' - ' . $e->getMessage() );
		}
	}

	private function get_session_from_id( $session_id ) {
		$sessions_handler = new WC_Session_Handler();
		$session          = $sessions_handler->get_session( $session_id );

		return $session;
	}

}

Klarna_Checkout_For_WooCommerce_API_Callbacks::get_instance();
