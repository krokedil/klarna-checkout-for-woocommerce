<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

/** Blocks and records outbound HTTP for the Integration suite. */
trait CanInterceptHttp {

	/**
	 * Every intercepted request, in order.
	 *
	 * @var array<int, array>
	 */
	private $interceptedHttpRequests = [];

	/**
	 * Responses queued by willRespondWith(), consumed in order.
	 *
	 * @var array<int, array>
	 */
	private $queuedHttpResponses = [];

	/** Starts intercepting. Safe to call more than once. */
	protected function interceptHttp(): void {
		if ( has_filter( 'pre_http_request', [ $this, 'interceptHttpRequest' ] ) ) {
			return;
		}

		add_filter( 'pre_http_request', [ $this, 'interceptHttpRequest' ], 10, 3 );
	}

	/** The `pre_http_request` callback. Public so WordPress can call it. */
	public function interceptHttpRequest( $preempt, $args, $url ) {
		$body = $args['body'] ?? null;

		$this->interceptedHttpRequests[] = [
			'url'     => $url,
			'method'  => $args['method'] ?? 'GET',
			'headers' => $args['headers'] ?? [],
			'body'    => $body,
			'json'    => is_string( $body ) ? json_decode( $body, true ) : null,
		];

		foreach ( $this->queuedHttpResponses as $index => $queued ) {
			if ( null !== $queued['url_contains'] && false === strpos( $url, $queued['url_contains'] ) ) {
				continue;
			}

			unset( $this->queuedHttpResponses[ $index ] );

			return $queued['response'];
		}

		return new \WP_Error(
			'kco_test_http_blocked',
			sprintf(
				'Outbound HTTP is blocked in the Integration suite. Queue a response with willRespondWith() if this call is expected. URL: %s',
				$url
			)
		);
	}

	/** Queues a canned response. */
	protected function willRespondWith( array $body, int $status = 200, ?string $url_contains = null, array $headers = [] ): void {
		$this->queuedHttpResponses[] = [
			'url_contains' => $url_contains,
			'response'     => [
				'headers'  => new \WpOrg\Requests\Utility\CaseInsensitiveDictionary( $headers ),
				'body'     => wp_json_encode( $body ),
				'response' => [
					'code'    => $status,
					'message' => get_status_header_desc( $status ),
				],
				'cookies'  => [],
				'filename' => null,
			],
		];
	}

	/** Queues a rejected response, in the shape the gateway's API reports errors. */
	protected function willRejectWith( string $url_contains, string $message, int $status = 400, array $body = [] ): void {
		$this->willRespondWith(
			array_merge( [ 'error_messages' => [ $message ] ], $body ),
			$status,
			$url_contains
		);
	}

	/** Every request intercepted so far. */
	protected function httpRequests(): array {
		return $this->interceptedHttpRequests;
	}

	/**
	 * Just the requests aimed at the gateway's API. WooCommerce core makes requests
	 * of its own that assertions about the gateway have to ignore.
	 */
	protected function gatewayRequests(): array {
		return array_values(
			array_filter(
				$this->interceptedHttpRequests,
				static function ( $request ) {
					return false !== strpos( $request['url'], 'kustom.co' );
				}
			)
		);
	}

	/** The gateway requests whose URL contains the given fragment. */
	protected function gatewayRequestsTo( string $url_contains ): array {
		return array_values(
			array_filter(
				$this->gatewayRequests(),
				static function ( $request ) use ( $url_contains ) {
					return false !== strpos( $request['url'], $url_contains );
				}
			)
		);
	}

	/** The one gateway request aimed at the given endpoint. */
	protected function gatewayRequestTo( string $url_contains ): array {
		$matching = $this->gatewayRequestsTo( $url_contains );

		if ( 1 !== count( $matching ) ) {
			$this->fail(
				sprintf(
					'Expected exactly one gateway request to "%s", got %d. Requests made: %s',
					$url_contains,
					count( $matching ),
					$this->describeGatewayRequests()
				)
			);
		}

		return $matching[0];
	}

	/** Asserts how many gateway requests were made, optionally to one endpoint. */
	protected function assertGatewayRequestCount( int $expected, string $url_contains = '', ?string $message = null ): void {
		$matching = '' === $url_contains
			? $this->gatewayRequests()
			: $this->gatewayRequestsTo( $url_contains );

		$this->assertCount(
			$expected,
			$matching,
			trim( ( $message ?? '' ) . ' Requests made: ' . $this->describeGatewayRequests() )
		);
	}

	/** The gateway requests made so far, for a failure message. */
	private function describeGatewayRequests(): string {
		$urls = array_column( $this->gatewayRequests(), 'url' );

		return empty( $urls ) ? 'none' : implode( ', ', $urls );
	}

	/** Forgets recorded requests and queued responses. */
	protected function resetHttpInterception(): void {
		$this->interceptedHttpRequests = [];
		$this->queuedHttpResponses     = [];
	}

	/** Asserts that nothing called the gateway's API. */
	protected function assertNoGatewayRequests( string $message = '' ): void {
		$this->assertSame(
			[],
			array_column( $this->gatewayRequests(), 'url' ),
			$message ? $message : 'Expected no requests to the gateway.'
		);
	}
}
