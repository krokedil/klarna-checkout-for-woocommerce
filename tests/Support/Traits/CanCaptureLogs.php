<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

/** Records what the plugin writes to its WooCommerce log, so masking can be asserted on it. */
trait CanCaptureLogs {

	/**
	 * Every captured message, in order.
	 *
	 * @var array<int, string>
	 */
	private $capturedLogMessages = [];

	/** Turn logging on for this test and start capturing. KCO_Logger reads the setting per call. */
	protected function haveLoggingEnabled(): void {
		$settings = get_option( self::GATEWAY_SETTINGS_OPTION, [] );
		$this->setGatewaySettings( array_merge( is_array( $settings ) ? $settings : [], [ 'logging' => 'yes' ] ) );

		$this->capturedLogMessages = [];

		if ( ! has_filter( 'woocommerce_logger_log_message', [ $this, 'captureLogMessage' ] ) ) {
			add_filter( 'woocommerce_logger_log_message', [ $this, 'captureLogMessage' ], 10, 3 );
		}
	}

	/** Stops capturing. */
	protected function restoreLogging(): void {
		remove_filter( 'woocommerce_logger_log_message', [ $this, 'captureLogMessage' ], 10 );

		$this->capturedLogMessages = [];
	}

	/** The `woocommerce_logger_log_message` callback. Public so WordPress can call it. */
	public function captureLogMessage( $message, $level, $context ) {
		if ( 'kustom-checkout-for-woocommerce' === ( $context['source'] ?? '' ) ) {
			$this->capturedLogMessages[] = (string) $message;
		}

		return $message;
	}

	/**
	 * Every captured message as one string, which is what a leak assertion reads.
	 *
	 * Re-encoded without the escaping, so a search reads the value the customer entered
	 * rather than `Göteborg`.
	 */
	protected function loggedText(): string {
		return implode( "\n", array_map( [ $this, 'readable' ], $this->capturedLogMessages ) );
	}

	/** The failed requests kept for the system status report, as one readable string. */
	protected function storedLogText(): string {
		return $this->readable( (string) get_option( 'krokedil_debuglog_kco', '' ) );
	}

	/**
	 * The decoded entry whose title matches, since a single call logs several requests.
	 *
	 * @return array The decoded log entry.
	 */
	protected function loggedEntry( string $title ): array {
		foreach ( $this->capturedLogMessages as $message ) {
			$entry = json_decode( $message, true );

			if ( is_array( $entry ) && ( $entry['title'] ?? '' ) === $title ) {
				return $entry;
			}
		}

		$this->fail( sprintf( 'No log entry titled "%s". Titles logged: %s', $title, $this->loggedTitles() ) );
	}

	/** The titles of every decoded entry, for a failure message. */
	private function loggedTitles(): string {
		$titles = [];

		foreach ( $this->capturedLogMessages as $message ) {
			$entry    = json_decode( $message, true );
			$titles[] = is_array( $entry ) ? ( $entry['title'] ?? '(none)' ) : '(not an entry)';
		}

		return empty( $titles ) ? '(nothing was logged)' : implode( ', ', $titles );
	}

	/**
	 * Asserts that none of the values reached the text, keyed value => description.
	 *
	 * @param array<string, string> $values The values that must not appear.
	 */
	protected function assertNotLogged( array $values, ?string $text = null ): void {
		$logged = $text ?? $this->loggedText();

		$this->assertNotSame( '', $logged, 'Nothing was logged, so the assertion would pass for the wrong reason.' );

		foreach ( $values as $value => $description ) {
			$this->assertStringNotContainsString( (string) $value, $logged, "The log leaked the {$description}." );
		}
	}

	private function readable( string $message ): string {
		$entry = json_decode( $message, true );

		return null === $entry ? $message : (string) wp_json_encode( $entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}
}
