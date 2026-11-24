<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\ShippingAssistant\API\Controllers\ShippingOptionUpdateController;
use Tests\Support\IntegrationTestCase;

/**
 * The KSA shipping option update callback, which anyone can call with a known Kustom order id.
 *
 * @covers \Krokedil\KustomCheckout\ShippingAssistant\API\Controllers\ShippingOptionUpdateController
 */
class ShippingOptionUpdateTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	protected function tearDown(): void {
		delete_transient( 'kss_override_data_checkout-order-123' );

		parent::tearDown();
	}

	public function test_the_shipping_price_comes_from_kustom_not_the_caller(): void {
		$this->willRetrieveCheckoutOrder();

		[ $allowed, $response ] = $this->sendCallback(
			[
				'selected_shipping_option' => [
					'id'    => 'free',
					'name'  => 'Free shipping',
					'price' => 0,
				],
				'order_lines'              => [
					[
						'type'         => 'physical',
						'unit_price'   => 100,
						'total_amount' => 100,
					],
				],
			]
		);

		$this->assertTrue( $allowed );
		$this->assertSame( 200, $response->get_status() );

		$override = get_transient( 'kss_override_data_checkout-order-123' );
		$this->assertSame( 'Parcel locker delivery', $override['name'] ?? null, 'The stored override must be the option selected in Kustom.' );
		$this->assertSame( 2900, $override['price'] ?? null, 'The stored override must carry the price Kustom charges.' );

		$body = $response->get_data();
		$this->assertSame( 2900, $this->findOrderLine( $body['order_lines'], 'shipping_fee' )['total_amount'] ?? null );
		$this->assertSame( 15400, $body['order_amount'], 'The order lines come from the Kustom order, not the request.' );
		$this->assertSame( $body['order_amount'], array_sum( array_column( $body['order_lines'], 'total_amount' ) ) );
	}

	public function test_the_free_trial_tag_on_the_kustom_order_makes_shipping_free(): void {
		$this->willRetrieveCheckoutOrder( [ 'tags' => [ 'ksa_subscription_free_trial_shipping' ] ] );

		[ , $response ] = $this->sendCallback();

		$this->assertSame( 0, $this->findOrderLine( $response->get_data()['order_lines'], 'shipping_fee' )['total_amount'] ?? null );
	}

	public function test_a_caller_cannot_claim_the_free_trial_tag(): void {
		$this->willRetrieveCheckoutOrder();

		[ , $response ] = $this->sendCallback( [ 'tags' => [ 'ksa_subscription_free_trial_shipping' ] ] );

		$this->assertSame( 2900, $this->findOrderLine( $response->get_data()['order_lines'], 'shipping_fee' )['total_amount'] ?? null );
	}

	public function test_a_kustom_order_without_a_selected_option_stores_nothing(): void {
		$this->willRetrieveCheckoutOrder( [], [ 'selected_shipping_option' ] );

		[ , $response ] = $this->sendCallback( [ 'selected_shipping_option' => [ 'id' => 'free', 'price' => 0 ] ] );

		$this->assertSame( 500, $response->get_status() );
		$this->assertFalse( get_transient( 'kss_override_data_checkout-order-123' ) );
	}

	public function test_a_completed_kustom_order_is_refused(): void {
		$this->willRetrieveCheckoutOrder( [ 'status' => 'checkout_complete' ] );

		[ $allowed ] = $this->sendCallback();

		$this->assertFalse( $allowed );
	}

	public function test_an_order_kustom_was_not_told_to_call_back_for_is_refused(): void {
		$this->willRetrieveCheckoutOrder( [ 'merchant_urls' => [ 'checkout' => home_url( '/checkout/' ) ] ] );

		[ $allowed ] = $this->sendCallback();

		$this->assertFalse( $allowed, 'Nothing would refresh the override on an order Kustom never calls back for.' );
		$this->assertFalse( get_transient( 'kss_override_data_checkout-order-123' ) );
	}

	/**
	 * Runs the permission check and then the handler, as the REST server does.
	 *
	 * @return array{0: bool, 1: \WP_REST_Response|null}
	 */
	private function sendCallback( array $body = [] ): array {
		$request = new \WP_REST_Request( 'POST', '/kustom/shipping-service/v1/callback/shipping-option-update' );
		$request->set_query_params( [ 'kco_id' => 'checkout-order-123' ] );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );

		$controller = new ShippingOptionUpdateController();
		$allowed    = $controller->verify_request( $request );

		return [ $allowed, $allowed ? $controller->handle_shipping_option_update( $request ) : null ];
	}

	/** Queues the one Kustom lookup the callback makes, with the shopper's choice already on it. */
	private function willRetrieveCheckoutOrder( array $overrides = [], array $omit = [] ): void {
		$this->willRespondWith(
			$this->kustomRetrievedOrder(
				array_merge(
					[
						'merchant_urls'            => [
							'shipping_option_update' => home_url( '/wp-json/kustom/shipping-service/v1/callback/shipping-option-update?kco_id={checkout.order.id}' ),
						],
						'billing_address'          => [ 'country' => 'SE' ],
						'shipping_address'         => [ 'country' => 'SE' ],
						'tags'                     => [],
						'selected_shipping_option' => [
							'id'         => 'parcel-locker',
							'name'       => 'Parcel locker delivery',
							'price'      => 2900,
							'tax_rate'   => 2500,
							'tax_amount' => 580,
						],
					],
					$overrides
				),
				$omit
			),
			200,
			'/checkout/v3/orders/checkout-order-123'
		);
	}
}
