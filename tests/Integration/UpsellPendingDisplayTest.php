<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\Upsell\UpsellPendingDisplay;
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

	private function haveOrderAwaitingUpsell( $upsell_data ): \WC_Order {
		// The plugin's own waiting status is only registered when upsell is enabled at boot, so wait in on-hold instead.
		$this->setGatewaySettings( [ 'enable_upsell' => 'yes', 'upsell_waiting_status' => 'on-hold' ] );
		$this->display ??= new UpsellPendingDisplay();
		$order = $this->haveGatewayOrder( [ 'status' => 'on-hold' ] );
		$order->set_payment_method_title( 'Kustom' );
		$order->update_meta_data( '_kco_upsell_data', $upsell_data );
		$order->save();

		return $order;
	}

	private function pendingRowsOf( \WC_Order $order ): string {
		ob_start();
		$this->display->output_pending_rows( $order );

		return (string) ob_get_clean();
	}
}
