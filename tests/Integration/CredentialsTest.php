<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * Kustom does not differentiate API keys by region, so the gateway signs every
 * request with the single configured credential pair, chosen only by test/live mode.
 *
 * @covers \KCO_Credentials::get_credentials_from_session
 */
class CredentialsTest extends IntegrationTestCase {

	/**
	 * @dataProvider provide_credential_resolution
	 *
	 * @param mixed $expected The resolved pair, or false when nothing resolves.
	 */
	public function test_resolves_the_merchant_credentials( string $country, array $settings, $expected ): void {
		$this->configureStore( [ 'country' => $country, 'currency' => 'US' === $country ? 'USD' : 'SEK', 'calc_taxes' => false ] );
		$this->setGatewaySettings( $settings );

		$this->assertSame( $expected, ( new \KCO_Credentials() )->get_credentials_from_session() );
	}

	/** @return array<string, array{0: string, 1: array, 2: mixed}> */
	public function provide_credential_resolution(): array {
		$both_modes = [
			'merchant_id'        => 'live-mid',
			'shared_secret'      => 'live-secret',
			'test_merchant_id'   => 'test-mid',
			'test_shared_secret' => 'test-secret',
		];

		$pair = static fn( string $mid, string $secret ): array => [
			'merchant_id'   => $mid,
			'shared_secret' => $secret,
		];

		return [
			'test mode signs with the test keys' => [ 'SE', array_merge( [ 'testmode' => 'yes' ], $both_modes ), $pair( 'test-mid', 'test-secret' ) ],
			'live mode signs with the live keys' => [ 'SE', array_merge( [ 'testmode' => 'no' ], $both_modes ), $pair( 'live-mid', 'live-secret' ) ],
			// The credential set does not depend on the store's base country.
			'a US store reads the same pair'     => [ 'US', array_merge( [ 'testmode' => 'yes' ], $both_modes ), $pair( 'test-mid', 'test-secret' ) ],
			'testmode defaults to live keys'     => [ 'SE', $both_modes, $pair( 'live-mid', 'live-secret' ) ],
			'no credentials at all'              => [ 'SE', [ 'testmode' => 'yes' ], false ],
			'a half-filled pair is refused'      => [ 'SE', [ 'testmode' => 'yes', 'test_merchant_id' => 'mid' ], false ],
			// WooCommerce settings sanitisation HTML-encodes ampersands and quotes.
			'an HTML-encoded secret is decoded'  => [ 'SE', [ 'testmode' => 'yes', 'test_merchant_id' => 'mid', 'test_shared_secret' => 'a&amp;b&quot;c' ], $pair( 'mid', 'a&b"c' ) ],
		];
	}

	public function test_the_resolved_credentials_can_be_replaced_by_filter(): void {
		$this->configureStore( [ 'country' => 'SE', 'currency' => 'SEK', 'calc_taxes' => false ] );
		$this->haveGatewayCredentials();

		add_filter(
			'kco_wc_credentials_from_session',
			static fn() => [
				'merchant_id'   => 'filtered-mid',
				'shared_secret' => 'filtered-secret',
			]
		);

		$this->assertSame(
			[
				'merchant_id'   => 'filtered-mid',
				'shared_secret' => 'filtered-secret',
			],
			( new \KCO_Credentials() )->get_credentials_from_session()
		);
	}
}
