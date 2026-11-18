<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\ShippingAssistant\Giftcards;
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
