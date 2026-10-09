<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\Blocks\OrderValidation;
use Krokedil\KustomCheckout\Express\Express;
use Krokedil\KustomCheckout\Express\ExpressSession;
use Krokedil\KustomCheckout\Express\OrderCreator;
use Krokedil\KustomCheckout\Express\RateLimiter;
use Krokedil\KustomCheckout\Express\RestController;
use Krokedil\KustomCheckout\Utility\BlocksUtility;
use Tests\Support\IntegrationTestCase;

/**
 * Express buttons: when they show, the Kustom order their createOrder hook asks for, and its validation.
 *
 * @covers \Krokedil\KustomCheckout\Express\Express
 * @covers \Krokedil\KustomCheckout\Express\OrderCreator
 * @covers \Krokedil\KustomCheckout\Express\ExpressSession
 * @covers \Krokedil\KustomCheckout\Express\RestController
 * @covers \Krokedil\KustomCheckout\Express\RateLimiter
 * @covers \Krokedil\KustomCheckout\Blocks\OrderValidation::validate_kco_order
 */
class ExpressTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	protected function setUp(): void {
		parent::setUp();

		$this->haveCustomerAddress( $this->swedishAddress(), $this->swedishAddress() );
		$this->haveExpressSettings();
	}

	protected function tearDown(): void {
		delete_transient( RateLimiter::TRANSIENT_PREFIX . RateLimiter::get_id() );
		wp_set_current_user( 0 );
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		delete_transient( 'kss_data_checkout-order-123' );
		unset( $GLOBALS['wp']->query_vars['order-received'] );

		parent::tearDown();
	}

	/**
	 * @dataProvider provide_gating
	 */
	public function test_the_buttons_only_show_with_ksa_and_a_public_key( array $settings, bool $shown ): void {
		$this->haveExpressSettings( $settings );
		$this->haveCartWith( [ $this->haveSimpleProduct() ] );

		$this->assertSame( $shown, Express::is_available() );
		$this->assertSame( $shown, '' !== Express::render( Express::CONTEXT_CART ) );
	}

	/** @return array<string, array{0: array, 1: bool}> */
	public function provide_gating(): array {
		return [
			'everything set up'                 => [ [], true ],
			'KSA disabled'                      => [ [ 'ksa_enabled' => 'no' ], false ],
			'no public key'                     => [ [ 'elements_playground_public_api_key' => '' ], false ],
			'only the production key, testmode' => [ [ 'elements_playground_public_api_key' => '', 'elements_live_public_api_key' => 'pk_live' ], false ],
			'gateway disabled'                  => [ [ 'enabled' => 'no' ], false ],
		];
	}

	/**
	 * @dataProvider provide_product_placements
	 */
	public function test_product_express_is_off_until_a_placement_is_chosen( ?string $position, bool $enabled ): void {
		$this->haveExpressSettings( null === $position ? [] : [ 'elements_express_product_position' => $position ] );

		$this->assertSame( $enabled, Express::is_product_express_enabled() );
	}

	/** @return array<string, array{0: ?string, 1: bool}> */
	public function provide_product_placements(): array {
		return [
			'never saved'             => [ null, false ],
			'disabled'                => [ '', false ],
			'shortcode or block only' => [ 'manual', true ],
			'after the add to cart'   => [ '35', true ],
			'not a placement'         => [ '36', false ],
		];
	}

	public function test_the_cart_express_order_matches_the_snapshot(): void {
		$this->haveCartWith(
			[
				[ $this->haveSimpleProduct( [ 'name' => 'Express Product A', 'sku' => 'express-a' ] ), 2 ],
				$this->haveSimpleProduct( [ 'name' => 'Express Product B', 'sku' => 'express-b', 'price' => '59.50' ] ),
			]
		);
		$this->resetHttpInterception();
		$this->willCreateOrder();

		$response = $this->createExpressOrder( [ 'context' => 'cart' ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ 'order_id' => 'checkout-order-123' ], $response->get_data() );

		$request       = $this->gatewayRequestTo( '/checkout/v3/orders' );
		$merchant_data = json_decode( $request['json']['merchant_data'], true );

		$this->assertSame( 'cart', $merchant_data['kco_express'] );
		$this->assertTrue( $request['json']['options']['require_validate_callback_success'], 'Kustom must wait for the validation callback, which creates the order.' );
		$this->assertStringEndsWith( '/validate', $request['json']['merchant_urls']['validation'] );
		$this->assertRequestMatchesSnapshot( $request, 'express-create-cart', $this->merchantDataPlaceholders( $merchant_data ) );
	}

	public function test_an_express_order_id_is_kept_apart_from_the_iframe_order(): void {
		$this->haveCartWith( [ $this->haveSimpleProduct() ] );
		WC()->session->set( 'kco_wc_order_id', 'iframe-order-1' );
		$this->resetHttpInterception();
		$this->willCreateOrder();

		$this->createExpressOrder( [ 'context' => 'cart' ] );

		$this->assertSame( [ 'checkout-order-123' => '' ], OrderCreator::get_remembered() );
		$this->assertSame( 'iframe-order-1', WC()->session->get( 'kco_wc_order_id' ), 'An abandoned express order must never be picked up by the iframe.' );
	}

	public function test_product_express_buys_only_that_product_and_keeps_the_cart(): void {
		$this->haveExpressSettings( [ 'elements_express_product_position' => 'manual' ] );
		$in_cart = [ $this->haveSimpleProduct(), $this->haveSimpleProduct() ];
		$this->haveCartWith( $in_cart );
		$cart_before = WC()->cart->get_cart_contents_count();
		$express     = $this->haveSimpleProduct( [ 'name' => 'Express Product C', 'sku' => 'express-c', 'price' => '80.00' ] );
		$this->resetHttpInterception();
		$this->willCreateOrder();

		$response = $this->createExpressOrder(
			[
				'context'    => 'product',
				'product_id' => $express->get_id(),
				'quantity'   => 3,
			]
		);

		$this->assertSame( 200, $response->get_status() );

		$request       = $this->gatewayRequestTo( '/checkout/v3/orders' );
		$merchant_data = json_decode( $request['json']['merchant_data'], true );

		$this->assertSame( [ 'express-c' ], array_column( $request['json']['order_lines'], 'reference' ) );
		$this->assertSame( 'product', $merchant_data['kco_express'] );
		$this->assertSame( $cart_before, WC()->cart->get_cart_contents_count(), 'The shopper\'s own cart must be untouched.' );
		$this->assertEqualsCanonicalizing(
			array_map( static fn( $product ) => $product->get_id(), $in_cart ),
			array_column( WC()->cart->get_cart(), 'product_id' )
		);

		$express_cart = ExpressSession::open( $merchant_data['wc_cart_token'] )->get( 'cart' );
		$this->assertSame( [ $express->get_id() ], array_values( array_column( $express_cart, 'product_id' ) ), 'The cart token must load a cart holding only the express product.' );
		$this->assertSame( [ 3 ], array_values( array_column( $express_cart, 'quantity' ) ) );

		$this->assertRequestMatchesSnapshot( $request, 'express-create-product', $this->merchantDataPlaceholders( $merchant_data ) );
	}

	public function test_confirming_a_product_express_order_deletes_its_express_session(): void {
		$this->haveExpressSettings( [ 'elements_express_product_position' => 'manual' ] );
		$this->resetHttpInterception();
		$this->willCreateOrder();
		$this->createExpressOrder(
			[
				'context'    => 'product',
				'product_id' => $this->haveSimpleProduct()->get_id(),
			]
		);
		$token = json_decode( $this->gatewayRequestTo( '/checkout/v3/orders' )['json']['merchant_data'], true )['wc_cart_token'];
		$order = $this->haveGatewayOrder( [ 'kustom' => [ 'order_id' => 'checkout-order-123' ] ] );
		$order->update_meta_data( Express::ORDER_META, 'product' );
		$order->save();

		do_action( 'kco_wc_confirm_klarna_order', $order->get_id(), [] ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment

		$this->assertNull( ExpressSession::open( $token )->get( 'cart' ) );
		$this->assertSame( [], OrderCreator::get_remembered() );
	}

	public function test_confirming_one_express_order_keeps_another_open_one(): void {
		$this->haveExpressSettings( [ 'elements_express_product_position' => 'manual' ] );
		$this->resetHttpInterception();
		$this->willCreateOrder( [ 'order_id' => 'express-a' ] );
		$this->willCreateOrder( [ 'order_id' => 'express-b' ] );
		$this->createExpressOrder( [ 'context' => 'product', 'product_id' => $this->haveSimpleProduct()->get_id() ] );
		$this->createExpressOrder( [ 'context' => 'product', 'product_id' => $this->haveSimpleProduct()->get_id() ] );
		[ $token_a, $token_b ] = array_map(
			static fn( $request ) => json_decode( $request['json']['merchant_data'], true )['wc_cart_token'],
			$this->gatewayRequestsTo( '/checkout/v3/orders' )
		);
		$order = $this->haveGatewayOrder( [ 'kustom' => [ 'order_id' => 'express-a' ] ] );
		$order->update_meta_data( Express::ORDER_META, 'product' );
		$order->save();

		do_action( 'kco_wc_confirm_klarna_order', $order->get_id(), [] ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment

		$this->assertNull( ExpressSession::open( $token_a )->get( 'cart' ) );
		$this->assertNotEmpty( ExpressSession::open( $token_b )->get( 'cart' ), 'The newer open sheet must keep its cart.' );
		$this->assertSame( [ 'express-b' ], array_keys( OrderCreator::get_remembered() ), 'The newer order must still be confirmable.' );
	}

	public function test_a_refused_product_express_order_leaves_the_cart_as_it_was(): void {
		$this->haveExpressSettings( [ 'elements_express_product_position' => 'manual' ] );
		$this->haveCartWith( [ $this->haveSimpleProduct() ] );
		$cart_hash = WC()->cart->get_cart_hash();
		$this->resetHttpInterception();

		$response = $this->createExpressOrder(
			[
				'context'    => 'product',
				'product_id' => $this->haveSimpleProduct()->get_id(),
			]
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertArrayNotHasKey( 'order_id', $response->get_data() );
		$this->assertSame( $cart_hash, WC()->cart->get_cart_hash() );
		$this->assertSame( [], OrderCreator::get_remembered() );
	}

	public function test_a_variable_product_needs_a_chosen_variation(): void {
		$this->haveExpressSettings( [ 'elements_express_product_position' => 'manual' ] );
		[ $parent ] = $this->haveVariableProduct( [ 'Red' => [], 'Blue' => [] ] );
		$this->resetHttpInterception();

		$response = $this->createExpressOrder(
			[
				'context'    => 'product',
				'product_id' => $parent->get_id(),
			]
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertNoGatewayRequests( 'Nothing can be bought until a variation is chosen.' );
	}

	public function test_the_endpoint_refuses_requests_while_express_is_unavailable(): void {
		$this->assertTrue( ( new RestController() )->check_permission(), 'Cached pages carry no fresh nonce, so a guest needs none.' );

		$this->haveExpressSettings( [ 'ksa_enabled' => 'no' ] );

		$this->assertFalse( ( new RestController() )->check_permission() );
	}

	public function test_a_cart_express_order_is_not_tied_to_the_draft_order(): void {
		$this->haveCartWith( [ $this->haveSimpleProduct() ] );
		WC()->session->set( 'store_api_draft_order', $this->haveOrder()->get_id() );
		$this->resetHttpInterception();
		$this->willCreateOrder();

		$this->createExpressOrder( [ 'context' => 'cart' ] );

		$body = $this->gatewayRequestTo( '/checkout/v3/orders' )['json'];
		$this->assertArrayNotHasKey( 'merchant_reference2', $body, 'Paying the draft as is would ignore cart changes and the sheet\'s shipping.' );
	}

	public function test_the_endpoint_refuses_product_express_while_it_is_disabled(): void {
		$response = $this->createExpressOrder(
			[
				'context'    => 'product',
				'product_id' => $this->haveSimpleProduct()->get_id(),
			]
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertNoGatewayRequests( 'The setting must hold for the endpoint, not only for the button.' );
	}

	public function test_a_shopper_creating_too_many_express_orders_is_told_to_wait(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'customer' ] ) );
		$this->haveCartWith( [ $this->haveSimpleProduct() ] );
		$this->resetHttpInterception();

		for ( $i = 0; $i < 5; $i++ ) {
			$this->willCreateOrder();
			$this->assertSame( 200, $this->createExpressOrder( [ 'context' => 'cart' ] )->get_status() );
		}

		$response = $this->createExpressOrder( [ 'context' => 'cart' ] );

		$this->assertSame( 429, $response->get_status() );
		$this->assertGreaterThan( 0, (int) $response->get_headers()['Retry-After'] );
		$this->assertGatewayRequestCount( 5, '/checkout/v3/orders', 'A refused attempt must not reach Kustom.' );
	}

	public function test_the_rate_limit_window_starts_over_once_it_has_passed(): void {
		add_filter( 'kco_express_rate_limit', static fn() => [ 'limit' => 1, 'seconds' => 60 ] );
		$key = RateLimiter::TRANSIENT_PREFIX . RateLimiter::get_id();

		$this->assertFalse( RateLimiter::hit() );
		$this->assertIsInt( RateLimiter::hit() );

		set_transient( $key, [ 'count' => 1, 'reset' => time() - 1 ], 60 );

		$this->assertFalse( RateLimiter::hit() );
	}

	public function test_guests_get_a_looser_limit_since_a_proxy_may_give_them_one_ip(): void {
		$this->assertSame( 30, RateLimiter::get_options()['limit'] );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'customer' ] ) );

		$this->assertSame( 5, RateLimiter::get_options()['limit'] );
	}

	public function test_guests_and_logged_in_shoppers_are_counted_apart(): void {
		add_filter( 'kco_express_rate_limit', static fn() => [ 'limit' => 1, 'seconds' => 60 ] );

		$this->assertFalse( RateLimiter::hit(), 'The guest\'s first attempt.' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'customer' ] ) );
		$this->assertFalse( RateLimiter::hit(), 'A logged-in shopper has a counter of their own.' );
		delete_transient( RateLimiter::TRANSIENT_PREFIX . RateLimiter::get_id() );

		wp_set_current_user( 0 );
		$this->assertIsInt( RateLimiter::hit(), 'The guest\'s second attempt.' );
	}

	public function test_proxy_headers_only_identify_a_guest_when_the_store_trusts_them(): void {
		$remote_addr                     = $_SERVER['REMOTE_ADDR'] ?? null;
		$_SERVER['REMOTE_ADDR']          = '192.0.2.10';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.20, 192.0.2.10';

		$untrusted = RateLimiter::get_id();
		add_filter( 'woocommerce_store_api_rate_limit_options', static fn( $options ) => array_merge( $options, [ 'proxy_support' => true ] ) );
		$trusted = RateLimiter::get_id();

		$_SERVER['REMOTE_ADDR'] = $remote_addr;

		$this->assertSame( 'g' . md5( '192.0.2.10' ), $untrusted, 'Anyone can send X-Forwarded-For, so it is ignored by default.' );
		$this->assertSame( 'g' . md5( '198.51.100.20' ), $trusted );
	}

	public function test_the_express_order_leaves_shipping_to_ksa(): void {
		$this->haveCartWith( [ $this->haveSimpleProduct() ] );
		$this->haveChosenFlatRateShipping( 'SE', '50.00' );
		$this->recalculateCart();
		$this->resetHttpInterception();
		$this->willCreateOrder();

		$this->createExpressOrder( [ 'context' => 'cart' ] );

		$body = $this->gatewayRequestTo( '/checkout/v3/orders' )['json'];
		$this->assertNotContains( 'shipping_fee', array_column( $body['order_lines'], 'type' ), 'KSA adds shipping in the sheet.' );
		$this->assertSame(
			$body['order_amount'],
			array_sum( array_column( $body['order_lines'], 'total_amount' ) ),
			'The API rejects an order whose lines do not sum to the order amount.'
		);
	}

	/**
	 * The sheet's shipping is written to the session the order is placed from, then the
	 * order total, shipping included, has to match what the shopper approved.
	 *
	 * @dataProvider provide_approved_totals
	 */
	public function test_an_express_order_matching_the_approved_total_is_placed( array $kustom ): void {
		[ $order, $token ] = $this->haveExpressOrderToValidate( $kustom );
		$primed            = $this->captureSessionWhenTheOrderIsPlaced( $token );

		OrderValidation::validate_kco_order( 'checkout-order-123' );

		$this->assertSame( 'cart', $this->reload( $order )->get_meta( Express::ORDER_META ) );
		$this->assertSame( 'checkout-order-123', $primed['kco_wc_order_id'] ?? null, 'process_payment() reads the Kustom order id from this session.' );
		$this->assertTrue( $primed['kco_kss_enabled'] ?? null, 'The KSA rate is only offered when the session enables it.' );
		$this->assertSame( 4900, get_transient( 'kss_data_checkout-order-123' )['price'] ?? null );
		$this->assertNull( ExpressSession::open( $token )->get( 'kco_wc_order_id' ), 'The shopper\'s session must not keep the express order for the iframe to reuse.' );
	}

	/** @return array<string, array{0: array}> */
	public function provide_approved_totals(): array {
		// The order is one 100.00 product with 25% VAT, so 12500, and has no shipping line.
		return [
			'items plus the selected shipping'    => [ [ 'order_amount' => 7600 ] ],
			'shipping already in the order lines' => [
				[
					'order_amount' => 12500,
					'order_lines'  => [
						[ 'type' => 'physical', 'reference' => 'express-validated', 'quantity' => 1 ],
						[ 'type' => 'shipping_fee', 'reference' => 'shipping', 'quantity' => 1 ],
					],
				],
			],
		];
	}

	/**
	 * @dataProvider provide_unapproved_orders
	 */
	public function test_an_express_order_the_shopper_did_not_approve_is_refused( array $kustom ): void {
		[ , $token ] = $this->haveExpressOrderToValidate( $kustom );
		$session     = ExpressSession::open( $token );
		$session->set( 'kco_wc_order_id', 'iframe-order-1' );
		ExpressSession::save( $session );

		try {
			OrderValidation::validate_kco_order( 'checkout-order-123' );
			$this->fail( 'The order must not validate.' );
		} catch ( \Exception $e ) {
			$this->assertSame( 401, $e->getCode() );
		}

		$this->assertSame( 'iframe-order-1', ExpressSession::open( $token )->get( 'kco_wc_order_id' ), 'The session must get its iframe order back.' );
	}

	/** @return array<string, array{0: array}> */
	public function provide_unapproved_orders(): array {
		return [
			'shipping on top of an approved 12500' => [ [ 'order_amount' => 12500 ] ],
			'a different product, same total'      => [
				[
					'order_amount' => 7600,
					'order_lines'  => [ [ 'type' => 'physical', 'reference' => 'something-else', 'quantity' => 1 ] ],
				],
			],
			'one more of the product, same total'  => [
				[
					'order_amount' => 7600,
					'order_lines'  => [ [ 'type' => 'physical', 'reference' => 'express-validated', 'quantity' => 2 ] ],
				],
			],
		];
	}

	/**
	 * The Store API request is where the primed session is read, so record it there.
	 *
	 * @return \ArrayObject<string, mixed>
	 */
	private function captureSessionWhenTheOrderIsPlaced( string $token ): \ArrayObject {
		$primed = new \ArrayObject();
		add_filter(
			'pre_http_request',
			static function ( $response, $args, $url ) use ( $primed, $token ) {
				if ( false !== strpos( $url, 'wc/store/v1/checkout' ) ) {
					$session = ExpressSession::open( $token );
					foreach ( [ 'kco_wc_order_id', 'kco_kss_enabled' ] as $key ) {
						$primed[ $key ] = $session->get( $key );
					}
				}
				return $response;
			},
			1,
			3
		);

		return $primed;
	}

	/**
	 * @dataProvider provide_order_received_pages
	 */
	public function test_the_order_received_page_keeps_the_cart_only_for_product_express( string $express, bool $cleared ): void {
		$order = $this->haveGatewayOrder();
		$order->update_meta_data( Express::ORDER_META, $express );
		$order->save();
		$GLOBALS['wp']->query_vars['order-received'] = (string) $order->get_id();

		$this->assertSame( $cleared, apply_filters( 'woocommerce_should_clear_cart_after_payment', true ) );
	}

	/** @return array<string, array{0: string, 1: bool}> */
	public function provide_order_received_pages(): array {
		return [
			'iframe order'     => [ '', true ],
			'cart express'     => [ 'cart', true ],
			'product express'  => [ 'product', false ],
		];
	}

	/**
	 * A 12500 WooCommerce order the Store API answers with, and a Kustom express order
	 * whose cart token points at the shopper's session and whose sheet chose 4900 shipping.
	 *
	 * @return array{0: \WC_Order, 1: string}
	 */
	private function haveExpressOrderToValidate( array $kustom ): array {
		$token = BlocksUtility::create_cart_token( (string) WC()->session->get_customer_id() );
		$order = $this->haveOrder( [ 'items' => [ [ $this->haveSimpleProduct( [ 'sku' => 'express-validated' ] ), 1 ] ] ] );
		$order->update_meta_data( '_fees_hash', 'fees' );
		$order->update_meta_data( '_coupons_hash', 'coupons' );
		$order->save();

		$this->resetHttpInterception();
		$this->willRetrieveOrder(
			array_merge(
				[
					'status'                   => 'checkout_incomplete',
					'merchant_reference2'      => '',
					'order_lines'              => [ [ 'type' => 'physical', 'reference' => 'express-validated', 'quantity' => 1 ] ],
					'merchant_data'            => wp_json_encode(
						[
							'kco_express'     => 'cart',
							'wc_cart_token'   => $token,
							'wc_fees_hash'    => 'fees',
							'wc_coupons_hash' => 'coupons',
						]
					),
					'selected_shipping_option' => [
						'id'         => 'tms-option-1',
						'name'       => 'Home delivery',
						'price'      => 4900,
						'tax_amount' => 980,
						'tax_rate'   => 2500,
					],
				],
				$kustom
			)
		);
		$this->willRespondWith( [ 'order_id' => $order->get_id() ], 200, 'wc/store/v1/checkout' );

		return [ $order, $token ];
	}

	private function haveExpressSettings( array $overrides = [] ): void {
		$this->haveGatewayCredentials(
			array_merge(
				[
					'ksa_enabled'                        => 'yes',
					'elements_playground_public_api_key' => 'pk_test_express',
				],
				$overrides
			)
		);
	}

	private function createExpressOrder( array $params ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/' . RestController::NAMESPACE . '/order' );
		foreach ( array_merge( [ 'quantity' => 1, 'variation' => [] ], $params ) as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return ( new RestController() )->create_order( $request );
	}

	/** The merchant data values that differ per run: the cart token, the nonce and the cart hashes. */
	private function merchantDataPlaceholders( array $merchant_data ): array {
		$placeholders = [];
		foreach ( [ 'wc_cart_token', 'wc_nonce', 'wc_cart_hash', 'wc_shipping_hash', 'wc_fees_hash', 'wc_coupons_hash', 'wc_taxes_hash' ] as $key ) {
			$placeholders[ "<{$key}>" ] = $merchant_data[ $key ] ?? '';
		}

		return $placeholders;
	}
}
