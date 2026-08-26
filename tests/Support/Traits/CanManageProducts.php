<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

/**
 * Product fixtures for the Integration suite, built through the WooCommerce
 * CRUD layer. The E2E equivalent is CanManageE2EProducts.
 */
trait CanManageProducts {

	/** Creates a saved simple product. */
	protected function haveSimpleProduct( array $args = [] ): \WC_Product_Simple {
		static $sequence = 0;
		++$sequence;

		$args = array_merge(
			[
				'name'         => "Test Product {$sequence}",
				'sku'          => "test-product-{$sequence}",
				'price'        => '100.00',
				'tax_status'   => 'taxable',
				'tax_class'    => '',
				'virtual'      => false,
				'downloadable' => false,
			],
			$args
		);

		$product = new \WC_Product_Simple();
		$product->set_name( $args['name'] );
		$product->set_sku( $args['sku'] );
		$product->set_regular_price( $args['price'] );
		$product->set_price( $args['price'] );
		$product->set_tax_status( $args['tax_status'] );
		$product->set_tax_class( $args['tax_class'] );
		$product->set_virtual( $args['virtual'] );
		$product->set_downloadable( $args['downloadable'] );
		$product->set_status( 'publish' );
		$product->save();

		return $product;
	}

	/** Creates a variable product with one variation per entry in $variations. */
	protected function haveVariableProduct( array $variations, array $args = [] ): array {
		static $sequence = 0;
		++$sequence;

		$args = array_merge(
			[
				'name'      => "Test Variable Product {$sequence}",
				'sku'       => "test-variable-{$sequence}",
				'attribute' => 'Colour',
			],
			$args
		);

		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( $args['attribute'] );
		$attribute->set_options( array_keys( $variations ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );

		$parent = new \WC_Product_Variable();
		$parent->set_name( $args['name'] );
		$parent->set_sku( $args['sku'] );
		$parent->set_status( 'publish' );
		$parent->set_attributes( [ $attribute ] );
		$parent->save();

		$created = [];

		foreach ( $variations as $value => $variation_args ) {
			$variation_args = array_merge(
				[
					'price'     => '100.00',
					'sku'       => $args['sku'] . '-' . sanitize_title( (string) $value ),
					'tax_class' => '',
				],
				$variation_args
			);

			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $parent->get_id() );
			$variation->set_attributes( [ sanitize_title( $args['attribute'] ) => (string) $value ] );
			$variation->set_regular_price( $variation_args['price'] );
			$variation->set_price( $variation_args['price'] );
			$variation->set_sku( $variation_args['sku'] );
			$variation->set_tax_status( 'taxable' );
			$variation->set_tax_class( $variation_args['tax_class'] );
			$variation->set_status( 'publish' );
			$variation->save();

			$created[ $value ] = $variation;
		}

		return [ $parent, $created ];
	}
}
