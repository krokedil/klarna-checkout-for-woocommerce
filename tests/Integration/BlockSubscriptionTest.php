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
		$draft   = $this->haveDraftOrderToValidate( $product );

		OrderValidation::validate_kco_order( 'checkout-order-123' );

		$request = $this->storeApiCheckoutRequest();
		$this->assertStringEndsWith(
			'wc/store/v1/checkout',
			$request['url'],
			"Only the create route creates the guest's account and the recurring carts WooCommerce Subscriptions needs, and it reuses draft {$draft->get_id()} from the session."
		);
		$this->assertArrayNotHasKey( 'key', $request['json'], 'The order key is only sent to pay an existing order.' );
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
	private function haveDraftOrderToValidate( \WC_Product $product ): \WC_Order {
		$token = BlocksUtility::create_cart_token( (string) WC()->session->get_customer_id() );
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
		$this->willRetrieveOrder(
			[
				'status'              => 'checkout_incomplete',
				'merchant_reference2' => (string) $order->get_id(),
				'merchant_data'       => wp_json_encode(
					[
						'wc_cart_token'    => $token,
						'wc_cart_hash'     => $order->get_cart_hash(),
						'wc_shipping_hash' => 'hash',
						'wc_fees_hash'     => 'hash',
						'wc_coupons_hash'  => 'hash',
						'wc_taxes_hash'    => 'hash',
					]
				),
			]
		);
		$this->willRespondWith( [ 'order_id' => $order->get_id() ], 200, 'wc/store/v1/checkout' );

		return $order;
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
