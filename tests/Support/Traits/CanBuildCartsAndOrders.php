<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

/** Cart, customer and order fixtures for the Integration suite. */
trait CanBuildCartsAndOrders {

	/** Address fields WC_Customer / WC_Order expose setters for, per context. */
	private const BILLING_FIELDS = [
		'first_name',
		'last_name',
		'company',
		'address_1',
		'address_2',
		'city',
		'state',
		'postcode',
		'country',
		'email',
		'phone',
	];

	private const SHIPPING_FIELDS = [
		'first_name',
		'last_name',
		'company',
		'address_1',
		'address_2',
		'city',
		'state',
		'postcode',
		'country',
		'phone',
	];

	/** A complete Swedish billing address. */
	protected function swedishAddress( array $overrides = [] ): array {
		return array_merge(
			[
				'first_name' => 'Karl',
				'last_name'  => 'Karlsson',
				'company'    => 'Krokedil AB',
				'address_1'  => 'Storgatan 1',
				'address_2'  => 'Lgh 1102',
				'city'       => 'Göteborg',
				'state'      => '',
				'postcode'   => '411 06',
				'country'    => 'SE',
				'email'      => 'karl@example.com',
				'phone'      => '+46701234567',
			],
			$overrides
		);
	}

	/** A complete US billing address. */
	protected function usAddress( array $overrides = [] ): array {
		return array_merge(
			[
				'first_name' => 'Jane',
				'last_name'  => 'Doe',
				'company'    => 'Acme Inc',
				'address_1'  => '1 Market Street',
				'address_2'  => 'Suite 200',
				'city'       => 'San Francisco',
				'state'      => 'CA',
				'postcode'   => '94105',
				'country'    => 'US',
				'email'      => 'jane@example.com',
				'phone'      => '+14155550123',
			],
			$overrides
		);
	}

	/** Blanks every address field on WC()->customer and then applies the given ones. */
	protected function haveCustomerAddress( array $billing = [], array $shipping = [] ): void {
		$customer = WC()->customer;

		$this->applyAddress( $customer, 'billing', array_fill_keys( self::BILLING_FIELDS, '' ) );
		$this->applyAddress( $customer, 'shipping', array_fill_keys( self::SHIPPING_FIELDS, '' ) );

		$this->applyAddress( $customer, 'billing', $billing );
		$this->applyAddress( $customer, 'shipping', $shipping );
	}

	/**
	 * Empties the cart, the shipping selection, and the WC-session state
	 * third-party integrations read their cart contributions from.
	 */
	protected function emptyCart(): void {
		WC()->session->set( 'pw-gift-card-data', [] );

		WC()->cart->empty_cart();
		WC()->session->set( 'chosen_shipping_methods', [] );

		$this->resetShippingCaches();
	}

	/** Clears the shipping state WooCommerce caches outside the database. */
	protected function resetShippingCaches(): void {
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		\WC_Cache_Helper::invalidate_cache_group( 'shipping_zones' );
		delete_transient( 'wc_shipping_method_count' );

		foreach ( array_keys( WC()->session->get_session_data() ) as $key ) {
			if ( 0 === strpos( (string) $key, 'shipping_for_package_' ) ) {
				WC()->session->__unset( $key );
			}
		}
	}

	/** Adds products to the cart and recalculates totals. */
	protected function haveCartWith( array $items ): void {
		foreach ( $items as $item ) {
			$product  = is_array( $item ) ? $item[0] : $item;
			$quantity = is_array( $item ) ? ( $item[1] ?? 1 ) : 1;

			WC()->cart->add_to_cart( $product->get_id(), $quantity );
		}

		$this->recalculateCart();
	}

	/** Registers a cart fee. */
	protected function haveCartFee( string $name, float $amount, bool $taxable = false, string $tax_class = '' ): void {
		add_action(
			'woocommerce_cart_calculate_fees',
			static function ( $cart ) use ( $name, $amount, $taxable, $tax_class ) {
				$cart->add_fee( $name, $amount, $taxable, $tax_class );
			}
		);

		$this->recalculateCart();
	}

