<?php
namespace Krokedil\KustomCheckout\InPersonPayments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the create-session payload from a WooCommerce order.
 *
 * The IPP line item shape differs from the checkout one: the `type` enum is narrower
 * and `tax_rate` is in basis points rather than hundredths of a percent, so this does
 * not reuse the checkout or order management formatters.
 */
class SessionPayload {

	/**
	 * The WooCommerce order the session is built from.
	 *
	 * @var \WC_Order
	 */
	private $order;

	/**
	 * The device the session is dispatched to.
	 *
	 * @var string
	 */
	private $device_id;

	/**
	 * Class constructor.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @param string    $device_id The device to dispatch to.
	 */
	public function __construct( $order, $device_id ) {
		$this->order     = $order;
		$this->device_id = $device_id;
	}

	/**
	 * Build the payload.
	 *
	 * @return array
	 */
	public function get_payload() {
		$order_amount = self::to_minor( $this->order->get_total() );
		$order_items  = $this->get_order_items();
		$order_items  = $this->reconcile( $order_items, $order_amount );

		$payload = array(
			'device_id'           => $this->device_id,
			'order_amount'        => $order_amount,
			'order_tax_amount'    => self::to_minor( $this->order->get_total_tax() ),
			'purchase_currency'   => $this->order->get_currency(),
			'purchase_started_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'order_items'         => $order_items,
			'merchant_reference1' => (string) $this->order->get_order_number(),
			'merchant_data'       => (string) wp_json_encode(
				array(
					'order_id' => $this->order->get_id(),
					'site_url' => home_url(),
				),
				JSON_UNESCAPED_SLASHES
			),
		);

		/**
		 * The create-session payload before it is sent to the device.
		 *
		 * The lines must still add up to `order_amount`, and each line to itself, or
		 * Kustom refuses the session at the counter.
		 *
		 * @since 2.22.0
		 *
		 * @param array     $payload The payload, in the IPP API's shape.
		 * @param \WC_Order $order The WooCommerce order the session is for.
		 * @param string    $device_id The device the session is dispatched to.
		 */
		return apply_filters( 'kco_ipp_session_payload', $payload, $this->order, $this->device_id );
	}

	/**
	 * Build the line items from the order's products, shipping and fees.
	 *
	 * @return array
	 */
	private function get_order_items() {
		$order_items = array();

		foreach ( $this->order->get_items() as $item ) {
			$order_items[] = $this->product_line( $item );
		}

		foreach ( $this->order->get_items( 'shipping' ) as $item ) {
			$order_items[] = $this->shipping_line( $item );
		}

		foreach ( $this->order->get_items( 'fee' ) as $item ) {
			$order_items[] = $this->fee_line( $item );
		}

		return $order_items;
	}

	/**
	 * Format a product line.
	 *
	 * @param \WC_Order_Item_Product $item The order item.
	 * @return array
	 */
	private function product_line( $item ) {
		$quantity = max( 1, (int) $item->get_quantity() );
		$subtotal = self::to_minor( (float) $item->get_subtotal() + (float) $item->get_subtotal_tax() );
		$total    = self::to_minor( (float) $item->get_total() + (float) $item->get_total_tax() );

		// unit_price is an integer, so the discount absorbs what rounding it up leaves:
		// unit_price * quantity - total_discount_amount must equal total_amount.
		$unit_price = (int) ceil( $subtotal / $quantity );

		return array(
			'type'                  => $this->product_type( $item ),
			'reference'             => $this->product_reference( $item ),
			'name'                  => wp_strip_all_tags( $item->get_name() ),
			'quantity'              => $quantity,
			'unit_price'            => $unit_price,
			'total_amount'          => $total,
			'total_discount_amount' => $unit_price * $quantity - $total,
			'tax_rate'              => $this->tax_rate( $item ),
			'total_tax_amount'      => self::to_minor( $item->get_total_tax() ),
		);
	}

