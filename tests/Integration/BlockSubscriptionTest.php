<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\Blocks\OrderValidation;
use Krokedil\KustomCheckout\Utility\BlocksUtility;
use Tests\Support\IntegrationTestCase;

/**
 * Which Store API route the block checkout validation places an existing draft order through.
 *
 * @covers \Krokedil\KustomCheckout\Blocks\OrderValidation::validate_kco_order
 */
class BlockSubscriptionTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	public function test_a_draft_with_a_subscription_is_placed_through_the_create_route(): void {
		$product = $this->markAsSubscriptionProduct( $this->haveSimpleProduct( [ 'sku' => 'block-subscription' ] ) );
		$draft   = $this->haveDraftOrderToValidate(
			$product,
			[
				'recurring'       => true,
				'recurring_token' => 'recurring-token-123',
			]
		);

		OrderValidation::validate_kco_order( 'checkout-order-123' );

		$request = $this->storeApiCheckoutRequest();
		$this->assertStringEndsWith(
			'wc/store/v1/checkout',
			$request['url'],
			"Only the create route creates the guest's account and the recurring carts WooCommerce Subscriptions needs, and it reuses draft {$draft->get_id()} from the session."
		);
		$this->assertArrayNotHasKey( 'key', $request['json'], 'The order key is only sent to pay an existing order.' );

		$payment_data = array_column( $request['json']['payment_data'], 'value', 'key' );
		$this->assertSame( 'yes', $payment_data['_kco_recurring_order'] ?? null, 'The placed order must be marked as recurring so the token is stored on payment.' );
		$this->assertSame( 'recurring-token-123', $payment_data['_kco_recurring_token'] ?? null, 'Renewals are charged with the recurring token, so the create route must carry it.' );
	}

	public function test_an_order_other_than_the_referenced_one_is_refused(): void {
		$product    = $this->markAsSubscriptionProduct( $this->haveSimpleProduct( [ 'sku' => 'block-subscription' ] ) );
		$referenced = $this->haveDraftOrderToValidate( $product );
		$referenced->update_status( 'pending' );

		// The cart changed since the earlier attempt, so the create route places a new order.
		$placed = $this->haveOrderPlacedByTheCreateRoute( $product, $referenced );

		try {
			OrderValidation::validate_kco_order( 'checkout-order-123' );
			$this->fail( "Kustom confirms to order {$referenced->get_id()}, so order {$placed->get_id()} must not be accepted." );
		} catch ( \Exception $e ) {
			$this->assertSame( 401, $e->getCode() );
		}
	}

	public function test_orders_from_earlier_attempts_are_detached_when_the_create_route_places_a_new_one(): void {
		$product = $this->markAsSubscriptionProduct( $this->haveSimpleProduct( [ 'sku' => 'block-subscription' ] ) );
		$older   = $this->haveOrder( [ 'items' => [ [ $product, 3 ] ], 'status' => 'failed' ] );
		$older->update_meta_data( '_wc_klarna_order_id', 'checkout-order-123' );
		$older->save();

		// Without an early draft order there is no reference, so the create route places a new order.
		$placed = $this->haveOrderPlacedByTheCreateRoute( $product, null );

		OrderValidation::validate_kco_order( 'checkout-order-123' );

		$this->assertSame(
			'',
			wc_get_order( $older->get_id() )->get_meta( '_wc_klarna_order_id' ),
			"Lookups by the Kustom order id must only find the placed order, not unpaid order {$older->get_id()}."
		);
		$this->assertSame( $placed->get_id(), kco_get_order_by_klarna_id( 'checkout-order-123' )->get_id() );
		$this->assertSame( 'checkout-order-123', wc_get_order( $placed->get_id() )->get_meta( '_wc_klarna_order_id' ) );
	}

	public function test_a_draft_without_a_subscription_is_paid_through_its_order_route(): void {
		$draft = $this->haveDraftOrderToValidate( $this->haveSimpleProduct( [ 'sku' => 'block-plain' ] ) );

		OrderValidation::validate_kco_order( 'checkout-order-123' );

		$request = $this->storeApiCheckoutRequest();
		$this->assertStringEndsWith( 'wc/store/v1/checkout/' . $draft->get_id(), $request['url'] );
		$this->assertSame( $draft->get_order_key(), $request['json']['key'] ?? null );
	}

	/**
	 * A draft order for the product that the Kustom order references, and a Store API that answers with it.
	 */
	private function haveDraftOrderToValidate( \WC_Product $product, array $kustom_order = [] ): \WC_Order {
		$order = $this->haveOrder(
			[
				'items'  => [ [ $product, 1 ] ],
				'status' => 'checkout-draft',
			]
		);
		foreach ( [ '_shipping_hash', '_fees_hash', '_coupons_hash', '_taxes_hash' ] as $key ) {
			$order->update_meta_data( $key, 'hash' );
		}
		$order->save();

		$this->resetHttpInterception();
		$this->willRetrieveOrder( array_merge( $this->kustomOrderFor( $order, $order->get_cart_hash() ), $kustom_order ) );
		$this->willRespondWith( [ 'order_id' => $order->get_id() ], 200, 'wc/store/v1/checkout' );

		return $order;
	}

	/**
	 * An order the Store API create route answers with, for a Kustom order that references the given order, if any.
	 */
	private function haveOrderPlacedByTheCreateRoute( \WC_Product $product, ?\WC_Order $referenced ): \WC_Order {
		$placed = $this->haveOrder( [ 'items' => [ [ $product, 2 ] ] ] );
		foreach ( [ '_shipping_hash', '_fees_hash', '_coupons_hash', '_taxes_hash' ] as $key ) {
			$placed->update_meta_data( $key, 'hash' );
		}
		$placed->update_meta_data( '_wc_klarna_order_id', 'checkout-order-123' );
		$placed->save();

		$this->resetHttpInterception();
		$this->willRetrieveOrder( $this->kustomOrderFor( $referenced, $placed->get_cart_hash() ) );
		$this->willRespondWith( [ 'order_id' => $placed->get_id() ], 200, 'wc/store/v1/checkout' );

		return $placed;
	}

	/**
	 * A Kustom order that references the order, if any, with merchant data for a cart with the given hash.
	 */
	private function kustomOrderFor( ?\WC_Order $order, string $cart_hash ): array {
		return [
			'status'              => 'checkout_incomplete',
			'merchant_reference2' => $order ? (string) $order->get_id() : '',
			'merchant_data'       => wp_json_encode(
				[
					'wc_cart_token'    => BlocksUtility::create_cart_token( (string) WC()->session->get_customer_id() ),
					'wc_cart_hash'     => $cart_hash,
					'wc_shipping_hash' => 'hash',
					'wc_fees_hash'     => 'hash',
					'wc_coupons_hash'  => 'hash',
					'wc_taxes_hash'    => 'hash',
				]
			),
		];
	}

	private function storeApiCheckoutRequest(): array {
		$matching = array_values(
			array_filter(
				$this->httpRequests(),
				static function ( $request ) {
					return false !== strpos( $request['url'], 'wc/store/v1/checkout' );
				}
			)
		);
		$this->assertCount( 1, $matching, 'The order is placed with one Store API checkout request.' );

		return $matching[0];
	}
}
