<?php
/**
 * Sellers App
 *
 * Provides support for Klarna sellers app.
 *
 * @package WC_Klarna_Order_Management
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SellersApp class.
 *
 * Handles the creation of orders in WooCommerce when an order is created in Klarna with the sellers app.
 */
class SellersApp {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'wp_insert_post', array( $this, 'process_order_creation' ), 9999, 3 );
	}

	/**
	 * Handles the wp_insert_post hook.
	 *
	 * @param string  $post_id WordPress post id.
	 * @param WP_Post $post The post object.
	 * @param bool    $update If this was an update.
	 * @return void
	 */
	public function process_order_creation( $post_id, $post, $update ) {
		// If this is not an admin page bail.
		if ( ! is_admin() ) {
			return;
		}

		// If post status is not draft bail.
		if ( 'draft' !== $post->post_status ) {
			return;
		}

		// If post type is not shop_order bail.
		if ( 'shop_order' !== $post->post_type ) {
			return;
		}

		// Check that this is an update, and that we have a transaction number, and that the payment method is set to KCO or KP.
		$order = wc_get_order( $post_id );
		if ( empty( $order ) ) {
			return;
		}

		if ( $update && ! empty( $order->get_transaction_id() ) && in_array( $order->get_payment_method(), array( 'kco', 'klarna_payments' ), true ) ) {
			// Set post metas.
			$order->update_meta_data( '_wc_klarna_order_id', $order->get_transaction_id() );
			$order->update_meta_data( '_wc_klarna_country', wc_get_base_location()['country'] );
			$order->update_meta_data( '_wc_klarna_environment', self::get_klarna_environment( $order->get_payment_method() ) );
			$order->save();

			$klarna_order = WC_Klarna_Order_Management::get_instance()->retrieve_klarna_order( $post_id );

			self::populate_klarna_order( $post_id, $klarna_order );
		}
	}

	/**
	 * Populates the new order with customer data.
	 *
	 * @param string $post_id WordPress post id.
	 * @param object $klarna_order The klarna order.
	 * @return void
	 */
	public static function populate_klarna_order( $post_id, $klarna_order ) {

		if ( is_wp_error( $klarna_order ) || empty( $klarna_order ) ) {
			return;
		}

		$order = wc_get_order( $post_id );

		if ( empty( $order ) ) {
			return;
		}

		// Clear existing order items before populating.
		$order->remove_order_items();

		// Set billing address.
		$order->set_billing_first_name( sanitize_text_field( $klarna_order->billing_address->given_name ?? '' ) );
		$order->set_billing_last_name( sanitize_text_field( $klarna_order->billing_address->family_name ?? '' ) );
		$order->set_billing_address_1( sanitize_text_field( $klarna_order->billing_address->street_address ?? '' ) );
		$order->set_billing_address_2( sanitize_text_field( $klarna_order->billing_address->street_address2 ?? '' ) );
		$order->set_billing_city( sanitize_text_field( $klarna_order->billing_address->city ?? '' ) );
		$order->set_billing_state( sanitize_text_field( $klarna_order->billing_address->region ?? '' ) );
		$order->set_billing_postcode( sanitize_text_field( $klarna_order->billing_address->postal_code ?? '' ) );
		$order->set_billing_email( sanitize_text_field( $klarna_order->billing_address->email ?? '' ) );
		$order->set_billing_phone( sanitize_text_field( $klarna_order->billing_address->phone ?? '' ) );

		// Set shipping address.
		$order->set_shipping_first_name( sanitize_text_field( $klarna_order->shipping_address->given_name ?? '' ) );
		$order->set_shipping_last_name( sanitize_text_field( $klarna_order->shipping_address->family_name ?? '' ) );
		$order->set_shipping_address_1( sanitize_text_field( $klarna_order->shipping_address->street_address ?? '' ) );
		$order->set_shipping_address_2( sanitize_text_field( $klarna_order->shipping_address->street_address2 ?? '' ) );
		$order->set_shipping_city( sanitize_text_field( $klarna_order->shipping_address->city ?? '' ) );
		$order->set_shipping_state( sanitize_text_field( $klarna_order->shipping_address->region ?? '' ) );
		$order->set_shipping_postcode( sanitize_text_field( $klarna_order->shipping_address->postal_code ?? '' ) );

		self::process_order_lines( $klarna_order, $order );
		$order->set_date_paid( time() );
		$order->set_shipping_total( self::get_shipping_total( $klarna_order ) );
		$order->set_cart_tax( self::get_cart_contents_tax( $klarna_order ) );
		$order->set_shipping_tax( self::get_shipping_tax_total( $klarna_order ) );
		$order->set_total( $klarna_order->order_amount / 100 );
		$order->calculate_totals();

		$order->save();

		$order->add_order_note( __( 'Order address updated by Klarna Order management.', 'klarna-checkout-for-woocommerce' ) );
	}

	/**
	 * Gets environment (test/live) used for Klarna purchase.
	 *
	 * @param string $payment_method The selected payment method.
	 * @return mixed
	 */
	public static function get_klarna_environment( $payment_method ) {
		$env                     = 'test';
		$payment_method_settings = get_option( 'woocommerce_' . $payment_method . '_settings' );

		if ( 'yes' !== $payment_method_settings['testmode'] ) {
			$env = 'live';
		}

		return $env;
	}

	/**
	 * Processes order lines with order data received from Klarna.
	 *
	 * @param Klarna_Checkout_Order $klarna_order Klarna order.
	 * @param WC_Order              $order WooCommerce order.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	private static function process_order_lines( $klarna_order, $order ) {
		$order_id = $order->get_id();
		WC_Klarna_Logger::log( 'Processing order lines (from Klarna order) during sellers app creation for Klarna order ID ' . $klarna_order->order_id, $order_id );
		foreach ( $klarna_order->order_lines as $cart_item ) {

			// Only try to add the item to the order if we got a reference in the Klarna order.
			if ( empty( $cart_item->reference ) ) {
				continue;
			}
			if ( 'physical' === $cart_item->type || 'digital' === $cart_item->type ) {
				if ( wc_get_product_id_by_sku( $cart_item->reference ) ) {
					$id = wc_get_product_id_by_sku( $cart_item->reference );
				} else {
					$id = $cart_item->reference;
				}

				try {
					$product = wc_get_product( $id );
					$args    = array(
						'name'         => $product->get_name(),
						'tax_class'    => $product->get_tax_class(),
						'product_id'   => $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id(),
						'variation_id' => $product->is_type( 'variation' ) ? $product->get_id() : 0,
						'variation'    => $product->is_type( 'variation' ) ? $product->get_attributes() : array(),
						'subtotal'     => ( $cart_item->total_amount - $cart_item->total_tax_amount ) / 100,
						'total'        => ( $cart_item->total_amount - $cart_item->total_tax_amount ) / 100,
						'quantity'     => $cart_item->quantity,
					);
					$item    = new WC_Order_Item_Product();
					$item->set_props( $args );
					$item->set_backorder_meta();
					$item->set_order_id( $order->get_id() );
					$item->save();
					$order->add_item( $item );

				} catch ( Exception $e ) {
					WC_Klarna_Logger::log( 'Error during process order lines. Add to cart error:   ' . $e->getCode() . ' - ' . $e->getMessage(), $order_id );
				}
			}

			if ( 'shipping_fee' === $cart_item->type ) {
				try {
					$method_id   = substr( $cart_item->reference, 0, strpos( $cart_item->reference, ':' ) );
					$instance_id = substr( $cart_item->reference, strpos( $cart_item->reference, ':' ) + 1 );
					$rate        = new WC_Shipping_Rate( $cart_item->reference, $cart_item->name, ( $cart_item->total_amount - $cart_item->total_tax_amount ) / 100, array(), $method_id, $instance_id );
					$item        = new WC_Order_Item_Shipping();
					$item->set_props(
						array(
							'method_title' => $rate->label,
							'method_id'    => $rate->id,
							'total'        => wc_format_decimal( $rate->cost ),
							'taxes'        => $rate->taxes,
							'meta_data'    => $rate->get_meta_data(),
						)
					);
					$order->add_item( $item );
				} catch ( Exception $e ) {
					WC_Klarna_Logger::log( 'Error during process order lines. Add shipping error:   ' . $e->getCode() . ' - ' . $e->getMessage(), $order_id );
				}
			}

			if ( 'surcharge' === $cart_item->type ) {
				$tax_class = '';
				if ( isset( $cart_item->merchant_data ) ) {
					$merchant_data = json_decode( $cart_item->merchant_data );
					$tax_class     = $merchant_data->tax_class;
				}
				try {
					$args = array(
						'name'      => $cart_item->name,
						'tax_class' => $tax_class,
						'subtotal'  => ( $cart_item->total_amount - $cart_item->total_tax_amount ) / 100,
						'total'     => ( $cart_item->total_amount - $cart_item->total_tax_amount ) / 100,
						'quantity'  => $cart_item->quantity,
					);
					$fee  = new WC_Order_Item_Fee();
					$fee->set_props( $args );
					$order->add_item( $fee );
				} catch ( Exception $e ) {
					WC_Klarna_Logger::log( 'Error during process order lines. Add fee error:   ' . $e->getCode() . ' - ' . $e->getMessage(), $order_id );
				}
			}
		}
	}

	/**
	 * Gets the shipping total for the order.
	 *
	 * @param object $klarna_order The Klarna order.
	 * @return int
	 */
	private static function get_shipping_total( $klarna_order ) {
		$shipping_total = 0;
		foreach ( $klarna_order->order_lines as $cart_item ) {
			if ( 'shipping_fee' === $cart_item->type ) {
				$shipping_total += $cart_item->total_amount;
			}
		}
		if ( $shipping_total > 0 ) {
			$shipping_total = $shipping_total / 100;
		}

		return $shipping_total;
	}

	/**
	 * Gets the cart contents tax.
	 *
	 * @param object $klarna_order The Klarna order.
	 * @return int
	 */
	private static function get_cart_contents_tax( $klarna_order ) {
		$cart_contents_tax = 0;
		foreach ( $klarna_order->order_lines as $cart_item ) {
			if ( 'physical' === $cart_item->type || 'digital' === $cart_item->type ) {
				$cart_contents_tax += $cart_item->total_tax_amount;
			}
		}
		if ( $cart_contents_tax > 0 ) {
			$cart_contents_tax = $cart_contents_tax / 100;
		}

		return $cart_contents_tax;
	}

	/**
	 * Gets the shipping tax total for the order.
	 *
	 * @param object $klarna_order The Klarna order.
	 * @return int
	 */
	private static function get_shipping_tax_total( $klarna_order ) {
		$shipping_tax_total = 0;
		foreach ( $klarna_order->order_lines as $cart_item ) {
			if ( 'shipping_fee' === $cart_item->type ) {
				$shipping_tax_total += $cart_item->total_tax_amount;
			}
		}
		if ( $shipping_tax_total > 0 ) {
			$shipping_tax_total = $shipping_tax_total / 100;
		}

		return $shipping_tax_total;
	}
}
new WC_Klarna_Sellers_App();
