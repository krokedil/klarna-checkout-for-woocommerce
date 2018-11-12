<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klarna_Checkout_For_WooCommerce_Create_Local_Order_Fallback class.
 *
 * Class that handles fallback order creation in Woocommerce if checkout form submission fails.
 */
class Klarna_Checkout_For_WooCommerce_Create_Local_Order_Fallback {

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return self::$instance The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Klarna_Checkout_For_WooCommerce_Create_Local_Order_Fallback constructor.
	 */
	public function __construct() {

	}


	/**
	 * Fallbck order creation, in case checkout process failed.
	 *
	 * @param string $klarna_order_id Klarna order ID.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	public static function create( $klarna_order_id ) {
		$response     = KCO_WC()->api->request_post_get_order( $klarna_order_id );
		$klarna_order = json_decode( $response['body'] );

		// Create order
		$order = wc_create_order();

		try {
			$order_id = $order->get_id();

			$cart_hash = md5( json_encode( wc_clean( WC()->cart->get_cart_for_session() ) ) . WC()->cart->total );

			$order->set_created_via( 'checkout' );
			$order->set_cart_hash( $cart_hash );
			$order->set_customer_id( apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() ) );
			$order->set_currency( get_woocommerce_currency() );
			$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
			$order->set_customer_ip_address( WC_Geolocation::get_ip_address() );
			$order->set_customer_user_agent( wc_get_user_agent() );
			$order->set_shipping_total( WC()->cart->shipping_total );
			$order->set_discount_total( WC()->cart->get_cart_discount_total() );
			$order->set_discount_tax( WC()->cart->get_cart_discount_tax_total() );
			$order->set_cart_tax( WC()->cart->tax_total );
			$order->set_shipping_tax( WC()->cart->shipping_tax_total );
			$order->set_total( WC()->cart->total );

			update_post_meta( $order_id, '_created_via_klarna_fallback', 'yes' );
			update_post_meta( $order_id, '_wc_klarna_order_id', sanitize_key( $klarna_order_id ) );
			update_post_meta( $order_id, '_transaction_id', sanitize_key( $klarna_order_id ) );

			// Add payment method
			self::add_order_payment_method( $order );

			// Process customer data.
			self::process_customer_data( $order, $klarna_order );

			// Process customer data.
			self::create_order_line_items( $order, WC()->cart );

			// Add fees to order.
			self::create_order_fee_lines( $order, WC()->cart );

			// Add shipping
			self::create_order_shipping_lines( $order );

			// Tax
			self::create_order_tax_lines( $order, WC()->cart );

			// Coupons
			self::create_order_coupon_lines( $order, WC()->cart );

			// Acknowledge order in Klarna.
			self::acknowledge_order_in_klarna( $order, $klarna_order );

			// Save the order.
			$order->save();

			return $order;

		} catch ( Exception $e ) {
			return new WP_Error( 'checkout-error', $e->getMessage() );
		}

	}

	/**
	 * Set payment method.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public static function add_order_payment_method( $order ) {
		$available_gateways = WC()->payment_gateways->payment_gateways();
		$payment_method     = $available_gateways['kco'];
		$order->set_payment_method( $payment_method );
	}

	/**
	 * Processes customer data on fallback order creation.
	 *
	 * @param WC_Order              $order WooCommerce order.
	 * @param Klarna_Checkout_Order $klarna_order Klarna order.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	private static function process_customer_data( $order, $klarna_order ) {
		// First name.
		$order->set_billing_first_name( sanitize_text_field( $klarna_order->billing_address->given_name ) );
		$order->set_shipping_first_name( sanitize_text_field( $klarna_order->shipping_address->given_name ) );

		// Last name.
		$order->set_billing_last_name( sanitize_text_field( $klarna_order->billing_address->family_name ) );
		$order->set_shipping_last_name( sanitize_text_field( $klarna_order->shipping_address->family_name ) );

		// Country.
		$order->set_billing_country( sanitize_text_field( $klarna_order->billing_address->country ) );
		$order->set_shipping_country( sanitize_text_field( $klarna_order->shipping_address->country ) );

		// Street address 1.
		$order->set_billing_address_1( sanitize_text_field( $klarna_order->billing_address->street_address ) );
		$order->set_shipping_address_1( sanitize_text_field( $klarna_order->shipping_address->street_address ) );

		// Street address 2.
		$order->set_billing_address_2( sanitize_text_field( $klarna_order->billing_address->street_address2 ) );
		$order->set_shipping_address_2( sanitize_text_field( $klarna_order->shipping_address->street_address2 ) );

		// City.
		$order->set_billing_city( sanitize_text_field( $klarna_order->billing_address->city ) );
		$order->set_shipping_city( sanitize_text_field( $klarna_order->shipping_address->city ) );

		// County/State.
		$order->set_billing_state( sanitize_text_field( $klarna_order->billing_address->region ) );
		$order->set_shipping_state( sanitize_text_field( $klarna_order->shipping_address->region ) );

		// Postcode.
		$order->set_billing_postcode( sanitize_text_field( $klarna_order->billing_address->postal_code ) );
		$order->set_shipping_postcode( sanitize_text_field( $klarna_order->shipping_address->postal_code ) );

		// Phone.
		$order->set_billing_phone( sanitize_text_field( $klarna_order->billing_address->phone ) );

		// Email.
		$order->set_billing_email( sanitize_text_field( $klarna_order->billing_address->email ) );

	}


	/**
	 * Processes cart contents on fallback order creation.
	 *
	 * @paramWC_Order $order WooCommerce order.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	private static function create_order_line_items( &$order, $cart ) {

		// Remove items as to stop the item lines from being duplicated.
		$order->remove_order_items();

		foreach ( $cart->get_cart() as $cart_item_key => $values ) {
			$product                    = $values['data'];
			$item                       = new WC_Order_Item_Product();
			$item->legacy_values        = $values; // @deprecated For legacy actions.
			$item->legacy_cart_item_key = $cart_item_key; // @deprecated For legacy actions.
			$item->set_props(
				array(
					'quantity'     => $values['quantity'],
					'variation'    => $values['variation'],
					'subtotal'     => $values['line_subtotal'],
					'total'        => $values['line_total'],
					'subtotal_tax' => $values['line_subtotal_tax'],
					'total_tax'    => $values['line_tax'],
					'taxes'        => $values['line_tax_data'],
				)
			);
			if ( $product ) {
				$item->set_props(
					array(
						'name'         => $product->get_name(),
						'tax_class'    => $product->get_tax_class(),
						'product_id'   => $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id(),
						'variation_id' => $product->is_type( 'variation' ) ? $product->get_id() : 0,
					)
				);
			}
			$item->set_backorder_meta();
			/**
			 * Action hook to adjust item before save.
			 *
			 * @since 3.0.0
			 */
			do_action( 'woocommerce_checkout_create_order_line_item', $item, $cart_item_key, $values, $order );
			// Add item to order and save.
			$order->add_item( $item );
		}
	}

	/**
	 * Add fees to the order.
	 *
	 * @param  WC_Order $order
	 */
	public static function create_order_fee_lines( &$order, $cart ) {
		foreach ( $cart->get_fees() as $fee_key => $fee ) {
			$item                 = new WC_Order_Item_Fee();
			$item->legacy_fee     = $fee; // @deprecated For legacy actions.
			$item->legacy_fee_key = $fee_key; // @deprecated For legacy actions.
			$item->set_props(
				array(
					'name'      => $fee->name,
					'tax_class' => $fee->taxable ? $fee->tax_class : 0,
					'total'     => $fee->amount,
					'total_tax' => $fee->tax,
					'taxes'     => array(
						'total' => $fee->tax_data,
					),
				)
			);
			/**
			 * Action hook to adjust item before save.
			 *
			 * @since 3.0.0
			 */
			do_action( 'woocommerce_checkout_create_order_fee_item', $item, $fee_key, $fee, $order );
			// Add item to order and save.
			$order->add_item( $item );
		}
	}

	/**
	 * Add shipping lines to the order.
	 *
	 * @param  WC_Order $order
	 */
	public static function create_order_shipping_lines( &$order ) {

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}
		$order_id              = $order->get_id();
		$this_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
		WC()->cart->calculate_shipping();
		// Store shipping for all packages.
		foreach ( WC()->shipping->get_packages() as $package_key => $package ) {
			if ( isset( $package['rates'][ $this_shipping_methods[ $package_key ] ] ) ) {
				$item_id = $order->add_shipping( $package['rates'][ $this_shipping_methods[ $package_key ] ] );
				if ( ! $item_id ) {
					KCO_WC()->logger->log( 'Error: Unable to add shipping item in Create Local Order Fallback.' );
					krokedil_log_events( null, 'Error: Unable to add shipping item in Create Local Order Fallback.', '' );
				}
				// Allows plugins to add order item meta to shipping.
				do_action( 'woocommerce_add_shipping_order_item', $order_id, $item_id, $package_key );
			}
		}
	}


	/**
	 * Add tax lines to the order.
	 *
	 * @param  WC_Order $order
	 */
	public static function create_order_tax_lines( &$order, $cart ) {
		foreach ( array_keys( $cart->taxes + $cart->shipping_taxes ) as $tax_rate_id ) {
			if ( $tax_rate_id && apply_filters( 'woocommerce_cart_remove_taxes_zero_rate_id', 'zero-rated' ) !== $tax_rate_id ) {
				$item = new WC_Order_Item_Tax();
				$item->set_props(
					array(
						'rate_id'            => $tax_rate_id,
						'tax_total'          => $cart->get_tax_amount( $tax_rate_id ),
						'shipping_tax_total' => $cart->get_shipping_tax_amount( $tax_rate_id ),
						'rate_code'          => WC_Tax::get_rate_code( $tax_rate_id ),
						'label'              => WC_Tax::get_rate_label( $tax_rate_id ),
						'compound'           => WC_Tax::is_compound( $tax_rate_id ),
					)
				);
				/**
				 * Action hook to adjust item before save.
				 *
				 * @since 3.0.0
				 */
				do_action( 'woocommerce_checkout_create_order_tax_item', $item, $tax_rate_id, $order );
				// Add item to order and save.
				$order->add_item( $item );
			}
		}
	}

	/**
	 * Add coupon lines to the order.
	 *
	 * @param  WC_Order $order
	 */
	public static function create_order_coupon_lines( &$order, $cart ) {
		foreach ( $cart->get_coupons() as $code => $coupon ) {
			$item = new WC_Order_Item_Coupon();
			$item->set_props(
				array(
					'code'         => $code,
					'discount'     => $cart->get_coupon_discount_amount( $code ),
					'discount_tax' => $cart->get_coupon_discount_tax_amount( $code ),
				)
			);
			/**
			 * Action hook to adjust item before save.
			 *
			 * @since 3.0.0
			 */
			do_action( 'woocommerce_checkout_create_order_coupon_item', $item, $code, $coupon, $order );
			// Add item to order and save.
			$order->add_item( $item );
		}
	}

	/**
	 * Set order number in Klarnas system.
	 *
	 * @param WC_Order              $order WooCommerce order.
	 * @param Klarna_Checkout_Order $klarna_order Klarna order.
	 */
	public static function acknowledge_order_in_klarna( $order, $klarna_order ) {
		KCO_WC()->api->request_post_acknowledge_order( $klarna_order->order_id );
		KCO_WC()->api->request_post_set_merchant_reference(
			$klarna_order->order_id,
			array(
				'merchant_reference1' => $order->get_order_number(),
				'merchant_reference2' => $order->get_id(),
			)
		);
	}

}

Klarna_Checkout_For_WooCommerce_Create_Local_Order_Fallback::get_instance();
