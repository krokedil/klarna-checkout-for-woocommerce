<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\ShippingAssistant\API\ApiRegistry;
use Krokedil\KustomCheckout\ShippingAssistant\Giftcards;
use Krokedil\KustomCheckout\ShippingAssistant\RequestModifier;
use Tests\Support\IntegrationTestCase;

/**
 * How much of the order a gift card pays once the shipping option update callback knows the shipping price.
 *
 * @covers \Krokedil\KustomCheckout\ShippingAssistant\Giftcards
 */
class ShippingAssistantGiftcardsTest extends IntegrationTestCase {

	/**
	 * @dataProvider provide_carts
	 *
	 * @param list<array{0: int, 1: int}> $giftcards Each gift card as [ amount on the line, balance ].
	 * @param list<int>                   $expected  The amount each gift card line ends up with.
	 */
	public function test_a_gift_card_pays_for_shipping_up_to_its_balance( int $shipping, array $giftcards, array $expected, int $total ): void {
		$lines = [ $this->line( 'physical', 10000 ) ];
		foreach ( $giftcards as [ $amount, $balance ] ) {
			$lines[] = $this->line( 'gift_card', $amount, $balance );
		}
		$lines[] = $this->line( 'shipping_fee', $shipping );

		$result = array_values( Giftcards::apply_to_order_lines( $lines ) );

		$this->assertSame( $expected, array_column( array_slice( $result, 1, count( $giftcards ) ), 'total_amount' ) );
		$this->assertSame( $expected, array_column( array_slice( $result, 1, count( $giftcards ) ), 'unit_price' ) );
		$this->assertSame( $shipping, end( $result )['total_amount'], 'The shipping line keeps the TMS price.' );
		$this->assertSame( $total, array_sum( array_column( $result, 'total_amount' ) ) );
	}

	/** @return array<string, array{0: int, 1: list<array{0: int, 1: int}>, 2: list<int>, 3: int}> */
	public static function provide_carts(): array {
		return [
			'covers the goods and the shipping'      => [ 5000, [ [ -10000, 15000 ] ], [ -15000 ], 0 ],
			'covers part of the shipping'            => [ 5000, [ [ -10000, 12000 ] ], [ -12000 ], 3000 ],
			'covers part of the goods'               => [ 5000, [ [ -4000, 4000 ] ], [ -4000 ], 11000 ],
			'a cheaper option lowers what it pays'   => [ 2000, [ [ -15000, 15000 ] ], [ -12000 ], 0 ],
			'more balance than the order'            => [ 5000, [ [ -10000, 90000 ] ], [ -15000 ], 0 ],
			'two gift cards are used in order'       => [ 5000, [ [ -8000, 8000 ], [ -2000, 20000 ] ], [ -8000, -7000 ], 0 ],
			'the second gift card is no longer used' => [ 0, [ [ -10000, 12000 ], [ -2000, 5000 ] ], [ -10000, 0 ], 0 ],
		];
	}

	public function test_lines_without_a_balance_are_left_alone(): void {
		$lines = [
			$this->line( 'physical', 10000 ),
			$this->line( 'gift_card', -10000 ),
			$this->line( 'discount', 0 ) + [ 'merchant_data' => 'not json' ],
			$this->line( 'shipping_fee', 5000 ),
		];

		$this->assertSame( $lines, Giftcards::apply_to_order_lines( $lines ) );
	}

	/**
	 * A gift card added before the first callback is sent with an adjustment line in place of the shipping it covers.
	 *
	 * @dataProvider provide_adjustments
	 */
	public function test_only_the_shipping_stand_in_is_taken_out_of_the_adjustment( int $surcharge, ?int $stand_in, array $types, int $giftcard ): void {
		$adjustment = $this->line( 'surcharge', $surcharge ) + [ 'reference' => 'added-surcharge' ];
		if ( null !== $stand_in ) {
			$adjustment['merchant_data'] = wp_json_encode( [ Giftcards::SHIPPING_STAND_IN_KEY => $stand_in ] );
		}

		$result = Giftcards::apply_to_order_lines(
			[
				$this->line( 'physical', 7503 ),
				$this->line( 'gift_card', -7503 - $surcharge, 1000000 ),
				$adjustment,
				$this->line( 'shipping_fee', 999 ),
			]
		);

		$this->assertSame( $types, array_column( $result, 'type' ) );
		$this->assertSame( array_keys( $types ), array_keys( $result ), 'The lines must stay a list, or Kustom receives an object.' );
		$this->assertSame( $giftcard, $result[1]['total_amount'] );
		$this->assertSame( 0, array_sum( array_column( $result, 'total_amount' ) ) );
	}

	/** @return array<string, array{0: int, 1: ?int, 2: list<string>, 3: int}> */
	public static function provide_adjustments(): array {
		return [
			'only a stand-in is removed'             => [ 6125, 6125, [ 'physical', 'gift_card', 'shipping_fee' ], -8502 ],
			'only rounding is kept'                  => [ 1, null, [ 'physical', 'gift_card', 'surcharge', 'shipping_fee' ], -8503 ],
			'a stand-in with rounding keeps the öre' => [ 6126, 6125, [ 'physical', 'gift_card', 'surcharge', 'shipping_fee' ], -8503 ],
		];
	}

