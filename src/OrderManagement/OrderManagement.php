<?php

namespace Krokedil\KustomCheckout\OrderManagement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Klarna_Order_Management' ) ) {

	/**
	 * Class WC_Klarna_Order_Management
	 */
	class WC_Klarna_Order_Management {

		/**
		 * *Singleton* instance of this class
		 *
		 * @var $instance
		 */
		private static $instance;

		/**
		 * Klarna Order Management settings.
		 *
		 * @var WC_Klarna_Order_Management_Settings $settings
		 */
		public $settings;

		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @return self The *Singleton* instance.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Private clone method to prevent cloning of the instance of the
		 * *Singleton* instance.
		 *
		 * @return void
		 */
		private function __clone() {
		}

		/**
		 * Private unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		public function __wakeup() {
		}

		/**
		 * Protected constructor to prevent creating a new instance of the
		 * *Singleton* via the `new` operator from outside of this class.
		 */
		protected function __construct() {
			add_action( 'plugins_loaded', array( $this, 'init' ) );
			add_action( 'before_woocommerce_init', array( $this, 'declare_wc_compatibility' ) );

			// Add action links.
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
		}

		/**
		 * Init the plugin at plugins_loaded.
		 */
		public function init() {
			include_once KCO_WC_PLUGIN_PATH . '/classes/class-wc-klarna-order-management-settings.php';
			$this->settings = new WC_Klarna_Order_Management_Settings();

			// If Klarna Order Management is an unavailable feature, do not include the rest of the plugin.
			$kp_unavailable_feature_ids = get_option( 'kp_unavailable_feature_ids', array() );
			if ( in_array( 'kom', $kp_unavailable_feature_ids, true ) ) {
				return;
			}

			include_once KCO_WC_PLUGIN_PATH . '/includes/klarna-order-management-functions.php';

			include_once KCO_WC_PLUGIN_PATH . '/classes/class-wc-klarna-assets.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/class-wc-klarna-order-management-scheduled-actions.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/class-wc-klarna-sellers-app.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/class-wc-klarna-pending-orders.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/class-wc-klarna-meta-box.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/class-wc-klarna-order-management-order-lines.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/class-wc-klarna-logger.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/class-wc-klarna-refund-fee.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/request/class-kom-request.php';

			include_once KCO_WC_PLUGIN_PATH . '/classes/request/class-kom-request-get.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/request/get/class-kom-request-get-order.php';

			include_once KCO_WC_PLUGIN_PATH . '/classes/request/class-kom-request-patch.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/request/patch/class-kom-request-patch-update.php';

			include_once KCO_WC_PLUGIN_PATH . '/classes/request/class-kom-request-post.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/request/post/class-kom-request-post-cancel.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/request/post/class-kom-request-post-capture.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/request/post/class-kom-request-post-refund.php';

			// Add refunds support to Klarna Payments and Klarna Checkout gateways.
			add_action( 'wc_klarna_payments_supports', array( $this, 'add_gateway_support' ) );
			add_action( 'kco_wc_supports', array( $this, 'add_gateway_support' ) );

			// Cancel order.
			add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_klarna_order' ) );

			// Capture an order.
			add_action( 'woocommerce_order_status_completed', array( $this, 'capture_klarna_order' ) );

			// Update an order.
			add_action( 'woocommerce_saved_order_items', array( $this, 'update_klarna_order_items' ), 10, 2 );

			// Refund an order.
			add_filter( 'wc_klarna_payments_process_refund', array( $this, 'refund_klarna_order' ), 10, 4 );
			add_filter( 'wc_klarna_checkout_process_refund', array( $this, 'refund_klarna_order' ), 10, 4 );

			// Pending orders.
			add_action(
				'wc_klarna_notification_listener',
				array(
					'WC_Klarna_Pending_Orders',
					'notification_listener',
				),
				10,
				2
			);
		}


		/**
		 * Declare compatibility with WooCommerce features.
		 *
		 * @return void
		 */
		public function declare_wc_compatibility() {

			// Declare HPOS compatibility.
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}

		/**
		 * Adds plugin action link to Krokedil documentation for KOM.
		 *
		 * @param array $links Plugin action link before filtering.
		 *
		 * @return array Filtered links.
		 */
		public function plugin_action_links( $links ) {
			$plugin_links = array();

			if ( class_exists( 'KCO' ) ) {
				$plugin_links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=kco' ) . '">' . __( 'Settings (Klarna Checkout)', 'klarna-checkout-for-woocommerce' ) . '</a>';
			}

			if ( class_exists( 'WC_Klarna_Payments' ) ) {
				$plugin_links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=klarna_payments' ) . '">' . __( 'Settings (Klarna Payments)', 'klarna-checkout-for-woocommerce' ) . '</a>';
			}

			$plugin_links[] = '<a target="_blank" href="https://docs.krokedil.com/article/149-klarna-order-management">Docs</a>';

			return array_merge( $plugin_links, $links );
		}

		/**
		 * Add refunds support to Klarna Payments gateway.
		 *
		 * @param array $features Supported features.
		 *
		 * @return array $features Supported features.
		 */
		public function add_gateway_support( $features ) {
			$features[] = 'refunds';

			return $features;
		}

		/**
		 * Cancels a Klarna order.
		 *
		 * @param int  $order_id Order ID.
		 * @param bool $action If this was triggered through an action or not.
		 *
		 * @return bool|WP_Error Returns bool true if cancellation was successful or a WP_Error object if not.
		 */
		public function cancel_klarna_order( $order_id, $action = false ) {
			$options = self::get_instance()->settings->get_settings( $order_id );
			if ( ! isset( $options['kom_auto_cancel'] ) || 'yes' === $options['kom_auto_cancel'] || $action ) {
				$order = wc_get_order( $order_id );

				// The merchant has disconnected the order from the order manager.
				if ( $order->get_meta( '_kom_disconnect' ) ) {
					return new \WP_Error( 'order_sync_off', 'Order synchronization is disabled' );
				}

				// Check if the order has been paid.
				if ( empty( $order->get_date_paid() ) ) {
					return new \WP_Error( 'not_paid', 'Order has not been paid.' );
				}

				// Not going to do this for non-KP and non-KCO orders.
				if ( ! in_array( $order->get_payment_method(), array( 'klarna_payments', 'kco' ), true ) ) {
					return new \WP_Error( 'not_klarna_order', 'Order does not have klarna_payments or kco payment method.' );
				}

				// Don't do this if the order is being rejected in pending flow.
				if ( $order->get_meta( '_wc_klarna_pending_to_cancelled', true ) ) {
					return new \WP_Error( 'rejected_in_pending_flow', 'Order is being rejected in pending flow.' );
				}

				// Retrieve Klarna order first.
				$klarna_order = $this->retrieve_klarna_order( $order_id );

				if ( is_wp_error( $klarna_order ) ) {
					$order->add_order_note( 'Klarna order could not be cancelled due to an error.' );
					$order->save();

					return new \WP_Error( 'object_error', 'Klarna order object is of type WP_Error.', $klarna_order );
				}

				// Captured, part-captured and cancelled orders cannot be cancelled.
				if ( in_array( $klarna_order->status, array( 'CAPTURED', 'PART_CAPTURED' ), true ) ) {
					$order->add_order_note( 'The Klarna order cannot be cancelled due to it already being captured.' );
					$order->save();
					return new \WP_Error( 'already_captured', 'Klarna order is captured and must be refunded.' );
				} elseif ( 'CANCELLED' === $klarna_order->status ) {
					$order->add_order_note( 'Klarna order has already been cancelled.' );
					$order->save();
					return new \WP_Error( 'already_cancelled', 'Klarna order is already cancelled.' );
				} else {
					$request  = new KOM_Request_Post_Cancel( array( 'order_id' => $order_id ) );
					$response = $request->request();

					if ( ! is_wp_error( $response ) ) {
						$order->add_order_note( 'Klarna order cancelled.' );
						$order->update_meta_data( '_wc_klarna_cancelled', 'yes' );
						if ( $order->save() ) {
							return true;
						} else {
							return new \WP_Error( 'save_error', 'Could not save WooCommerce order object.' );
						}
					} else {
						$order->add_order_note( 'Could not cancel Klarna order. ' . $response->get_error_message() . '.' );
						$order->save();
						return new \WP_Error( 'unknown_error', 'Response object is of type WP_Error.', $response );
					}
				}
			}
		}

		/**
		 * Updates Klarna order items.
		 *
		 * @param int   $order_id Order ID.
		 * @param array $items Order items.
		 * @param bool  $action If this was triggered by an action.
		 *
		 * @return WP_Error|true Returns true if updating was successful or a WP_Error object if not.
		 */
		public function update_klarna_order_items( $order_id, $items, $action = false ) {
			$options = self::get_instance()->settings->get_settings( $order_id );
			$order   = wc_get_order( $order_id );

			if ( ! in_array( $order->get_payment_method(), array( 'klarna_payments', 'kco' ), true ) ) {
				return new \WP_Error( 'not_klarna_order', 'Order does not have klarna_payments or kco payment method.' );
			}

			// Are we on the subscription page?
			if ( 'shop_subscription' === $order->get_type() ) {
				$token_key = 'klarna_payments' === $order->get_payment_method() ? KP_Subscription::RECURRING_TOKEN : '_kco_recurring_token';

				// Did the customer update the subscription's recurring token?
				$recurring_token = wc_get_var( $items[ $token_key ] );
				$existing_token  = $order->get_meta( $token_key );
				if ( ! empty( $recurring_token ) && $existing_token !== $recurring_token ) {
					$order->update_meta_data( $token_key, $recurring_token );
					$order->add_order_note(
						sprintf(
						// translators: 1: User name, 2: Existing token, 3: New token.
							__( '%1$s updated the subscription recurring token from "%2$s" to "%3$s".', 'klarna-checkout-for-woocommerce' ),
							ucfirst( wp_get_current_user()->display_name ),
							$existing_token,
							$recurring_token
						)
					);
					$order->save();

					// If the recurring token was changed, we can assume the merchant didn't update the subscription as that would require a recurring token which as has now been modified, but not yet saved.
					return true;
				}
			}

			if ( ! isset( $options['kom_auto_update'] ) || 'yes' === $options['kom_auto_update'] || $action ) {

				// The merchant has disconnected the order from the order manager.
				if ( $order->get_meta( '_kom_disconnect' ) ) {
					return new \WP_Error( 'order_sync_off', 'Order synchronization is disabled' );
				}

				// Check if the order has been paid.
				if ( empty( $order->get_date_paid() ) ) {
					return new \WP_Error( 'not_paid', 'Order has not been paid.' );
				}

				// Changes are only possible if order is an allowed order status.
				if ( ! in_array( $order->get_status(), apply_filters( 'kom_allowed_update_statuses', array( 'on-hold' ) ), true ) ) {
					return new \WP_Error( 'not_allowed_status', 'Order is not in allowed status.' );
				}

				// Retrieve Klarna order first.
				$klarna_order = $this->retrieve_klarna_order( $order_id );
				if ( is_wp_error( $klarna_order ) ) {
					$order->add_order_note( 'Klarna order could not be updated due to an error.' );
					$order->save();

					return new \WP_Error( 'object_error', 'Klarna order object is of type WP_Error.', $klarna_order );
				}

				if ( ! in_array( $klarna_order->status, array( 'CANCELLED', 'CAPTURED', 'PART_CAPTURED' ), true ) ) {
					$request  = new KOM_Request_Patch_Update(
						array(
							'request'      => 'update_order_lines',
							'order_id'     => $order_id,
							'klarna_order' => $klarna_order,
						)
					);
					$response = $request->request();
					if ( ! is_wp_error( $response ) ) {
						$order->add_order_note( 'Klarna order updated.' );
						$order->save();
					} else {
						$reason = $response->get_error_message();
						if ( ! empty( $reason ) ) {
							// translators: %s: error message from Klarna.
							$order_note = sprintf( __( 'Could not update Klarna order lines: %s.', 'klarna-checkout-for-woocommerce' ), $reason );
						} else {
							$order_note = __( 'Could not update Klarna order lines. An unknown error occurred.', 'klarna-checkout-for-woocommerce' );
						}

						$order->add_order_note( $order_note );
						$order->save();
						return new \WP_Error( 'unknown_error', 'Response object is of type WP_Error.', $response );
					}
				}
			}

			return true;
		}

		/**
		 * Captures a Klarna order.
		 *
		 * @param int  $order_id Order ID.
		 * @param bool $action If this was triggered by an action.
		 *
		 * @return bool|WP_Error Returns bool true if capture was successful or a WP_Error object if not.
		 */
		public function capture_klarna_order( $order_id, $action = false ) {
			$options = self::get_instance()->settings->get_settings( $order_id );
			$order   = wc_get_order( $order_id );

			if ( ! isset( $options['kom_auto_capture'] ) || 'yes' === $options['kom_auto_capture'] || $action ) {

				// The merchant has disconnected the order from the order manager.
				if ( $order->get_meta( '_kom_disconnect' ) ) {
					return new \WP_Error( 'order_sync_off', 'Order synchronization is disabled' );
				}

					// Check if the order has been paid.
				if ( empty( $order->get_date_paid() ) ) {
					return new \WP_Error( 'not_paid', 'Order has not been paid.' );
				}

				// Not going to do this for non-KP and non-KCO orders.
				if ( ! in_array( $order->get_payment_method(), array( 'klarna_payments', 'kco' ), true ) ) {
					return new \WP_Error( 'not_klarna_order', 'Order does not have klarna_payments or kco payment method.' );
				}
				// Do nothing if Klarna order was already captured.
				if ( $order->get_meta( '_wc_klarna_capture_id', true ) ) {
					$order->add_order_note( 'Klarna order has already been captured.' );
					$order->save();

					return new \WP_Error( 'already_captured', 'Order has already been captured.' );
				}
				// Do nothing if we don't have Klarna order ID.
				if ( ! $order->get_meta( '_wc_klarna_order_id', true ) && ! $order->get_transaction_id() ) {
					$order->update_status( 'on-hold', 'Klarna order ID is missing, Klarna order could not be captured at this time.' );
					return new \WP_Error( 'klarna_id_missing', 'Klarna order id is missing for order.' );
				}
				// Retrieve Klarna order.
				$klarna_order = $this->retrieve_klarna_order( $order_id );

				if ( is_wp_error( $klarna_order ) ) {
					$order->update_status( 'on-hold', 'Klarna order could not be captured due to an error.' );
					return new \WP_Error( 'object_error', 'Klarna order object is of type WP_Error.', $klarna_order );
				}
				// Check if order is pending review.
				if ( 'PENDING' === $klarna_order->fraud_status ) {
					$order->update_status( 'on-hold', 'Klarna order is pending review and could not be captured at this time.' );
					return new \WP_Error( 'pending_fraud_review', 'Order is pending fraud review and cannot be captured.' );
				}
				// Check if Klarna order has already been captured.
				if ( in_array( $klarna_order->status, array( 'CAPTURED' ), true ) ) {
					$order->add_order_note( 'Klarna order has already been captured on ' . $klarna_order->captures[0]->captured_at );
					$order->update_meta_data( '_wc_klarna_capture_id', $klarna_order->captures[0]->capture_id );
					$order->save();
					return new \WP_Error( 'already_captured', 'Order has already been captured.' );
				}
				// Check if Klarna order has already been canceled.
				if ( 'CANCELLED' === $klarna_order->status ) {
					$order->add_order_note( 'Klarna order failed to capture, the order has already been canceled' );
					$order->save();

					return new \WP_Error( 'klarna_order_cancelled', 'Order is cancelled. Capture failed.' );
				}
				// Only send capture request if Klarna order fraud status is accepted.
				if ( 'ACCEPTED' !== $klarna_order->fraud_status ) {
					$order->add_order_note( 'Klarna order could not be captured at this time.' );
					$order->save();

					return new \WP_Error( 'pending_fraud_review', 'Order is pending fraud review and cannot be captured.' );
				} else {
					$request  = new KOM_Request_Post_Capture(
						array(
							'request'      => 'capture',
							'order_id'     => $order_id,
							'klarna_order' => $klarna_order,
						)
					);
					$response = $request->request();

					if ( ! is_wp_error( $response ) ) {
						$order->add_order_note( 'Klarna order captured. Capture amount: ' . $order->get_formatted_order_total( '', false ) . '. Capture ID: ' . $response );
						$order->update_meta_data( '_wc_klarna_capture_id', $response );
						$order->save();
						return true;
					} else {

						/* The suggested approach by Klarna is to try again after some time. If that still fails, the merchant should inform the customer, and ask them to either "create a new subscription or add funds to their payment method if they wish to continue." */
						if ( isset( $response->get_error_data()['code'] ) && 403 === $response->get_error_data()['code'] && 'PAYMENT_METHOD_FAILED' === $response->get_error_code() ) {
							$order->update_status( 'on-hold', __( 'Klarna could not charge the customer. Please try again later. If that still fails, the customer may have to create a new subscription or add funds to their payment method if they wish to continue.', 'klarna-checkout-for-woocommerce' ) );
							return new \WP_Error( 'capture_failed', 'Capture failed. Please try again later.' );
						} else {
							$error_message = $response->get_error_message();

							if ( ! is_array( $error_message ) && false !== strpos( $error_message, 'Captured amount is higher than the remaining authorized amount.' ) ) {
								$error_message = str_replace( '. Capture not possible.', sprintf( ': %s %s.', $klarna_order->remaining_authorized_amount / 100, $klarna_order->purchase_currency ), $error_message );
							}

							// translators: %s: Error message from Klarna.
							$order->update_status( 'on-hold', sprintf( __( 'Could not capture Klarna order. %s', 'klarna-checkout-for-woocommerce' ), $error_message ) );
							return new \WP_Error( 'capture_failed', 'Capture failed.', $error_message );
						}
					}
					if ( $order->save() ) {
						return true;
					} else {
						return new \WP_Error( 'save_error', 'Could not save WooCommerce order object.' );
					}
				}
			}
		}

		/**
		 * Refund a Klarna order.
		 *
		 * @param bool        $result Refund attempt result.
		 * @param int         $order_id WooCommerce order ID.
		 * @param null|string $amount Refund amount, full order amount if null.
		 * @param string      $reason Refund reason.
		 *
		 * @return bool|WP_Error Returns bool true if refund was successful or a WP_Error object if not.
		 */
		public function refund_klarna_order( $result, $order_id, $amount = null, $reason = '' ) {
			$order = wc_get_order( $order_id );
			// The merchant has disconnected the order from the order manager.
			if ( $order->get_meta( '_kom_disconnect' ) ) {
				return new \WP_Error( 'order_sync_off', 'Order synchronization is disabled' );
			}

			// Not going to do this for non-KP and non-KCO orders.
			if ( ! in_array( $order->get_payment_method(), array( 'klarna_payments', 'kco' ), true ) ) {
				return new \WP_Error( 'not_klarna_order', 'Order does not have klarna_payments or kco payment method.' );
			}

			// Do nothing if Klarna order is not captured.
			if ( ! $order->get_meta( '_wc_klarna_capture_id', true ) ) {
				$order->add_order_note( 'Klarna order has not been captured and cannot be refunded.' );
				$order->save();

				return new \WP_Error( 'not_captured', 'Order has not been captured and cannot be refunded.' );
			}

			// Retrieve Klarna order first.
			$klarna_order = $this->retrieve_klarna_order( $order_id );

			if ( is_wp_error( $klarna_order ) ) {
				$order->add_order_note( 'Could not refund Klarna order. ' . $klarna_order->get_error_message() . '.' );
				$order->save();

				return new \WP_Error( 'object_error', 'Klarna order object is of type WP_Error.', $klarna_order );
			}

			// Get the refund order ID.
			$refund_order_id = $order->get_refunds()[0]->get_id();
			$refund_order    = wc_get_order( $refund_order_id );

			// Check that the refund order is valid.
			if ( ! $refund_order ) {
				$order->add_order_note( 'Could not retrieve the refund order.' );
				$order->save();
				return new \WP_Error( 'invalid_refund_order', 'Refund order is not valid.' );
			}

			// If the order is not captured, we cannot refund it.
			if ( in_array( $klarna_order->status, array( 'CAPTURED', 'PART_CAPTURED' ), true ) ) {
				$return_fee   = $this->get_return_fee_from_post();
				$request_args = array(
					'order_id'      => $order_id,
					'refund_amount' => $amount,
					'refund_reason' => $reason,
					'refund_id'     => $refund_order_id,
				);

				if ( ! empty( $return_fee['amount'] ) && 0 < floatval( $return_fee['amount'] ) ) {
					$request_args['return_fee'] = $return_fee;
				}

				$request  = new KOM_Request_Post_Refund( $request_args );
				$response = $request->request();

				if ( is_wp_error( $response ) ) {
					$order->add_order_note( 'Could not refund Klarna order. ' . $response->get_error_message() . '.' );
					$order->save();
					return new \WP_Error( 'unknown_error', 'Response object is of type WP_Error.', $response );
				}

				$applied_return_fees = apply_filters( 'klarna_applied_return_fees', array() );

				// translators: refund amount, refund id.
				$text = __( 'Processing a refund of %1$s with Klarna', 'klarna-checkout-for-woocommerce' );
				if ( ! empty( floatval( $applied_return_fees['amount'] ?? 0 ) ) ) {
					$total_return_fee_amount     = $applied_return_fees['amount'] ?? 0;
					$total_return_fee_tax_amount = $applied_return_fees['tax_amount'] ?? 0;
					$total_return_fees           = $total_return_fee_amount + $total_return_fee_tax_amount;
					$original_amount             = wc_price( $amount + $total_return_fees, array( 'currency' => $order->get_currency() ) );

					$formatted_total_return_fees = wc_price( $total_return_fees, array( 'currency' => $order->get_currency() ) );

					// translators: 1: original amount, 2: return fee amount.
					$extra_text = sprintf( __( ' (original amount of %1$s - return fee of %2$s)', 'klarna-checkout-for-woocommerce' ), $original_amount, $formatted_total_return_fees );
					$text      .= $extra_text;
				}

				$formatted_text = sprintf( $text, wc_price( $amount, array( 'currency' => $order->get_currency() ) ) );
				$order->add_order_note( $formatted_text . '.' );

				return true;

			}
		}

		/**
		 * Retrieve a Klarna order.
		 *
		 * @param int $order_id WooCommerce order ID.
		 *
		 * @return object $klarna_order Klarna Order.
		 */
		public function retrieve_klarna_order( $order_id ) {
			$request      = new KOM_Request_Get_Order(
				array(
					'order_id' => $order_id,
				)
			);
			$klarna_order = $request->request();

			return $klarna_order;
		}

		/**
		 * Get the return fee from the posted data.
		 *
		 * @return array
		 */
		public static function get_return_fee_from_post() {
			$return_fee = array(
				'amount'      => 0,
				'tax_amount'  => 0,
				'tax_rate_id' => 0,
			);

			$line_item_totals_json     = filter_input( INPUT_POST, 'line_item_totals', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$line_item_tax_totals_json = filter_input( INPUT_POST, 'line_item_tax_totals', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

			$line_item_totals     = json_decode( htmlspecialchars_decode( $line_item_totals_json ), true ) ?? array();
			$line_item_tax_totals = json_decode( htmlspecialchars_decode( $line_item_tax_totals_json ), true ) ?? array();

			foreach ( $line_item_totals as $key => $total ) {
				if ( 'klarna_return_fee' === $key ) {
					$return_fee['amount'] = str_replace( ',', '.', $total );
				}
			}

			foreach ( $line_item_tax_totals as $key => $tax_line ) {
				if ( 'klarna_return_fee' === $key ) {
					// Get the rate id from the tax by the first key in the line.
					$tax_rate_id               = array_keys( $tax_line )[0];
					$return_fee['tax_rate_id'] = $tax_rate_id;
					$return_fee['tax_amount']  = str_replace( ',', '.', $tax_line[ $tax_rate_id ] );
				}
			}

			return $return_fee;
		}
	}

	WC_Klarna_Order_Management::get_instance();

}
