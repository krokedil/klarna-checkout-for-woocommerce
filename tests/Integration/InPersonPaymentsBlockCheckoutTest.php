<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\Blocks\InPersonPayments\BlockCheckout;
use Krokedil\KustomCheckout\Blocks\InPersonPayments\CheckoutBlock;
use Krokedil\KustomCheckout\InPersonPayments\Gateway;
use Krokedil\KustomCheckout\InPersonPayments\InPersonPayments;
use Krokedil\KustomCheckout\InPersonPayments\WaitingScreen;
use Tests\Support\IntegrationTestCase;

/**
 * The in-person payment method on the block checkout: what reaches the React side,
 * and what selecting the method does to the cart.
 *
 * @covers \Krokedil\KustomCheckout\Blocks\InPersonPayments\CheckoutBlock
 * @covers \Krokedil\KustomCheckout\Blocks\InPersonPayments\BlockCheckout
 */
class InPersonPaymentsBlockCheckoutTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	private const DEVICE_ID = '550e8400-e29b-41d4-a716-446655440000';

	/** The feature as wired by a test, so its gateway filter can be taken down again. */
	private ?InPersonPayments $ipp = null;

	protected function tearDown(): void {
		unset( $_POST['payment_method'], $_GET['key'] );
		set_query_var( 'order-received', '' );
		wp_set_current_user( 0 );

		if ( $this->ipp ) {
			remove_filter( 'woocommerce_payment_gateways', [ $this->ipp, 'add_gateway' ] );
			$this->ipp = null;
		}

		parent::tearDown();
	}

	/** The feature switch decides whether the block checkout offers the method. */
	public function test_the_method_is_offered_only_when_the_feature_is_on(): void {
		$this->haveIppSettings( [ 'ipp_enabled' => 'no' ] );
		$this->assertFalse( ( new CheckoutBlock() )->is_active() );

		$this->haveIppSettings();
		$this->assertTrue( ( new CheckoutBlock() )->is_active() );
	}

	/**
	 * The payment method data is printed into the checkout page, so the device list
	 * only goes to a user who is allowed to take a payment on one.
	 *
	 * @dataProvider provide_users
	 */
	public function test_the_device_list_only_reaches_staff( string $role, bool $expected ): void {
		$this->haveIppSettings();
		$this->willListDevices();
		$this->beA( $role );

		$devices = ( new CheckoutBlock() )->get_payment_method_data()['devices'];

		$this->assertSame( $expected, [ [ 'id' => self::DEVICE_ID, 'name' => 'Counter' ] ] === $devices );
	}

	/** @return array<string, array{0: string, 1: bool}> */
	public function provide_users(): array {
		return [
			'a shop manager'       => [ 'shop_manager', true ],
			'a customer'           => [ 'customer', false ],
			'a logged out visitor' => [ 'guest', false ],
		];
	}

	/** A device list Kustom will not give up hides the method rather than breaking it. */
	public function test_an_unreachable_device_list_leaves_no_devices(): void {
		$this->haveIppSettings();
		$this->beA( 'shop_manager' );
		$this->willRespondWith( [ 'detail' => 'Service unavailable' ], 503, 'ipp/v1/devices' );

		$this->assertSame( [], ( new CheckoutBlock() )->get_payment_method_data()['devices'] );
	}

	/** Nothing but the id and the name of a device belongs in a page's settings. */
	public function test_only_the_id_and_the_name_of_a_device_are_exposed(): void {
		$this->haveIppSettings();
		$this->beA( 'shop_manager' );
		$this->willListDevices();

		$device = ( new CheckoutBlock() )->get_payment_method_data()['devices'][0];

		$this->assertSame( [ 'id', 'name' ], array_keys( $device ) );
	}

	/**
	 * A walk-in customer carries the goods out, so picking the method on the block
	 * checkout switches shipping off, and picking another one puts it back.
	 */
	public function test_choosing_the_method_switches_shipping_off(): void {
		$this->haveIppSettings();
		$this->beA( 'shop_manager' );
		$this->ipp = new InPersonPayments();
		$this->haveShippableCart();

		$this->assertTrue( WC()->cart->needs_shipping() );

		BlockCheckout::choose( true );
		$this->assertFalse( WC()->cart->needs_shipping() );

		BlockCheckout::choose( false );
		$this->assertTrue( WC()->cart->needs_shipping() );
	}

	/** Shipping is not something a shopper gets to switch off for themselves. */
	public function test_a_shopper_cannot_switch_shipping_off(): void {
		$this->haveIppSettings();
		$this->beA( 'customer' );
		$this->ipp = new InPersonPayments();
		$this->haveShippableCart();

		BlockCheckout::choose( true );

		$this->assertTrue( WC()->cart->needs_shipping() );
		$this->assertNotSame( Gateway::ID, WC()->session->get( 'chosen_payment_method' ) );
	}

	/**
	 * The block checkout is told which fields are required once per page load, so the
	 * address is relaxed for whoever may take an in-person payment.
	 *
	 * @dataProvider provide_field_users
	 */
	public function test_the_address_is_optional_for_staff( string $role, bool $optional ): void {
		$this->haveIppSettings();
		$this->beA( $role );
		$this->simulateCheckoutPage();
		new BlockCheckout();

		$settings = apply_filters( 'woocommerce_shared_settings', $this->shared_settings() );

		$this->assertSame( $optional, false === $settings['defaultFields']['email']['required'] );
		$this->assertSame( $optional, false === $settings['countryData']['SE']['locale']['postcode']['required'] );
		$this->assertTrue( $settings['defaultFields']['country']['required'], 'The address format and the state list are derived from the country.' );
	}

	/** @return array<string, array{0: string, 1: bool}> */
	public function provide_field_users(): array {
		return [
			'a shop manager' => [ 'shop_manager', true ],
			'a customer'     => [ 'customer', false ],
		];
	}

	/** A store that does not want a relaxed checkout for its staff can say so. */
	public function test_the_relaxed_address_can_be_switched_off(): void {
		$this->haveIppSettings();
		$this->beA( 'shop_manager' );
		$this->simulateCheckoutPage();
		new BlockCheckout();
		add_filter( 'kco_ipp_relax_block_checkout_fields', '__return_false' );

		$settings = apply_filters( 'woocommerce_shared_settings', $this->shared_settings() );

		$this->assertTrue( $settings['defaultFields']['email']['required'] );
	}

	/**
	 * The salesperson watches the same waiting screen on a block order received page:
	 * the block order confirmation renders `woocommerce_thankyou_kco_ipp`, so the
	 * ticket-03 state machine drives both surfaces.
	 */
	public function test_the_waiting_screen_renders_on_a_block_order_received_page(): void {
		$this->haveIppSettings();
		$this->beA( 'shop_manager' );
		new WaitingScreen();

		$order = $this->haveOrder( [ 'status' => 'pending', 'billing' => $this->swedishAddress() ] );
		$order->set_payment_method( Gateway::ID );
		$order->set_customer_id( get_current_user_id() );
		$order->update_meta_data( Gateway::SESSION_META, 'session-1' );
		$order->save();

		set_query_var( 'order-received', (string) $order->get_id() );
		$_GET['key'] = $order->get_order_key();

		$rendered = do_blocks( '<!-- wp:woocommerce/order-confirmation-additional-information /-->' );

		$this->assertStringContainsString( 'kco-ipp-waiting', $rendered );
		$this->assertTrue( wp_script_is( 'kco_ipp_checkout', 'enqueued' ) );
	}

	/**
	 * The React side hands the device back as payment method data; the Store API puts
	 * it in `$_POST`, and the same gateway sends the sale to the device. The customer
	 * gave no address, and the Store API's own validation has to let that through.
	 */
	public function test_placing_a_block_order_sends_the_sale_to_the_chosen_device(): void {
		$this->haveIppSettings();
		$this->beA( 'shop_manager' );
		$this->ipp = new InPersonPayments();
		new BlockCheckout();
		$this->reloadPaymentGateways();
		$this->haveCartWith( [ [ $this->haveSimpleProduct( [ 'name' => 'Counter product', 'price' => '100.00', 'sku' => 'COUNTER-1' ] ), 2 ] ] );
		BlockCheckout::choose( true );
		$this->willListDevices();
		$this->willListDevices();
		$this->willRespondWith( [ 'session_id' => 'session-1', 'status' => 'CREATED', 'device_id' => self::DEVICE_ID ], 200, 'ipp/v1/sessions' );
		add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );

		// The locale is computed once per request; the sale was chosen after that here.
		WC()->countries = new \WC_Countries();

		$request = new \WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
		$request->set_body_params(
			[
				'billing_address' => [ 'country' => 'SE' ],
				'payment_method'  => Gateway::ID,
				'payment_data'    => [ [ 'key' => Gateway::DEVICE_FIELD, 'value' => self::DEVICE_ID ] ],
			]
		);

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertSame( self::DEVICE_ID, $this->gatewayRequestTo( 'ipp/v1/sessions' )['json']['device_id'] );

		$order = wc_get_order( $response->get_data()['order_id'] );
		$this->assertSame( 'pending', $order->get_status() );
		$this->assertSame( 'session-1', $order->get_meta( Gateway::SESSION_META ) );
		$this->assertSame( $order->get_checkout_order_received_url(), $response->get_data()['payment_result']['redirect_url'] );
	}

	/** A cart of physical goods with a shipping rate to choose from. */
	private function haveShippableCart(): void {
		$this->haveCartWith( [ [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ), 1 ] ] );
		$this->haveChosenFlatRateShipping( 'SE' );
	}

	/** The parts of the block checkout settings the address relaxation rewrites. */
	private function shared_settings(): array {
		return [
			'defaultFields' => [
				'email'    => [ 'required' => true ],
				'country'  => [ 'required' => true ],
				'postcode' => [ 'required' => true ],
			],
			'countryData'   => [
				'SE' => [ 'locale' => [ 'postcode' => [ 'required' => true ] ] ],
			],
		];
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
}