	/** Creates a shipping zone with a single flat rate method and selects it. */
	protected function haveChosenFlatRateShipping( string $country, string $cost = '50.00', string $tax_status = 'taxable' ): string {
		$zone = new \WC_Shipping_Zone();
		$zone->set_zone_name( "Test zone {$country}" );
		$zone->add_location( $country, 'country' );
		$instance_id = $zone->add_shipping_method( 'flat_rate' );
		$zone->save();

		update_option(
			"woocommerce_flat_rate_{$instance_id}_settings",
			[
				'title'      => 'Flat rate',
				'tax_status' => $tax_status,
				'cost'       => $cost,
			]
		);

		$rate_id = "flat_rate:{$instance_id}";

		$this->resetShippingCaches();
		WC()->shipping()->load_shipping_methods();
		WC()->session->set( 'chosen_shipping_methods', [ $rate_id ] );

		$this->recalculateCart();

		return $rate_id;
	}

	/** Makes WooCommerce believe the current request is the checkout page. */
	protected function simulateCheckoutPage(): void {
		add_filter( 'woocommerce_is_checkout', '__return_true' );
	}

	/** Recalculates shipping and totals for the current cart. */
	protected function recalculateCart(): void {
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();
	}

	/** Creates a saved WC_Order with items, shipping, fees and addresses. */
	protected function haveOrder( array $args = [] ): \WC_Order {
		$args = array_merge(
			[
				'items'            => [],
				'shipping'         => null,
				'fees'             => [],
				'billing'          => [],
				'shipping_address' => [],
				'currency'         => null,
				'status'           => null,
				'paid'             => false,
				'kustom'           => false,
				'created_via'      => null,
			],
			$args
		);

		$order = new \WC_Order();

		if ( $args['currency'] ) {
			$order->set_currency( $args['currency'] );
		}

		if ( $args['created_via'] ) {
			$order->set_created_via( $args['created_via'] );
		}

		$this->applyAddress( $order, 'billing', $args['billing'] );
		$this->applyAddress( $order, 'shipping', $args['shipping_address'] );

		foreach ( $args['items'] as $item ) {
			$product  = is_array( $item ) ? $item[0] : $item;
			$quantity = is_array( $item ) ? ( $item[1] ?? 1 ) : 1;

			$order->add_product( $product, $quantity );
		}

		if ( $args['shipping'] ) {
			$shipping = array_merge(
				[
					'method_title' => 'Flat rate',
					'method_id'    => 'flat_rate',
					'instance_id'  => 1,
					'total'        => '50.00',
				],
				$args['shipping']
			);

			$shipping_item = new \WC_Order_Item_Shipping();
			$shipping_item->set_method_title( $shipping['method_title'] );
			$shipping_item->set_method_id( $shipping['method_id'] );
			$shipping_item->set_instance_id( (string) $shipping['instance_id'] );
			$shipping_item->set_total( $shipping['total'] );
			$order->add_item( $shipping_item );
		}

		foreach ( $args['fees'] as $fee ) {
			$fee = array_merge(
				[
					'name'       => 'Handling fee',
					'total'      => '10.00',
					'tax_status' => 'taxable',
					'tax_class'  => '',
				],
				$fee
			);

			$fee_item = new \WC_Order_Item_Fee();
			$fee_item->set_name( $fee['name'] );
			$fee_item->set_amount( (string) $fee['total'] );
			$fee_item->set_total( (string) $fee['total'] );
			$fee_item->set_tax_status( $fee['tax_status'] );
			$fee_item->set_tax_class( $fee['tax_class'] );
			$order->add_item( $fee_item );
		}

		if ( false !== $args['kustom'] ) {
			$gateway = is_array( $args['kustom'] ) ? $args['kustom'] : [];

			$this->applyGatewayMeta(
				$order,
				$gateway['order_id'] ?? 'kustom-order-123',
				$gateway['country'] ?? 'SE'
			);
		}

		if ( $args['paid'] ) {
			$order->set_date_paid( time() );
		}

		if ( $args['status'] ) {
			$order->set_status( $args['status'] );
		}

		$order->calculate_totals( true );
		$order->save();

		return $order;
	}

