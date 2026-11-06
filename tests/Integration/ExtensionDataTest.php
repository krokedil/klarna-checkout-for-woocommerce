<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\Blocks\OrderValidation;
use Krokedil\KustomCheckout\Express\ExpressSession;
use Krokedil\KustomCheckout\Utility\BlocksUtility;
use Tests\Support\IntegrationTestCase;

/**
 * Block checkout extension data, such as the Smart Coupons gift form, reaching the order Kustom validates.
 *
 * @covers \Krokedil\KustomCheckout\Blocks\BlockExtension::block_callback
 * @covers \Krokedil\KustomCheckout\Blocks\OrderValidation::validate_kco_order
 * @covers ::kco_unset_sessions
 */
class ExtensionDataTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	private const GIFT = [
		'woocommerce-smart-coupons' => [
			'is_gift'               => 'yes',
			'gift_receiver_email'   => [ '0_0' => 'recipient@example.com' ],
			'gift_receiver_message' => [ '0_0' => 'Happy birthday' ],
		],
	];

	/**
	 * @dataProvider provide_pushes
	 */
	public function test_the_session_keeps_what_the_checkout_last_pushed( ?array $stored, $pushed, ?array $expected ): void {
		if ( null !== $stored ) {
			WC()->session->set( 'kco_wc_extension_data', $stored );
		}

		KCO_WC()->block_extension->block_callback(
			[
				'action'     => 'extension_data_changed',
				'extensions' => $pushed,
			]
		);

		$this->assertSame( $expected, WC()->session->get( 'kco_wc_extension_data' ) );
	}

	/** @return array<string, array{0: ?array, 1: mixed, 2: ?array}> */
	public function provide_pushes(): array {
		return [
			'a filled in gift form'            => [ null, self::GIFT, self::GIFT ],
			'an emptied form drops the old'    => [ self::GIFT, [], null ],
			'a malformed push keeps the old'   => [ self::GIFT, 'not-an-array', self::GIFT ],
		];
	}

	public function test_an_iframe_order_is_placed_with_the_stored_extension_data(): void {
		$this->haveIframeOrderToValidate( self::GIFT );

		OrderValidation::validate_kco_order( 'checkout-order-123' );

		$this->assertSame( self::GIFT, $this->storeApiCheckoutRequest()['json']['extensions'] ?? null );
	}

	public function test_an_iframe_order_without_extension_data_sends_none(): void {
		$this->haveIframeOrderToValidate( null );

		OrderValidation::validate_kco_order( 'checkout-order-123' );

		$this->assertArrayNotHasKey( 'extensions', $this->storeApiCheckoutRequest()['json'] );
	}

	public function test_finishing_a_purchase_forgets_the_extension_data(): void {
		WC()->session->set( 'kco_wc_extension_data', self::GIFT );

		kco_unset_sessions();

		$this->assertNull( WC()->session->get( 'kco_wc_extension_data' ), 'A later order in the same session must not get this gift recipient.' );
	}

	/**
	 * A Kustom iframe order whose cart token points at a session holding the given extension data,
	 * and a WooCommerce order the Store API answers with whose hashes match.
	 */
	private function haveIframeOrderToValidate( ?array $extensions ): void {
		$token = BlocksUtility::create_cart_token( (string) WC()->session->get_customer_id() );
		$order = $this->haveOrder( [ 'items' => [ [ $this->haveSimpleProduct( [ 'sku' => 'extension-data' ] ), 1 ] ] ] );
		foreach ( [ '_shipping_hash', '_fees_hash', '_coupons_hash', '_taxes_hash' ] as $key ) {
			$order->update_meta_data( $key, 'hash' );
		}
		$order->save();

		$session = ExpressSession::open( $token );
		if ( null !== $extensions ) {
			$session->set( 'kco_wc_extension_data', $extensions );
		}
		ExpressSession::save( $session );

		$this->resetHttpInterception();
		$this->willRetrieveOrder(
			[
				'status'              => 'checkout_incomplete',
				'merchant_reference2' => '',
				'merchant_data'       => wp_json_encode(
					[
						'wc_cart_token'    => $token,
						'wc_cart_hash'     => $order->get_cart_hash(),
						'wc_shipping_hash' => 'hash',
						'wc_fees_hash'     => 'hash',
						'wc_coupons_hash'  => 'hash',
						'wc_taxes_hash'    => 'hash',
					]
				),
			]
		);
		$this->willRespondWith( [ 'order_id' => $order->get_id() ], 200, 'wc/store/v1/checkout' );
	}

	private function storeApiCheckoutRequest(): array {
		$matching = array_values(
			array_filter(
				$this->httpRequests(),
				static function ( $request ) {
					return false !== strpos( $request['url'], 'wc/store/v1/checkout' );
				}
			)
		);
		$this->assertCount( 1, $matching, 'The order is placed with one Store API checkout request.' );

		return $matching[0];
	}
}
