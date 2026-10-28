<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\Elements\KeyValidator;
use Tests\Support\IntegrationTestCase;

/**
 * How a Kustom Elements public API key is verified, and how long the answer is trusted.
 *
 * @covers \Krokedil\KustomCheckout\Elements\KeyValidator
 * @covers \Krokedil\KustomCheckout\Elements\Settings::sanitize_public_api_keys
 */
class ElementsKeyValidatorTest extends IntegrationTestCase {

	/**
	 * Expirations passed to set_transient() for key statuses, in order.
	 *
	 * @var array<int, int>
	 */
	private $statusExpirations = [];

	protected function setUp(): void {
		parent::setUp();

		$this->statusExpirations = [];
		add_action( 'set_transient', [ $this, 'recordStatusExpiration' ], 10, 3 );
	}

	protected function tearDown(): void {
		remove_action( 'set_transient', [ $this, 'recordStatusExpiration' ], 10 );

		parent::tearDown();
	}

	/** The `set_transient` callback. Public so WordPress can call it. */
	public function recordStatusExpiration( $transient, $value, $expiration ): void {
		if ( 0 === strpos( $transient, 'kco_elements_key_status_' ) ) {
			$this->statusExpirations[] = $expiration;
		}
	}

	/**
	 * What Kustom answers, and the status the merchant is shown for it.
	 *
	 * @dataProvider provide_responses
	 */
	public function test_the_response_decides_the_status( array $body, int $status, string $expected ): void {
		$this->willRespondWith( $body, $status, 'kustom-elements-api' );

		$this->assertSame( $expected, KeyValidator::validate( $this->freshKey(), true ) );
	}

	/** @return array<string, array{0: array, 1: int, 2: string}> */
	public function provide_responses(): array {
		return [
			'the key and domain are accepted' => [ [ 'placements' => [] ], 200, KeyValidator::STATUS_OK ],
			'the key does not exist'          => [ [ 'code' => 'CLIENT_NOT_FOUND', 'message' => 'Client with id pk not found' ], 401, KeyValidator::STATUS_INVALID_KEY ],
			'the domain is not allowed'       => [ [ 'code' => 'ORIGIN_NOT_ALLOWED' ], 403, KeyValidator::STATUS_ORIGIN_NOT_ALLOWED ],
			'an error the plugin cannot map'  => [ [ 'code' => 'CONSTRAINT_VIOLATION' ], 400, KeyValidator::STATUS_UNKNOWN ],
			'an outage without an error code' => [ [], 503, KeyValidator::STATUS_UNKNOWN ],
		];
	}

	public function test_a_network_failure_is_not_reported_as_a_bad_key(): void {
		// No queued response, so the request comes back as a WP_Error.
		$status = KeyValidator::validate( $this->freshKey(), true );

		$this->assertSame( KeyValidator::STATUS_UNKNOWN, $status );
		$this->assertFalse( KeyValidator::is_error( $status ), 'A merchant must not be told to replace a key Kustom never answered for.' );
	}

	public function test_a_missing_key_is_not_sent_to_kustom(): void {
		$this->assertSame( KeyValidator::STATUS_MISSING, KeyValidator::get_status( '', true ) );
		$this->assertNoGatewayRequests();
	}

	/**
	 * The browser sends the shop's origin, so the check has to as well, or every domain looks allowed.
	 *
	 * @dataProvider provide_environments
	 */
	public function test_the_key_is_checked_against_its_environment_from_the_shops_origin( bool $testmode, string $host ): void {
		$key = $this->freshKey();
		$this->willRespondWith( [], 200, 'kustom-elements-api' );

		KeyValidator::validate( $key, $testmode );

		$request = $this->gatewayRequestTo( 'kustom-elements-api' );
		$this->assertSame( $host, wp_parse_url( $request['url'], PHP_URL_HOST ) );
		$this->assertStringContainsString( 'client_id=' . $key, $request['url'] );
		$this->assertSame( KeyValidator::get_origin(), $request['headers']['Origin'] );
	}

	/** @return array<string, array{0: bool, 1: string}> */
	public function provide_environments(): array {
		return [
			'test mode uses the playground host' => [ true, 'js.playground.kustom.co' ],
			'live mode uses the live host'       => [ false, 'js.live.kustom.co' ],
		];
	}

	public function test_a_status_is_reused_instead_of_asking_again(): void {
		$key = $this->freshKey();
		$this->willRespondWith( [], 200, 'kustom-elements-api' );

		KeyValidator::get_status( $key, true );
		$status = KeyValidator::get_status( $key, true );

		$this->assertSame( KeyValidator::STATUS_OK, $status );
		$this->assertGatewayRequestCount( 1, 'kustom-elements-api', 'The settings page renders the status on every load.' );
	}

	/**
	 * A definitive answer is kept for a day, a failed check is retried sooner.
	 *
	 * @dataProvider provide_expirations
	 */
	public function test_how_long_a_status_is_kept( array $body, int $status, int $expiration ): void {
		$this->willRespondWith( $body, $status, 'kustom-elements-api' );

		KeyValidator::get_status( $this->freshKey(), true );

		$this->assertSame( [ $expiration ], $this->statusExpirations );
	}

	/** @return array<string, array{0: array, 1: int, 2: int}> */
	public function provide_expirations(): array {
		return [
			'a working key'      => [ [], 200, DAY_IN_SECONDS ],
			'an unknown key'     => [ [ 'code' => 'CLIENT_NOT_FOUND' ], 401, DAY_IN_SECONDS ],
			'an unreachable API' => [ [], 503, 15 * MINUTE_IN_SECONDS ],
		];
	}

	/**
	 * Saving the settings rechecks a rejected key, since the merchant may have fixed it in the
	 * Kustom Portal, but leaves a working key alone so unrelated saves don't wait on Kustom.
	 *
	 * @dataProvider provide_saves
	 */
	public function test_saving_the_settings_only_rechecks_a_rejected_key( array $first_body, int $first_status, int $expected_requests ): void {
		$key = $this->freshKey();
		$this->willRespondWith( $first_body, $first_status, 'kustom-elements-api' );
		KeyValidator::get_status( $key, true );

		$this->resetHttpInterception();
		$this->willRespondWith( [], 200, 'kustom-elements-api' );
		apply_filters( 'woocommerce_settings_api_sanitized_fields_kco', [ 'elements_playground_public_api_key' => $key ] );

		$this->assertGatewayRequestCount( $expected_requests, 'kustom-elements-api' );
		$this->assertSame( KeyValidator::STATUS_OK, KeyValidator::get_status( $key, true ) );
	}

	/** @return array<string, array{0: array, 1: int, 2: int}> */
	public function provide_saves(): array {
		return [
			'a working key is not rechecked'         => [ [], 200, 0 ],
			'a key on a blocked domain is rechecked' => [ [ 'code' => 'ORIGIN_NOT_ALLOWED' ], 403, 1 ],
			'an unknown key is rechecked'            => [ [ 'code' => 'CLIENT_NOT_FOUND' ], 401, 1 ],
		];
	}

	/** A key no other test has cached a status for, since the options cache outlives the rollback. */
	private function freshKey(): string {
		return 'pk-' . md5( $this->getName() . wp_generate_uuid4() );
	}
}
