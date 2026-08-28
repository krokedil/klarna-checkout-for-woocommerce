<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * Whether the gateway is available at checkout, and whether the cart it is offered
 * for actually needs paying for.
 *
 * @covers \KCO_Gateway::is_available
 * @covers ::kco_cart_needs_payment
 */
class GatewayAvailabilityTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se-no-tax';

	/**
	 * @dataProvider provide_availability
	 */
	public function test_whether_the_gateway_offers_itself( array $overrides, bool $on_checkout, bool $expected ): void {
		$this->haveGatewayCredentials( 'eu', $overrides );

		if ( $on_checkout ) {
			$this->simulateCheckoutPage();
		}

		$this->assertSame( $expected, $this->gateway()->is_available() );

		// Checkout renders on every page load, so a request here would block it on an API round trip.
		$this->assertNoGatewayRequests();
	}

	/** @return array<string, array{0: array, 1: bool, 2: bool}> */
	public function provide_availability(): array {
		$blank_credentials = [
			'test_merchant_id_eu'   => '',
			'test_shared_secret_eu' => '',
		];

		return [
			'the happy path'                          => [ [], true, true ],
			'the gateway is disabled'                 => [ [ 'enabled' => 'no' ], true, false ],
			// The credential check only runs on the checkout page, so elsewhere it stays available.
			'no credentials, on the checkout page'    => [ $blank_credentials, true, false ],
			'no credentials, away from the checkout'  => [ $blank_credentials, false, true ],
		];
	}

	/**
	 * A store whose base country has no credential set cannot serve the checkout.
	 */
	public function test_a_store_without_a_credential_set_for_its_country_is_unavailable(): void {
		$this->configureStore( [ 'country' => 'US:CA', 'currency' => 'USD', 'calc_taxes' => false ] );

		// Only the EU pair is configured, and a US store reads the 'us' one.
		$this->haveGatewayCredentials( 'eu' );
		$this->simulateCheckoutPage();

		$this->assertFalse( $this->gateway()->is_available() );
	}

	/**
	 * Whether the cart needs paying for at all, which is what decides between the
	 * checkout and the free-order path.
	 *
	 * @dataProvider provide_payment_need
	 */
	public function test_whether_the_cart_needs_payment( string $scenario, bool $expected ): void {
		if ( 'priced' === $scenario ) {
			$this->haveCartWith( [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ] );
		}

		if ( 'free' === $scenario ) {
			$this->haveCartWith( [ $this->haveSimpleProduct( [ 'price' => '0.00' ] ) ] );
		}

		if ( 'free-subscription' === $scenario ) {
			$this->haveCartWith( [ $this->haveSimpleProduct( [ 'price' => '0.00' ] ) ] );
			$this->haveCartContaining( 'renewal' );
		}

		$this->assertSame( $expected, kco_cart_needs_payment() );
	}

	/** @return array<string, array{0: string, 1: bool}> */
	public function provide_payment_need(): array {
		return [
			'an empty cart'          => [ 'empty', false ],
			'a priced cart'          => [ 'priced', true ],
			'a free cart'            => [ 'free', false ],
			// A subscription always needs payment, so the token can be minted.
			'a free subscription'    => [ 'free-subscription', true ],
		];
	}

	/**
	 * The gateway is registered under the id order management and the metabox both
	 * match orders on.
	 */
	public function test_the_gateway_is_registered_under_its_own_id(): void {
		$this->haveGatewayCredentials( 'eu' );
		$this->reloadPaymentGateways();

		$this->assertArrayHasKey( 'kco', WC()->payment_gateways()->payment_gateways() );
	}

	private function gateway(): \KCO_Gateway {
		$this->flushGatewaySettingsCache();

		return new \KCO_Gateway();
	}
}
