<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\InPersonPayments\Checkout;
use Krokedil\KustomCheckout\InPersonPayments\Devices;
use Krokedil\KustomCheckout\InPersonPayments\Gateway;
use Krokedil\KustomCheckout\InPersonPayments\InPersonPayments;
use Krokedil\KustomCheckout\InPersonPayments\Settings\Admin;
use Tests\Support\IntegrationTestCase;

/**
 * The in-person payment method: who is offered it, what it sends to the device, and
 * what it leaves on the order.
 *
 * @covers \Krokedil\KustomCheckout\InPersonPayments\Gateway
 * @covers \Krokedil\KustomCheckout\InPersonPayments\Checkout
 * @covers \Krokedil\KustomCheckout\InPersonPayments\Devices
 * @covers \Krokedil\KustomCheckout\InPersonPayments\SessionPayload
 * @covers \Krokedil\KustomCheckout\InPersonPayments\Request\Post\RequestPostSession
 */
class InPersonPaymentsCheckoutTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	private const DEVICE_ID = '550e8400-e29b-41d4-a716-446655440000';

	protected function tearDown(): void {
		unset( $_POST['payment_method'], $_POST[ Gateway::DEVICE_FIELD ] );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/** The feature switch decides whether WooCommerce ever sees the method at all. */
	public function test_the_method_is_registered_only_when_the_feature_is_on(): void {
		$this->haveIppSettings( [ 'ipp_enabled' => 'no' ] );
		new InPersonPayments();
		$this->reloadPaymentGateways();

		$this->assertArrayNotHasKey( Gateway::ID, WC()->payment_gateways()->payment_gateways() );

		$this->haveIppSettings();
		$ipp = new InPersonPayments();
		$this->reloadPaymentGateways();

		$this->assertArrayHasKey( Gateway::ID, WC()->payment_gateways()->payment_gateways() );

		// Take the gateway filter down again, so later tests decide for themselves.
		remove_filter( 'woocommerce_payment_gateways', [ $ipp, 'add_gateway' ] );
	}

	/**
	 * Only staff take payments at the counter, so the method is capability gated
	 * rather than shown to every shopper.
	 *
	 * @dataProvider provide_availability
	 */
	public function test_who_is_offered_the_method( string $role, string $capability, bool $expected ): void {
		$this->haveIppSettings( [ 'ipp_capability' => $capability ] );
		$this->beA( $role );

		$this->assertSame( $expected, ( new Gateway() )->is_available() );

		// The method renders on every checkout load; a round trip here would block it.
		$this->assertNoGatewayRequests();
	}

	/** @return array<string, array{0: string, 1: string, 2: bool}> */
	public function provide_availability(): array {
		return [
			'a shop manager'                  => [ 'shop_manager', 'manage_woocommerce', true ],
			'an administrator'                => [ 'administrator', 'manage_woocommerce', true ],
			'a customer'                      => [ 'customer', 'manage_woocommerce', false ],
			'a logged out visitor'            => [ 'guest', 'manage_woocommerce', false ],
			'a shop manager, admins only'     => [ 'shop_manager', 'manage_options', false ],
		];
	}

	/** A store that switched the feature off keeps the method off even for staff. */
	public function test_the_disabled_feature_hides_the_method_from_staff(): void {
		$this->haveIppSettings( [ 'ipp_enabled' => 'no' ] );
		$this->beA( 'administrator' );

		$this->assertFalse( ( new Gateway() )->is_available() );
	}

	/** The capability is filterable, for the custom roles membership plugins add. */
	public function test_the_required_capability_can_be_filtered(): void {
		$this->haveIppSettings();
		add_filter( 'kco_ipp_required_capability', static fn () => 'read' );
		$this->beA( 'customer' );

		$this->assertTrue( ( new Gateway() )->is_available() );
	}

	/** The toggle on the payment methods list has to reach the setting the page shows. */
	public function test_toggling_the_method_writes_the_in_person_payments_setting(): void {
		$this->haveIppSettings( [ 'ipp_enabled' => 'no' ] );

		$gateway = new Gateway();
		$gateway->update_option( 'enabled', 'yes' );

		$this->assertSame( 'yes', get_option( 'woocommerce_kco_settings' )['ipp_enabled'] );
		$this->assertSame( 'yes', ( new Gateway() )->get_option( 'enabled' ) );
		$this->assertArrayNotHasKey( 'woocommerce_kco_ipp_settings', wp_load_alloptions() );
	}

	/** The device list is read once and reused, rather than on every checkout render. */
	public function test_the_device_list_is_cached_between_renders(): void {
		$this->haveIppSettings();
		$this->willListDevices();

		$this->assertSame( Devices::all(), Devices::all() );
		$this->assertGatewayRequestCount( 1, 'ipp/v1/devices' );
	}

	/**
	 * Test and live are two accounts with two device lists, and so are two merchant
	 * ids; a list cached for one must not be offered for the other.
	 *
	 * @dataProvider provide_account_switches
	 */
	public function test_the_device_list_is_cached_per_account( array $switch_to ): void {
		$this->haveIppSettings();
		$this->willListDevices();
		Devices::all();

		$this->haveIppSettings( $switch_to );
		$this->willListDevices();
		Devices::all();

		$this->assertGatewayRequestCount( 2, 'ipp/v1/devices' );
	}

	/** @return array<string, array{0: array}> */
	public function provide_account_switches(): array {
		return [
			'from test to live mode'   => [ [ 'testmode' => 'no', 'merchant_id' => 'live-mid', 'shared_secret' => 'live-secret' ] ],
			'to another merchant id'   => [ [ 'test_merchant_id' => 'other-mid' ] ],
		];
	}

	/** Saving the settings is when a merchant expects a newly paired device to show up. */
	public function test_saving_the_settings_forgets_the_cached_device_list(): void {
		$this->haveIppSettings();
		new Admin();
		$this->willListDevices();
		Devices::all();

		do_action( 'woocommerce_update_options_payment_gateways_kco' );
		$this->willListDevices();
		Devices::all();

		$this->assertGatewayRequestCount( 2, 'ipp/v1/devices' );
	}

	/**
	 * A device id arrives from a form, so it is checked against the merchant's own
	 * list rather than trusted into the session payload.
	 *
	 * @dataProvider provide_posted_devices
	 */
	public function test_an_unknown_device_is_refused( string $device_id ): void {
		$this->haveIppSettings();
		$this->willListDevices();
		$_POST[ Gateway::DEVICE_FIELD ] = $device_id;

		$this->assertFalse( ( new Gateway() )->validate_fields() );
		$this->assertNoGatewayRequestsTo( 'ipp/v1/sessions' );
	}

	/** @return array<string, array{0: string}> */
	public function provide_posted_devices(): array {
		return [
			'nothing chosen'          => [ '' ],
			'a device of another mid' => [ '11111111-2222-3333-4444-555555555555' ],
		];
	}

	/** A device the merchant does have passes. */
	public function test_a_paired_device_passes_validation(): void {
		$this->haveIppSettings();
		$this->willListDevices();
		$_POST[ Gateway::DEVICE_FIELD ] = self::DEVICE_ID;

		$this->assertTrue( ( new Gateway() )->validate_fields() );
	}

	/** What the device is asked to collect. The fixture is the assertion. */
	public function test_the_session_payload_matches_the_order(): void {
		// The order number and the id are the same unless a plugin says otherwise, and
		// the fixture has to be able to tell which one is sent where.
		add_filter( 'woocommerce_order_number', static fn ( $number ) => 'SHOP-' . $number );
		$order = $this->haveInPersonOrder();

		$this->processPayment( $order );

		$request = $this->gatewayRequestTo( 'ipp/v1/sessions' );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $request['json']['purchase_started_at'] );

		$request['json']['purchase_started_at'] = '<started-at>';

		$this->assertRequestMatchesSnapshot(
			$request,
			'ipp-create-session',
			[
				'<order-number>' => $order->get_order_number(),
				'<order-id>'     => $order->get_id(),
			]
		);
	}

	/** Kustom rejects a session whose lines do not add up to the amount charged. */
	public function test_the_session_lines_add_up_to_the_order_amount(): void {
		$order = $this->haveInPersonOrder(
			[
				'items'    => [ [ $this->haveSimpleProduct( [ 'name' => 'Thirds', 'price' => '33.33', 'sku' => 'THIRDS' ] ), 3 ] ],
				'shipping' => [ 'total' => '19.99' ],
				'fees'     => [ [ 'name' => 'Handling fee', 'total' => '7.77' ] ],
			]
		);

		$this->processPayment( $order );

		$payload = $this->gatewayRequestTo( 'ipp/v1/sessions' )['json'];

		$this->assertSame(
			$payload['order_amount'],
			array_sum( array_column( $payload['order_items'], 'total_amount' ) ),
			'The session lines must sum to the order amount, or Kustom refuses the session.'
		);
	}

	/**
	 * unit_price is an integer, so a price that does not divide evenly has to be
	 * reconciled inside the line: unit_price × quantity − discount = total_amount.
	 *
	 * @dataProvider provide_awkward_lines
	 */
	public function test_every_line_reconciles_on_its_own( string $price, int $quantity, ?string $discounted_total ): void {
		$order = $this->haveInPersonOrder(
			[ 'items' => [ [ $this->haveSimpleProduct( [ 'name' => 'Awkward', 'price' => $price, 'sku' => 'AWKWARD' ] ), $quantity ] ] ]
		);
		if ( null !== $discounted_total ) {
			$this->discountTheOnlyItem( $order, $discounted_total );
		}

		$this->processPayment( $order );

		$payload = $this->gatewayRequestTo( 'ipp/v1/sessions' )['json'];
		$line    = $this->assertHasOrderLine( $payload['order_items'], 'physical' );

		$this->assertSame( $line['total_amount'], $line['unit_price'] * $line['quantity'] - $line['total_discount_amount'], 'The line does not reconcile.' );
		$this->assertGreaterThanOrEqual( 0, $line['total_discount_amount'], 'A negative discount is not a discount.' );
		$this->assertSame( $payload['order_amount'], array_sum( array_column( $payload['order_items'], 'total_amount' ) ) );
	}

	/** @return array<string, array{0: string, 1: int, 2: ?string}> */
	public function provide_awkward_lines(): array {
		return [
			'3 × 33.33'            => [ '33.33', 3, null ],
			'7 × 9.99'             => [ '9.99', 7, null ],
			'3 × 33.33 with a coupon' => [ '33.33', 3, '80.00' ],
		];
	}

	/** A 25% rate is 2500 basis points, not 25 and not 2500 hundredths of a percent. */
	public function test_the_tax_rate_is_sent_in_basis_points(): void {
		$order = $this->haveInPersonOrder();

		$this->processPayment( $order );

		$line = $this->assertHasOrderLine( $this->gatewayRequestTo( 'ipp/v1/sessions' )['json']['order_items'], 'physical' );

		$this->assertSame( 2500, $line['tax_rate'] );
	}

	/** A virtual product is not something the customer carries out of the shop. */
	public function test_a_virtual_product_is_sent_as_digital(): void {
		$order = $this->haveInPersonOrder(
			[ 'items' => [ [ $this->haveSimpleProduct( [ 'name' => 'Ebook', 'price' => '100.00', 'sku' => 'EBOOK', 'virtual' => true ] ), 1 ] ] ]
		);

		$this->processPayment( $order );

		$this->assertHasOrderLine( $this->gatewayRequestTo( 'ipp/v1/sessions' )['json']['order_items'], 'digital' );
	}

	/** The device decides whether the sale happens, so the order waits unpaid. */
	public function test_a_dispatched_session_leaves_the_order_pending(): void {
		$order = $this->haveInPersonOrder();

		$result = $this->processPayment( $order );

		$this->assertSame( 'success', $result['result'] );
		$this->assertSame( 'pending', $this->statusOf( $order ) );

		$stored = $this->reload( $order );
		$this->assertSame( 'session-1', $stored->get_meta( Gateway::SESSION_META ) );
		$this->assertSame( self::DEVICE_ID, $stored->get_meta( Gateway::DEVICE_META ) );
		$this->assertSame( 'CREATED', $stored->get_meta( Gateway::STATUS_META ) );
		$this->assertOrderHasNote( $order, 'Counter' );
	}

	/** A session Kustom refused leaves nothing behind to be confused for a live one. */
	public function test_a_refused_session_leaves_the_order_untouched(): void {
		$order = $this->haveInPersonOrder();
		$this->willRespondWith( [ 'detail' => 'Device is offline' ], 409, 'ipp/v1/sessions' );

		$result = $this->processPayment( $order, false );

		$this->assertSame( 'failure', $result['result'] );
		$this->assertSame( 'pending', $this->statusOf( $order ) );
		$this->assertSame( '', $this->reload( $order )->get_meta( Gateway::SESSION_META ) );
		$this->assertOrderHasNote( $order, 'Device is offline' );
		$this->assertStringNotContainsString( 'Device is offline', implode( ' ', $this->noticeMessages() ), 'The counter gets a plain sentence; the provider text stays on the order.' );
	}

	/** An order with no email is still one Woo can email a receipt for. */
	public function test_an_order_without_an_email_falls_back_to_the_store_address(): void {
		update_option( 'admin_email', 'shop@example.com' );
		$order = $this->haveInPersonOrder( [ 'billing' => [] ] );

		$this->processPayment( $order );

		$this->assertSame( 'shop@example.com', $this->reload( $order )->get_billing_email() );
	}

	/** The fallback is provisional, so a store can point it somewhere else. */
	public function test_the_fallback_email_can_be_filtered(): void {
		add_filter( 'kco_ipp_fallback_billing_email', static fn () => 'counter@example.com' );

		$this->assertSame( 'counter@example.com', Checkout::fallback_email() );
	}

	/**
	 * A walk-in customer has no address to give and carries the goods out, so nothing
	 * is required and shipping goes away.
	 *
	 * @dataProvider provide_checkout_fields
	 */
	public function test_the_checkout_is_stripped_down_for_a_walk_in_sale( string $chosen, bool $stripped, string $role = 'shop_manager' ): void {
		$this->haveIppSettings();
		$this->beA( $role );
		$_POST['payment_method'] = $chosen;

		$checkout = new Checkout();
		$fields   = $checkout->relax_checkout_fields(
			[
				'billing'  => [ 'billing_email' => [ 'required' => true ], 'billing_address_1' => [ 'required' => true ] ],
				'shipping' => [ 'shipping_address_1' => [ 'required' => true ] ],
			]
		);

		$this->assertSame( $stripped, ! isset( $fields['shipping'] ) );
		$this->assertSame( $stripped, false === $fields['billing']['billing_email']['required'] );
		$this->assertSame( $stripped, false === $checkout->skip_shipping( true ) );
	}

	/** @return array<string, array{0: string, 1: bool, 2?: string}> */
	public function provide_checkout_fields(): array {
		return [
			'in-person payment'                   => [ Gateway::ID, true ],
			'the normal gateway'                  => [ 'kco', false ],
			'in-person payment, posted by a shopper' => [ Gateway::ID, false, 'customer' ],
			'in-person payment, posted logged out'   => [ Gateway::ID, false, 'guest' ],
		];
	}

	/** A shopper can put any method id in the session; that must not strip their checkout. */
	public function test_a_shopper_cannot_strip_the_checkout_through_the_session(): void {
		$this->haveIppSettings();
		$this->beA( 'customer' );
		WC()->session->set( 'chosen_payment_method', Gateway::ID );

		$this->assertTrue( ( new Checkout() )->skip_shipping( true ) );
		$this->assertFalse( Checkout::is_chosen() );
	}

	/** The provider's wording is kept on the order, but not its markup. */
	public function test_provider_markup_does_not_reach_the_order_note(): void {
		$order = $this->haveInPersonOrder();
		$this->willRespondWith( [ 'detail' => 'Device <script>alert(1)</script> is <b>offline</b>' ], 409, 'ipp/v1/sessions' );

		$this->processPayment( $order, false );

		$this->assertOrderHasNote( $order, 'is offline' );
		$this->assertStringNotContainsString( '<', implode( ' ', $this->orderNotes( $order ) ) );
	}

	/** Switches the current user to a role, or to nobody at all. */
	private function beA( string $role ): void {
		if ( 'guest' === $role ) {
			wp_set_current_user( 0 );
			return;
		}

		wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
	}

	/** Turns the feature on, over the store profile's credentials. */
	private function haveIppSettings( array $overrides = [] ): void {
		$this->haveGatewayCredentials(
			array_merge(
				[
					'ipp_enabled'    => 'yes',
					'ipp_title'      => 'In-Person Payment',
					'ipp_capability' => 'manage_woocommerce',
				],
				$overrides
			)
		);
	}

	/** Queues the device list the checkout reads. */
	private function willListDevices(): void {
		$this->willRespondWith(
			[ 'content' => [ [ 'id' => self::DEVICE_ID, 'name' => 'Counter', 'platform' => 'ANDROID' ] ] ],
			200,
			'ipp/v1/devices'
		);
	}

	/** A pending order placed by a salesperson, with the device already chosen. */
	private function haveInPersonOrder( array $args = [] ): \WC_Order {
		$this->haveIppSettings();
		$this->beA( 'shop_manager' );
		$_POST[ Gateway::DEVICE_FIELD ] = self::DEVICE_ID;

		$order = $this->haveOrder(
			array_merge(
				[
					'items'   => [ [ $this->haveSimpleProduct( [ 'name' => 'Counter product', 'price' => '100.00', 'sku' => 'COUNTER-1' ] ), 2 ] ],
					'billing' => $this->swedishAddress(),
					'status'  => 'pending',
				],
				$args
			)
		);
		$order->set_payment_method( Gateway::ID );
		$order->save();

		return $order;
	}

	/** Runs the gateway over the order, with the device list and the session queued. */
	private function processPayment( \WC_Order $order, bool $queue_session = true ): array {
		$this->willListDevices();

		if ( $queue_session ) {
			$this->willRespondWith(
				[ 'session_id' => 'session-1', 'status' => 'CREATED', 'device_id' => self::DEVICE_ID ],
				200,
				'ipp/v1/sessions'
			);
		}

		wc_clear_notices();

		return ( new Gateway() )->process_payment( $order->get_id() );
	}

	/** Takes a coupon-shaped discount on the order's one product line. */
	private function discountTheOnlyItem( \WC_Order $order, string $total_incl_tax ): void {
		foreach ( $order->get_items() as $item ) {
			$total = (float) $total_incl_tax / 1.25;
			$item->set_total( (string) $total );
			$item->set_total_tax( (string) ( (float) $total_incl_tax - $total ) );
			$item->save();
		}

		$order->calculate_totals( false );
		$order->save();
	}

	/** @return array<int, string> */
	private function noticeMessages(): array {
		return array_column( wc_get_notices( 'error' ), 'notice' );
	}

	private function assertNoGatewayRequestsTo( string $url_contains ): void {
		$this->assertSame( [], $this->gatewayRequestsTo( $url_contains ), sprintf( 'Expected no request to "%s".', $url_contains ) );
	}
}
