<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Handles subscription payments with Klarna checkout.
 *
 * @class    Klarna_Checkout_Subscription
 * @version  1.0
 * @package  Klarna_Checkout/Classes
 * @category Class
 * @author   Krokedil
 */
class Klarna_Checkout_Subscription {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		// add_filter( 'kco_wc_api_request_args', array( $this, 'create_extra_merchant_data' ) );
		add_filter( 'kco_wc_api_request_args', array( $this, 'set_recurring' ) );
		add_action( 'kco_wc_process_payment', array( $this, 'set_recurring_token_for_order' ), 10, 2 );
		add_action( 'woocommerce_scheduled_subscription_payment_kco', array( $this, 'trigger_scheduled_payment' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'show_recurring_token' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_kco_recurring_token_update' ), 45, 2 );

		add_action( 'wc_klarna_push_cb', array( $this, 'handle_push_cb_for_payment_method_change' ) );
		add_action( 'init', array( $this, 'display_thankyou_message_for_payment_method_change' ) );

	}

	/**
	 * Checks the cart if it has a subscription product in it.
	 *
	 * @return bool
	 */
	public function check_if_subscription() {
		if ( class_exists( 'WC_Subscriptions_Cart' ) && ( WC_Subscriptions_Cart::cart_contains_subscription() || wcs_cart_contains_renewal() ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Checks if this is a KCO subscription payment method change.
	 *
	 * @return bool
	 */
	public function is_kco_subs_change_payment_method() {
		if ( isset( $_GET['key'] ) && ( isset( $_GET['kco-action'] ) && 'change-subs-payment' === $_GET['kco-action'] ) ) {

			return true;
		}
		return false;
	}

	/**
	 * Creates the extra merchant data array needed for a subscription.
	 *
	 * @param array $request_args The Klarna request arguments.
	 * @return array
	 */
	public function create_extra_merchant_data( $request_args ) {
		if ( class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) {
			$subscription_product_id = false;
			if ( ! empty( WC()->cart->cart_contents ) ) {
				foreach ( WC()->cart->cart_contents as $cart_item ) {
					if ( WC_Subscriptions_Product::is_subscription( $cart_item['product_id'] ) ) {
						$subscription_product_id = $cart_item['product_id'];
						break;
					}
				}
			}

			if ( $subscription_product_id ) {
				$subscription_expiration_time = WC_Subscriptions_Product::get_expiration_date( $subscription_product_id );
				if ( 0 !== $subscription_expiration_time ) {
					$end_time = date( 'Y-m-d\TH:i', strtotime( $subscription_expiration_time ) );
				} else {
					$end_time = date( 'Y-m-d\TH:i', strtotime( '+50 year' ) );
				}

				$emd_subscription = array(
					'subscription_name'            => 'Subscription: ' . get_the_title( $subscription_product_id ),
					'start_time'                   => date( 'Y-m-d\TH:i' ),
					'end_time'                     => $end_time,
					'auto_renewal_of_subscription' => false,
				);

				if ( is_user_logged_in() ) {
					// User is logged in - add user_login as unique_account_identifier.
					$current_user = wp_get_current_user();

					$emd_account = array(
						'unique_account_identifier' => $current_user->user_login,
						'account_registration_date' => date( 'Y-m-d\TH:i', strtotime( $current_user->user_registered ) ),
						'account_last_modified'     => date( 'Y-m-d\TH:i' ),
					);
				} else {
					// User is not logged in - send empty params.
					$emd_account = array(
						'unique_account_identifier' => '',
					);
				}
				$emd                                        = array(
					'Subscription'          => array( $emd_subscription ),
					'customer_account_info' => array( $emd_account ),
				);
				$request_args['attachment']['content_type'] = 'application/vnd.klarna.internal.emd-v2+json';
				$request_args['attachment']['body']         = json_encode( $emd );
			}
		}
		return $request_args;
	}

	/**
	 * Marks the order as a recurring order for Klarna
	 *
	 * @param array $request_args The Klarna request arguments.
	 * @return array
	 */
	public function set_recurring( $request_args ) {

		// Check if we have a subscription product. If yes set recurring field.
		if ( $this->check_if_subscription() || $this->is_kco_subs_change_payment_method() ) {
			$request_args['recurring'] = true;
		}

		// If this is a change payment method request.
		if ( $this->is_kco_subs_change_payment_method() ) {
			$order_id = wc_get_order_id_by_order_key( sanitize_key( $_GET['key'] ) );
			if ( $order_id ) {
				$wc_order = wc_get_order( $order_id );
				if ( is_object( $wc_order ) && function_exists( 'wcs_order_contains_subscription' ) && function_exists( 'wcs_is_subscription' ) ) {
					if ( wcs_order_contains_subscription( $wc_order, array( 'parent', 'renewal', 'resubscribe', 'switch' ) ) || wcs_is_subscription( $wc_order ) ) {

						// Modify order lines.
						$order_lines = array();
						foreach ( $wc_order->get_items() as $item ) {
							$order_lines[] = array(
								'name'             => $item->get_name(),
								'quantity'         => $item->get_quantity(),
								'total_amount'     => 0,
								'unit_price'       => 0,
								'total_tax_amount' => 0,
								'tax_rate'         => 0,
							);
						}
						$request_args['order_lines'] = $order_lines;

						// Modify merchant url's.
						global $wp;
						$current_url      = add_query_arg( $_SERVER['QUERY_STRING'], '', home_url( $wp->request ) );
						$confirmation_url = add_query_arg( 'kco-action', 'subs-payment-changed', $wc_order->get_view_order_url() );
						$push_url         = add_query_arg(
							array(
								'kco-action' => 'subs-payment-changed',
								'key'        => sanitize_key( $_GET['key'] ),
							),
							$request_args['merchant_urls']['push']
						);

						unset( $request_args['merchant_urls']['validation'] );
						unset( $request_args['merchant_urls']['shipping_option_update'] );
						$request_args['merchant_urls']['checkout']     = $current_url;
						$request_args['merchant_urls']['confirmation'] = $confirmation_url;
						$request_args['merchant_urls']['push']         = $push_url;
					}
				}
			}
		}

		return $request_args;
	}

	/**
	 * Sets the recurring token for the subscription order
	 *
	 * @param string $order_id WooCommerce order ID.
	 * @param object $klarna_order Klarna order.
	 *
	 * @return void
	 */
	public function set_recurring_token_for_order( $order_id = null, $klarna_order ) {
		$wc_order = wc_get_order( $order_id );
		if ( isset( $klarna_order->recurring_token ) ) {
			// Store recurring token in the order.
			update_post_meta( $order_id, '_kco_recurring_token', $klarna_order->recurring_token );

			// This function is run after WCS has created the subscription order.
			// Let's add the _kco_recurring_token to the subscription(s) as well.
			if ( class_exists( 'WC_Subscriptions' ) && ( wcs_order_contains_subscription( $wc_order, array( 'parent', 'renewal', 'resubscribe', 'switch' ) ) || wcs_is_subscription( $wc_order ) ) ) {
				$subcriptions = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => 'any' ) );
				foreach ( $subcriptions as $subcription ) {
					update_post_meta( $subcription->get_id(), '_kco_recurring_token', $klarna_order->recurring_token );
				}
			}
		}
	}

	/**
	 * Creates an order in Klarna from the recurring token saved.
	 *
	 * @param string $renewal_total The total price for the order.
	 * @param object $renewal_order The WooCommerce order for the renewal.
	 */
	public function trigger_scheduled_payment( $renewal_total, $renewal_order ) {
		$order_id = $renewal_order->get_id();

		$subscriptions = wcs_get_subscriptions_for_renewal_order( $renewal_order->get_id() );

		$recurring_token = get_post_meta( $order_id, '_kco_recurring_token', true );
		if ( empty( $recurring_token ) ) {
			$recurring_token = get_post_meta( WC_Subscriptions_Renewal_Order::get_parent_order_id( $order_id ), '_kco_recurring_token', true );
			update_post_meta( $order_id, '_kco_recurring_token', $recurring_token );
		}

		$create_order_response = new Klarna_Checkout_For_WooCommerce_API();
		$create_order_response = $create_order_response->request_create_recurring_order( $renewal_order, $recurring_token );
		if ( 200 === $create_order_response['response']['code'] ) {
			$klarna_order_id = json_decode( $create_order_response['body'] )->order_id;

			$renewal_order->add_order_note( sprintf( __( 'Subscription payment made with Klarna. Klarna order id: %s', 'klarna-checkout-for-woocommerce' ), $klarna_order_id ) );
			foreach ( $subscriptions as $subscription ) {
				$subscription->payment_complete( $klarna_order_id );
			}
		} else {
			$error_message = ' ';
			$errors        = json_decode( $create_order_response['body'], true );
			foreach ( $errors['error_messages'] as $error ) {
				$error_message = $error_message . $error . '. ';
			}
			$renewal_order->add_order_note( sprintf( __( 'Subscription payment failed with Klarna. Error code: %1$s. Message: %2$s', 'klarna-checkout-for-woocommerce' ), $create_order_response['response']['code'], $error_message ) );
			foreach ( $subscriptions as $subscription ) {
				$subscription->payment_failed();
			}
		}
	}

	public function show_recurring_token( $order ) {
		if ( 'shop_subscription' === $order->get_type() && get_post_meta( $order->get_id(), '_kco_recurring_token' ) ) {
			?>
			<div class="order_data_column" style="clear:both; float:none; width:100%;">
				<div class="address">
					<?php
						echo '<p><strong>' . __( 'Klarna recurring token' ) . ':</strong>' . get_post_meta( $order->id, '_kco_recurring_token', true ) . '</p>';
					?>
				</div>
				<div class="edit_address">
					<?php
						woocommerce_wp_text_input(
							array(
								'id'            => '_kco_recurring_token',
								'label'         => __( 'Klarna recurring token' ),
								'wrapper_class' => '_billing_company_field',
							)
						);
					?>
				</div>
			</div>
			<?php
		}
	}

	public function save_kco_recurring_token_update( $post_id, $post ) {
		$order = wc_get_order( $post_id );
		if ( 'shop_subscription' === $order->get_type() && get_post_meta( $post_id, '_kco_recurring_token' ) ) {
			update_post_meta( $post_id, '_kco_recurring_token', wc_clean( $_POST['_kco_recurring_token'] ) );
		}

	}

	/**
	 * Handle pus callback from Klarna if this is a KCO subscription payment method change.
	 *
	 * @return void
	 */
	public function handle_push_cb_for_payment_method_change( $klarna_order_id ) {
		if ( isset( $_GET['key'] ) && ( isset( $_GET['kco-action'] ) && 'subs-payment-changed' === $_GET['kco-action'] ) ) {
			$order_id = wc_get_order_id_by_order_key( sanitize_key( $_GET['key'] ) );
			$order    = wc_get_order( $order_id );

			// Add recurring token to order via Checkout API.
			$response_data = KCO_WC()->api->request_pre_get_order( $klarna_order_id );
			if ( ! is_wp_error( $response_data ) && ( $response_data['response']['code'] >= 200 && $response_data['response']['code'] <= 299 ) ) {
				$klarna_order_data = json_decode( $response_data['body'] );
				if ( isset( $klarna_order_data->recurring_token ) && ! empty( $klarna_order_data->recurring_token ) ) {
					update_post_meta( $order->get_id(), '_kco_recurring_token', sanitize_key( $klarna_order_data->recurring_token ) );
					$note = sprintf( __( 'Payment method changed via Klarna Checkout. New recurring token for subscription: %s', 'klarna-checkout-for-woocommerce' ), sanitize_key( $klarna_order_data->recurring_token ) );
					$order->add_order_note( $note );
				}
			} else {
				// Retrieve error.
				$error = KCO_WC()->api->extract_error_messages( $response_data );
				KCO_WC()->logger->log( 'ERROR when requesting Klarna order via Checkout API (' . stripslashes_deep( json_encode( $error ) ) . ') ' . stripslashes_deep( json_encode( $response_data ) ) );
				$note = sprintf( __( 'Could not retrieve new Klarna recurring token for subscription when customer changed payment method. Read the log for detailed information.', 'klarna-checkout-for-woocommerce' ), sanitize_key( $klarna_order_data->recurring_token ) );
				$order->add_order_note( $note );
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
			exit;
		}
	}

	/**
	 * Display thankyou notice when customer is redirected back to the
	 * subscription page in front-end after changing payment method.
	 *
	 * @return void
	 */
	public function display_thankyou_message_for_payment_method_change() {
		if ( isset( $_GET['kco-action'] ) && 'subs-payment-changed' === $_GET['kco-action'] ) {
			wc_add_notice( __( 'Thank you, your subscription payment method is now updated.', 'klarna-checkout-for-woocommerce' ), 'success' );
			KCO_WC()->api->maybe_clear_session_values();
		}
	}
}
new Klarna_Checkout_Subscription();

