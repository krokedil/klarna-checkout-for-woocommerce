<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * Creating, updating and reusing the Kustom order the iframe renders from. The
 * gateway has no session object of its own: the order id lives in the WooCommerce
 * session, and `kco_create_or_update_order()` decides whether to patch it or start
 * a new one.
 *
 * @covers ::kco_create_or_update_order
 * @covers \KCO_API::create_klarna_order
 * @covers \KCO_API::update_klarna_order
 */
class CheckoutOrderTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	protected function setUp(): void {
		parent::setUp();

		$this->haveCustomerAddress( $this->swedishAddress(), $this->swedishAddress() );
		$this->reloadPaymentGateways();
		$this->simulateCheckoutPage();
		$this->resetHttpInterception();
	}

	/**
	 * With no order in the session, the first call has to create one and remember it.
	 */
	public function test_the_first_call_creates_an_order_and_stores_its_reference(): void {
		$this->haveCartWith( [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ] );
		$this->resetHttpInterception();

		$this->willCreateOrder();

		$kustom_order = kco_create_or_update_order();

		$this->assertSame( 'checkout-order-123', $kustom_order['order_id'] );
		$this->assertSame( 'checkout-order-123', WC()->session->get( 'kco_wc_order_id' ) );
		$this->assertCallCounts( 1, 0 );
	}

	/**
	 * With one already in the session, the call has to patch it rather than start
	 * a second one: a new order id would orphan the iframe the shopper is looking at.
	 */
	public function test_a_second_call_updates_the_order_it_already_has(): void {
		$this->haveOrderInTheSession();

		$this->willUpdateOrder();

		kco_create_or_update_order();

		$this->assertCallCounts( 0, 1 );
		$this->assertStringEndsWith( '/checkout/v3/orders/checkout-order-123', $this->updateCalls()[0]['url'] );
	}

	/**
	 * An order the API will not patch any more is dead, and the shopper needs a new
	 * one rather than an error.
	 */
	public function test_an_order_that_cannot_be_updated_is_replaced(): void {
		$this->haveOrderInTheSession();

		// The patch is refused, which is what makes the order dead.
		$this->willRejectWith( '/checkout/v3/orders/checkout-order-123', 'Order is not allowed to be updated', 403 );
		$this->willCreateOrder( [ 'order_id' => 'checkout-order-456' ] );

		ob_start();
		$kustom_order = kco_create_or_update_order();
		ob_end_clean();

		$this->assertSame( 'checkout-order-456', $kustom_order['order_id'] );
		$this->assertSame( 'checkout-order-456', WC()->session->get( 'kco_wc_order_id' ) );
		$this->assertCallCounts( 1, 1 );
	}

	/**
	 * Both calls failing leaves nothing to render, and must not leave a reference
	 * behind that a later request would try to patch.
	 */
	public function test_nothing_is_stored_when_the_order_cannot_be_created(): void {
		$this->haveCartWith( [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ] );
		$this->resetHttpInterception();

		ob_start();
		$kustom_order = kco_create_or_update_order();
		ob_end_clean();

		$this->assertNull( $kustom_order );
		$this->assertEmpty( WC()->session->get( 'kco_wc_order_id' ) );
	}

	/**
	 * The cart is recalculated before the body is built, so an amount changed since
	 * the last page load reaches the API.
	 */
	public function test_the_body_carries_the_current_cart(): void {
		$this->haveCartWith( [ [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ), 2 ] ] );
		$this->haveChosenFlatRateShipping( 'SE', '50.00' );
		$this->resetHttpInterception();

		$this->willCreateOrder();
		kco_create_or_update_order();

		$body = $this->createCalls()[0]['json'];

		$this->assertSame( 'SE', $body['purchase_country'] );
		$this->assertSame( 'SEK', $body['purchase_currency'] );
		$this->assertSame(
			$body['order_amount'],
			array_sum( array_column( $body['order_lines'], 'total_amount' ) ),
			'The API rejects an order whose lines do not sum to the order amount.'
		);
		$this->assertHasOrderLine( $body['order_lines'], 'shipping_fee' );
	}

	/**
	 * The merchant URLs the API calls back on. Without a confirmation URL the
	 * shopper never returns from the iframe.
	 */
	public function test_the_body_carries_the_callback_urls(): void {
		$this->haveCartWith( [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ] );
		$this->resetHttpInterception();

		$this->willCreateOrder();
		kco_create_or_update_order();

		$urls = $this->createCalls()[0]['json']['merchant_urls'];

		foreach ( [ 'terms', 'checkout', 'confirmation', 'push' ] as $key ) {
			$this->assertNotEmpty( $urls[ $key ], $key );
		}

		$this->assertStringContainsString( '/wc-api/KCO_WC_Push/', $urls['push'] );
	}

	/**
	 * A US store bills sales tax as its own line rather than folding it into the
	 * item prices, which is a different order-line shape entirely.
	 */
	public function test_a_us_store_sends_sales_tax_as_its_own_line(): void {
		$this->deleteAllTaxRates();
		$this->configureUsStore();
		$this->haveGatewayCredentials( 'us' );
		$this->flushGatewaySettingsCache();
		$this->haveCustomerAddress( $this->usAddress(), $this->usAddress() );
		$this->reloadPaymentGateways();

		$this->haveCartWith( [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ] );
		$this->resetHttpInterception();

		$this->willCreateOrder();
		kco_create_or_update_order();

		$body = $this->createCalls()[0]['json'];

		$this->assertSame( 'US', $body['purchase_country'] );
		$this->assertHasOrderLine( $body['order_lines'], 'sales_tax' );
	}

	/**
	 * The cart-contents filters integrations hang their own data off.
	 */
	public function test_the_order_line_filters_are_applied(): void {
		$this->haveCartWith( [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ] );
		$this->resetHttpInterception();

		add_filter(
			'kco_wc_api_request_args',
			static function ( $args ) {
				$args['merchant_data'] = wp_json_encode( [ 'internal_id' => 'abc123' ] );
				return $args;
			}
		);

		$this->willCreateOrder();
		kco_create_or_update_order();

		$this->assertSame(
			wp_json_encode( [ 'internal_id' => 'abc123' ] ),
			$this->createCalls()[0]['json']['merchant_data']
		);
	}

	/** Puts a live order reference in the session, the way a rendered iframe does. */
	private function haveOrderInTheSession(): void {
		$this->haveCartWith( [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ] );
		WC()->session->set( 'kco_wc_order_id', 'checkout-order-123' );
		$this->resetHttpInterception();
	}

	private function willUpdateOrder(): void {
		$this->willRespondWith(
			[
				'order_id'     => 'checkout-order-123',
				'status'       => 'checkout_incomplete',
				'html_snippet' => '<div id="checkout-snippet"></div>',
			],
			200,
			'/checkout/v3/orders/checkout-order-123'
		);
	}

	private function createCalls(): array {
		return $this->callsMatching( '#/checkout/v3/orders$#' );
	}

	private function updateCalls(): array {
		return $this->callsMatching( '#/checkout/v3/orders/.+$#' );
	}

	private function callsMatching( string $pattern ): array {
		return array_values(
			array_filter(
				$this->httpRequests(),
				static fn( $request ) => (bool) preg_match( $pattern, $request['url'] )
			)
		);
	}

	private function assertCallCounts( int $creates, int $updates ): void {
		$this->assertSame(
			[ 'creates' => $creates, 'updates' => $updates ],
			[ 'creates' => count( $this->createCalls() ), 'updates' => count( $this->updateCalls() ) ],
			'Requests made: ' . implode( ', ', array_column( $this->httpRequests(), 'url' ) )
		);
	}
}
