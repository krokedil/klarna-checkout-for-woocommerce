<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\InPersonPayments\Request\Get\RequestGetDevices;
use Krokedil\KustomCheckout\InPersonPayments\Request\Get\RequestGetLocations;
use Krokedil\KustomCheckout\InPersonPayments\Request\Post\RequestPostEnrollment;
use Krokedil\KustomCheckout\InPersonPayments\Request\Put\RequestPutDevice;
use Krokedil\KustomCheckout\InPersonPayments\Settings\Admin;
use Krokedil\KustomCheckout\InPersonPayments\Settings\Fields;
use lucatume\WPBrowser\WordPress\WPDieException;
use Tests\Support\IntegrationTestCase;

/**
 * The In-Person Payments API layer and the settings section that drives it.
 *
 * @covers \Krokedil\KustomCheckout\InPersonPayments\Request\Request
 * @covers \Krokedil\KustomCheckout\InPersonPayments\Request\Get\RequestGetDevices
 * @covers \Krokedil\KustomCheckout\InPersonPayments\Request\Get\RequestGetLocations
 * @covers \Krokedil\KustomCheckout\InPersonPayments\Request\Post\RequestPostEnrollment
 * @covers \Krokedil\KustomCheckout\InPersonPayments\Request\Put\RequestPutDevice
 * @covers \Krokedil\KustomCheckout\InPersonPayments\Settings\Fields
 * @covers \Krokedil\KustomCheckout\InPersonPayments\Settings\Admin
 */
class InPersonPaymentsTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	/**
	 * Which host and path each IPP request goes to.
	 *
	 * @dataProvider provide_endpoints
	 */
	public function test_the_request_goes_to_the_ipp_endpoint( string $class, bool $testmode, string $expected ): void {
		$this->haveGatewayCredentials( [], $testmode );

		$this->assertSame( $expected, $this->readRequestUrl( new $class() ) );
	}

	/** @return array<string, array{0: string, 1: bool, 2: string}> */
	public function provide_endpoints(): array {
		return [
			'devices in test mode'     => [ RequestGetDevices::class, true, 'https://api.playground.kustom.co/ipp/v1/devices?page_size=100' ],
			'devices in live mode'     => [ RequestGetDevices::class, false, 'https://api.kustom.co/ipp/v1/devices?page_size=100' ],
			'locations in test mode'   => [ RequestGetLocations::class, true, 'https://api.playground.kustom.co/ipp/v1/locations?page_size=100' ],
			'locations in live mode'   => [ RequestGetLocations::class, false, 'https://api.kustom.co/ipp/v1/locations?page_size=100' ],
			'enrollments in test mode' => [ RequestPostEnrollment::class, true, 'https://api.playground.kustom.co/ipp/v1/enrollments' ],
			'enrollments in live mode' => [ RequestPostEnrollment::class, false, 'https://api.kustom.co/ipp/v1/enrollments' ],
		];
	}

	/**
	 * The enrollment code pairs any device to the account for up to 72 hours, and logs
	 * end up in support tickets.
	 */
	public function test_the_enrollment_code_does_not_reach_the_log(): void {
		$this->haveGatewayCredentials( [ 'logging' => 'yes' ] );
		$this->willRespondWith( [ 'enrollment_id' => 'enr-1', 'enrollment_code' => 'ABC123XYZ', 'expires_at' => '2026-09-18T10:00:00Z' ], 201, 'ipp/v1/enrollments' );
		$logged = [];
		add_filter(
			'woocommerce_logger_log_message',
			static function ( $message ) use ( &$logged ) {
				$logged[] = $message;
				return $message;
			}
		);

		$response = ( new RequestPostEnrollment( [ 'ttl' => 'TWO_HOURS' ] ) )->request();

		$this->assertSame( 'ABC123XYZ', $response['enrollment_code'], 'The caller still gets the code.' );
		$this->assertNotEmpty( $logged, 'The request was logged at all.' );
		$this->assertStringNotContainsString( 'ABC123XYZ', implode( "\n", $logged ) );
		$this->assertStringContainsString( 'enrollment_code', implode( "\n", $logged ), 'The key stays, so the log still shows what came back.' );
	}

	/**
	 * The admin-ajax handlers create pairing codes and rename devices, so a request
	 * without the settings-page nonce or the capability never reaches Kustom.
	 *
	 * @dataProvider provide_refused_ajax_requests
	 */
	public function test_an_unauthorised_ajax_request_is_refused( string $action, string $role, bool $right_nonce ): void {
		$this->haveGatewayCredentials();
		wp_set_current_user( 'guest' === $role ? 0 : self::factory()->user->create( [ 'role' => $role ] ) );
		$_POST['nonce'] = $right_nonce ? wp_create_nonce( Admin::NONCE_ACTION ) : 'not-the-nonce';
		$_POST['ttl']   = 'TWO_HOURS';

		$output = $this->runAjax( $action );

		$this->assertFalse( json_decode( $output, true )['success'] ?? false, 'Refused: ' . $output );
		$this->assertNoGatewayRequests();
	}

	/** @return array<string, array{0: string, 1: string, 2: bool}> */
	public function provide_refused_ajax_requests(): array {
		return [
			'enrollment, no capability' => [ 'kco_ipp_create_enrollment', 'customer', true ],
			'enrollment, logged out'    => [ 'kco_ipp_create_enrollment', 'guest', true ],
			'enrollment, wrong nonce'   => [ 'kco_ipp_create_enrollment', 'administrator', false ],
			'rename, no capability'     => [ 'kco_ipp_rename_device', 'customer', true ],
			'rename, wrong nonce'       => [ 'kco_ipp_rename_device', 'administrator', false ],
			'refresh, wrong nonce'      => [ 'kco_ipp_refresh_devices', 'administrator', false ],
		];
	}

	/** Runs an admin-ajax action the way admin-ajax.php would, returning what it printed. */
	private function runAjax( string $action ): string {
		// Outside an ajax request wp_send_json() would die() the test process.
		$throw = static function () {
			return static function ( $message, $title, $args ) {
				throw new WPDieException( (string) $message, (string) $title, (array) $args );
			};
		};
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', $throw );

		new Admin();
		ob_start();

		try {
			do_action( 'wp_ajax_' . $action );
		} catch ( WPDieException $e ) {
			// wp_send_json_*() and check_ajax_referer() both end in wp_die().
		}

		$output = (string) ob_get_clean();
		remove_filter( 'wp_doing_ajax', '__return_true' );
		remove_filter( 'wp_die_ajax_handler', $throw );
		unset( $_POST['nonce'], $_POST['ttl'] );
		wp_set_current_user( 0 );

		return '' === $output ? '{"success":false}' : $output;
	}

	/** An IPP request signs itself from the settings, with no order to read an environment off. */
	public function test_the_request_signs_itself_with_the_settings_credentials(): void {
		$this->haveGatewayCredentials( [ 'test_merchant_id' => 'test-mid', 'test_shared_secret' => 'test-secret' ] );

		$header = $this->requestHeaders( new RequestGetDevices() )['Authorization'];

		$this->assertStringStartsWith( 'Basic ', $header );
		$this->assertSame( [ 'test-mid', 'test-secret' ], explode( ':', base64_decode( substr( $header, 6 ) ), 2 ) );
	}

	/** Without credentials the request never leaves, rather than 401ing at Kustom. */
	public function test_a_request_without_credentials_fails_before_it_is_sent(): void {
		$this->setGatewaySettings( [ 'testmode' => 'yes', 'logging' => 'no' ] );

		$this->assertWpErrorCode( 'missing_credentials', ( new RequestGetDevices() )->request() );
		$this->assertNoGatewayRequests();
	}

	/**
	 * The merchant id header identifies which MID to list for, and only the list
	 * endpoint takes it.
	 *
	 * @dataProvider provide_merchant_id_header
	 */
	public function test_only_the_list_endpoint_carries_the_merchant_id( string $class, ?string $expected ): void {
		$this->haveGatewayCredentials( [ 'test_merchant_id' => 'test-mid' ] );

		$headers = $this->requestHeaders( new $class() );

		$this->assertSame( $expected, $headers['x-merchant-id'] ?? null );
	}

	/** @return array<string, array{0: string, 1: ?string}> */
	public function provide_merchant_id_header(): array {
		return [
			'devices carries it'     => [ RequestGetDevices::class, 'test-mid' ],
			'locations carries it'   => [ RequestGetLocations::class, 'test-mid' ],
			'enrollments omits it'   => [ RequestPostEnrollment::class, null ],
		];
	}

	/**
	 * TWO_HOURS is the only TTL the plugin asks for; the longer ones return an
	 * untypable UUID. Kustom rejects an enrollment with no location unless the
	 * account has exactly one, so the configured id has to reach the body.
	 *
	 * @dataProvider provide_enrollment_bodies
	 */
	public function test_the_enrollment_body_carries_the_ttl_and_the_location( string $location_id, array $arguments, array $expected ): void {
		$this->haveGatewayCredentials( [ 'ipp_location_id' => $location_id ] );
		$this->willRespondWith( [ 'enrollment_code' => 'ABC12345' ], 201, 'ipp/v1/enrollments' );

		( new RequestPostEnrollment( $arguments ) )->request();

		$this->assertSame( $expected, $this->gatewayRequestTo( 'ipp/v1/enrollments' )['json'] );
	}

	/** @return array<string, array{0: string, 1: array, 2: array}> */
	public function provide_enrollment_bodies(): array {
		$location = '550e8400-e29b-41d4-a716-446655440000';

		return [
			'a configured location is sent'     => [ $location, [], [ 'ttl' => 'TWO_HOURS', 'location_id' => $location ] ],
			// An empty key is omitted rather than sent blank, which Kustom rejects
			// outright even for the single-location account that needs no location.
			'no location leaves the key out'    => [ '', [], [ 'ttl' => 'TWO_HOURS' ] ],
			'whitespace counts as no location'  => [ '   ', [], [ 'ttl' => 'TWO_HOURS' ] ],
			'a chosen ttl is passed through'    => [ '', [ 'ttl' => 'SEVENTY_TWO_HOURS' ], [ 'ttl' => 'SEVENTY_TWO_HOURS' ] ],
		];
	}

	/**
	 * The TTL reaches the body from a dropdown, so anything outside the enum falls
	 * back to the one TTL that yields a code a salesperson can type.
	 *
	 * @dataProvider provide_bad_ttls
	 */
	public function test_an_unsupported_ttl_falls_back_to_two_hours( string $ttl ): void {
		$this->haveGatewayCredentials();
		$this->willRespondWith( [ 'enrollment_code' => 'ABC12345' ], 201, 'ipp/v1/enrollments' );

		( new RequestPostEnrollment( [ 'ttl' => $ttl ] ) )->request();

		$this->assertSame( [ 'ttl' => 'TWO_HOURS' ], $this->gatewayRequestTo( 'ipp/v1/enrollments' )['json'] );
	}

	/** @return array<string, array{0: string}> */
	public function provide_bad_ttls(): array {
		return [
			'an empty ttl'          => [ '' ],
			'an invented ttl'       => [ 'ONE_WEEK' ],
			'the wrong case'        => [ 'two_hours' ],
		];
	}

	/** A successful list comes back as the decoded body for the settings table to render. */
	public function test_a_successful_device_list_is_returned_decoded(): void {
		$this->haveGatewayCredentials();
		$this->willRespondWith(
			[ 'content' => [ [ 'id' => 'device-1', 'name' => 'Counter', 'platform' => 'IOS' ] ] ],
			200,
			'ipp/v1/devices'
		);

		$response = ( new RequestGetDevices() )->request();

		$this->assertSame( 'device-1', $response['content'][0]['id'] );
	}

	/**
	 * IPP answers with RFC 7807 problem details rather than the `error_messages`
	 * array the rest of the API uses, so the message has to be read out of those.
	 *
	 * @dataProvider provide_error_bodies
	 */
	public function test_an_api_error_surfaces_a_readable_message( array $body, int $status, string $expected ): void {
		$this->haveGatewayCredentials();
		$this->willRespondWith( $body, $status, 'ipp/v1/devices' );

		$response = ( new RequestGetDevices() )->request();

		$this->assertInstanceOf( \WP_Error::class, $response );
		$this->assertSame( $status, $response->get_error_code() );
		$this->assertSame( $expected, $response->get_error_message() );
	}

	/** @return array<string, array{0: array, 1: int, 2: string}> */
	public function provide_error_bodies(): array {
		return [
			'field errors are joined' => [ [ 'title' => 'Validation Failed', 'errors' => [ 'ttl' => 'must not be null' ] ], 400, 'must not be null' ],
			'detail is preferred'     => [ [ 'title' => 'Unauthorized', 'detail' => 'Access Denied' ], 401, 'Access Denied' ],
			'title is the fallback'   => [ [ 'title' => 'Unauthorized' ], 401, 'Unauthorized' ],
			'an empty body still says something' => [ [], 500, 'Kustom API error 500.' ],
		];
	}

	/** The location list feeds the settings dropdown, so the names have to survive decoding. */
	public function test_a_successful_location_list_is_returned_decoded(): void {
		$this->haveGatewayCredentials();
		$this->willRespondWith(
			[ 'content' => [ [ 'id' => 'location-1', 'name' => 'Downtown Store', 'city' => 'Stockholm' ] ] ],
			200,
			'ipp/v1/locations'
		);

		$response = ( new RequestGetLocations() )->request();

		$this->assertSame( 'Downtown Store', $response['content'][0]['name'] );
	}

	/** Renaming a device addresses it by id in the path. */
	public function test_the_device_update_addresses_the_device_by_id(): void {
		$this->haveGatewayCredentials();

		$request = new RequestPutDevice( [ 'device_id' => 'device-1', 'name' => 'Counter 1' ] );

		$this->assertSame( 'https://api.playground.kustom.co/ipp/v1/devices/device-1', $this->readRequestUrl( $request ) );
	}

	/**
	 * The endpoint replaces the device rather than patching it, so a rename has to
	 * carry back what the device already had or it is silently detached from its
	 * location.
	 *
	 * @dataProvider provide_device_updates
	 */
	public function test_the_device_update_preserves_what_it_is_not_changing( array $arguments, array $expected ): void {
		$this->haveGatewayCredentials();
		$this->willRespondWith( [ 'id' => 'device-1' ], 200, 'ipp/v1/devices/device-1' );

		( new RequestPutDevice( array_merge( [ 'device_id' => 'device-1' ], $arguments ) ) )->request();

		$this->assertSame( $expected, $this->gatewayRequestTo( 'ipp/v1/devices/device-1' )['json'] );
	}

	/** @return array<string, array{0: array, 1: array}> */
	public function provide_device_updates(): array {
		return [
			'the location and metadata ride along' => [
				[ 'name' => 'Counter 1', 'metadata' => [ 'store_id' => '123' ], 'location_id' => 'location-1' ],
				[ 'name' => 'Counter 1', 'metadata' => [ 'store_id' => '123' ], 'location_id' => 'location-1' ],
			],
			'a device with neither sends neither'  => [
				[ 'name' => 'Counter 1' ],
				[ 'name' => 'Counter 1' ],
			],
			'the name is trimmed'                  => [
				[ 'name' => '  Counter 1  ' ],
				[ 'name' => 'Counter 1' ],
			],
		];
	}

	/** The settings section is additive: every key is new and namespaced. */
	public function test_the_settings_section_adds_only_new_ipp_keys(): void {
		$ipp_keys = array_keys( Fields::fields() );

		$this->assertSame(
			[ 'in_person_payments', 'ipp_enabled', 'ipp_title', 'ipp_capability', 'ipp_webhook_secret', 'ipp_location_id', 'ipp_enrollment', 'ipp_devices', 'in_person_payments_end' ],
			$ipp_keys
		);
	}

	/**
	 * The settings defaults are read on `plugins_loaded`, before WordPress has the
	 * rewrite component a REST URL is built from, so building the fields must not
	 * depend on one.
	 */
	public function test_the_fields_can_be_built_before_wordpress_has_rewrite_rules(): void {
		$rewrite = $GLOBALS['wp_rewrite'];
		unset( $GLOBALS['wp_rewrite'] );

		try {
			$fields = Fields::fields();
		} finally {
			$GLOBALS['wp_rewrite'] = $rewrite;
		}

		$this->assertSame( '', $fields['ipp_webhook_secret']['default'] );
		$this->assertStringNotContainsString( '<code>http', $fields['ipp_webhook_secret']['description'] );
		$this->assertStringContainsString( '<code>http', Fields::fields()['ipp_webhook_secret']['description'] );
	}

	/** The section reaches the gateway settings page without disturbing the existing fields. */
	public function test_the_section_is_spliced_into_the_gateway_fields(): void {
		$fields = \KCO_Fields::fields();

		$this->assertArrayHasKey( 'ipp_enabled', $fields );
		$this->assertArrayHasKey( 'enabled', $fields );
		$this->assertArrayHasKey( 'color_settings_title', $fields );
		$this->assertSame( 'no', $fields['ipp_enabled']['default'] );
		$this->assertSame( 'manage_woocommerce', $fields['ipp_capability']['default'] );
	}

	/** @return array<string, string> */
	private function requestHeaders( object $request ): array {
		$method = new \ReflectionMethod( $request, 'get_request_headers' );
		$method->setAccessible( true );

		return $method->invoke( $request );
	}

	private function readRequestUrl( object $request ): string {
		$method = new \ReflectionMethod( $request, 'get_request_url' );
		$method->setAccessible( true );

		return $method->invoke( $request );
	}
}
