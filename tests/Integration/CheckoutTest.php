<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\CheckoutFlow\CheckoutFlow;
use Krokedil\KustomCheckout\CheckoutFlow\EmbeddedBlockFlow;
use Krokedil\KustomCheckout\CheckoutFlow\EmbeddedFlow;
use Krokedil\KustomCheckout\CheckoutFlow\RedirectFlow;
use Tests\Support\IntegrationTestCase;

/**
 * The checkout entry point. Which flow handles a purchase, what each one stamps on
 * the order, and what it hands back to WooCommerce.
 *
 * @covers \Krokedil\KustomCheckout\CheckoutFlow\CheckoutFlow
 * @covers \KCO_Gateway::process_payment
 */
class CheckoutTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	protected function setUp(): void {
		parent::setUp();

		$this->haveCustomerAddress( $this->swedishAddress(), $this->swedishAddress() );
	}

	/**
	 * Which flow a request lands on. The setting only decides between embedded and
	 * redirect; the block checkout and the pay-for-order page override it.
	 *
	 * @dataProvider provide_handlers
	 */
	public function test_the_flow_setting_picks_the_handler( string $flow, string $expected ): void {
		$this->haveRedirectFlowEnabled();
		$this->haveGatewayCredentials( 'eu', [ 'checkout_flow' => $flow ] );
		$this->flushGatewaySettingsCache();

		$this->assertInstanceOf( $expected, CheckoutFlow::get_handler() );
	}

	/** @return array<string, array{0: string, 1: class-string}> */
	public function provide_handlers(): array {
		return [
			'embedded'                    => [ 'embedded', EmbeddedFlow::class ],
			'redirect'                    => [ 'redirect', RedirectFlow::class ],
			'an unknown flow is embedded' => [ 'something-new', EmbeddedFlow::class ],
		];
	}

	public function test_the_block_checkout_overrides_the_flow_setting(): void {
		$this->haveRedirectFlowEnabled();
		$this->haveGatewayCredentials( 'eu', [ 'checkout_flow' => 'redirect' ] );
		$this->flushGatewaySettingsCache();

		// The handler is picked off the checkout page's own content, not a setting.
		$this->haveCheckoutBlockOnTheCheckoutPage();

		$this->assertInstanceOf( EmbeddedBlockFlow::class, CheckoutFlow::get_handler() );
	}

	/**
	 * Swaps the checkout page's shortcode for the WooCommerce checkout block. KSES is
	 * dropped for the save because a block delimiter is an HTML comment, and KSES
	 * strips those from anyone who may not post unfiltered HTML, which is nobody in CLI.
	 */
	private function haveCheckoutBlockOnTheCheckoutPage(): void {
		$page_id = wc_get_page_id( 'checkout' );

		kses_remove_filters();

		wp_update_post(
			[
				'ID'           => $page_id,
				'post_content' => '<!-- wp:woocommerce/checkout --><div class="wp-block-woocommerce-checkout"></div><!-- /wp:woocommerce/checkout -->',
			]
		);

		kses_init_filters();

		clean_post_cache( $page_id );
	}

	/**
	 * Turns on the redirect flow's feature flag.
	 *
	 * The setting only exists behind `kco_enable_redirected_flow`. With the flag off,
	 * `KCO_Fields::fields()` writes `checkout_flow => embedded` straight back over the
	 * stored option, so a store cannot be on the redirect flow without it.
	 */
	private function haveRedirectFlowEnabled(): void {
		add_filter( 'kco_enable_redirected_flow', '__return_true' );
	}

	/**
	 * Every flow stamps the same reference, environment and country on the order.
	 *
	 * @dataProvider provide_stamped_meta
	 */
	public function test_a_processed_order_carries_the_kustom_reference( string $flow, array $expected_meta ): void {
		$this->arrangeFlow( $flow );

		$order  = $this->haveCheckoutOrder();
		$result = $this->gateway()->process_payment( $order->get_id() );

		$reloaded = $this->reload( $order );

		$this->assertSame( 'success', $result['result'] );

		foreach ( $expected_meta as $key => $value ) {
			$this->assertSame( $value, $reloaded->get_meta( $key, true ), $key );
		}
	}

	/** @return array<string, array{0: string, 1: array<string, string>}> */
	public function provide_stamped_meta(): array {
		$meta = static fn( string $flow ): array => [
			'_wc_klarna_order_id'      => 'checkout-order-123',
			'_wc_klarna_environment'   => 'test',
			'_wc_klarna_country'       => 'SE',
			'_wc_klarna_checkout_flow' => $flow,
		];

		return [
			'the embedded flow' => [ 'embedded', $meta( 'embedded' ) ],
			'the redirect flow' => [ 'redirect', $meta( 'redirect' ) ],
		];
	}

	/**
	 * The redirect flow's whole job: hand WooCommerce the hosted payment page URL.
	 */
	public function test_the_redirect_flow_returns_the_hosted_payment_page(): void {
		$this->arrangeFlow( 'redirect' );

		$order  = $this->haveCheckoutOrder();
		$result = $this->gateway()->process_payment( $order->get_id() );

		$this->assertSame( 'https://pay.playground.kustom.co/eu/hpp/payment/hpp-1', $result['redirect'] );

		$reloaded = $this->reload( $order );
		$this->assertSame( $result['redirect'], $reloaded->get_meta( '_wc_klarna_hpp_url', true ) );
		$this->assertSame( 'hpp-session-1', $reloaded->get_meta( '_wc_klarna_hpp_session_id', true ) );
		$this->assertOrderHasNote( $order, 'redirected to Kustom Hosted Payment Page' );
	}

	/**
	 * The embedded flow finishes in the browser, so it must not hand WooCommerce a
	 * redirect of its own.
	 */
	public function test_the_embedded_flow_leaves_the_redirect_to_the_iframe(): void {
		$this->arrangeFlow( 'embedded' );

		$order  = $this->haveCheckoutOrder();
		$result = $this->gateway()->process_payment( $order->get_id() );

		$this->assertArrayNotHasKey( 'redirect', $result );
	}

	public function test_a_live_store_records_the_live_environment(): void {
		$this->arrangeFlow( 'embedded', false );

		$order = $this->haveCheckoutOrder();
		$this->gateway()->process_payment( $order->get_id() );

		$this->assertSame(
			'live',
			$this->reload( $order )->get_meta( '_wc_klarna_environment', true ),
			'Order management reads this to decide which host to talk to.'
		);
	}

	/**
	 * The recurring flags a subscription purchase leaves, which is what a renewal
	 * later charges against.
	 */
	public function test_a_recurring_order_records_its_token(): void {
		$this->arrangeFlow( 'embedded', true, [ 'recurring' => true, 'recurring_token' => 'customer-token-1' ] );

		$order = $this->haveCheckoutOrder();
		$this->gateway()->process_payment( $order->get_id() );

		$reloaded = $this->reload( $order );

		$this->assertSame( 'yes', $reloaded->get_meta( '_kco_recurring_order', true ) );
		$this->assertSame( 'customer-token-1', $reloaded->get_meta( '_kco_recurring_token', true ) );
	}

	/**
	 * @dataProvider provide_failure_guards
	 */
	public function test_a_purchase_that_cannot_proceed_is_stopped( string $scenario, string $message ): void {
		$order = $this->arrangeFailure( $scenario );

		$thrown = null;

		try {
			$this->gateway()->process_payment( $order->get_id() );
		} catch ( \Exception $exception ) {
			$thrown = $exception;
		}

		$this->assertNotNull( $thrown, sprintf( 'Expected "%s" to stop the purchase.', $scenario ) );
		// The plugin escapes the message before it throws, so the apostrophe arrives as an entity.
		$this->assertStringContainsString( $message, html_entity_decode( $thrown->getMessage(), ENT_QUOTES ) );
		$this->assertSame( 'pending', $this->statusOf( $order ) );
	}

	/** @return array<string, array{0: string, 1: string}> */
	public function provide_failure_guards(): array {
		return [
			'the order was never created' => [ 'redirect-no-order', "couldn't create your payment session" ],
			'the hosted page was refused' => [ 'redirect-no-hpp', "couldn't start your payment session" ],
		];
	}

	/** An order id nothing resolves must not be processed as if it were an order. */
	public function test_an_order_id_that_does_not_resolve_is_refused(): void {
		$this->haveGatewayCredentials( 'eu' );
		$this->flushGatewaySettingsCache();

		$this->expectExceptionMessage( 'Invalid order ID.' );

		$this->gateway()->process_payment( 999999999 );
	}

	private function arrangeFailure( string $scenario ): \WC_Order {
		$this->haveRedirectFlowEnabled();
		$this->haveGatewayCredentials( 'eu', [ 'checkout_flow' => 'redirect' ] );
		$this->flushGatewaySettingsCache();

		if ( 'redirect-no-hpp' === $scenario ) {
			// The create succeeds, the hosted page does not.
			$this->willCreateOrder();
		}

		return $this->haveCheckoutOrder();
	}

	/**
	 * Puts the store on the given flow and queues the API answers it needs. The
	 * embedded flow reads back an order the iframe already created, so the reference
	 * is in the session before process_payment() runs.
	 */
	private function arrangeFlow( string $flow, bool $testmode = true, array $klarna_order = [] ): void {
		$this->haveRedirectFlowEnabled();
		$this->haveGatewayCredentials( 'eu', [ 'checkout_flow' => $flow ], $testmode );
		$this->flushGatewaySettingsCache();
		$this->simulateCheckoutPage();

		if ( 'redirect' === $flow ) {
			$this->willCreateOrder( $klarna_order );
			$this->willCreateHpp();
			return;
		}

		WC()->session->set( 'kco_wc_order_id', 'checkout-order-123' );
		$this->willRetrieveOrder( $klarna_order );
	}

	private function gateway(): \KCO_Gateway {
		$this->reloadPaymentGateways();

		return WC()->payment_gateways()->payment_gateways()['kco'];
	}

	private function haveCheckoutOrder( array $args = [] ): \WC_Order {
		return $this->haveOrder(
			array_merge(
				[
					'items'   => [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ],
					'billing' => $this->swedishAddress(),
				],
				$args
			)
		);
	}
}
