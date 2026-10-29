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

	public function test_on_hold_as_waiting_status_keeps_the_woocommerce_emails(): void {
		$order = $this->haveOrderWaitingIn( 'on-hold' );

		$order->update_status( 'processing' );

		$this->assertSame( [ 'customer_processing_order' ], $this->sent, 'WooCommerce already sent the new order email when the order went on-hold.' );
	}

	private function haveOrderWaitingIn( string $status ): \WC_Order {
		$this->setGatewaySettings( [ 'enable_upsell' => 'yes', 'upsell_waiting_status' => $status ] );
		// The plugin only hooks the status in when upsell is enabled at boot, and WooCommerce reads the email actions on init.
		( new UpsellStatus() )->register_post_status();
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