	/**
	 * A saved order that went through the gateway: one 100.00 simple product, Swedish
	 * billing address, and the gateway's order meta. Needs `CanManageProducts`
	 * composed alongside (as `IntegrationTestCase` does).
	 */
	protected function haveGatewayOrder( array $args = [] ): \WC_Order {
		return $this->haveOrder(
			array_merge(
				[
					'items'   => [ [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ), 1 ] ],
					'billing' => $this->swedishAddress(),
					'kustom'  => true,
				],
				$args
			)
		);
	}

	/** A paid gateway order in processing (2 x 100.00), ready to be captured. */
	protected function haveCapturableGatewayOrder( array $args = [] ): \WC_Order {
		return $this->haveGatewayOrder(
			array_merge(
				[
					'items'  => [ [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ), 2 ] ],
					'paid'   => true,
					'status' => 'processing',
				],
				$args
			)
		);
	}

	/** Stamps an order with the meta a completed gateway purchase leaves behind. */
	protected function markAsGatewayOrder( \WC_Order $order, string $gateway_order_id = 'kustom-order-123', string $country = 'SE' ): \WC_Order {
		$this->applyGatewayMeta( $order, $gateway_order_id, $country );
		$order->save();

		return $order;
	}

	/**
	 * Writes the gateway's order meta onto an order, without saving. The meta keys are
	 * the plugin's own storage keys, kept verbatim so the fixture matches what it reads.
	 */
	private function applyGatewayMeta( \WC_Order $order, string $gateway_order_id, string $country ): void {
		$order->set_payment_method( 'kco' );
		$order->set_transaction_id( $gateway_order_id );
		$order->update_meta_data( '_wc_klarna_order_id', $gateway_order_id );
		$order->update_meta_data( '_wc_klarna_country', $country );
		$order->update_meta_data( '_wc_klarna_environment', 'test' );
	}

	/** Refunds order items, the way "Refund" in wp-admin does. */
	protected function haveRefundForItems( \WC_Order $order, array $item_ids = [], string $reason = '', array $quantities = [] ): \WC_Order_Refund {
		if ( empty( $item_ids ) ) {
			$item_ids = array_keys( $order->get_items( [ 'line_item', 'shipping', 'fee' ] ) );
		}

		$line_items = [];
		$amount     = 0.0;

		foreach ( $item_ids as $item_id ) {
			$item      = $order->get_item( $item_id );
			$ordered   = max( 1, (int) $item->get_quantity() );
			$quantity  = $quantities[ $item_id ] ?? $ordered;
			$proportion = $quantity / $ordered;

			$tax = array_map(
				static function ( $value ) use ( $proportion ) {
					return round( (float) $value * $proportion, 2 );
				},
				$item->get_taxes()['total'] ?? []
			);

			$total = round( (float) $item->get_total() * $proportion, 2 );

			$line_items[ $item_id ] = [
				'qty'          => $quantity,
				'refund_total' => $total,
				'refund_tax'   => $tax,
			];

			$amount += $total + array_sum( $tax );
		}

		$refund = wc_create_refund(
			[
				'order_id'   => $order->get_id(),
				'amount'     => $amount,
				'reason'     => $reason,
				'line_items' => $line_items,
			]
		);

		if ( is_wp_error( $refund ) ) {
			throw new \RuntimeException( 'Could not create refund: ' . $refund->get_error_message() );
		}

		return $refund;
	}

	/**
	 * Applies 'billing' or 'shipping' address fields, keyed without the context
	 * prefix, to a WC_Customer or WC_Order via its setters.
	 */
	private function applyAddress( $target, string $context, array $fields ): void {
		foreach ( $fields as $key => $value ) {
			$setter = "set_{$context}_{$key}";

			if ( is_callable( [ $target, $setter ] ) ) {
				$target->{$setter}( $value );
			}
		}
	}
}
