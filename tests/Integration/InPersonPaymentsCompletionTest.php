<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\InPersonPayments\Gateway;
use Krokedil\KustomCheckout\InPersonPayments\Rest;
use Krokedil\KustomCheckout\InPersonPayments\SessionHandler;
use Krokedil\KustomCheckout\InPersonPayments\WebhookSignature;
use Tests\Support\IntegrationTestCase;

/**
 * What the device's answer does to the order, whether it arrives through the browser
 * poll or through the webhook.
 *
 * @covers \Krokedil\KustomCheckout\InPersonPayments\SessionHandler
 * @covers \Krokedil\KustomCheckout\InPersonPayments\Rest
 * @covers \Krokedil\KustomCheckout\InPersonPayments\Webhook
 * @covers \Krokedil\KustomCheckout\InPersonPayments\WebhookSignature
 */
class InPersonPaymentsCompletionTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	private const SESSION_ID = '660e8400-e29b-41d4-a716-446655440001';
	private const DEVICE_ID  = '550e8400-e29b-41d4-a716-446655440000';
	private const KUSTOM_ID  = '770e8400-e29b-41d4-a716-446655440002';
	private const SECRET     = 'whsec_aXBwLXRlc3Qtc2lnbmluZy1rZXk=';

	protected function setUp(): void {
		parent::setUp();

		$this->haveIppSettings();
		$this->haveRestRoutes();
	}

	protected function tearDown(): void {
		wp_set_current_user( 0 );
		$this->restoreTheCart();

		parent::tearDown();
	}

	/** The tap paid for the goods the customer is already holding. */
	public function test_a_finalized_session_completes_the_order(): void {
		$order = $this->haveWaitingOrder();

		$outcome = SessionHandler::sync( $order, $this->session( 'FINALIZED' ) );

		$paid = $this->reload( $order );
		$this->assertSame( SessionHandler::PAID, $outcome['state'] );
		$this->assertSame( 'completed', $paid->get_status() );
		$this->assertTrue( $paid->is_paid() );
		$this->assertSame( 'FINALIZED', $paid->get_meta( Gateway::STATUS_META ) );
	}

	/** Refunds go through the existing order management screen, which reads these. */
	public function test_a_finalized_session_stores_the_kustom_order_id(): void {
		$order = $this->haveWaitingOrder();

		SessionHandler::sync( $order, $this->session( 'FINALIZED' ) );

		$paid = $this->reload( $order );
		$this->assertSame( self::KUSTOM_ID, $paid->get_meta( '_wc_klarna_order_id' ) );
		$this->assertSame( self::KUSTOM_ID, $paid->get_transaction_id() );
		$this->assertSame( 'test', $paid->get_meta( '_wc_klarna_environment' ) );
	}

	/**
	 * A sale that did not happen leaves the goods unsold, and says why.
	 *
	 * @dataProvider provide_terminal_statuses
	 */
	public function test_a_session_that_did_not_pay_leaves_the_order_unpaid( string $status, string $expected ): void {
		$order = $this->haveWaitingOrder();

		$outcome = SessionHandler::sync( $order, $this->session( $status ) );

		$unpaid = $this->reload( $order );
		$this->assertSame( SessionHandler::FAILED, $outcome['state'] );
		$this->assertSame( $expected, $unpaid->get_status() );
		$this->assertFalse( $unpaid->is_paid() );
		$this->assertSame( $status, $unpaid->get_meta( Gateway::STATUS_META ) );
		$this->assertNotSame( '', $outcome['message'] );
	}

	/** @return array<string, array{0: string, 1: string}> */
	public function provide_terminal_statuses(): array {
		return [
			'cancelled on the device' => [ 'CANCELLED', 'failed' ],
			'the session expired'     => [ 'EXPIRED', 'failed' ],
			'the payment failed'      => [ 'FAILED', 'failed' ],
		];
	}

	/**
	 * The session is still with the customer. Nothing about the order may move yet.
	 *
	 * @dataProvider provide_open_statuses
	 */
	public function test_an_open_session_leaves_the_order_waiting( string $status ): void {
		$order = $this->haveWaitingOrder();

		$outcome = SessionHandler::sync( $order, $this->session( $status ) );

		$this->assertSame( SessionHandler::WAITING, $outcome['state'] );
		$this->assertSame( 'pending', $this->statusOf( $order ) );
		$this->assertSame( $status, $this->reload( $order )->get_meta( Gateway::STATUS_META ) );
	}

	/** @return array<string, array{0: string}> */
	public function provide_open_statuses(): array {
		return [
			'dispatched to the device' => [ 'CREATED' ],
			'the customer is paying'    => [ 'ACTIVE' ],
		];
	}

	/** The poll and the webhook both arrive as a matter of course. */
	public function test_the_same_session_completes_the_order_only_once(): void {
		$order = $this->haveWaitingOrder();

		SessionHandler::sync( $order, $this->session( 'FINALIZED' ) );
		$paid_at = $this->reload( $order )->get_date_paid();

		$outcome = SessionHandler::sync( $order, $this->session( 'FINALIZED' ) );

		$this->assertSame( SessionHandler::PAID, $outcome['state'] );
		$this->assertEquals( $paid_at, $this->reload( $order )->get_date_paid() );
		$this->assertCount( 1, $this->notesContaining( $order, 'Payment collected' ) );
	}

	/**
	 * The poll and the webhook can pass the paid check together, so the completion is
	 * locked. The loser still learns the order is paid, so the browser redirects.
	 */
	public function test_a_completion_already_under_way_is_not_repeated(): void {
		$order = $this->haveWaitingOrder();
		$fired = 0;
		add_action( 'kco_ipp_payment_complete', static function () use ( &$fired ) { ++$fired; } );

		$this->assertTrue( SessionHandler::lock( $order->get_id() ), 'The first caller takes the lock.' );
		$loser = SessionHandler::sync( $order, $this->session( 'FINALIZED' ) );

		$this->assertSame( SessionHandler::WAITING, $loser['state'], 'A caller shut out of a live lock reports the order as still moving.' );
		$this->assertSame( 'pending', $this->statusOf( $order ) );
		$this->assertSame( 0, $fired );

		SessionHandler::unlock( $order->get_id() );
		$winner = SessionHandler::sync( $order, $this->session( 'FINALIZED' ) );

		$this->assertSame( SessionHandler::PAID, $winner['state'] );
		$this->assertSame( 1, $fired );
		$this->assertCount( 1, $this->notesContaining( $order, 'Payment collected' ) );
		$this->assertTrue( SessionHandler::lock( $order->get_id() ), 'The lock is released after completion.' );
		SessionHandler::unlock( $order->get_id() );
	}

	/** A FINALIZED session for another amount than the order's is not a payment for it. */
	public function test_a_session_for_another_amount_does_not_complete_the_order(): void {
		$order = $this->haveWaitingOrder();

		$outcome = SessionHandler::sync( $order, $this->session( 'FINALIZED', [ 'order_amount' => 19900 ] ) );

		$this->assertSame( SessionHandler::HELD, $outcome['state'] );
		$this->assertSame( 'pending', $this->statusOf( $order ), 'An amount mismatch is investigated, not written off as a failed sale.' );
		$this->assertFalse( $this->reload( $order )->is_paid() );
		$this->assertOrderHasNote( $order, '19900' );
		$this->assertOrderHasNote( $order, '25000' );
	}

	/** A cancellation arriving after the tap must not unsell the goods. */
	public function test_a_paid_order_is_not_reopened_by_a_later_status(): void {
		$order = $this->haveWaitingOrder();
		SessionHandler::sync( $order, $this->session( 'FINALIZED' ) );

		$outcome = SessionHandler::sync( $order, $this->session( 'CANCELLED' ) );

		$this->assertSame( SessionHandler::PAID, $outcome['state'] );
		$this->assertSame( 'completed', $this->statusOf( $order ) );
	}

	/** The salesperson's screen asks, and the answer is applied on the way past. */
	public function test_the_poll_completes_the_order_and_sends_the_browser_on(): void {
		$order = $this->haveWaitingOrder();
		$this->beA( 'shop_manager' );
		$this->willReturnSession( 'FINALIZED' );

		$response = rest_do_request( $this->pollRequest( $order ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( SessionHandler::PAID, $response->get_data()['state'] );
		$this->assertSame( $order->get_checkout_order_received_url(), $response->get_data()['redirect'] );
		$this->assertSame( 'completed', $this->statusOf( $order ) );
	}

	/**
	 * A sale that fell through goes back to the checkout with the goods still in the
	 * cart. WooCommerce loads no cart for a REST request, so the route has to.
	 */
	public function test_a_failed_session_puts_the_sale_back_in_the_cart(): void {
		$order = $this->haveWaitingOrder();
		$this->beA( 'shop_manager' );
		$this->willReturnSession( 'CANCELLED' );
		$this->haveNoCartLoaded();

		$response = rest_do_request( $this->pollRequest( $order ) );

		$this->assertSame( SessionHandler::FAILED, $response->get_data()['state'] );
		$this->assertSame( wc_get_checkout_url(), $response->get_data()['redirect'] );
		$this->assertInstanceOf( \WC_Cart::class, WC()->cart );
		$this->assertSame( 2, WC()->cart->get_cart_contents_count() );
		$this->assertContains( $response->get_data()['message'], array_column( wc_get_notices( 'error' ), 'notice' ) );
	}

	/** Kustom being briefly unreachable is not a sale that failed. */
	public function test_an_unreachable_api_keeps_the_screen_waiting(): void {
		$order = $this->haveWaitingOrder();
		$this->beA( 'shop_manager' );
		$this->willRespondWith( [ 'detail' => 'Service unavailable' ], 503, 'ipp/v1/sessions' );

		$response = rest_do_request( $this->pollRequest( $order ) );

		$this->assertSame( SessionHandler::WAITING, $response->get_data()['state'] );
		$this->assertSame( 'pending', $this->statusOf( $order ) );
	}

	/** The button under the waiting screen tells the device to give up. */
	public function test_cancelling_on_the_device_cancels_the_order(): void {
		$order = $this->haveWaitingOrder();
		$this->beA( 'shop_manager' );
		$this->willRespondWith( $this->session( 'CANCELLED' ), 200, 'ipp/v1/sessions/' . self::SESSION_ID . '/cancel' );

		$request  = new \WP_REST_Request( 'POST', '/kco/v1/ipp/sessions/' . $order->get_id() . '/cancel' );
		$response = rest_do_request( $this->withOrderKey( $request, $order ) );

		$this->assertSame( SessionHandler::FAILED, $response->get_data()['state'] );
		$this->assertSame( 'failed', $this->statusOf( $order ) );
		$this->assertSame( 'PUT', $this->gatewayRequestTo( '/cancel' )['method'] );
	}

	/**
	 * The session routes drive a payment, so they are staff-only and tied to the one
	 * order whose key the caller already has.
	 *
	 * @dataProvider provide_unauthorised_polls
	 */
	public function test_who_may_drive_a_session( string $role, bool $right_key, int $expected ): void {
		$order = $this->haveWaitingOrder();
		$this->beA( $role );

		$request = new \WP_REST_Request( 'GET', '/kco/v1/ipp/sessions/' . $order->get_id() );
		$request->set_param( 'key', $right_key ? $order->get_order_key() : 'wc_order_notthekey' );

		$this->assertSame( $expected, rest_do_request( $request )->get_status() );
		$this->assertNoGatewayRequests();
	}

	/** @return array<string, array{0: string, 1: bool, 2: int}> */
	public function provide_unauthorised_polls(): array {
		return [
			'a customer'           => [ 'customer', true, 403 ],
			'a logged out visitor' => [ 'guest', true, 401 ],
			'staff, wrong key'     => [ 'shop_manager', false, 404 ],
		];
	}

	/** The backstop for a salesperson who closed the tab before the customer paid. */
	public function test_a_signed_webhook_completes_the_order(): void {
		$order = $this->haveWaitingOrder();
		$this->willReturnSession( 'FINALIZED' );

		$response = rest_do_request( $this->webhookRequest() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'completed', $this->statusOf( $order ) );
	}

	/** A delivery the store could not act on must be retried, so it is not remembered. */
	public function test_a_webhook_the_store_could_not_act_on_is_retried(): void {
		$order = $this->haveWaitingOrder();
		$this->willRespondWith( [ 'detail' => 'Service unavailable' ], 503, 'ipp/v1/sessions' );

		$first = rest_do_request( $this->webhookRequest( [ 'id' => 'msg_retry' ] ) );

		$this->assertSame( 503, $first->get_status() );
		$this->assertSame( 'pending', $this->statusOf( $order ) );

		$this->willReturnSession( 'FINALIZED' );
		$second = rest_do_request( $this->webhookRequest( [ 'id' => 'msg_retry' ] ) );

		$this->assertSame( 200, $second->get_status() );
		$this->assertSame( 'completed', $this->statusOf( $order ) );
	}

	/** Kustom retries, and a retry must not be a second sale. */
	public function test_a_redelivered_webhook_is_only_handled_once(): void {
		$this->haveWaitingOrder();
		$this->willReturnSession( 'FINALIZED' );

		rest_do_request( $this->webhookRequest( [ 'id' => 'msg_repeat' ] ) );
		$response = rest_do_request( $this->webhookRequest( [ 'id' => 'msg_repeat' ] ) );

		$this->assertSame( 'duplicate', $response->get_data()['status'] );
		$this->assertGatewayRequestCount( 1, 'ipp/v1/sessions' );
	}

	/**
	 * The endpoint is public, so the signature is the only thing standing between a
	 * stranger and an order marked paid.
	 *
	 * @dataProvider provide_rejected_deliveries
	 */
	public function test_an_unverified_webhook_is_refused( array $delivery ): void {
		$order = $this->haveWaitingOrder();

		$response = rest_do_request( $this->webhookRequest( $delivery ) );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'pending', $this->statusOf( $order ) );
		$this->assertNoGatewayRequests();
	}

	/** @return array<string, array{0: array}> */
	public function provide_rejected_deliveries(): array {
		return [
			'a tampered body'      => [ [ 'tamper' => true ] ],
			'a stale timestamp'    => [ [ 'timestamp' => time() - 3600 ] ],
			'a timestamp from the future' => [ [ 'timestamp' => time() + 3600 ] ],
			'another secret'       => [ [ 'secret' => 'whsec_YW5vdGhlci1zaWduaW5nLWtleQ==' ] ],
			'no signature at all'  => [ [ 'signature' => '' ] ],
			'a signature of another scheme' => [ [ 'signature' => 'v2,ZGVhZGJlZWY=' ] ],
		];
	}

	/** A store that has not been given a secret cannot verify anything. */
	public function test_a_webhook_is_refused_when_no_secret_is_configured(): void {
		$this->haveIppSettings( [ 'ipp_webhook_secret' => '' ] );
		$order = $this->haveWaitingOrder();

		$this->assertSame( 401, rest_do_request( $this->webhookRequest() )->get_status() );
		$this->assertSame( 'pending', $this->statusOf( $order ) );
	}

	/** One webhook URL serves a merchant account, staging sites included. */
	public function test_a_webhook_for_another_site_is_left_alone(): void {
		$order = $this->haveWaitingOrder();
		$this->willRespondWith(
			$this->session( 'FINALIZED', [ 'merchant_data' => wp_json_encode( [ 'order_id' => $order->get_id(), 'site_url' => 'https://staging.example.com' ] ) ] ),
			200,
			'ipp/v1/sessions'
		);

		$response = rest_do_request( $this->webhookRequest() );

		$this->assertSame( 'unmatched', $response->get_data()['status'] );
		$this->assertSame( 'pending', $this->statusOf( $order ) );
	}

	/** The tolerance is replay protection, so a filter cannot switch it off. */
	public function test_the_timestamp_tolerance_cannot_be_filtered_away(): void {
		add_filter( 'kco_ipp_webhook_tolerance', static fn () => PHP_INT_MAX );
		$order = $this->haveWaitingOrder();

		$response = rest_do_request( $this->webhookRequest( [ 'timestamp' => time() - WebhookSignature::MAX_TOLERANCE - 60 ] ) );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'pending', $this->statusOf( $order ) );
	}

	/** A verified signature is the whole basis for trusting a delivery. */
	public function test_a_correctly_signed_delivery_verifies(): void {
		$payload = '{"type":"session.finalized"}';

		$this->assertTrue(
			WebhookSignature::verify( self::SECRET, 'msg_1', (string) time(), $this->sign( $payload, 'msg_1', time() ), $payload )
		);
	}

	/** Turns the feature on, over the store profile's credentials. */
	private function haveIppSettings( array $overrides = [] ): void {
		$this->haveGatewayCredentials(
			array_merge(
				[
					'ipp_enabled'        => 'yes',
					'ipp_capability'     => 'manage_woocommerce',
					'ipp_webhook_secret' => self::SECRET,
					'testmode'           => 'yes',
				],
				$overrides
			)
		);
	}

	/** Registers the REST routes against a fresh server. */
	private function haveRestRoutes(): void {
		global $wp_rest_server;

		new Rest();

		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	/** An order that has been sent to a device and is waiting for the customer. */
	private function haveWaitingOrder(): \WC_Order {
		$order = $this->haveOrder(
			[
				'items'   => [ [ $this->haveSimpleProduct( [ 'name' => 'Counter product', 'price' => '100.00', 'sku' => 'COUNTER-1' ] ), 2 ] ],
				'billing' => $this->swedishAddress(),
				'status'  => 'pending',
			]
		);

		$order->set_payment_method( Gateway::ID );
		$order->update_meta_data( Gateway::SESSION_META, self::SESSION_ID );
		$order->update_meta_data( Gateway::DEVICE_META, self::DEVICE_ID );
		$order->update_meta_data( Gateway::STATUS_META, 'CREATED' );
		$order->save();

		$this->orderId = $order->get_id();

		return $order;
	}

	/** The order the queued session points back at. */
	private ?int $orderId = null;

	/** The cart and session the suite runs on, while a test has taken them away. */
	private ?\WC_Cart $cart       = null;
	private ?\WC_Session $session = null;

	/** The state a REST request arrives in: WooCommerce has loaded neither cart nor session. */
	private function haveNoCartLoaded(): void {
		WC()->cart->empty_cart();
		$this->cart    = WC()->cart;
		$this->session = WC()->session;
		WC()->cart     = null;
		WC()->session  = null;
	}

	/**
	 * Puts the suite's cart and session back and unhooks the ones `wc_load_cart()`
	 * built, which would otherwise keep writing an empty cart into the session.
	 */
	private function restoreTheCart(): void {
		if ( null === $this->cart ) {
			return;
		}

		foreach ( [ WC()->cart, WC()->session ] as $orphan ) {
			if ( ! is_object( $orphan ) || $orphan === $this->cart || $orphan === $this->session ) {
				continue;
			}
			$this->unhookEverythingOf( $orphan );
			if ( $orphan instanceof \WC_Cart ) {
				$property = new \ReflectionProperty( $orphan, 'session' );
				$property->setAccessible( true );
				$this->unhookEverythingOf( $property->getValue( $orphan ) );
			}
		}

		WC()->cart     = $this->cart;
		WC()->session  = $this->session;
		$this->cart    = null;
		$this->session = null;
	}

	/** Removes every hook callback bound to the object. */
	private function unhookEverythingOf( object $target ): void {
		global $wp_filter;

		foreach ( $wp_filter as $hook => $filters ) {
			foreach ( $filters->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					if ( is_array( $callback['function'] ) && ( $callback['function'][0] ?? null ) === $target ) {
						remove_filter( $hook, $callback['function'], $priority );
					}
				}
			}
		}
	}

	/** A session as Kustom reports it. */
	private function session( string $status, array $overrides = [] ): array {
		return array_merge(
			[
				'session_id'          => self::SESSION_ID,
				'status'              => $status,
				'device_id'           => self::DEVICE_ID,
				'order_id'            => self::KUSTOM_ID,
				'order_amount'        => 25000,
				'merchant_reference1' => (string) $this->orderId,
				'merchant_data'       => wp_json_encode( [ 'order_id' => $this->orderId, 'site_url' => home_url() ] ),
			],
			$overrides
		);
	}

	/** Queues the session the poll and the webhook read back. */
	private function willReturnSession( string $status ): void {
		$this->willRespondWith( $this->session( $status ), 200, 'ipp/v1/sessions/' . self::SESSION_ID );
	}

	/** The request the waiting screen makes. */
	private function pollRequest( \WC_Order $order ): \WP_REST_Request {
		return $this->withOrderKey( new \WP_REST_Request( 'GET', '/kco/v1/ipp/sessions/' . $order->get_id() ), $order );
	}

	private function withOrderKey( \WP_REST_Request $request, \WC_Order $order ): \WP_REST_Request {
		$request->set_param( 'key', $order->get_order_key() );

		return $request;
	}

	/** A delivery, signed unless the case asks for something else. */
	private function webhookRequest( array $overrides = [] ): \WP_REST_Request {
		$id        = $overrides['id'] ?? 'msg_' . wp_generate_password( 12, false );
		$timestamp = $overrides['timestamp'] ?? time();
		$payload   = wp_json_encode( [ 'type' => 'session.finalized', 'data' => [ 'session_id' => self::SESSION_ID ] ] );
		$signature = $overrides['signature'] ?? $this->sign( $payload, $id, $timestamp, $overrides['secret'] ?? self::SECRET );

		if ( $overrides['tamper'] ?? false ) {
			$payload = wp_json_encode( [ 'type' => 'session.finalized', 'data' => [ 'session_id' => 'another-session' ] ] );
		}

		$request = new \WP_REST_Request( 'POST', '/kco/v1/ipp/webhook' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_header( 'webhook-id', $id );
		$request->set_header( 'webhook-timestamp', (string) $timestamp );
		$request->set_header( 'webhook-signature', $signature );
		$request->set_body( $payload );

		return $request;
	}

	/** Signs content the way the Standard Webhooks scheme does. */
	private function sign( string $payload, string $id, int $timestamp, string $secret = self::SECRET ): string {
		$key = base64_decode( substr( $secret, 6 ) ); // phpcs:ignore

		return 'v1,' . base64_encode( hash_hmac( 'sha256', "{$id}.{$timestamp}.{$payload}", $key, true ) ); // phpcs:ignore
	}

	/** Switches the current user to a role, or to nobody at all. */
	private function beA( string $role ): void {
		if ( 'guest' === $role ) {
			wp_set_current_user( 0 );
			return;
		}

		wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
	}

	/** @return array<int, string> */
	private function notesContaining( \WC_Order $order, string $text ): array {
		return array_values(
			array_filter(
				$this->orderNotes( $order ),
				static fn ( $note ) => false !== strpos( $note, $text )
			)
		);
	}
}
