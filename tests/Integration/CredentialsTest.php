<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * Which merchant account a request signs itself with. The credential set is derived
 * from the store's base location, not from the shopper: `US` reads the `us` pair and
 * everything else the `eu` one.
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
			'merchant_id_eu'        => 'live-mid-eu',
			'shared_secret_eu'      => 'live-secret-eu',
			'test_merchant_id_eu'   => 'test-mid-eu',
			'test_shared_secret_eu' => 'test-secret-eu',
			'merchant_id_us'        => 'live-mid-us',
			'shared_secret_us'      => 'live-secret-us',
			'test_merchant_id_us'   => 'test-mid-us',
			'test_shared_secret_us' => 'test-secret-us',
		];

		$pair = static fn( string $mid, string $secret ): array => [
			'merchant_id'   => $mid,
			'shared_secret' => $secret,
		];

		return [
			'test mode signs with the test keys' => [ 'SE', array_merge( [ 'testmode' => 'yes' ], $both_modes ), $pair( 'test-mid-eu', 'test-secret-eu' ) ],
			'live mode signs with the live keys' => [ 'SE', array_merge( [ 'testmode' => 'no' ], $both_modes ), $pair( 'live-mid-eu', 'live-secret-eu' ) ],
			'a US store reads the us pair'       => [ 'US', array_merge( [ 'testmode' => 'yes' ], $both_modes ), $pair( 'test-mid-us', 'test-secret-us' ) ],
			// Every non-US base country resolves to the same 'eu' credential set.
			'a German store still reads eu'      => [ 'DE', array_merge( [ 'testmode' => 'yes' ], $both_modes ), $pair( 'test-mid-eu', 'test-secret-eu' ) ],
			'testmode defaults to live keys'     => [ 'SE', $both_modes, $pair( 'live-mid-eu', 'live-secret-eu' ) ],
			'no credentials at all'              => [ 'SE', [ 'testmode' => 'yes' ], false ],
			'a half-filled pair is refused'      => [ 'SE', [ 'testmode' => 'yes', 'test_merchant_id_eu' => 'mid-eu' ], false ],
			// WooCommerce settings sanitisation HTML-encodes ampersands and quotes.
			'an HTML-encoded secret is decoded'  => [ 'SE', [ 'testmode' => 'yes', 'test_merchant_id_eu' => 'mid-eu', 'test_shared_secret_eu' => 'a&amp;b&quot;c' ], $pair( 'mid-eu', 'a&b"c' ) ],
		];
	}

	public function test_the_resolved_credentials_can_be_replaced_by_filter(): void {
		$this->configureStore( [ 'country' => 'SE', 'currency' => 'SEK', 'calc_taxes' => false ] );
		$this->haveGatewayCredentials( 'eu' );

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
