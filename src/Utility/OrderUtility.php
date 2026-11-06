<?php
namespace Krokedil\KustomCheckout\Utility;

use Krokedil\KustomCheckout\InPersonPayments\Gateway as InPersonPaymentsGateway;

/**
 * Utility class for helper functions related to which orders the plugin manages.
 */
class OrderUtility {

	/**
	 * The payment methods whose orders are Kustom orders.
	 *
	 * An in-person payment leaves an ordinary Kustom order behind, so order management
	 * handles it alongside a checkout order. The list is not conditional on the feature
	 * being switched on: an order taken at the counter stays refundable afterwards.
	 *
	 * @return array
	 */
	public static function payment_methods() {
		/**
		 * The payment method ids whose orders Kustom order management captures,
		 * cancels, updates and refunds.
		 *
		 * @since 2.22.0
		 *
		 * @param string[] $payment_methods The payment method ids.
		 */
		return apply_filters( 'kco_managed_payment_methods', array( 'kco', InPersonPaymentsGateway::ID ) );
	}

	/**
	 * Whether an order was paid with one of them.
	 *
	 * @param \WC_Abstract_Order|false|null $order The WooCommerce order.
	 * @return bool
	 */
	public static function is_kustom_order( $order ) {
		if ( ! $order instanceof \WC_Abstract_Order ) {
			return false;
		}

		return in_array( $order->get_payment_method(), self::payment_methods(), true );
	}

	/**
	 * Whether the payment was collected on a Kustom POS device, and so captured by
	 * Kustom at the tap rather than by this plugin.
	 *
	 * @param \WC_Abstract_Order|false|null $order The WooCommerce order.
	 * @return bool
	 */
	public static function is_in_person_order( $order ) {
		return $order instanceof \WC_Abstract_Order && InPersonPaymentsGateway::ID === $order->get_payment_method();
	}
}
