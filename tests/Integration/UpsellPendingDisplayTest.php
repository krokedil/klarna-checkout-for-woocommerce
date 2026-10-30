<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\Upsell\UpsellPendingDisplay;
use Krokedil\KustomCheckout\Upsell\UpsellProcessor;
use Tests\Support\IntegrationTestCase;

/**
 * Upsells accepted by Kustom but not yet added by the push callback, as shown in the order details.
 *
 * @covers \Krokedil\KustomCheckout\Upsell\UpsellPendingDisplay
 */
class UpsellPendingDisplayTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	private ?UpsellPendingDisplay $display = null;

	protected function tearDown(): void {
		// The plugin only hooks the display in when upsell is enabled at boot, so the test hooks it in itself.
		if ( $this->display ) {
			remove_action( 'woocommerce_order_details_after_order_table_items', [ $this->display, 'output_pending_rows' ] );
			remove_filter( 'woocommerce_get_order_item_totals', [ $this->display, 'add_pending_totals' ] );
			remove_action( 'woocommerce_thankyou_kco', [ $this->display, 'enqueue_script' ], 20 );
			remove_action( 'wc_ajax_kco_wc_upsell_order_details', [ $this->display, 'ajax_order_details' ] );
		}

		parent::tearDown();
	}

	public function test_a_pending_upsell_is_shown_as_a_row_and_in_the_totals(): void {
		$this->haveSimpleProduct( [ 'name' => 'Cap', 'sku' => 'cap', 'price' => '16.00' ] );
		$order = $this->haveOrderAwaitingUpsell( [ [ [ 'reference' => 'cap', 'name' => 'Cap', 'quantity' => 2, 'total_amount' => 3200 ] ] ] );

		$this->assertStringContainsString( 'Cap</a>', $this->pendingRowsOf( $order ) );
		$this->assertStringContainsString( '&times;&nbsp;2', $this->pendingRowsOf( $order ) );

		$totals = $order->get_order_item_totals();
		$keys   = array_keys( $totals );
		$this->assertSame( array_search( 'payment_method', $keys, true ) - 2, array_search( 'kco_upsell_pending', $keys, true ), 'The pending rows sit right before the payment method.' );
		$this->assertSame( '+ ' . wc_price( 32 ), $totals['kco_upsell_pending']['value'] );
		$this->assertSame( wc_price( (float) $order->get_total() + 32 ), $totals['kco_upsell_new_total']['value'] );
	}

	/**
	 * @dataProvider provide_orders_without_pending_upsells
	 */
	public function test_nothing_is_shown_without_a_pending_upsell( string $status, $data ): void {
		$order = $this->haveOrderAwaitingUpsell( $data );
		$order->set_status( $status );
		$order->save();

		$this->assertSame( '', $this->pendingRowsOf( $order ) );
		$this->assertArrayNotHasKey( 'kco_upsell_pending', $order->get_order_item_totals() );
	}

	/** @return array<string, array{0: string, 1: mixed}> */
	public function provide_orders_without_pending_upsells(): array {
		$line = [ [ [ 'reference' => 'cap', 'quantity' => 1, 'total_amount' => 1600 ] ] ];

		return [
			'no upsell stored'           => [ 'on-hold', '' ],
			'push already processed'     => [ 'processing', $line ],
			'stored line has no product' => [ 'on-hold', [ [ [ 'quantity' => 1 ] ] ] ],
		];
	}

	/**
	 * The thank-you page asks for the upsell to be added once the customer adds it, and Kustom's order decides.
	 */
	public function test_an_upsell_in_the_kustom_order_is_added_while_the_order_keeps_waiting(): void {
		$this->haveSimpleProduct( [ 'name' => 'Cap', 'sku' => 'cap', 'price' => '16.00' ] );
		$order = $this->haveOrderAwaitingUpsell( [ [ [ 'reference' => 'cap', 'name' => 'Cap', 'quantity' => 2, 'total_amount' => 3200 ] ] ] );
		$this->willRetrieveManagedOrder( [ 'order_lines' => [ [ 'reference' => 'cap', 'quantity' => 2, 'total_amount' => 3200 ] ] ] );

		UpsellPendingDisplay::add_confirmed_upsells( $order );

		$order = $this->reload( $order );
		$this->assertContains( 'Cap', $this->itemNamesOf( $order ) );
		$this->assertSame( '', $this->pendingRowsOf( $order ), 'The upsell is a real row now.' );
		$this->assertSame( 'on-hold', $order->get_status(), 'The push still confirms the order, and more upsells can be added until then.' );
	}

	/**
	 * @dataProvider provide_kustom_orders_without_the_upsell
	 */
	public function test_an_upsell_not_in_the_kustom_order_stays_pending( array $kustom_lines ): void {
		$this->haveSimpleProduct( [ 'name' => 'Cap', 'sku' => 'cap', 'price' => '16.00' ] );
		$this->haveSimpleProduct( [ 'name' => 'Belt', 'sku' => 'belt', 'price' => '20.00' ] );
		$order = $this->haveOrderAwaitingUpsell(
			[
				[
					[ 'reference' => 'cap', 'quantity' => 1, 'total_amount' => 1600 ],
					[ 'reference' => 'belt', 'quantity' => 1, 'total_amount' => 2000 ],
				],
			]
		);
		$this->willRetrieveManagedOrder( [ 'order_lines' => $kustom_lines ] );

		UpsellPendingDisplay::add_confirmed_upsells( $order );

		$order = $this->reload( $order );
		$this->assertNotContains( 'Cap', $this->itemNamesOf( $order ), 'No line is added until all of them are in the Kustom order.' );
		$this->assertCount( 2, UpsellPendingDisplay::get_pending_lines( $order ) );
	}

	/** @return array<string, array{0: array}> */
	public function provide_kustom_orders_without_the_upsell(): array {
		return [
			'not added by Kustom yet' => [ [ [ 'reference' => 'kco-test-1', 'quantity' => 1, 'total_amount' => 10000 ] ] ],
			'only one of two lines'   => [ [ [ 'reference' => 'cap', 'quantity' => 1, 'total_amount' => 1600 ] ] ],
		];
	}

	public function test_the_push_does_not_add_an_upsell_the_thank_you_page_already_added(): void {
		$this->haveSimpleProduct( [ 'name' => 'Cap', 'sku' => 'cap', 'price' => '16.00' ] );
		$order        = $this->haveOrderAwaitingUpsell( [ [ [ 'reference' => 'cap', 'quantity' => 1, 'total_amount' => 1600 ] ] ] );
		$kustom_order = [ 'order_lines' => [ [ 'reference' => 'cap', 'quantity' => 1, 'total_amount' => 1600 ] ] ];
		$this->willRetrieveManagedOrder( $kustom_order );
		UpsellPendingDisplay::add_confirmed_upsells( $order );

		( new UpsellProcessor( $this->reload( $order ), $kustom_order ) )->process();

		$this->assertSame( [ 'Cap' ], array_values( array_intersect( $this->itemNamesOf( $this->reload( $order ) ), [ 'Cap' ] ) ) );
	}

	private function haveOrderAwaitingUpsell( $upsell_data ): \WC_Order {
		// The plugin's own waiting status is only registered when upsell is enabled at boot, so wait in on-hold instead.
		$this->setGatewaySettings( array_merge( get_option( 'woocommerce_kco_settings', [] ), [ 'enable_upsell' => 'yes', 'upsell_waiting_status' => 'on-hold' ] ) );
		$this->display ??= new UpsellPendingDisplay();
		$order = $this->haveGatewayOrder( [ 'status' => 'on-hold' ] );
		$order->set_payment_method_title( 'Kustom' );
		$order->update_meta_data( '_kco_upsell_data', $upsell_data );
		$order->update_meta_data( '_wc_klarna_order_id', 'kustom-order-123' );
		$order->save();

		return $order;
	}

	/** @return string[] */
	private function itemNamesOf( \WC_Order $order ): array {
		return array_values(
			array_map(
				static function ( \WC_Order_Item $item ): string {
					return $item->get_name();
				},
				$order->get_items()
			)
		);
	}

	private function pendingRowsOf( \WC_Order $order ): string {
		ob_start();
		$this->display->output_pending_rows( $order );

		return (string) ob_get_clean();
	}
}
