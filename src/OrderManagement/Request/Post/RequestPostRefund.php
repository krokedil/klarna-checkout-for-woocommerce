<?php
namespace Krokedil\KustomCheckout\OrderManagement\Request\Post;

use Krokedil\KustomCheckout\OrderManagement\OrderManagement;
use Krokedil\KustomCheckout\OrderManagement\Request\RequestPost;
use Krokedil\KustomCheckout\OrderManagement\OrderLines;
use Krokedil\KustomCheckout\OrderManagement\Utility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POST request class for order refund
 */
class RequestPostRefund extends RequestPost {


	/**
	 * The Refund Reason
	 *
	 * @var string
	 */
	protected $refund_reason;

	/**
	 * The Refund Amount
	 *
	 * @var integer
	 */
	protected $refund_amount;

	/**
	 * The Return Fee
	 *
	 * @var array
	 */
	protected $return_fee;

	/**
	 * The Refund ID
	 *
	 * @var string
	 */
	protected $refund_id;

	/**
	 * The Kustom id of the POS device an in-person refund is registered on.
	 *
	 * @var string
	 */
	protected $device_id;

	/**
	 * Class constructor.
	 *
	 * @param OrderManagement $order_management The order management instance.
	 * @param array           $arguments The request arguments.
	 */
	public function __construct( $order_management, $arguments ) {
		parent::__construct( $order_management, $arguments );
		$this->log_title     = 'Refund Kustom order';
		$this->refund_reason = $arguments['refund_reason'];
		$this->refund_amount = $arguments['refund_amount'];
		$this->return_fee    = $arguments['return_fee'] ?? array();
		$this->refund_id     = $arguments['refund_id'] ?? '';
		$this->device_id     = $arguments['device_id'] ?? '';
	}

	/**
	 * Get the request headers.
	 *
	 * @return array|\WP_Error
	 */
	protected function get_request_headers() {
		$headers = parent::get_request_headers();
		if ( is_wp_error( $headers ) ) {
			return $headers;
		}

		$headers['Klarna-Idempotency-Key'] = wp_generate_uuid4();

		// Only the extended API accepts the device a refund is fiscally registered on.
		if ( '' !== $this->device_id ) {
			$headers['kco-expand-api'] = 'true';
		}

		return $headers;
	}

