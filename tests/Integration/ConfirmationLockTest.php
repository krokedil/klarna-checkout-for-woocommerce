<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * The lock that stops two requests from confirming the same order at once.
 *
 * @covers \KCO_Confirmation::lock_kco_confirmation
 * @covers \KCO_Confirmation::unlock_kco_confirmation
 */
class ConfirmationLockTest extends IntegrationTestCase {

	private const ORDER_ID = 4242;

	/** Whether the competing request's insert actually ran. */
	private bool $competingWriteRan = false;

	/**
	 * Why the lock writes to the options table directly: the options API loses this race.
	 * The competing write lands between the check and the insert, where a second process would.
	 *
	 * @dataProvider provide_lock_strategies
	 */
	public function test_only_an_atomic_insert_survives_a_competing_write( string $option, callable $take_lock, bool $also_wins, string $message ): void {
		$this->anotherRequestInsertsFirst( $option );

		$this->assertSame( $also_wins, (bool) $take_lock(), $message );
		$this->assertTrue( $this->competingWriteRan, 'The competing write never ran, so the race was not tested.' );
	}

	/** @return array<string, array{0: string, 1: callable, 2: bool, 3: string}> */
	public function provide_lock_strategies(): array {
		return [
			'the confirmation lock, refused'           => [
				'kco_confirmation_lock_' . self::ORDER_ID,
				static fn() => \KCO_Confirmation::lock_kco_confirmation( 'kustom-order-123', self::ORDER_ID ),
				false,
				'The lock must refuse a request that lost the race.',
			],
			'add_option(), both requests hold it'      => [
				'kco_lock_candidate',
				static fn() => add_option( 'kco_lock_candidate', time(), '', false ),
				true,
				'add_option() upserts, so it cannot be the lock. If this fails, core made it atomic and the direct query can go.',
			],
			'set_transient(), both requests hold it' => [
				'_transient_kco_lock_candidate',
				static fn() => set_transient( 'kco_lock_candidate', time(), MINUTE_IN_SECONDS ),
				true,
				'set_transient() ends in add_option(), so it cannot be the lock either.',
			],
		];
	}

	public function test_a_lock_left_by_a_request_that_died_expires_after_a_minute(): void {
		global $wpdb;

		\KCO_Confirmation::lock_kco_confirmation( 'kustom-order-123', self::ORDER_ID );

		$wpdb->update(
			$wpdb->options,
			[ 'option_value' => time() - MINUTE_IN_SECONDS - 1 ],
			[ 'option_name' => 'kco_confirmation_lock_' . self::ORDER_ID ]
		);

		$this->assertTrue( \KCO_Confirmation::lock_kco_confirmation( 'kustom-order-123', self::ORDER_ID ), 'A stale lock is taken over.' );
		$this->assertFalse( \KCO_Confirmation::lock_kco_confirmation( 'kustom-order-123', self::ORDER_ID ), 'The taken-over lock holds again.' );
	}

	/** Inserts the option row just before the next INSERT for it runs, as a faster request would. */
	private function anotherRequestInsertsFirst( string $option ): void {
		global $wpdb;

		add_filter(
			'query',
			function ( $query ) use ( $option, $wpdb ) {
				if ( $this->competingWriteRan || 0 !== stripos( ltrim( $query ), 'INSERT' ) || false === strpos( $query, "'{$option}'" ) ) {
					return $query;
				}

				$this->competingWriteRan = true;
				$wpdb->insert( $wpdb->options, [ 'option_name' => $option, 'option_value' => (string) time(), 'autoload' => 'no' ] );

				return $query;
			}
		);
	}
}
