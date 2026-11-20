<?php
namespace Krokedil\KustomCheckout\InPersonPayments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks that an in-person refund is one Kustom can register. Kustom refunds whole
 * units of what was sold, so a custom amount on an item is refused before it is sent.
 */
class RefundLines {

	/**
	 * Whether the refund is made of whole items, or is the whole order.
	 *
	 * @param \WC_Order        $order The in-person order being refunded.
	 * @param \WC_Order_Refund $refund The refund WooCommerce created.
	 * @param float|string     $amount The amount being refunded.
	 * @return true|\WP_Error
	 */
	public static function validate( $order, $refund, $amount ) {
		$error = new \WP_Error( 'partial_item_refund', __( 'An in-person purchase can only be refunded by whole items. Enter the quantity to refund for each item and leave the amounts as WooCommerce fills them in, or refund the order in full.', 'klarna-checkout-for-woocommerce' ) );
		$items = $refund->get_items( array( 'line_item', 'shipping', 'fee' ) );

		// An amount with no items is sent without lines, which only a full refund may be.
		if ( empty( $items ) ) {
			return self::to_minor( $amount ) === self::to_minor( $order->get_total() ) ? true : $error;
		}

		foreach ( $items as $item ) {
			$sale_item = $order->get_item( absint( $item->get_meta( '_refunded_item_id' ) ) );
			if ( ! $sale_item ) {
				return $error;
			}

			$quantity = abs( (int) $item->get_quantity() );
			$sold     = max( 1, abs( (int) $sale_item->get_quantity() ) );
			$refunded = self::to_minor( abs( self::line_total( $item ) ) );
			$expected = self::to_minor( self::line_total( $sale_item ) * $quantity / $sold );

			// One minor unit of slack, for a price that does not divide evenly by the quantity sold.
			if ( $quantity < 1 || abs( $refunded - $expected ) > 1 ) {
				return $error;
			}
		}

		return true;
	}

	/**
	 * What an order item comes to, tax included.
	 *
	 * @param \WC_Order_Item $item The order item.
	 * @return float
	 */
	private static function line_total( $item ) {
		if ( $item instanceof \WC_Order_Item_Product || $item instanceof \WC_Order_Item_Shipping || $item instanceof \WC_Order_Item_Fee ) {
			return (float) $item->get_total() + (float) $item->get_total_tax();
		}

		return 0.0;
	}

	/**
	 * An amount in minor units.
	 *
	 * @param float|string $amount The amount in major units.
	 * @return int
	 */
	private static function to_minor( $amount ) {
		return (int) round( (float) $amount * 100 );
	}
}