	/**
	 * Get the request URL for this type of request.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . 'ordermanagement/v1/orders/' . $this->klarna_order_id . '/refunds';
	}

	/**
	 * Build the request body for this request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$data = array(
			'refunded_amount' => round( $this->refund_amount * 100 ),
			'description'     => $this->refund_reason,
		);

		// Get the original order number.
		$order        = wc_get_order( $this->order_id );
		$order_number = empty( $order ) ? $this->order_id : $order->get_order_number();

		// Add the order number and refund id if available.
		if ( ! empty( $this->refund_id ) ) {
			$data['reference'] = "{$order_number}|{$this->refund_id}";
		}

		if ( '' !== $this->device_id ) {
			$data['device_id'] = $this->device_id;
		}

		$refund_order_lines = $this->get_refund_order_lines();

		if ( isset( $refund_order_lines ) && ! empty( $refund_order_lines ) ) {
			$data['order_lines'] = $refund_order_lines;
		}

		return $data;
	}

	/**
	 * Returns the refund order lines.
	 * TODO: Move this functionality to OrderLines class.
	 *
	 * @return array
	 */
	public function get_refund_order_lines() {
		$refund_id = $this->get_refunded_order_id( $this->order_id );

		if ( ! empty( $refund_id ) ) {
			$refund_order            = wc_get_order( $refund_id );
			$order                   = wc_get_order( $this->order_id );
			$order_items             = $order->get_items();
			$refunded_items          = $refund_order->get_items();
			$refunded_shipping       = $refund_order->get_shipping_method();
			$refunded_shipping_items = $refund_order->get_items( 'shipping' );
			$order_lines_processor   = new OrderLines( $refund_id );
			$separate_sales_tax      = $order_lines_processor->separate_sales_tax;
			$data                    = array();

			if ( $refunded_items ) {
				/**
				 * Process order item products.
				 *
				 * @var \WC_Order_Item_Product $item WooCommerce order item product.
				 */
				foreach ( $refunded_items as $item ) {
					$product = wc_get_product( $item->get_product_id() );

					$order_line_tax_rate = 0;
					$sale_item           = null;
					$refunded_item_id    = absint( $item->get_meta( '_refunded_item_id' ) );
					/**
					 * Get the tax rate WooCommerce applied to the matching order item.
					 *
					 * @var \WC_Order_Item_Product $order_item WooCommerce order item product.
					 */
					foreach ( $order_items as $order_item ) {
						if ( $order_item->get_id() === $refunded_item_id ) {
							$order_line_tax_rate = $this->get_order_item_tax_rate( $order, $order_item );
							$sale_item           = $order_item;
							break;
						}
					}

					/**
					 *
					 *  If a product is not available inside of WC anymore wc_get_product() will return false
					 *  and the default check will fail resulting in an fatal error, creating the Refund with WC but not sending it to Kustom.
					 *  This fallback allows DEVs to provide the product type which they saved before.
					 *
					 *  Alternatively KOM or KCO could save this information on order creation.
					 */

					if ( is_object( $product ) && method_exists( $product, 'is_downloadable' ) ) {
							$type = $product->is_downloadable() || $product->is_virtual() ? 'digital' : 'physical';
					} else {
							/**
							 * Filters the Kustom product type for a refunded item whose product no longer exists in WooCommerce.
							 *
							 * @param string                 $type The Kustom order line type. Default 'physical'.
							 * @param \WC_Order_Item_Product $item The refunded order item.
							 */
							$type = apply_filters( 'kom_line_item_product_type', 'physical', $item );
					}

					$reference           = $order_lines_processor->get_item_reference( $item );
					$name                = $order_lines_processor->get_item_name( $item );
					$quantity            = abs( $order_lines_processor->get_item_quantity( $item ) );
					$refund_price_amount = round( abs( $refund_order->get_line_subtotal( $item, false ) ) * 100 );
					$total_discount      = $order_lines_processor->get_item_discount_amount( $item );
					$refund_tax_amount   = $separate_sales_tax ? 0 : abs( $order_lines_processor->get_item_tax_amount( $item ) );
					$unit_price          = round( ( $refund_price_amount + $refund_tax_amount ) / $quantity );
					$total               = round( $quantity * $unit_price );

					// An in-person refund line has to carry the unit price of the sale; only quantity and total may differ.
					if ( '' !== $this->device_id && $sale_item ) {
						$total          = $refund_price_amount + $refund_tax_amount;
						$unit_price     = $this->get_sale_unit_price( $sale_item );
						$total_discount = max( 0, $unit_price * $quantity - $total );
					}

					$item_data = array(
						'type'                  => $type,
						'reference'             => $reference,
						'name'                  => $name,
						'quantity'              => $quantity,
						'unit_price'            => $unit_price,
						'tax_rate'              => $order_line_tax_rate,
						'total_amount'          => $total,
						'total_discount_amount' => $total_discount,
						'total_tax_amount'      => $refund_tax_amount,
					);

					$product_urls = Utility::maybe_add_product_urls( $item );
					if ( ! empty( $product_urls ) ) {
						$item_data = array_merge( $item_data, Utility::maybe_add_product_urls( $item ) );
					}

					// Do not add order lines if separate sales tax and no refund amount entered.
					if ( ! ( $separate_sales_tax && '0' == $refund_price_amount ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Can be float *or* integer, so non-strict is required.
						$data[] = $item_data;
					}
				}
			}
			// if shipping is refunded.
			if ( $refunded_shipping ) {
				/**
				 * Process Shipping
				 *
				 * @var \WC_Order_Item_Shipping $shipping_item WooCommerce order item *shipping*.
				 */
				foreach ( $refunded_shipping_items as $shipping_item ) {

					$order_shipping_total    = round( $order->get_shipping_total() * 100 );
					$order_shipping_tax      = round( $order->get_shipping_tax() * 100 );
					$order_shipping_tax_rate = empty( $order_shipping_total ) ? 0 : round( ( $order_shipping_tax / $order_shipping_total ) * 10000 );

					$type                = 'shipping_fee';
					$reference           = $shipping_item->get_method_id() . ':' . $shipping_item->get_instance_id();
					$name                = $shipping_item->get_name();
					$quantity            = 1;
					$total_discount      = $refund_order->get_total_discount( false );
					$refund_price_amount = round( abs( $shipping_item->get_total() ) * 100 );
					$refund_tax_amount   = $separate_sales_tax ? 0 : round( abs( $shipping_item->get_total_tax() ) * 100 );
					$unit_price          = round( $refund_price_amount + $refund_tax_amount );
					$total               = round( $quantity * $unit_price );
					$shipping_data       = array(
						'type'                  => $type,
						'reference'             => $reference,
						'name'                  => $name,
						'quantity'              => $quantity,
						'unit_price'            => $unit_price,
						'tax_rate'              => $order_shipping_tax_rate,
						'total_amount'          => $total,
						'total_discount_amount' => $total_discount,
						'total_tax_amount'      => $refund_tax_amount,
					);

					// Do not add order lines if separate sales tax and no refund amount entered.
					if ( ! ( $separate_sales_tax && '0' == $refund_price_amount ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Can be float *or* integer, so non-strict is required.
						$data[] = $shipping_data;
					}
				}
			}
			// If separate sales tax and if tax is being refunded.
			if ( $separate_sales_tax && '0' != $refund_order->get_total_tax() ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- Can be float *or* integer, so non-strict is required.
				$sales_tax_amount = round( abs( $refund_order->get_total_tax() ) * 100 );

				// Add sales tax line item.
				$sales_tax = array(
					'type'                  => 'sales_tax',
					'reference'             => __( 'Sales Tax', 'klarna-checkout-for-woocommerce' ),
					'name'                  => __( 'Sales Tax', 'klarna-checkout-for-woocommerce' ),
					'quantity'              => 1,
					'unit_price'            => $sales_tax_amount,
					'tax_rate'              => 0,
					'total_amount'          => $sales_tax_amount,
					'total_discount_amount' => 0,
					'total_tax_amount'      => 0,
				);

				$data[] = $sales_tax;
			}

			// If return fees are set.
			if ( ! empty( $this->return_fee ) ) {
				add_filter( 'klarna_applied_return_fees', fn( $fees ) => array_merge( $fees, $this->return_fee ), 10, 1 );

				// Calculate the tax rate for the return fee.
				$return_fee_tax_rate = 0;
				$tax_rate_id         = $this->return_fee['tax_rate_id'] ?? 0;
				if ( $tax_rate_id ) {
					$tax_rate_data = \WC_Tax::_get_tax_rate( $tax_rate_id );
					if ( $tax_rate_data && isset( $tax_rate_data['tax_rate'] ) ) {
						$return_fee_tax_rate = round( floatval( $tax_rate_data['tax_rate'] ) * 100 );
					}
				}

				$return_fee = array(
					'type'             => 'return_fee',
					'name'             => __( 'Return fee', 'klarna-checkout-for-woocommerce' ),
					'quantity'         => 1,
					'unit_price'       => round( -1 * ( abs( $this->return_fee['amount'] + $this->return_fee['tax_amount'] ) * 100 ) ),
					'tax_rate'         => $return_fee_tax_rate,
					'total_amount'     => round( -1 * ( abs( $this->return_fee['amount'] + $this->return_fee['tax_amount'] ) * 100 ) ),
					'total_tax_amount' => round( -1 * ( abs( $this->return_fee['tax_amount'] ) * 100 ) ),
				);

				$data[] = $return_fee;
			}
		}

		/**
		 * Filters the request body sent to Kustom when refunding an order.
		 *
		 * @param array $data     The refund request body.
		 * @param int   $order_id The WooCommerce order ID.
		 */
		return apply_filters( 'kom_refund_order_args', $data, $this->order_id );
	}

	/**
	 * The unit price an in-person sale sent Kustom for an order item, in minor units.
	 *
	 * @param \WC_Order_Item_Product $sale_item The item of the original order.
	 * @return int
	 */
	private function get_sale_unit_price( $sale_item ) {
		$quantity = max( 1, (int) $sale_item->get_quantity() );
		$subtotal = round( ( (float) $sale_item->get_subtotal() + (float) $sale_item->get_subtotal_tax() ) * 100 );

		// Rounded up the same way SessionPayload::product_line() does for the sale.
		return (int) ceil( $subtotal / $quantity );
	}

	/**
	 * Kustom's line mismatch answer names amounts in its own notation, so the merchant
	 * is told what to do instead.
	 *
	 * @param object|\WP_Error $response The response from the request.
	 * @param array            $request_args The request args.
	 * @param string           $request_url The request url.
	 * @return array|\WP_Error
	 */
	protected function process_response( $response, $request_args, $request_url ) {
		$processed = parent::process_response( $response, $request_args, $request_url );

		if ( is_wp_error( $processed ) && ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( 'REFUND_LINE_MISMATCH' === ( $body['error_code'] ?? '' ) ) {
				return new \WP_Error( 'refund_line_mismatch', __( 'Kustom could not match the refund to the items of the original sale. Refund whole items at the price they were sold for, or refund the order in full.', 'klarna-checkout-for-woocommerce' ) );
			}
		}

		return $processed;
	}

	/**
	 * Get the tax rate WooCommerce applied to an order item.
	 *
	 * Reads the rate from the order's tax data instead of recalculating it from the
	 * line totals, since WooCommerce is not always consistent with computed tax rates.
	 * The rate is returned in basis points (e.g. 25% becomes 2500).
	 *
	 * @param \WC_Order      $order      The WooCommerce order.
	 * @param \WC_Order_Item $order_item The WooCommerce order item.
	 * @return float
	 */
	private function get_order_item_tax_rate( $order, $order_item ) {
		// If the item has no tax, the rate is 0.
		if ( '0' === (string) $order_item->get_total_tax() ) {
			return 0;
		}

		$item_taxes = $order_item->get_taxes()['total'] ?? array();
		if ( empty( $item_taxes ) ) {
			return 0;
		}

		$rate_id = key( $item_taxes );

		foreach ( $order->get_items( 'tax' ) as $tax_item ) {
			if ( $tax_item->get_rate_id() === $rate_id ) {
				return round( floatval( \WC_Tax::get_rate_percent_value( $rate_id ) ) * 100 );
			}
		}

		return 0;
	}

	/**
	 * Returns the id of the refunded order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return int
	 */
	public function get_refunded_order_id( $order_id ) {
		$order = wc_get_order( $order_id );

		/* Always retrieve the most recent (current) refund (index 0). */
		return $order->get_refunds()[0]->get_id();
	}
}
