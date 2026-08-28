<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\OrderManagement\PendingOrders;
use Tests\Support\IntegrationTestCase;

/**
 * The out-of-band fraud resolution flow: Kustom calls back once it has finished
 * reviewing an order it first answered PENDING for.
 *
 * @covers \Krokedil\KustomCheckout\OrderManagement\PendingOrders::notification_listener
 */
class PendingOrdersTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	/** @var array<int, array> Every mail wp_mail() was asked to send, in order. */
	private $sentMail = [];

	protected function setUp(): void {
		parent::setUp();

		add_filter( 'pre_wp_mail', [ $this, 'recordMail' ], 10, 2 );

		// The legacy post datastore warns about the lookup's meta_query, which WPTestCase fails on. HPOS does not.
		if ( ! $this->isHposEnabled() ) {
			$this->setExpectedIncorrectUsage( 'WC_Order_Data_Store_CPT::query' );
		}
	}

	/**
	 * The `pre_wp_mail` callback; public so WordPress can call it. wp-browser's mock
	 * PHPMailer never resets between tests and stores a quoted-printable body.
	 *
	 * @param null|bool $short_circuit Whether sending has already been handled.
	 * @param array     $attributes    to / subject / message / headers / attachments.
	 */
	public function recordMail( $short_circuit, $attributes ): bool {
		$this->sentMail[] = $attributes;

		return true;
	}

	/**
	 * What Kustom's finished fraud verdict does to the order.
	 *
	 * @dataProvider provide_verdicts
	 */
	public function test_the_fraud_verdict_resolves_the_order( string $fraud_status, string $status, bool $paid, ?string $note ): void {
		$order = $this->havePendingGatewayOrder();

		$this->willRetrieveManagedOrder( [ 'fraud_status' => $fraud_status ] );

		PendingOrders::notification_listener( 'kustom-order-123' );

		$reloaded = $this->reload( $order );

		$this->assertSame( $status, $reloaded->get_status() );
		$this->assertSame( $paid, null !== $reloaded->get_date_paid() );
		$this->assertGatewayRequestCount( 1, '', 'The verdict is read; nothing else is sent.' );

		if ( null !== $note ) {
			$this->assertOrderHasNote( $order, $note );
		}
	}

	/** @return array<string, array{0: string, 1: string, 2: bool, 3: string|null}> */
	public function provide_verdicts(): array {
		return [
			'accepted'                  => [ 'ACCEPTED', 'processing', true, 'accepted' ],
			'refused outright'          => [ 'REJECTED', 'cancelled', false, 'rejected' ],
			'held back by Kustom fraud' => [ 'STOPPED', 'cancelled', false, 'rejected' ],
			'still undecided'           => [ 'PENDING', 'on-hold', false, null ],
		];
	}

	public function test_an_accepted_order_records_the_kustom_reference(): void {
		$order = $this->havePendingGatewayOrder();
		$order->set_transaction_id( '' );
		$order->save();

		$this->willRetrieveManagedOrder( [ 'fraud_status' => 'ACCEPTED' ] );

		PendingOrders::notification_listener( 'kustom-order-123' );

		$this->assertSame( 'kustom-order-123', $this->reload( $order )->get_transaction_id() );
	}

	/**
	 * @dataProvider provide_untouchable_orders
	 */
	public function test_an_order_the_notification_cannot_resolve_is_left_alone( string $scenario, int $requests ): void {
		$order = 'no-orders' === $scenario ? null : $this->havePendingGatewayOrder( [ 'paid' => 'paid' === $scenario ] );

		if ( 'lookup-failed' !== $scenario && null !== $order ) {
			$this->willRetrieveManagedOrder( [ 'fraud_status' => 'REJECTED' ] );
		}

		PendingOrders::notification_listener( 'no-orders' === $scenario ? 'kustom-order-nobody-has' : 'kustom-order-123' );

		if ( null !== $order ) {
			$this->assertSame( 'on-hold', $this->statusOf( $order ) );
		}

		$this->assertSame( [], $this->sentMail, 'Nothing was resolved, so nobody is told anything.' );
		$this->assertGatewayRequestCount( $requests );
	}

	/** @return array<string, array{0: string, 1: int}> */
	public function provide_untouchable_orders(): array {
		return [
			// The lookup happens before the paid guard, so a replay still costs a round trip.
			'the order already paid'  => [ 'paid', 1 ],
			'the kustom lookup failed' => [ 'lookup-failed', 1 ],
			'the store has no orders' => [ 'no-orders', 0 ],
		];
	}

	public function test_a_refused_order_emails_the_admin(): void {
		update_option( 'admin_email', 'shop-admin@example.com' );

		$this->havePendingGatewayOrder();

		// Distinct from the order's own id, so only the reference Kustom answered with can pass.
		$this->willRetrieveManagedOrder( [ 'fraud_status' => 'REJECTED', 'order_id' => 'kustom-ref-from-om' ] );

		PendingOrders::notification_listener( 'kustom-order-123' );

		$this->assertNotEmpty( $this->sentMail, 'A refusal nobody reads is a shipped fraud order.' );
		$this->assertSame( 'shop-admin@example.com', $this->sentMail[0]['to'] );
		$this->assertStringContainsString( 'do not ship this order', $this->sentMail[0]['message'] );
		$this->assertStringContainsString( 'kustom-ref-from-om', $this->sentMail[0]['message'] );
	}

	private function havePendingGatewayOrder( array $args = [] ): \WC_Order {
		return $this->haveGatewayOrder( array_merge( [ 'status' => 'on-hold' ], $args ) );
	}

	private function isHposEnabled(): bool {
		return class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}
}
