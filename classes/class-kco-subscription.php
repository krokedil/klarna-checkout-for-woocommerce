<?php
/**
 * Subscription handler.
 *
 * @package Klarna_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
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
class KCO_Subscription {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'kco_wc_api_request_args', array( $this, 'create_extra_merchant_data' ) );
		add_filter( 'kco_wc_api_request_args', array( $this, 'set_recurring' ) );
		add_action( 'kco_wc_payment_complete', array( $this, 'set_recurring_token_for_order' ), 10, 2 );
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
		$key        = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_STRING );
		$kco_action = filter_input( INPUT_GET, 'kco-action', FILTER_SANITIZE_STRING );

		if ( ! empty( $key ) && ( ! empty( $kco_action ) && 'change-subs-payment' === $kco_action ) ) {
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
					$end_time = date( 'Y-m-d\TH:i', strtotime( $subscription_expiration_time ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions -- Date is not used for display.
				} else {
					$end_time = date( 'Y-m-d\TH:i', strtotime( '+50 year' ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions -- Date is not used for display.
				}

				$emd_subscription = array(
					'subscription_name'            => 'Subscription: ' . get_the_title( $subscription_product_id ),
					'start_time'                   => date( 'Y-m-d\TH:i' ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions -- Date is not used for display.
					'end_time'                     => $end_time,
					'auto_renewal_of_subscription' => false,
				);

				if ( is_user_logged_in() ) {
					// User is logged in - add user_login as unique_account_identifier.
					$current_user = wp_get_current_user();

					$emd_account = array(
						'unique_account_identifier' => $current_user->user_login,
						'account_registration_date' => date( 'Y-m-d\TH:i', strtotime( $current_user->user_registered ) ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions -- Date is not used for display.
						'account_last_modified'     => date( 'Y-m-d\TH:i' ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions -- Date is not used for display.
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
				$request_args['attachment']['body']         = wp_json_encode( $emd );
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
			$key      = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_STRING );
			$order_id = wc_get_order_id_by_order_key( $key );
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
						$request_args['order_lines']      = $order_lines;
						$request_args['order_tax_amount'] = 0;
						$request_args['order_amount']     = 0;

						// Modify merchant url's.
						global $wp;
						$query_string     = filter_input( INPUT_SERVER, 'QUERY_STRING', FILTER_SANITIZE_URL );
						$query_string     = $query_string;
						$current_url      = add_query_arg( $query_string, '', home_url( $wp->request ) );
						$confirmation_url = add_query_arg( 'kco-action', 'subs-payment-changed', $wc_order->get_view_order_url() );
						$push_url         = add_query_arg(
							array(
								'kco-action' => 'subs-payment-changed',
								'key'        => sanitize_key( $order_id ),
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
	 * @param int   $order_id The WooCommerce order id.
	 * @param array $klarna_order The Klarna order.
	 * @return void
	 */
	public function set_recurring_token_for_order( $order_id = null, $klarna_order ) {
		$wc_order = wc_get_order( $order_id );
		if ( class_exists( 'WC_Subscription' ) && ( wcs_order_contains_subscription( $wc_order, array( 'parent', 'renewal', 'resubscribe', 'switch' ) ) || wcs_is_subscription( $wc_order ) ) ) {
			$subcriptions    = wcs_get_subscriptions_for_order( $order_id );
			$klarna_order_id = $wc_order->get_transaction_id();
			$klarna_order    = KCO_WC()->api->get_klarna_order( $klarna_order_id );
			if ( isset( $klarna_order['recurring_token'] ) ) {
				$recurring_token = $klarna_order['recurring_token'];
				foreach ( $subcriptions as $subcription ) {
					update_post_meta( $subcription->get_id(), '_kco_recurring_token', $recurring_token );
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

		$subscriptions   = wcs_get_subscriptions_for_renewal_order( $renewal_order->get_id() );
		$recurring_token = get_post_meta( $order_id, '_kco_recurring_token', true );

		if ( empty( $recurring_token ) ) {
			// Try getting it from parent order.
			$recurring_token = get_post_meta( WC_Subscriptions_Renewal_Order::get_parent_order_id( $order_id ), '_kco_recurring_token', true );
			update_post_meta( $order_id, '_kco_recurring_token', $recurring_token );
		}

		if ( empty( $recurring_token ) ) {
			// Try getting it from _klarna_recurring_token (the old Klarna plugin).
			$recurring_token = get_post_meta( $order_id, '_klarna_recurring_token', true );
			if ( ! empty( $recurring_token ) ) {
				update_post_meta( $order_id, '_kco_recurring_token', $recurring_token );
			}
		}

		$create_order_response = KCO_WC()->api->create_recurring_order( $order_id, $recurring_token );
		if ( ! is_wp_error( $create_order_response ) ) {
			$klarna_order_id = $create_order_response['order_id'];
			// Translators: Klarna order id.
			$renewal_order->add_order_note( sprintf( __( 'Subscription payment made with Klarna. Klarna order id: %s', 'klarna-checkout-for-woocommerce' ), $klarna_order_id ) );
			foreach ( $subscriptions as $subscription ) {
				$subscription->payment_complete( $klarna_order_id );
			}
		} else {
			$error_message = $create_order_response->get_error_message();
			// Translators: Error message.
			$renewal_order->add_order_note( sprintf( __( 'Subscription payment failed with Klarna. Message: %1$s', 'klarna-checkout-for-woocommerce' ), $error_message ) );
			foreach ( $subscriptions as $subscription ) {
				$subscription->payment_failed();
			}
		}
	}

	/**
	 * Shows the recurring token for the order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @return void
	 */
	public function show_recurring_token( $order ) {
		if ( 'shop_subscription' === $order->get_type() && get_post_meta( $order->get_id(), '_kco_recurring_token' ) ) {
			?>
			<div class="order_data_column" style="clear:both; float:none; width:100%;">
				<div class="address">
					<p>
						<strong><?php echo esc_html( 'Klarna recurring token' ); ?>:</strong><?php echo esc_html( get_post_meta( $order->get_id(), '_kco_recurring_token', true ) ); ?>
					</p>
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

	/**
	 * Saves the recurring token.
	 *
	 * @param int     $post_id WordPress post id.
	 * @param WP_Post $post The WordPress post.
	 * @return void
	 */
	public function save_kco_recurring_token_update( $post_id, $post ) {
		$klarna_recurring_token = filter_input( INPUT_POST, '_kco_recurring_token', FILTER_SANITIZE_STRING );
		$order                  = wc_get_order( $post_id );
		if ( 'shop_subscription' === $order->get_type() && get_post_meta( $post_id, '_kco_recurring_token' ) ) {
			update_post_meta( $post_id, '_kco_recurring_token', $klarna_recurring_token );
		}

	}

	/**
	 * Handle pus callback from Klarna if this is a KCO subscription payment method change.
	 *
	 * @param string $klarna_order_id The order id for the Klarna order.
	 * @return void
	 */
	public function handle_push_cb_for_payment_method_change( $klarna_order_id ) {
		$key        = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_STRING );
		$kco_action = filter_input( INPUT_GET, 'kco-action', FILTER_SANITIZE_STRING );
		if ( ! empty( $key ) && ( ! empty( $kco_action ) && 'subs-payment-changed' === $kco_action ) ) {
			$order_id = wc_get_order_id_by_order_key( sanitize_key( $key ) );
			$order    = wc_get_order( $order_id );

			// Add recurring token to order via Checkout API.
			$response_data = KCO_WC()->api->request_pre_get_order( $klarna_order_id );
			if ( ! is_wp_error( $response_data ) && ( $response_data['response']['code'] >= 200 && $response_data['response']['code'] <= 299 ) ) {
				$klarna_order_data = json_decode( $response_data['body'] );
				if ( isset( $klarna_order_data->recurring_token ) && ! empty( $klarna_order_data->recurring_token ) ) {
					update_post_meta( $order->get_id(), '_kco_recurring_token', sanitize_key( $klarna_order_data->recurring_token ) );
					// translators: %s Klarna recurring token.
					$note = sprintf( __( 'Payment method changed via Klarna Checkout. New recurring token for subscription: %s', 'klarna-checkout-for-woocommerce' ), sanitize_key( $klarna_order_data->recurring_token ) );
					$order->add_order_note( $note );
				}
			} else {
				// Retrieve error.
				$note = sprintf( __( 'Could not retrieve new Klarna recurring token for subscription when customer changed payment method. Read the log for detailed information.', 'klarna-checkout-for-woocommerce' ), sanitize_key( $klarna_order_data->recurring_token ) );
				$order->add_order_note( $note );
			}

			// Acknowledge order in Klarna.
			KCO_WC()->api->acknowledge_klarna_order( $klarna_order_id );
			KCO_WC()->api->set_merchant_reference( $klarna_order_id, $order_id );

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
		$kco_action = filter_input( INPUT_GET, 'kco-action', FILTER_SANITIZE_STRING );
		if ( ! empty( $kco_action ) && 'subs-payment-changed' === $kco_action ) {
			wc_add_notice( __( 'Thank you, your subscription payment method is now updated.', 'klarna-checkout-for-woocommerce' ), 'success' );
			kco_unset_sessions();
		}
	}
}
new KCO_Subscription();

