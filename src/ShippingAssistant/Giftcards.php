<?php
namespace Krokedil\KustomCheckout\ShippingAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Giftcards class.
 *
 * Lets a gift card or store credit pay for TMS-controlled shipping. The balance travels with the order line,
 * so the shipping option update callback can decide how much of it the selected shipping option uses.
 */
class Giftcards {
	/**
	 * The key in the order line's merchant data that holds the gift card balance in minor units.
	 */
	const BALANCE_KEY = 'ksa_giftcard_balance';

	/**
	 * The key in the adjustment line's merchant data that holds how much of it stands in for shipping, in minor units.
	 */
	const SHIPPING_STAND_IN_KEY = 'ksa_shipping_stand_in';

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'kco_wc_gift_card_line_item', array( $this, 'add_balance_to_order_line' ), 10, 2 );
		add_filter( 'kco_wc_allow_negative_cart_order_amount', array( $this, 'maybe_allow_negative_order_amount' ) );
		add_filter( 'kco_wc_api_request_args', array( $this, 'mark_shipping_stand_in' ) );
		add_filter( 'yith_ywgc_detract_residue_to_shipping_total', array( $this, 'maybe_keep_yith_shipping_total' ) );
	}

	/**
	 * Add the gift card balance to the order line sent to Kustom.
	 *
	 * @param array  $order_line The gift card order line formatted for Kustom.
	 * @param object $giftcard   The gift card line from the gift card compatibility.
	 *
	 * @return array
	 */
	public function add_balance_to_order_line( $order_line, $giftcard ) {
		$balance = self::get_balance( $giftcard );
		if ( null === $balance ) {
			return $order_line;
		}

		$order_line['merchant_data'] = wp_json_encode( array( self::BALANCE_KEY => $balance ) );

		return $order_line;
	}

	/**
	 * Mark how much of the adjustment line is shipping the gift card already paid for in WooCommerce.
	 *
	 * The rest of that line is a real rounding difference, which the callback has to keep.
	 *
	 * @param array $request_args The request args for Kustom Checkout.
	 *
	 * @return array
	 */
	public function mark_shipping_stand_in( $request_args ) {
		if ( empty( $request_args['order_lines'] ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return $request_args;
		}

		// With a negative amount allowed, nothing was clamped and no stand-in was added.
		if ( \KCO_Request_Cart::is_negative_order_amount_allowed() ) {
			return $request_args;
		}

		$cart_helper = new \KCO_Request_Cart();
		$stand_in    = $cart_helper->get_shipping_amount() - intval( round( floatval( WC()->cart->get_total( 'edit' ) ) * 100 ) );
		if ( $stand_in <= 0 ) {
			return $request_args;
		}

		foreach ( $request_args['order_lines'] as $key => $order_line ) {
			if ( 'added-surcharge' === ( $order_line['reference'] ?? '' ) ) {
				$request_args['order_lines'][ $key ]['merchant_data'] = wp_json_encode( array( self::SHIPPING_STAND_IN_KEY => $stand_in ) );
			}
		}

		return $request_args;
	}

	/**
	 * Let the cart amount go below zero while the selected shipping option is added back to the request.
	 *
	 * @param bool $allow Whether a negative amount is allowed.
	 *
	 * @return bool
	 */
	public function maybe_allow_negative_order_amount( $allow ) {
		if ( ! WC()->session ) {
			return $allow;
		}

		$kco_order_id = WC()->session->get( 'kco_wc_order_id' );
		if ( empty( $kco_order_id ) ) {
			return $allow;
		}

		// RequestModifier adds the shipping line and its price back when this exists.
		return $allow || ! empty( get_transient( "kss_override_data_$kco_order_id" ) );
	}

	/**
	 * Stop YITH Gift Cards from replacing the shipping total with the amount left to pay.
	 *
	 * @param bool $detract Whether YITH changes the shipping total.
	 *
	 * @return bool
	 */
	public function maybe_keep_yith_shipping_total( $detract ) {
		if ( WC()->session && WC()->session->get( 'kco_kss_enabled' ) ) {
			return false;
		}

		return $detract;
	}

	/**
	 * Whether the cart has a gift card or store credit that can pay for shipping.
	 *
	 * @return bool
	 */
	public static function cart_has_giftcard() {
		foreach ( KCO_WC()->krokedil->compatibility()->giftcards() as $compatibility ) {
			// The WooCommerce Gift Cards compatibility can not run without the plugin.
			if ( false !== strpos( get_class( $compatibility ), 'WCGiftCards' ) && ! function_exists( 'WC_GC' ) ) {
				continue;
			}

			foreach ( $compatibility->get_cart_giftcards() as $giftcard ) {
				if ( null !== self::get_balance( $giftcard ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Recalculate how much each gift card pays, now that the order lines include the shipping line.
	 *
	 * @param array $order_lines The Kustom order lines, including the shipping line.
	 *
	 * @return array The order lines with the gift card amounts updated.
	 */
	public static function apply_to_order_lines( $order_lines ) {
		$balances = array();
		$due      = 0;

		foreach ( $order_lines as $key => $order_line ) {
			$balance = self::get_order_line_balance( $order_line );
			if ( null === $balance ) {
				$due += intval( $order_line['total_amount'] ?? 0 );
				continue;
			}

			$balances[ $key ] = $balance;
		}

		if ( empty( $balances ) ) {
			return $order_lines;
		}

		// Part of the adjustment line stood in for shipping, which the shipping line now carries. Any rounding difference stays.
		foreach ( $order_lines as $key => $order_line ) {
			$stand_in = self::get_merchant_data_amount( $order_line, self::SHIPPING_STAND_IN_KEY );
			if ( null === $stand_in ) {
				continue;
			}

			$due      -= $stand_in;
			$remaining = intval( $order_line['total_amount'] ?? 0 ) - $stand_in;
			if ( 0 === $remaining ) {
				unset( $order_lines[ $key ] );
				continue;
			}

			$order_lines[ $key ]['unit_price']   = $remaining;
			$order_lines[ $key ]['total_amount'] = $remaining;
			unset( $order_lines[ $key ]['merchant_data'] );
		}

		foreach ( $balances as $key => $balance ) {
			$applied = max( 0, min( $balance, $due ) );
			$due    -= $applied;

			$order_lines[ $key ]['unit_price']   = -$applied;
			$order_lines[ $key ]['total_amount'] = -$applied;
		}

		return array_values( $order_lines );
	}

	/**
	 * Get the gift card balance from a Kustom order line.
	 *
	 * @param array $order_line The Kustom order line.
	 *
	 * @return int|null The balance in minor units, or null if the line is not a gift card with a known balance.
	 */
	private static function get_order_line_balance( $order_line ) {
		return self::get_merchant_data_amount( $order_line, self::BALANCE_KEY );
	}

	/**
	 * Get an amount stored in a Kustom order line's merchant data.
	 *
	 * @param array  $order_line The Kustom order line.
	 * @param string $key        The merchant data key.
	 *
	 * @return int|null The amount in minor units, never negative, or null if the line does not have it.
	 */
	private static function get_merchant_data_amount( $order_line, $key ) {
		$merchant_data = $order_line['merchant_data'] ?? '';
		if ( ! is_string( $merchant_data ) || '' === $merchant_data ) {
			return null;
		}

		$merchant_data = json_decode( $merchant_data, true );
		if ( ! is_array( $merchant_data ) || ! is_numeric( $merchant_data[ $key ] ?? null ) ) {
			return null;
		}

		return max( 0, intval( $merchant_data[ $key ] ) );
	}

	/**
	 * Get the balance of a gift card line from the gift card compatibility.
	 *
	 * @param object $giftcard The gift card line.
	 *
	 * @return int|null The balance in minor units, or null if it is unknown.
	 */
	private static function get_balance( $giftcard ) {
		if ( ! is_callable( array( $giftcard, 'get_gift_card_balance' ) ) ) {
			return null;
		}

		$balance = $giftcard->get_gift_card_balance();

		return null === $balance ? null : intval( $balance );
	}
}
