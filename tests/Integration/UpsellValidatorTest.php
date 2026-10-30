<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\Upsell\UpsellException;
use Krokedil\KustomCheckout\Upsell\UpsellStatus;
use Krokedil\KustomCheckout\Upsell\UpsellValidator;
use Tests\Support\IntegrationTestCase;

/**
 * The upsell validation callback, which Kustom calls before it adds an upsell to its order.
 *
 * @covers \Krokedil\KustomCheckout\Upsell\UpsellValidator
 */
class UpsellValidatorTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	/**
	 * Kustom's portal and the recommendations filter do not limit the quantity to the stock.
	 */
	public function test_an_upsell_quantity_above_the_stock_is_rejected_before_kustom_is_asked(): void {
		$product = $this->haveSimpleProduct( [ 'name' => 'Belt', 'sku' => 'belt', 'price' => '20.00' ] );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 1 );
		$product->save();
		$this->setGatewaySettings( array_merge( get_option( 'woocommerce_kco_settings', [] ), [ 'enable_upsell' => 'yes' ] ) );
		$this->haveGatewayOrder( [ 'status' => UpsellStatus::STATUS_SLUG ] );

		try {
			( new UpsellValidator( [ 'upsell_order_lines' => [ [ 'reference' => 'belt', 'quantity' => 2, 'total_amount' => 4000 ] ] ], 'kustom-order-123' ) )->process();
			$this->fail( 'The upsell was accepted.' );
		} catch ( UpsellException $e ) {
			$this->assertStringContainsString( 'does not have enough stock', $e->getMessage() );
		}

		$this->assertGatewayRequestCount( 0, 'checkout/v3/orders', 'The stock is checked before the Kustom order is fetched.' );
	}
}