	/**
	 * The request marks how much of the adjustment line is shipping, so the callback can tell it from rounding.
	 */
	public function test_the_request_marks_the_shipping_the_gift_card_already_paid(): void {
		$this->haveCustomerAddress( $this->swedishAddress(), $this->swedishAddress() );
		$this->haveCartWith( [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ] );
		$this->haveChosenFlatRateShipping( 'SE', '40.00' );

		$shipping  = ( new \KCO_Request_Cart() )->get_shipping_amount();
		$giftcards = ( new \ReflectionClass( Giftcards::class ) )->newInstanceWithoutConstructor();
		$args      = [ 'order_lines' => [ $this->line( 'surcharge', $shipping - 2999 ) + [ 'reference' => 'added-surcharge' ] ] ];

		$this->assertSame( $args, $giftcards->mark_shipping_stand_in( $args ), 'A cart total that covers the shipping has no stand-in.' );

		// What a gift card plugin leaves when it pays the goods and all but 30.00 of the shipping.
		WC()->cart->set_total( '30.00' );

		$marked = $giftcards->mark_shipping_stand_in( $args );

		$this->assertSame(
			[ Giftcards::SHIPPING_STAND_IN_KEY => $shipping - 3000 ],
			json_decode( $marked['order_lines'][0]['merchant_data'], true )
		);
	}

	/**
	 * Without the callback Kustom adds the selected shipping option itself, so a shipping line from the
	 * plugin would charge it twice. This is the state a removed gift card leaves behind.
	 *
	 * @dataProvider provide_callback_settings
	 */
	public function test_the_shipping_line_is_only_sent_with_the_callback( string $setting, bool $giftcard, bool $sent ): void {
		$restore = [ $this->haveShippingAssistantApi() ];
		if ( $giftcard ) {
			$restore[] = $this->haveGiftcardInCart( 5000 );
		}

		$this->haveGatewayCredentials( [ 'ksa_enabled' => 'yes', 'ksa_enable_shipping_option_update_callback' => $setting ] );
		$this->flushGatewaySettingsCache();
		$this->haveCustomerAddress( $this->swedishAddress(), $this->swedishAddress() );
		$this->haveCartWith( [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ] );
		$this->haveChosenFlatRateShipping( 'SE', '40.00' );
		WC()->session->set( 'kco_wc_order_id', 'checkout-order-123' );
		set_transient( 'kss_override_data_checkout-order-123', [ 'name' => 'DHL Express', 'price' => 5000 ], HOUR_IN_SECONDS );

		$args   = [ 'order_lines' => [ $this->line( 'physical', 10000 ) ], 'order_amount' => 10000, 'order_tax_amount' => 2000 ];
		$result = ( new \ReflectionClass( RequestModifier::class ) )->newInstanceWithoutConstructor()->maybe_add_shipping_override_data( $args );

		array_map( static fn( callable $undo ) => $undo(), $restore );

		$this->assertSame( $sent, in_array( 'shipping_fee', array_column( $result['order_lines'], 'type' ), true ) );
		$this->assertSame( $sent, false !== get_transient( 'kss_override_data_checkout-order-123' ), 'The stored override must go when it is not used.' );

		delete_transient( 'kss_override_data_checkout-order-123' );
	}

	/** @return array<string, array{0: string, 1: bool, 2: bool}> */
	public static function provide_callback_settings(): array {
		return [
			'callback off'                  => [ 'no', false, false ],
			'callback on'                   => [ 'yes', false, true ],
			'callback off with a gift card' => [ 'no', true, true ],
		];
	}

	/**
	 * The module only registers its API when the gateway boots with the shipping assistant enabled.
	 *
	 * @return callable(): void Puts back what was there.
	 */
	private function haveShippingAssistantApi(): callable {
		$module   = KCO_WC()->shipping_assistant;
		$property = new \ReflectionProperty( $module, 'api_registry' );
		$property->setAccessible( true );
		$original = $property->getValue( $module );

		if ( ! $original ) {
			$property->setValue( $module, ( new \ReflectionClass( ApiRegistry::class ) )->newInstanceWithoutConstructor() );
		}

		return static function () use ( $property, $module, $original ): void {
			$property->setValue( $module, $original );
		};
	}

	/**
	 * Put a gift card with a known balance in the cart, as a gift card plugin's compatibility would report it.
	 *
	 * @return callable(): void Removes the gift card again.
	 */
	private function haveGiftcardInCart( int $balance ): callable {
		$giftcard = new class( $balance ) {
			private int $balance;

			public function __construct( int $balance ) {
				$this->balance = $balance;
			}

			public function get_gift_card_balance(): int {
				return $this->balance;
			}
		};

		$compatibility = new class( $giftcard ) {
			private object $giftcard;

			public function __construct( object $giftcard ) {
				$this->giftcard = $giftcard;
			}

			/** @return list<object> */
			public function get_cart_giftcards(): array {
				return [ $this->giftcard ];
			}
		};

		$property = new \ReflectionProperty( KCO_WC()->krokedil->compatibility(), 'giftcards' );
		$property->setAccessible( true );
		$registry = KCO_WC()->krokedil->compatibility();
		$original = $property->getValue( $registry );
		$property->setValue( $registry, [ 'test' => $compatibility ] );

		return static function () use ( $property, $registry, $original ): void {
			$property->setValue( $registry, $original );
		};
	}

	/** @return array<string, mixed> */
	private function line( string $type, int $amount, ?int $balance = null ): array {
		$line = [
			'type'         => $type,
			'quantity'     => 1,
			'unit_price'   => $amount,
			'total_amount' => $amount,
		];

		if ( null !== $balance ) {
			$line['merchant_data'] = wp_json_encode( [ Giftcards::BALANCE_KEY => $balance ] );
		}

		return $line;
	}
}
