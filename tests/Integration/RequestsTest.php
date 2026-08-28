<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\OrderManagement\Request\Get\RequestGetOrder;
use Tests\Support\IntegrationTestCase;

/**
 * How a request signs itself, where it is sent, and the payload builder behind it.
 * KCO_Request is abstract, so the routing runs through KCO_Request_Create.
 *
 * @covers \KCO_Request::get_api_url_base
 * @covers \KCO_Request::get_request_headers
 * @covers \KCO_Request_Cart
 */
class RequestsTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	protected function setUp(): void {
		parent::setUp();

		$this->haveCustomerAddress( $this->swedishAddress(), $this->swedishAddress() );
	}

	/**
	 * Which host a request goes to. There is one API host, and testmode picks the
	 * playground subdomain of it.
	 *
	 * @dataProvider provide_endpoint_routing
	 */
	public function test_testmode_decides_the_host( bool $testmode, string $expected ): void {
		$this->haveGatewayCredentials( 'eu', [], $testmode );

		$this->assertSame( $expected, ( new \KCO_Request_Create() )->get_api_url_base() );
	}

	/** @return array<string, array{0: bool, 1: string}> */
	public function provide_endpoint_routing(): array {
		return [
			'test mode uses the playground host' => [ true, 'https://api.playground.kustom.co/' ],
			'live mode drops the subdomain'      => [ false, 'https://api.kustom.co/' ],
		];
	}

	/** Order management routes itself the same way, off the order's stored environment. */
	public function test_order_management_routes_off_the_orders_environment(): void {
		$order = $this->haveGatewayOrder();

		$request = new RequestGetOrder( KCO_WC()->order_management, [ 'order_id' => $order->get_id() ] );

		$this->assertSame(
			'https://api.playground.kustom.co/ordermanagement/v1/orders/kustom-order-123',
			$this->readRequestUrl( $request )
		);
	}

	/**
	 * Which merchant account a request signs itself with, read back out of the header.
	 *
	 * @dataProvider provide_signing
	 */
	public function test_signs_the_request_with_the_resolved_credentials( array $settings, array $expected ): void {
		$this->setGatewaySettings( $settings );

		$this->assertSame( $expected, $this->decodedAuth() );
	}

	/** @return array<string, array{0: array, 1: array}> */
	public function provide_signing(): array {
		$both_modes = [
			'merchant_id_eu'        => 'live-mid',
			'shared_secret_eu'      => 'live-secret',
			'test_merchant_id_eu'   => 'test-mid',
			'test_shared_secret_eu' => 'test-secret',
		];

		return [
			'test mode signs with the test keys' => [ array_merge( [ 'testmode' => 'yes' ], $both_modes ), [ 'test-mid', 'test-secret' ] ],
			'live mode signs with the live keys' => [ array_merge( [ 'testmode' => 'no' ], $both_modes ), [ 'live-mid', 'live-secret' ] ],
		];
	}

	public function test_every_request_names_the_partner(): void {
		$headers = $this->requestHeaders();

		$this->assertSame( 'PG000651', $headers['kustom-partner'] );
		$this->assertSame( 'application/json', $headers['Content-Type'] );
	}

	/**
	 * @dataProvider provide_numeric_coercions
	 *
	 * @param mixed $value    The value to coerce.
	 * @param mixed $expected What it has to become before it reaches the API.
	 */
	public function test_amounts_are_coerced_to_numbers( $value, $expected ): void {
		$this->assertSame( $expected, kco_ensure_numeric( $value ) );
	}

	/** @return array<string, array{0: mixed, 1: mixed}> */
	public function provide_numeric_coercions(): array {
		return [
			'an int passes through as a float' => [ 25000, 25000.0 ],
			'a numeric string is converted'    => [ '25000', 25000.0 ],
			'a decimal string is converted'    => [ '250.55', 250.55 ],
			// Empty is 0 rather than the default, so a missing amount is not a silent fallback.
			'an empty string is zero'          => [ '', 0 ],
			'null is zero'                     => [ null, 0 ],
			'a non-numeric string is zero'     => [ 'not-a-number', 0.0 ],
			'a negative amount survives'       => [ -10000, -10000.0 ],
		];
	}

	public function test_the_order_lines_add_up_to_the_order_amount(): void {
		$this->haveCartWith(
			[
				[ $this->haveSimpleProduct( [ 'price' => '100.00' ] ), 2 ],
				[ $this->haveSimpleProduct( [ 'price' => '59.50' ] ), 3 ],
			]
		);
		$this->haveCartFee( 'Handling fee', 25.00 );
		$this->haveChosenFlatRateShipping( 'SE', '50.00' );
		$this->recalculateCart();

		$cart = new \KCO_Request_Cart();
		$cart->process_data();

		$this->assertSame(
			$cart->get_order_amount(),
			$cart->get_order_lines_total_amount(),
			'The API rejects an order whose lines do not sum to the order amount.'
		);
	}

	/**
	 * The body a create-order request carries.
	 *
	 * @dataProvider provide_order_bodies
	 */
	public function test_the_order_body_matches_the_snapshot( string $scenario ): void {
		$placeholders = $this->arrangeOrderBody( $scenario );

		$this->willCreateOrder();
		KCO_WC()->api->create_klarna_order();

		$this->assertRequestMatchesSnapshot(
			$this->gatewayRequestTo( '/checkout/v3/orders' ),
			'create-' . $scenario,
			$placeholders
		);
	}

	/** @return array<string, array{0: string}> */
	public function provide_order_bodies(): array {
		return [
			'b2c SE'         => [ 'se-b2c' ],
			'b2b SE'         => [ 'se-b2b' ],
			'US sales tax'   => [ 'us-sales-tax' ],
			'cart fee'       => [ 'cart-fee' ],
			'no SKU'         => [ 'no-sku' ],
			'shipping'       => [ 'shipping' ],
			'coupon'         => [ 'coupon' ],
		];
	}

	/** @return array<string, scalar> Volatile values to mask out of the snapshot. */
	private function arrangeOrderBody( string $scenario ): array {
		$product = $this->haveSimpleProduct( [ 'name' => 'Kustom Test Product', 'sku' => 'kco-test-1', 'price' => '100.00' ] );

		switch ( $scenario ) {
			case 'se-b2b':
				$this->haveGatewayCredentials( 'eu', [ 'allowed_customer_types' => 'B2B' ] );
				$this->flushGatewaySettingsCache();
				break;
			case 'us-sales-tax':
				$this->deleteAllTaxRates();
				$this->configureUsStore();
				$this->haveGatewayCredentials( 'us' );
				$this->flushGatewaySettingsCache();
				$this->haveCustomerAddress( $this->usAddress(), $this->usAddress() );
				break;
			case 'cart-fee':
				$this->haveCartFee( 'Handling fee', 25.00 );
				break;
			case 'no-sku':
				$product = $this->haveSimpleProduct( [ 'name' => 'No SKU Product', 'sku' => '', 'price' => '100.00' ] );
				break;
		}

		$this->haveCartWith( [ [ $product, 2 ] ] );

		if ( 'shipping' === $scenario ) {
			$this->haveChosenFlatRateShipping( 'SE', '50.00' );
		}

		if ( 'coupon' === $scenario ) {
			$this->haveAppliedCoupon( 'kco-request-10', 10 );
		}

		$this->resetHttpInterception();

		return [ '<product-id>' => $product->get_id() ];
	}

	private function haveAppliedCoupon( string $code, int $percent ): void {
		$coupon = new \WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( $percent );
		$coupon->save();

		WC()->cart->apply_coupon( $code );
		$this->recalculateCart();
	}

	/**
	 * The Basic auth header decoded back into a merchant id / shared secret pair.
	 *
	 * @return array{0: string, 1: string}
	 */
	private function decodedAuth(): array {
		$header = $this->requestHeaders()['Authorization'];

		$this->assertStringStartsWith( 'Basic ', $header );

		return explode( ':', base64_decode( substr( $header, strlen( 'Basic ' ) ) ), 2 );
	}

	/** @return array<string, string> */
	private function requestHeaders(): array {
		$method = new \ReflectionMethod( \KCO_Request::class, 'get_request_headers' );
		$method->setAccessible( true );

		return $method->invoke( new \KCO_Request_Create() );
	}

	private function readRequestUrl( object $request ): string {
		$method = new \ReflectionMethod( $request, 'get_request_url' );
		$method->setAccessible( true );

		return $method->invoke( $request );
	}
}
