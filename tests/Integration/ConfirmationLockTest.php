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

	/** Whether a test dropped the unique key on option_name. */
	private bool $restoreUniqueKey = false;

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

	public function test_a_request_that_lost_its_lock_leaves_the_new_owners_lock_alone(): void {
		global $wpdb;

		\KCO_Confirmation::lock_kco_confirmation( 'kustom-order-123', self::ORDER_ID );

		$wpdb->update(
			$wpdb->options,
			[ 'option_value' => time() + 5 ],
			[ 'option_name' => 'kco_confirmation_lock_' . self::ORDER_ID ]
		);
		\KCO_Confirmation::unlock_kco_confirmation( 'kustom-order-123', self::ORDER_ID );

		$this->assertFalse( \KCO_Confirmation::lock_kco_confirmation( 'kustom-order-123', self::ORDER_ID ), 'The release must not delete a lock another request took over.' );
	}

	/**
	 * A migrated options table can lose its unique key, and then every INSERT IGNORE succeeds.
	 *
	 * @dataProvider provide_rows_from_other_requests
	 */
	public function test_the_oldest_live_row_holds_the_lock_without_a_unique_key( int $age, bool $locks, string $message ): void {
		global $wpdb;

		$this->dropUniqueKeyOnOptionName();
		$wpdb->insert( $wpdb->options, [ 'option_name' => 'kco_confirmation_lock_' . self::ORDER_ID, 'option_value' => (string) ( time() - $age ), 'autoload' => 'no' ] );

		$this->assertSame( $locks, \KCO_Confirmation::lock_kco_confirmation( 'kustom-order-123', self::ORDER_ID ), $message );
		\KCO_Confirmation::unlock_kco_confirmation( 'kustom-order-123', self::ORDER_ID );
		$this->assertSame( '1', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", 'kco_confirmation_lock_' . self::ORDER_ID ) ), 'Only the other request\'s row is left.' );
	}

	/** @return array<string, array{0: int, 1: bool, 2: string}> */
	public function provide_rows_from_other_requests(): array {
		return [
			'a request still confirming' => [ 0, false, 'The second request must be refused although its insert went through.' ],
			'a request that died'        => [ MINUTE_IN_SECONDS + 1, true, 'A row older than a minute must not block the order forever.' ],
		];
	}

	private function dropUniqueKeyOnOptionName(): void {
		global $wpdb;

		$this->assertNotFalse( $wpdb->query( "ALTER TABLE {$wpdb->options} DROP INDEX option_name" ), "Could not drop the unique key: {$wpdb->last_error}" );
		$this->restoreUniqueKey = true;
	}

	protected function tearDown(): void {
		global $wpdb;

		if ( $this->restoreUniqueKey ) {
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name = 'kco_confirmation_lock_" . self::ORDER_ID . "'" );
			$wpdb->query( "ALTER TABLE {$wpdb->options} ADD UNIQUE KEY option_name (option_name)" );
		}

		parent::tearDown();
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
