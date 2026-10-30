<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\Upsell\UpsellRecommendations;
use Tests\Support\IntegrationTestCase;

/**
 * The products recommended to Kustom on the upsell callback.
 *
 * @covers \Krokedil\KustomCheckout\Upsell\UpsellRecommendations
 */
class UpsellRecommendationsTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	/**
	 * @dataProvider provide_sources
	 */
	public function test_the_configured_source_decides_what_is_recommended( string $source, array $expected ): void {
		$cross   = $this->haveSimpleProduct( [ 'sku' => 'cross', 'price' => '50.00' ] );
		$upsell  = $this->haveSimpleProduct( [ 'sku' => 'upsell', 'price' => '50.00' ] );
		$kustom  = $this->haveSimpleProduct( [ 'sku' => 'kustom', 'price' => '50.00' ] );
		$ordered = $this->haveLinkedProduct( [ $cross ], [ $upsell ] );
		$ordered->update_meta_data( '_kco_upsell_ids', [ $kustom->get_id() ] );
		$ordered->save();
		$this->setGatewaySettings( [ 'upsell_recommendations' => $source ] );

		$response = $this->recommendationsFor( $this->haveGatewayOrder( [ 'items' => [ $ordered ] ] ) );

		$this->assertSame( $expected, array_column( $response['upsell_lines'], 'reference' ) );
	}

	/** @return array<string, array{0: string, 1: string[]}> */
	public function provide_sources(): array {
		return [
			'none'                => [ 'none', [] ],
			'custom, filter only' => [ 'custom', [] ],
			'cross-sells'         => [ 'cross_sells', [ 'cross' ] ],
			'upsells'             => [ 'upsells', [ 'upsell' ] ],
			'kustom upsells'      => [ 'kustom_upsells', [ 'kustom' ] ],
		];
	}

	public function test_products_already_ordered_or_pending_an_upsell_are_not_recommended(): void {
		$in_order = $this->haveSimpleProduct( [ 'sku' => 'in-order', 'price' => '50.00' ] );
		$pending  = $this->haveSimpleProduct( [ 'sku' => 'pending', 'price' => '50.00' ] );
		$fresh    = $this->haveSimpleProduct( [ 'sku' => 'fresh', 'price' => '50.00' ] );
		$ordered  = $this->haveLinkedProduct( [ $in_order, $pending, $fresh ] );
		$this->setGatewaySettings( [ 'upsell_recommendations' => 'cross_sells' ] );

		$order = $this->haveGatewayOrder( [ 'items' => [ $ordered, $in_order ] ] );
		$order->update_meta_data( '_kco_upsell_data', [ [ [ 'reference' => 'pending', 'quantity' => 1 ] ] ] );
		$order->save();

		$this->assertSame( [ 'fresh' ], array_column( $this->recommendationsFor( $order )['upsell_lines'], 'reference' ) );
	}

	public function test_variations_are_recommended_but_not_variable_products_or_any_variations(): void {
		[ $variable, $variations ] = $this->haveVariableProduct( [ 'Red' => [ 'price' => '50.00' ], 'Blue' => [ 'price' => '50.00' ] ] );
		$variations['Blue']->set_attributes( [ 'colour' => '' ] );
		$variations['Blue']->save();
		$ordered = $this->haveLinkedProduct( [ $variable, $variations['Red'], $variations['Blue'] ] );
		$this->setGatewaySettings( [ 'upsell_recommendations' => 'cross_sells' ] );

		$response = $this->recommendationsFor( $this->haveGatewayOrder( [ 'items' => [ $ordered ] ] ) );

		$this->assertSame( [ $variations['Red']->get_sku() ], array_column( $response['upsell_lines'], 'reference' ) );
	}

	public function test_a_line_adds_up_and_fits_the_upsell_amount(): void {
		$ordered = $this->haveLinkedProduct( [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ] );
		$this->setGatewaySettings( [ 'upsell_recommendations' => 'cross_sells' ] );

		$response = $this->recommendationsFor( $this->haveGatewayOrder( [ 'items' => [ $ordered ] ] ), 25000 );
		$line     = $response['upsell_lines'][0];

		$this->assertSame( 2500, $line['tax_rate'] );
		$this->assertSame( $line['unit_price'], $line['total_amount'] );
		$this->assertEqualsWithDelta( $line['total_amount'] - $line['total_amount'] * 10000 / 12500, $line['total_tax_amount'], 1 );
		$this->assertSame( intdiv( 25000, $line['unit_price'] ), $line['max_allowed_quantity'] );
	}

	/**
	 * @dataProvider provide_empty_answers
	 */
	public function test_no_recommendations_answers_empty( bool $possible, int $max_amount ): void {
		$ordered = $this->haveLinkedProduct( [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ] );
		$this->setGatewaySettings( [ 'upsell_recommendations' => 'cross_sells' ] );

		$response = ( new UpsellRecommendations( $this->haveGatewayOrder( [ 'items' => [ $ordered ] ] ), [ 'upsell_possible' => $possible, 'max_upsell_amount' => $max_amount ] ) )->get_response();

		$this->assertSame( [ 'upsell_lines' => [], 'empty' => true ], $response );
	}

	/** @return array<string, array{0: bool, 1: int}> */
	public function provide_empty_answers(): array {
		return [
			'upsell not possible'           => [ false, 100000 ],
			'product above the upsell max' => [ true, 100 ],
			'no upsell amount'              => [ true, 0 ],
		];
	}

	public function test_the_filter_can_replace_the_recommendations(): void {
		$own = [ [ 'reference' => 'own', 'name' => 'Own' ] ];
		add_filter( 'kco_wc_upsell_recommendations', static fn() => $own );

		$response = $this->recommendationsFor( $this->haveGatewayOrder() );

		remove_all_filters( 'kco_wc_upsell_recommendations' );
		$this->assertSame( [ 'upsell_lines' => $own ], $response );
	}

	private function haveLinkedProduct( array $cross_sells, array $upsells = [] ): \WC_Product_Simple {
		$product = $this->haveSimpleProduct();
		$product->set_cross_sell_ids( array_map( static fn( $linked ) => $linked->get_id(), $cross_sells ) );
		$product->set_upsell_ids( array_map( static fn( $linked ) => $linked->get_id(), $upsells ) );
		$product->save();

		return $product;
	}

	private function recommendationsFor( \WC_Order $order, int $max_amount = 100000 ): array {
		return ( new UpsellRecommendations( $order, [ 'upsell_possible' => true, 'max_upsell_amount' => $max_amount ] ) )->get_response();
	}
}
