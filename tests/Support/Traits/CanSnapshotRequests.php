<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use Tests\Support\Reporting\Redactor;
use Tests\Support\Reporting\SecretRegistry;

/**
 * Pins an outgoing gateway request against a committed JSON fixture.
 *
 * Run with `UPDATE_SNAPSHOTS=1` to (re)write the fixtures, then review the diff —
 * the fixture is the assertion.
 */
trait CanSnapshotRequests {

	/**
	 * Asserts the request's method, path and body match `Data/snapshots/<name>.json`.
	 *
	 * @param array                 $request      A request from gatewayRequestTo().
	 * @param string                $name         Fixture name, without the extension.
	 * @param array<string, scalar> $placeholders Placeholder token => volatile value.
	 */
	protected function assertRequestMatchesSnapshot( array $request, string $name, array $placeholders = [] ): void {
		$this->assertMatchesSnapshot(
			[
				'method' => $request['method'],
				'path'   => $request['url'],
				'body'   => $request['json'],
			],
			$name,
			$placeholders
		);
	}

	/**
	 * Asserts an array matches `Data/snapshots/<name>.json`, with environment-specific
	 * values masked out first.
	 *
	 * @param array<string, scalar> $placeholders Placeholder token => volatile value.
	 */
	protected function assertMatchesSnapshot( array $actual, string $name, array $placeholders = [] ): void {
		$json   = (string) wp_json_encode( $actual, JSON_UNESCAPED_SLASHES );
		$actual = json_decode( $this->maskSnapshotValues( $json, $placeholders ), true );
		$path   = $this->snapshotPath( $name );

		if ( getenv( 'UPDATE_SNAPSHOTS' ) ) {
			$this->writeSnapshot( $path, $actual );
			$this->addToAssertionCount( 1 );
			return;
		}

		$this->assertFileExists( $path, sprintf( 'Missing snapshot "%s". Re-run with UPDATE_SNAPSHOTS=1 to create it.', $name ) );
		$this->assertSame(
			json_decode( (string) file_get_contents( $path ), true ),
			$actual,
			sprintf( 'Does not match snapshot "%s". Re-run with UPDATE_SNAPSHOTS=1 if the change is intended.', $name )
		);
	}

	/**
	 * Swaps out the site URL, the caller's volatile ids and any known secret.
	 */
	private function maskSnapshotValues( string $text, array $placeholders ): string {
		foreach ( $placeholders as $token => $value ) {
			$value = (string) $value;

			if ( '' === $value || '0' === $value ) {
				continue;
			}

			// Digit-bounded so an id like 42 cannot be replaced inside an amount like 4200.
			$text = (string) preg_replace(
				'/(?<![0-9])' . preg_quote( $value, '/' ) . '(?![0-9])/',
				$token,
				$text
			);
		}

		// The API host stays intact; the regional endpoint is part of what is pinned.
		$text = str_replace( [ home_url(), untrailingslashit( home_url() ) ], '<site>', $text );

		return $this->snapshotRedactor()->scrub( $text );
	}

	private function snapshotRedactor(): Redactor {
		static $redactor = null;

		if ( null === $redactor ) {
			$redactor = SecretRegistry::fromEnvironment();
		}

		return $redactor;
	}

	private function snapshotPath( string $name ): string {
		return __DIR__ . '/../Data/snapshots/' . $name . '.json';
	}

	private function writeSnapshot( string $path, array $contents ): void {
		if ( ! is_dir( dirname( $path ) ) ) {
			mkdir( dirname( $path ), 0777, true );
		}

		file_put_contents( $path, wp_json_encode( $contents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
	}
}
