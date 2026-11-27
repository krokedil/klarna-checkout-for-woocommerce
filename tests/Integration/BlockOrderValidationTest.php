<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\Blocks\Api\Controllers\OrderController;
use Krokedil\KustomCheckout\Utility\BlocksUtility;
use Tests\Support\IntegrationTestCase;

/**
 * Kustom's validation callback for block checkout orders, and the reason it leaves in the log when it refuses one.
 *
 * @covers \Krokedil\KustomCheckout\Blocks\Api\Controllers\OrderController::validate
 * @covers \Krokedil\KustomCheckout\Blocks\OrderValidation::validate_kco_order
 */
class BlockOrderValidationTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	private const KUSTOM_ORDER_ID = 'checkout-order-123';

	protected function tearDown(): void {
		$this->restoreLogging();

		parent::tearDown();
	}

	/**
	 * @dataProvider provide_refusals
	 */
	public function test_a_refused_order_logs_why( string $kustom_order_id, array $merchant_data, array $retrieved, string $reason ): void {
		$order = $this->haveIframeOrderToValidate( $merchant_data, $retrieved );
		$this->haveLoggingEnabled();

		$response = ( new OrderController() )->validate( $this->validationRequest( $kustom_order_id ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'Failed to validate your order, please try again.', $response->get_data()['error_text'], 'The shopper keeps the generic message.' );
		$this->assertStringContainsString(
			sprintf( '[Blocks] - Order validation failed for Kustom order %s: %s', $kustom_order_id, str_replace( '<order>', (string) $order->get_id(), $reason ) ),
			$this->loggedText()
		);
	}

	/** @return array<string, array{0: string, 1: array, 2: array, 3: string}> */
	public function provide_refusals(): array {
		return [
			'no Kustom order id'           => [ '', [], [], 'Missing the Kustom order ID.' ],
			'the Kustom order unreachable' => [ self::KUSTOM_ORDER_ID, [], [ 'unreachable' => true ], 'Could not retrieve the Kustom order.' ],
			'no merchant data'             => [ self::KUSTOM_ORDER_ID, [], [ 'merchant_data' => '' ], 'The Kustom order has no merchant data.' ],
			'a deleted WooCommerce order'  => [ self::KUSTOM_ORDER_ID, [], [ 'merchant_reference2' => '999999' ], 'WooCommerce order 999999 does not exist.' ],
			'the order already paid'       => [ self::KUSTOM_ORDER_ID, [], [ 'paid' => true ], 'WooCommerce order <order> is already paid.' ],
			'the cart changed'             => [ self::KUSTOM_ORDER_ID, [ 'wc_cart_hash' => 'stale' ], [], 'Cart hash mismatch.' ],
			'the shipping changed'         => [ self::KUSTOM_ORDER_ID, [ 'wc_shipping_hash' => 'stale' ], [], 'Shipping hash mismatch.' ],
			'the fees changed'             => [ self::KUSTOM_ORDER_ID, [ 'wc_fees_hash' => 'stale' ], [], 'Fees hash mismatch.' ],
			'the coupons changed'          => [ self::KUSTOM_ORDER_ID, [ 'wc_coupons_hash' => 'stale' ], [], 'Coupons hash mismatch.' ],
			'the taxes changed'            => [ self::KUSTOM_ORDER_ID, [ 'wc_taxes_hash' => 'stale' ], [], 'Taxes hash mismatch.' ],
			'no shipping hash in merchant data' =>[ self::KUSTOM_ORDER_ID, [ 'wc_shipping_hash' => null ], [], 'Shipping hash mismatch.' ],
		];
	}

	public function test_a_refusal_on_the_express_route_is_labelled_express(): void {
		$this->haveLoggingEnabled();

		$request = new \WP_REST_Request( 'POST', '/kco-express/v1/validate/' );
		$request->set_param( 'id', '' );
		( new OrderController() )->validate( $request );

		$this->assertStringContainsString( '[Express] - Order validation failed for Kustom order : Missing the Kustom order ID.', $this->loggedText() );
	}

	public function test_a_valid_order_is_accepted_without_a_failure_line(): void {
		$this->haveIframeOrderToValidate( [], [] );
		$this->haveLoggingEnabled();

		$response = ( new OrderController() )->validate( $this->validationRequest( self::KUSTOM_ORDER_ID ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertStringNotContainsString( 'Order validation failed', $this->loggedText() );
	}

	/**
	 * A Kustom iframe order whose merchant data matches the order the Store API answers with,
	 * changed by the row's overrides. A null merchant data value drops the key.
	 */
	private function haveIframeOrderToValidate( array $merchant_data, array $retrieved ): \WC_Order {
		$token = BlocksUtility::create_cart_token( (string) WC()->session->get_customer_id() );
		$order = $this->haveOrder( [ 'items' => [ [ $this->haveSimpleProduct( [ 'sku' => 'block-validation' ] ), 1 ] ] ] );
		foreach ( [ '_shipping_hash', '_fees_hash', '_coupons_hash', '_taxes_hash' ] as $key ) {
			$order->update_meta_data( $key, 'hash' );
		}
		if ( ! empty( $retrieved['paid'] ) ) {
			$order->set_date_paid( time() );
			$retrieved['merchant_reference2'] = (string) $order->get_id();
		}
		$order->save();

		$merchant_data = array_filter(
			array_merge(
				[
					'wc_cart_token'    => $token,
					'wc_cart_hash'     => $order->get_cart_hash(),
					'wc_shipping_hash' => 'hash',
					'wc_fees_hash'     => 'hash',
					'wc_coupons_hash'  => 'hash',
					'wc_taxes_hash'    => 'hash',
				],
				$merchant_data
			),
			static function ( $value ) {
				return null !== $value;
			}
		);

		$this->resetHttpInterception();
		if ( empty( $retrieved['unreachable'] ) ) {
			$this->willRetrieveOrder(
				[
					'status'              => 'checkout_incomplete',
					'merchant_reference2' => $retrieved['merchant_reference2'] ?? '',
					'merchant_data'       => $retrieved['merchant_data'] ?? wp_json_encode( $merchant_data ),
				]
			);
		}
		$this->willRespondWith( [ 'order_id' => $order->get_id() ], 200, 'wc/store/v1/checkout' );

		return $order;
	}

	private function validationRequest( string $kustom_order_id ): \WP_REST_Request {
		$request = new \WP_REST_Request( 'POST', '/kco-block/order/' . $kustom_order_id . '/validate' );
		$request->set_param( 'id', $kustom_order_id );

		return $request;
	}
}
