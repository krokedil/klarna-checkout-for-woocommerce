<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\Upsell\UpsellPendingDisplay;
use Krokedil\KustomCheckout\Upsell\UpsellProcessor;
use Krokedil\KustomCheckout\Upsell\UpsellStatus;
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
			'no upsell stored'           => [ UpsellStatus::STATUS_SLUG, '' ],
			'push already processed'     => [ 'processing', $line ],
			'stored line has no product' => [ UpsellStatus::STATUS_SLUG, [ [ [ 'quantity' => 1 ] ] ] ],
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
		$this->assertSame( UpsellStatus::STATUS_SLUG, $order->get_status(), 'The push still confirms the order, and more upsells can be added until then.' );
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
		$this->assertNotContains( 'Cap', $this->itemNamesOf( $order ), 'An upsell is only added once all its lines are in the Kustom order.' );
		$this->assertCount( 2, UpsellPendingDisplay::get_pending_lines( $order ) );
	}

	/** @return array<string, array{0: array}> */
	public function provide_kustom_orders_without_the_upsell(): array {
		return [
			'not added by Kustom yet' => [ [ [ 'reference' => 'kco-test-1', 'quantity' => 1, 'total_amount' => 10000 ] ] ],
			'only one of two lines'   => [ [ [ 'reference' => 'cap', 'quantity' => 1, 'total_amount' => 1600 ] ] ],
		];
	}

	/**
	 * Kustom adds an upsell in the same request as the validation callback, so a failed upsell is never added later.
	 */
	public function test_a_failed_upsell_is_dropped_and_the_others_are_added(): void {
		$this->haveSimpleProduct( [ 'name' => 'Cap', 'sku' => 'cap', 'price' => '16.00' ] );
		$this->haveSimpleProduct( [ 'name' => 'Belt', 'sku' => 'belt', 'price' => '20.00' ] );
		$order = $this->haveOrderAwaitingUpsell(
			[
				[ [ 'reference' => 'cap', 'name' => 'Cap', 'quantity' => 1, 'total_amount' => 1600 ] ],
				[ [ 'reference' => 'belt', 'name' => 'Belt', 'quantity' => 1, 'total_amount' => 2000 ] ],
			]
		);
		$this->willRetrieveManagedOrder( [ 'order_lines' => [ [ 'reference' => 'cap', 'quantity' => 1, 'total_amount' => 1600 ] ] ] );

		UpsellPendingDisplay::add_confirmed_upsells( $order, true );

		$order = $this->reload( $order );
		$this->assertContains( 'Cap', $this->itemNamesOf( $order ) );
		$this->assertNotContains( 'Belt', $this->itemNamesOf( $order ) );
		$this->assertSame( [], UpsellPendingDisplay::get_pending_lines( $order ), 'The failed upsell is no longer shown as pending.' );
		$this->assertStringContainsString( 'Belt was not added', implode( ' ', wp_list_pluck( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ), 'content' ) ) );
	}

	public function test_the_push_drops_an_upsell_that_is_not_in_the_kustom_order(): void {
		$this->haveSimpleProduct( [ 'name' => 'Belt', 'sku' => 'belt', 'price' => '20.00' ] );
		$order = $this->haveOrderAwaitingUpsell( [ [ [ 'reference' => 'belt', 'name' => 'Belt', 'quantity' => 1, 'total_amount' => 2000 ] ] ] );
		$total = $order->get_total();

		( new UpsellProcessor( $order, [ 'order_lines' => [ [ 'reference' => 'kco-test-1', 'quantity' => 1, 'total_amount' => 10000 ] ] ] ) )->process();

		$order = $this->reload( $order );
		$this->assertNotContains( 'Belt', $this->itemNamesOf( $order ) );
		$this->assertSame( '', $order->get_meta( '_kco_upsell_data' ) );
		$this->assertSame( $total, $order->get_total() );
	}

	/**
	 * Two thank-you tabs both get upsell_product_added. The second request loaded the order before the first one added the upsell.
	 */
	public function test_a_request_with_an_older_copy_of_the_order_does_not_add_the_upsell_again(): void {
		$this->haveSimpleProduct( [ 'name' => 'Cap', 'sku' => 'cap', 'price' => '16.00' ] );
		$order = $this->haveOrderAwaitingUpsell( [ [ [ 'reference' => 'cap', 'quantity' => 1, 'total_amount' => 1600 ] ] ] );
		$stale = $this->reload( $order );
		$this->willRetrieveManagedOrder( [ 'order_lines' => [ [ 'reference' => 'cap', 'quantity' => 1, 'total_amount' => 1600 ] ] ] );
		$this->willRetrieveManagedOrder( [ 'order_lines' => [ [ 'reference' => 'cap', 'quantity' => 1, 'total_amount' => 1600 ] ] ] );
		UpsellPendingDisplay::add_confirmed_upsells( $order );

		UpsellPendingDisplay::add_confirmed_upsells( $stale );

		$this->assertCount( 1, array_keys( $this->itemNamesOf( $this->reload( $order ) ), 'Cap', true ) );
	}

	public function test_nothing_is_added_while_another_request_holds_the_lock(): void {
		$this->haveSimpleProduct( [ 'name' => 'Cap', 'sku' => 'cap', 'price' => '16.00' ] );
		$order = $this->haveOrderAwaitingUpsell( [ [ [ 'reference' => 'cap', 'quantity' => 1, 'total_amount' => 1600 ] ] ] );
		\KCO_Confirmation::lock_kco_confirmation( 'kustom-order-123', $order->get_id(), 'upsell' );

		try {
			UpsellPendingDisplay::add_confirmed_upsells( $order );
		} finally {
			\KCO_Confirmation::unlock_kco_confirmation( 'kustom-order-123', $order->get_id(), 'upsell' );
		}

		$this->assertNotContains( 'Cap', $this->itemNamesOf( $this->reload( $order ) ) );
		$this->assertCount( 1, UpsellPendingDisplay::get_pending_lines( $this->reload( $order ) ) );
	}

	/**
	 * Each Kustom line can only confirm one stored line, and not one that is already on the WooCommerce order.
	 *
	 * @dataProvider provide_stored_lines_and_kustom_orders
	 */
	public function test_each_kustom_line_confirms_one_upsell( array $upsell_data, array $kustom_lines, int $caps_after ): void {
		$cap   = $this->haveSimpleProduct( [ 'name' => 'Cap', 'sku' => 'cap', 'price' => '16.00' ] );
		$order = $this->haveOrderAwaitingUpsell( $upsell_data, [ 'items' => [ [ $cap, 1 ] ] ] );

		( new UpsellProcessor( $order, [ 'order_lines' => $kustom_lines ] ) )->process();

		$this->assertCount( $caps_after, array_keys( $this->itemNamesOf( $this->reload( $order ) ), 'Cap', true ) );
	}

	/** @return array<string, array{0: array, 1: array, 2: int}> */
	public function provide_stored_lines_and_kustom_orders(): array {
		$cap_line = [ 'reference' => 'cap', 'quantity' => 1, 'total_amount' => 1600 ];

		return [
			'a copy of the ordered line'           => [ [ [ $cap_line ] ], [ $cap_line ], 1 ],
			'the same upsell stored twice'         => [ [ [ $cap_line ], [ $cap_line ] ], [ $cap_line, $cap_line ], 2 ],
			'an upsell of a product in the order'  => [ [ [ $cap_line ] ], [ $cap_line, $cap_line ], 2 ],
		];
	}

	public function test_the_discount_comes_from_the_kustom_order(): void {
		$this->haveSimpleProduct( [ 'name' => 'Belt', 'sku' => 'belt', 'price' => '20.00' ] );
		$order = $this->haveOrderAwaitingUpsell( [ [ [ 'reference' => 'belt', 'quantity' => 1, 'total_amount' => 2000, 'total_discount_amount' => 5000 ] ] ] );

		( new UpsellProcessor( $order, [ 'order_lines' => [ [ 'reference' => 'belt', 'quantity' => 1, 'total_amount' => 2000, 'total_discount_amount' => 0 ] ] ] ) )->process();

		$belts = array_filter(
			$this->reload( $order )->get_items(),
			static function ( \WC_Order_Item $item ): bool {
				return 'Belt' === $item->get_name();
			}
		);
		$belt  = reset( $belts );
		$this->assertSame( (float) $belt->get_subtotal(), (float) $belt->get_total() );
	}

	/**
	 * @dataProvider provide_orders_for_the_order_details_request
	 */
	public function test_the_order_details_are_only_returned_while_the_order_waits( string $status, string $payment_method, bool $success ): void {
		$order = $this->haveOrderAwaitingUpsell( '' );
		$order->set_status( $status );
		$order->set_payment_method( $payment_method );
		$order->save();
		$_POST = [ 'order_id' => (string) $order->get_id(), 'key' => $order->get_order_key() ];
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function () {
					throw new \RuntimeException( 'wp_die' );
				};
			}
		);

		ob_start();
		try {
			$this->display->ajax_order_details();
		} catch ( \RuntimeException $e ) {
			// wp_send_json() ends the request.
		}
		$response = json_decode( (string) ob_get_clean(), true );
		$_POST    = [];

		$this->assertSame( $success, $response['success'] );
	}

	/** @return array<string, array{0: string, 1: string, 2: bool}> */
	public function provide_orders_for_the_order_details_request(): array {
		return [
			'waiting Kustom order'  => [ UpsellStatus::STATUS_SLUG, 'kco', true ],
			'processed order'       => [ 'processing', 'kco', false ],
			'another payment method' => [ UpsellStatus::STATUS_SLUG, 'bacs', false ],
		];
	}

	/**
	 * WooCommerce taxes the added item again when it recalculates the order, so the price without tax must use the same rates.
	 *
	 * @dataProvider provide_untaxed_upsells
	 */
	public function test_an_upsell_adds_the_amount_kustom_charged( array $product_args, array $order_args, string $tax_based_on ): void {
		update_option( 'woocommerce_tax_based_on', $tax_based_on );
		$this->haveSimpleProduct( array_merge( [ 'name' => 'Belt', 'sku' => 'belt', 'price' => '20.00' ], $product_args ) );
		$order = $this->haveOrderAwaitingUpsell( [ [ [ 'reference' => 'belt', 'quantity' => 1, 'total_amount' => 2000 ] ] ], $order_args );
		$total = (float) $order->get_total();

		( new UpsellProcessor( $order, [ 'order_lines' => [ [ 'reference' => 'belt', 'quantity' => 1, 'total_amount' => 2000 ] ] ] ) )->process();

		$this->assertEqualsWithDelta( $total + 20, (float) $this->reload( $order )->get_total(), 0.001 );
	}

	/** @return array<string, array{0: array, 1: array, 2: string}> */
	public function provide_untaxed_upsells(): array {
		return [
			'a product without tax'               => [ [ 'tax_status' => 'none' ], [], 'billing' ],
			'taxed by a shipping address abroad' => [ [], [ 'shipping_address' => [ 'country' => 'US', 'state' => 'CA', 'postcode' => '90210', 'city' => 'Beverly Hills' ] ], 'shipping' ],
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

	private function haveOrderAwaitingUpsell( $upsell_data, array $args = [] ): \WC_Order {
		$this->setGatewaySettings( array_merge( get_option( 'woocommerce_kco_settings', [] ), [ 'enable_upsell' => 'yes' ] ) );
		$this->display ??= new UpsellPendingDisplay();
		$order = $this->haveGatewayOrder( array_merge( [ 'status' => UpsellStatus::STATUS_SLUG ], $args ) );
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