	/**
	 * Format a shipping line.
	 *
	 * @param \WC_Order_Item_Shipping $item The order item.
	 * @return array
	 */
	private function shipping_line( $item ) {
		$total = self::to_minor( (float) $item->get_total() + (float) $item->get_total_tax() );

		return array(
			'type'                  => 'shipping_fee',
			'reference'             => $item->get_method_id() . ':' . $item->get_instance_id(),
			'name'                  => wp_strip_all_tags( $item->get_name() ),
			'quantity'              => 1,
			'unit_price'            => $total,
			'total_amount'          => $total,
			'total_discount_amount' => 0,
			'tax_rate'              => $this->tax_rate( $item ),
			'total_tax_amount'      => self::to_minor( $item->get_total_tax() ),
		);
	}

	/**
	 * Format a fee line. A negative fee is a discount to Kustom.
	 *
	 * @param \WC_Order_Item_Fee $item The order item.
	 * @return array
	 */
	private function fee_line( $item ) {
		$total = self::to_minor( (float) $item->get_total() + (float) $item->get_total_tax() );

		return array(
			'type'                  => $total < 0 ? 'discount' : 'surcharge',
			'reference'             => 'fee',
			'name'                  => wp_strip_all_tags( $item->get_name() ),
			'quantity'              => 1,
			'unit_price'            => $total,
			'total_amount'          => $total,
			'total_discount_amount' => 0,
			'tax_rate'              => $this->tax_rate( $item ),
			'total_tax_amount'      => self::to_minor( $item->get_total_tax() ),
		);
	}

	/**
	 * Kustom rejects a session whose lines do not add up to the order amount, and Woo
	 * rounding settings can leave a discrepancy of a minor unit or two. A correcting
	 * line keeps the sale going rather than failing it at the counter.
	 *
	 * @param array $order_items The line items built so far.
	 * @param int   $order_amount The order total in minor units.
	 * @return array
	 */
	private function reconcile( $order_items, $order_amount ) {
		$difference = $order_amount - array_sum( array_column( $order_items, 'total_amount' ) );

		if ( 0 === $difference ) {
			return $order_items;
		}

		$order_items[] = array(
			'type'                  => $difference < 0 ? 'discount' : 'surcharge',
			'reference'             => 'rounding',
			'name'                  => __( 'Rounding', 'klarna-checkout-for-woocommerce' ),
			'quantity'              => 1,
			'unit_price'            => $difference,
			'total_amount'          => $difference,
			'total_discount_amount' => 0,
			'tax_rate'              => 0,
			'total_tax_amount'      => 0,
		);

		return $order_items;
	}

	/**
	 * The IPP item type for a product line.
	 *
	 * @param \WC_Order_Item_Product $item The order item.
	 * @return string
	 */
	private function product_type( $item ) {
		$product = $item->get_product();

		return $product && $product->is_virtual() ? 'digital' : 'physical';
	}

	/**
	 * The SKU of a product line, falling back to its id and then to its name.
	 *
	 * @param \WC_Order_Item_Product $item The order item.
	 * @return string
	 */
	private function product_reference( $item ) {
		$product   = $item->get_product();
		$reference = $item->get_name();

		if ( $product ) {
			$reference = '' !== $product->get_sku() ? $product->get_sku() : $product->get_id();
		}

		return substr( (string) $reference, 0, 64 );
	}

	/**
	 * The item's tax rate in basis points, e.g. 2500 for 25%.
	 *
	 * @param \WC_Order_Item $item The order item.
	 * @return int
	 */
	private function tax_rate( $item ) {
		foreach ( $item->get_taxes()['total'] ?? array() as $rate_id => $amount ) {
			if ( '' === $amount ) {
				continue;
			}

			$rate = \WC_Tax::_get_tax_rate( $rate_id );
			if ( ! empty( $rate['tax_rate'] ) ) {
				return (int) round( (float) $rate['tax_rate'] * 100 );
			}
		}

		return 0;
	}

	/**
	 * Convert an amount to minor units.
	 *
	 * @param float|string $amount The amount.
	 * @return int
	 */
	private static function to_minor( $amount ) {
		return (int) round( (float) $amount * 100 );
	}
}
