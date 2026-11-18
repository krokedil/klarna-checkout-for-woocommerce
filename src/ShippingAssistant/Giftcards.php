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
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'kco_wc_gift_card_line_item', array( $this, 'add_balance_to_order_line' ), 10, 2 );
		add_filter( 'kco_wc_allow_negative_cart_order_amount', array( $this, 'maybe_allow_negative_order_amount' ) );
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

		// The plugin's own adjustment line stood in for shipping the gift card could not cover yet, so it is not a real charge.
		foreach ( $order_lines as $key => $order_line ) {
			if ( 'added-surcharge' === ( $order_line['reference'] ?? '' ) ) {
				$due -= intval( $order_line['total_amount'] ?? 0 );
				unset( $order_lines[ $key ] );
			}
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
		$merchant_data = $order_line['merchant_data'] ?? '';
		if ( ! is_string( $merchant_data ) || '' === $merchant_data ) {
			return null;
		}

		$merchant_data = json_decode( $merchant_data, true );
		if ( ! is_array( $merchant_data ) || ! is_numeric( $merchant_data[ self::BALANCE_KEY ] ?? null ) ) {
			return null;
		}

		return max( 0, intval( $merchant_data[ self::BALANCE_KEY ] ) );
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
