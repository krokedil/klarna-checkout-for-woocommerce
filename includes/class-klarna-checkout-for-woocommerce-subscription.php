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
		add_action( 'woocommerce_thankyou_kco', array( $this, 'set_recurring_token_for_order' ) );
		add_action( 'woocommerce_scheduled_subscription_payment_kco', array( $this, 'trigger_scheduled_payment' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'show_recurring_token' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_kco_recurring_token_update' ), 45, 2 );
	}

	/**
	 * Checks the cart if it has a subscription product in it.
	 *
	 * @return bool
	 */
	public function check_if_subscription() {
		if ( class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) {
			$subscription_product_id = false;
			if ( ! empty( WC()->cart->cart_contents ) ) {
				foreach ( WC()->cart->cart_contents as $cart_item ) {
					if ( WC_Subscriptions_Product::is_subscription( $cart_item['product_id'] ) ) {
						$subscription_product_id = $cart_item['product_id'];
						return true;
					}
				}
			}
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
		if ( $this->check_if_subscription() ) {
			$request_args['recurring'] = true;
		}
		return $request_args;
	}

	/**
	 * Sets the recurring token for the subscription order
	 *
	 * @return void
	 */
	public function set_recurring_token_for_order( $order_id = null ) {
		$wc_order = wc_get_order( $order_id );
		if ( class_exists( 'WC_Subscription' ) && wcs_order_contains_subscription( $wc_order ) ) {
			$subcriptions = wcs_get_subscriptions_for_order( $order_id );
			$klarna_order = KCO_WC()->api->get_order();
			if ( isset( $klarna_order->recurring_token ) ) {
				$recurring_token = $klarna_order->recurring_token;
				foreach ( $subcriptions as $subcription ) {
					update_post_meta( $subcription->get_id(), '_kco_recurring_token', $recurring_token );
				}
				update_post_meta( $order_id, '_kco_recurring_token', $recurring_token );
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
		reset( $subscriptions );
		$subscription_id = key( $subscriptions );

		$recurring_token = get_post_meta( $order_id, '_kco_recurring_token', true );
		if ( empty( $recurring_token ) ) {
			$recurring_token = get_post_meta( WC_Subscriptions_Renewal_Order::get_parent_order_id( $order_id ), '_kco_recurring_token', true );
			update_post_meta( $order_id, '_kco_recurring_token', $recurring_token );
		}

		$create_order_response = new Klarna_Checkout_For_WooCommerce_API();
		$create_order_response = $create_order_response->request_create_recurring_order( $renewal_order, $recurring_token );
		if ( 200 === $create_order_response['response']['code'] ) {
			$klarna_order_id = json_decode( $create_order_response['body'] )->order_id;
			WC_Subscriptions_Manager::process_subscription_payments_on_order( $renewal_order );
			$renewal_order->add_order_note( sprintf( __( 'Subscription payment made with Klarna. Klarna order id: %s', 'klarna-checkout-for-woocommerce' ), $klarna_order_id ) );
			$renewal_order->payment_complete( $klarna_order_id );
		} else {
			WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $renewal_order );
			$renewal_order->add_order_note( sprintf( __( 'Subscription payment failed with Klarna. Error code: %1$s. Message: %2$s', 'klarna-checkout-for-woocommerce' ), $create_order_response['response']['code'], $create_order_response['response']['message'] ) );
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
}
new Klarna_Checkout_Subscription();

