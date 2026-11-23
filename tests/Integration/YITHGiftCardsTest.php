<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\Compatibility\YITHGiftCards;
use Tests\Support\IntegrationTestCase;

/**
 * YITH gift cards on a Kustom order, which YITH keeps as order meta until it adds them as a fee.
 *
 * @covers \Krokedil\KustomCheckout\Compatibility\YITHGiftCards
 * @covers \Krokedil\KustomCheckout\OrderManagement\OrderLines
 */
class YITHGiftCardsTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	private const GIFT_CARDS = [
		'GIFT-100' => 100.0,
		'GIFT-50'  => 50.0,
	];

	protected function setUp(): void {
		parent::setUp();

		// YITH is not installed in the suite, the compatibility only checks for its version constant.
		if ( ! defined( 'YITH_YWGC_VERSION' ) ) {
			define( 'YITH_YWGC_VERSION', 'test' );
		}

		YITHGiftCards::init();
	}

	protected function tearDown(): void {
		remove_action( 'woocommerce_order_before_calculate_totals', [ YITHGiftCards::class, 'add_gift_cards_as_fee' ], 9 );

		parent::tearDown();
	}

	/**
	 * Saving the edit order page recalculates the totals, which used to drop the gift cards.
	 *
	 * @dataProvider provide_gift_card_states
	 */
	public function test_a_capture_asks_for_what_kustom_authorized( int $recalculations, bool $yith_added_fee ): void {
		$order      = $this->haveGiftCardOrder();
		$authorized = (int) round( (float) $order->get_total() * 100 );

		if ( $yith_added_fee ) {
			$this->haveYithAddItsOwnFee( $order );
		}

		for ( $i = 0; $i < $recalculations; $i++ ) {
			$order = $this->reload( $order );
			$order->calculate_totals( false );
		}

		$this->willRetrieveManagedOrder( [ 'remaining_authorized_amount' => $authorized ] );
		$this->willCapture();
		KCO_WC()->order_management->capture_klarna_order( $order->get_id() );

		$body = $this->gatewayRequestTo( '/captures' )['json'];

		$this->assertSame( $authorized, (int) $body['captured_amount'], 'Kustom rejects a capture above the remaining authorized amount.' );
		$this->assertEquals(
			$body['captured_amount'],
			array_sum( array_column( $body['order_lines'], 'total_amount' ) ),
			'The gift cards must reach the order lines exactly once.'
		);
	}

	/** @return array<string, array{int, bool}> */
	public function provide_gift_card_states(): array {
		return [
			'gift cards as order meta only'   => [ 0, false ],
			'recalculated once'               => [ 1, false ],
			'recalculated twice'              => [ 2, false ],
			'yith already added its own fee'  => [ 1, true ],
		];
	}

	public function test_an_order_paid_with_another_gateway_is_left_to_yith(): void {
		$order = $this->haveGiftCardOrder();
		$order->set_payment_method( 'cod' );
		$order->calculate_totals( false );

		$this->assertEmpty( $this->reload( $order )->get_items( 'fee' ), 'Only Kustom orders get the gift card fee.' );
	}

	/** A paid Kustom order with what YITH stores at checkout: the gift cards deducted from the total, as meta only. */
	private function haveGiftCardOrder(): \WC_Order {
		$order = $this->haveCapturableGatewayOrder(
			[
				'items'    => [
					[ $this->haveSimpleProduct( [ 'name' => 'Gift Card Test Product', 'sku' => 'kco-gift-1', 'price' => '100.00' ] ), 2 ],
					[ $this->haveSimpleProduct( [ 'name' => 'Gift Card Test Product 2', 'sku' => 'kco-gift-2', 'price' => '40.00' ] ), 1 ],
				],
				'shipping' => [ 'total' => '50.00' ],
			]
		);

		$paid_total = (float) $order->get_total() - array_sum( self::GIFT_CARDS );

		$order->set_total( (string) $paid_total );
		$order->update_meta_data( '_ywgc_applied_gift_cards', self::GIFT_CARDS );
		$order->update_meta_data( '_ywgc_applied_gift_cards_totals', array_sum( self::GIFT_CARDS ) );
		$order->update_meta_data( '_ywgc_applied_gift_cards_order_total', $paid_total );
		$order->save();

		return $order;
	}

	/** The fee and flag YITH's own handler leaves on a recalculated order. */
	private function haveYithAddItsOwnFee( \WC_Order $order ): void {
		$fee = new \WC_Order_Item_Fee();
		$fee->set_props(
			[
				'name'  => 'Gift Card (' . implode( ', ', array_keys( self::GIFT_CARDS ) ) . ')',
				'total' => -1 * array_sum( self::GIFT_CARDS ),
			]
		);

		$order->add_item( $fee );
		$order->update_meta_data( 'ywgc_gift_card_updated_as_fee', true );
		$order->save();
	}
}
