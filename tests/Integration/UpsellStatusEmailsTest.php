<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\Upsell\UpsellStatus;
use Tests\Support\IntegrationTestCase;

/**
 * The order emails WooCommerce skips when an order leaves the upsell waiting status.
 *
 * @covers \Krokedil\KustomCheckout\Upsell\UpsellStatus
 */
class UpsellStatusEmailsTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	/** @var string[] IDs of the emails sent during the test. */
	private array $sent = [];

	protected function setUp(): void {
		parent::setUp();

		add_filter( 'pre_wp_mail', '__return_true' );
		add_action(
			'woocommerce_email_sent',
			function ( $result, string $id ) {
				$this->sent[] = $id;
			},
			10,
			2
		);
	}

	/**
	 * @dataProvider provide_transitions
	 *
	 * @param string[] $expected Email IDs, in the order they are sent.
	 */
	public function test_leaving_the_waiting_status_sends_the_order_emails( string $to, array $expected ): void {
		$order = $this->haveOrderWaitingIn( UpsellStatus::STATUS_SLUG );

		$order->update_status( $to );

		$this->assertSame( $expected, $this->sent );
	}

	/** @return array<string, array{0: string, 1: string[]}> */
	public function provide_transitions(): array {
		return [
			'push accepted'       => [ 'processing', [ 'new_order', 'customer_processing_order' ] ],
			'push under review'   => [ 'on-hold', [ 'new_order', 'customer_on_hold_order' ] ],
			'completed by an ERP' => [ 'completed', [ 'customer_completed_order', 'new_order' ] ],
		];
	}

	/**
	 * Pending is every order's status before it is confirmed, and on-hold reduces stock before the upsells are added.
	 *
	 * @dataProvider provide_disallowed_statuses
	 */
	public function test_a_disallowed_waiting_status_falls_back_to_the_kustom_status( string $status ): void {
		$this->setGatewaySettings( [ 'enable_upsell' => 'yes', 'upsell_waiting_status' => $status ] );

		$this->assertSame( UpsellStatus::STATUS_SLUG, UpsellStatus::get_configured_status() );
		$this->assertArrayNotHasKey( $status, UpsellStatus::get_waiting_status_options() );
	}

	/** @return array<string, array{0: string}> */
	public function provide_disallowed_statuses(): array {
		return [
			'pending' => [ 'pending' ],
			'on-hold' => [ 'on-hold' ],
		];
	}

	/**
	 * payment_complete() only acts on the statuses it is allowed to, so a status from another plugin must be allowed too.
	 */
	public function test_payment_completes_an_order_waiting_in_another_plugins_status(): void {
		register_post_status( 'wc-awaiting-ship', [ 'label' => 'Awaiting shipment' ] );
		add_filter(
			'wc_order_statuses',
			static function ( array $statuses ): array {
				return $statuses + [ 'wc-awaiting-ship' => 'Awaiting shipment' ];
			}
		);
		$order = $this->haveOrderWaitingIn( 'awaiting-ship' );

		$order->payment_complete( 'kustom-order-123' );

		$this->assertNotNull( $this->reload( $order )->get_date_paid() );
		$this->assertNotSame( 'awaiting-ship', $this->reload( $order )->get_status() );
	}

	private function haveOrderWaitingIn( string $status ): \WC_Order {
		$this->setGatewaySettings( [ 'enable_upsell' => 'yes', 'upsell_waiting_status' => $status ] );
		// WooCommerce reads the email actions on init.
		\WC_Emails::init_transactional_emails();
		// The emails hook themselves in once per process, and the hooks are reset between tests.
		WC()->mailer()->init();

		// Not a Kustom order, so completing it does not try to capture.
		$order = $this->haveOrder( [ 'billing' => $this->swedishAddress() ] );
		$order->set_status( $status );
		$order->save();
		$this->sent = [];

		return $order;
	}
}
