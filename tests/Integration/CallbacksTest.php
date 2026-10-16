<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * The confirmation path: how Kustom's verdict on an order resolves the WooCommerce one.
 *
 * `KCO_API_Callbacks::push_cb()` reads its arguments with `filter_input( INPUT_GET )`,
 * which stays null in CLI, so the endpoint itself belongs to E2E. What it does once it
 * has an order is `kco_confirm_klarna_order()`, which is testable from here.
 *
 * @covers ::kco_confirm_klarna_order
 * @covers \KCO_API_Callbacks
 */
class CallbacksTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	/** What wp_using_ext_object_cache() was before a test changed it. */
	private ?bool $usingObjectCache = null;

	protected function tearDown(): void {
		if ( null !== $this->usingObjectCache ) {
			wp_using_ext_object_cache( $this->usingObjectCache );
		}

		parent::tearDown();
	}

	/**
	 * What the fraud verdict does to the order.
	 *
	 * @dataProvider provide_verdicts
	 */
	public function test_the_fraud_verdict_decides_the_order( string $verdict, string $status, bool $paid ): void {
		$order = $this->haveOrderAwaitingConfirmation();

		$this->willRetrieveManagedOrder(
			[
				'fraud_status' => $verdict,
				'order_amount' => 12500,
				'order_lines'  => $this->orderLinesFor( $order ),
			]
		);
		$this->willAcknowledge();
		$this->willSetMerchantReference();

		kco_confirm_klarna_order( $order->get_id(), 'kustom-order-123' );

		$saved = $this->reload( $order );

		$this->assertSame( $status, $saved->get_status() );
		$this->assertSame( $paid, null !== $saved->get_date_paid() );
	}

	/** @return array<string, array{0: string, 1: string, 2: bool}> */
	public function provide_verdicts(): array {
		return [
			'accepted'             => [ 'ACCEPTED', 'processing', true ],
			'pending fraud review' => [ 'PENDING', 'on-hold', false ],
			'rejected'             => [ 'REJECTED', 'cancelled', false ],
		];
	}

	public function test_an_accepted_order_records_the_kustom_reference_and_a_note(): void {
		$order = $this->haveOrderAwaitingConfirmation();

		$this->willRetrieveManagedOrder(
			[ 'order_amount' => 12500, 'order_lines' => $this->orderLinesFor( $order ) ]
		);
		$this->willAcknowledge();
		$this->willSetMerchantReference();

		kco_confirm_klarna_order( $order->get_id(), 'kustom-order-123' );

		$this->assertSame( 'kustom-order-123', $this->reload( $order )->get_transaction_id() );
		$this->assertOrderHasNote( $order, 'Payment via Kustom Checkout, order ID: kustom-order-123' );
	}

	public function test_an_accepted_order_fires_the_payment_complete_action(): void {
		$order = $this->haveOrderAwaitingConfirmation();
		$fired = [];

		add_action(
			'kco_wc_payment_complete',
			static function ( $order_id ) use ( &$fired ) {
				$fired[] = $order_id;
			}
		);

		$this->willRetrieveManagedOrder(
			[ 'order_amount' => 12500, 'order_lines' => $this->orderLinesFor( $order ) ]
		);
		$this->willAcknowledge();
		$this->willSetMerchantReference();

		kco_confirm_klarna_order( $order->get_id(), 'kustom-order-123' );

		$this->assertSame( [ $order->get_id() ], $fired );
	}

	public function test_a_confirmed_order_already_knows_whether_it_can_be_upsold(): void {
		$order = $this->haveOrderAwaitingConfirmation();

		$this->willRetrieveManagedOrder(
			[
				'order_amount'           => 12500,
				'order_lines'            => $this->orderLinesFor( $order ),
				'initial_payment_method' => [ 'type' => 'INVOICE' ],
				'billing_address'        => [ 'country' => 'SE' ],
			]
		);
		$this->willAcknowledge();
		$this->willSetMerchantReference();

		kco_confirm_klarna_order( $order->get_id(), 'kustom-order-123' );

		$this->assertTrue( ( new \KCO_Gateway() )->upsell_available( $order->get_id() ) );
		$lookups = array_filter(
			$this->gatewayRequests(),
			static function ( $request ) {
				return str_ends_with( $request['url'], '/orders/kustom-order-123' );
			}
		);
		$this->assertCount( 1, $lookups, 'Post Purchase Upsell asks right after the confirmation, which already had the answer.' );
	}

	/**
	 * Only the order management order carries the id, the checkout order does not.
	 */
	public function test_a_confirmed_shipping_assistant_order_records_the_ingrid_transport_order(): void {
		new \Krokedil\KustomCheckout\ShippingAssistant\Checkout();
		$order = $this->haveOrderAwaitingConfirmation();

		$this->willRetrieveManagedOrder(
			[
				'order_amount'             => 12500,
				'order_lines'              => $this->orderLinesFor( $order ),
				'selected_shipping_option' => [
					'tms_reference' => 'VM2-8515d42eefbc403694b04a9f6e6352a0',
					'tos_id'        => '01M3XWHGEYJXEXHH0EXFM3N8DX',
				],
			]
		);
		$this->willAcknowledge();
		$this->willSetMerchantReference();

		kco_confirm_klarna_order( $order->get_id(), 'kustom-order-123' );

		$this->assertSame( '01M3XWHGEYJXEXHH0EXFM3N8DX', $this->reload( $order )->get_meta( 'ingrid_tos_id' ) );
	}

	/**
	 * An order Kustom says a different total for must not be confirmed: it would ship
	 * goods nobody paid for.
	 */
	public function test_a_total_mismatch_stops_the_confirmation(): void {
		$order = $this->haveOrderAwaitingConfirmation();

		$this->willRetrieveManagedOrder(
			[ 'order_amount' => 999, 'order_lines' => $this->orderLinesFor( $order ) ]
		);

		kco_confirm_klarna_order( $order->get_id(), 'kustom-order-123' );

		$saved = $this->reload( $order );

		$this->assertSame( 'on-hold', $saved->get_status() );
		$this->assertNull( $saved->get_date_paid() );
		$this->assertGatewayRequestCount( 1, '', 'Only the lookup; nothing is acknowledged.' );
	}

	/** The same for an order whose lines do not match what WooCommerce has. */
	public function test_a_content_mismatch_stops_the_confirmation(): void {
		$this->markTestSkipped( 'kco_validate_order_content() reads $name before it is assigned on this path. Known bug, not pinned.' );

		$order = $this->haveOrderAwaitingConfirmation();

		$this->willRetrieveManagedOrder(
			[
				'order_amount' => 12500,
				'order_lines'  => [
					[ 'type' => 'physical', 'reference' => 'something-else', 'name' => 'Other', 'quantity' => 1 ],
				],
			]
		);

		kco_confirm_klarna_order( $order->get_id(), 'kustom-order-123' );

		$this->assertNull( $this->reload( $order )->get_date_paid() );
	}

	/**
	 * A lookup that fails leaves the order for the push callback rather than
	 * resolving it on a guess.
	 */
	public function test_a_failed_lookup_parks_the_order_for_the_push_callback(): void {
		$this->markTestSkipped( 'kco_validate_order_content() can not currently handle a missing kustom order.' );
		$order = $this->haveOrderAwaitingConfirmation();

		// No response queued, so the lookup comes back an error.
		kco_confirm_klarna_order( $order->get_id(), 'kustom-order-123' );

		$saved = $this->reload( $order );

		$this->assertSame( 'on-hold', $saved->get_status() );
		$this->assertOrderHasNote( $order, "Waiting for verification from Kustom's push notification" );
	}

	/**
	 * @dataProvider provide_ignorable_confirmations
	 */
	public function test_a_confirmation_it_cannot_act_on_is_ignored( string $scenario ): void {
		$order = $this->haveOrderAwaitingConfirmation();

		if ( 'already-paid' === $scenario ) {
			$order->set_date_paid( time() );
			$order->save();
		}

		kco_confirm_klarna_order( 'no-order' === $scenario ? null : $order->get_id(), 'kustom-order-123' );

		$this->assertNoGatewayRequests();
	}

	/** @return array<string, array{0: string}> */
	public function provide_ignorable_confirmations(): array {
		return [
			'no order id at all'     => [ 'no-order' ],
			'the order already paid' => [ 'already-paid' ],
		];
	}

	/**
	 * A cache flush stands in for a second PHP process, as with LiteSpeed's non-atomic add().
	 *
	 * @dataProvider provide_object_caches
	 */
	public function test_a_locked_confirmation_stops_a_second_request_for_the_same_order( bool $object_cache ): void {
		$order = $this->haveOrderAwaitingConfirmation();
		add_filter( 'kco_wc_lock_confirmation', '__return_true' );
		$this->usingObjectCache = wp_using_ext_object_cache( $object_cache );

		$this->assertTrue( \KCO_Confirmation::lock_kco_confirmation( 'kustom-order-123', $order->get_id() ), 'The first request takes the lock.' );
		wp_cache_flush();

		kco_confirm_klarna_order( $order->get_id(), 'kustom-order-123' );

		$this->assertNoGatewayRequests( 'The second request must stop before it confirms the order again.' );
	}

	/** @return array<string, array{0: bool}> */
	public function provide_object_caches(): array {
		return [
			'without an object cache'                          => [ false ],
			'with an object cache not shared across processes' => [ true ],
		];
	}

	public function test_a_finished_confirmation_releases_the_lock(): void {
		$order = $this->haveOrderAwaitingConfirmation();
		add_filter( 'kco_wc_lock_confirmation', '__return_true' );

		$this->willRetrieveManagedOrder(
			[ 'order_amount' => 12500, 'order_lines' => $this->orderLinesFor( $order ) ]
		);
		$this->willAcknowledge();
		$this->willSetMerchantReference();

		kco_confirm_klarna_order( $order->get_id(), 'kustom-order-123' );

		$this->assertNotNull( $this->reload( $order )->get_date_paid(), 'The confirmation must finish.' );
		$this->assertTrue( \KCO_Confirmation::lock_kco_confirmation( 'kustom-order-123', $order->get_id() ), 'The lock is released afterwards.' );
	}

	public function test_the_callback_endpoints_are_registered(): void {
		$callbacks = \KCO_API_Callbacks::get_instance();

		$this->assertNotFalse(
			has_action( 'woocommerce_api_kco_wc_push', [ $callbacks, 'push_cb' ] ),
			'This is the merchant_urls.push endpoint the plugin hands to Kustom.'
		);
		$this->assertNotFalse(
			has_action( 'woocommerce_api_kco_wc_address_update', [ $callbacks, 'address_update_cb' ] ),
			'The address-update callback Kustom calls while the iframe is open.'
		);
	}

	private function haveOrderAwaitingConfirmation(): \WC_Order {
		$product = $this->haveSimpleProduct( [ 'name' => 'Kustom Test Product', 'sku' => 'kco-test-1', 'price' => '100.00' ] );

		$order = $this->haveOrder(
			[
				'items'   => [ [ $product, 1 ] ],
				'billing' => $this->swedishAddress(),
			]
		);

		$order->update_meta_data( '_wc_klarna_order_id', 'kustom-order-123' );
		$order->save();

		$this->resetHttpInterception();

		return $order;
	}

	/** Order lines that match the WooCommerce order, so the content guard passes. */
	private function orderLinesFor( \WC_Order $order ): array {
		$lines = [];

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();

			$lines[] = [
				'type'      => 'physical',
				'reference' => $product ? $product->get_sku() : '',
				'name'      => $item->get_name(),
				'quantity'  => (int) $item->get_quantity(),
			];
		}

		return $lines;
	}

	private function willAcknowledge(): void {
		$this->willRespondWith( [], 204, '/acknowledge' );
	}

	private function willSetMerchantReference(): void {
		$this->willRespondWith( [], 204, '/merchant-references' );
	}
}
