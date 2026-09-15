<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use Tests\Support\Data\TestProducts;

trait CanManageE2EProducts {

	use \Tests\Support\_generated\EndToEndTesterActions;

	/** Creates a product in the database for the given SKU. */
	public function haveProductInDatabase(string $sku): int
	{
		$productData = TestProducts::PRODUCT_DATA[$sku] ?? null;

		if (!$productData) {
			throw new \InvalidArgumentException("No product data found for SKU: $sku");
		}

		$productId = $this->haveInDatabase('wp_posts', [
			'post_title' => $productData['name'],
			'post_type' => 'product',
			'post_status' => 'publish',
		]);

		$this->haveProductMetaInDatabase($productId, $productData);

		return $productId;
	}

	/** Creates a variation of a variable product in the database. */
	public function haveVariationProductInDatabase(int $parentId, string $variationSku): int
	{
		$variationData = TestProducts::PRODUCT_DATA[$variationSku] ?? null;

		if (!$variationData) {
			throw new \InvalidArgumentException("No product data found for SKU: $variationSku");
		}

		$variationId = $this->haveInDatabase('wp_posts', [
			'post_title' => $variationData['name'],
			'post_type' => 'product_variation',
			'post_status' => 'publish',
			'post_parent' => $parentId,
		]);

		$this->haveProductMetaInDatabase($variationId, $variationData);

		return $variationId;
	}

	/** Creates the product metadata from the data array in the database. */
	public function haveProductMetaInDatabase(int $productId, array $productData): void
	{
		$skippedKeys = ['name', 'variations', 'type']; // Keys to skip when inserting metadata, since they are handled separately.

		// Fall back to regular_price when no explicit price is given.
		if (!isset($productData['price']) && isset($productData['regular_price'])) {
			$productData['price'] = $productData['regular_price'];
		}

		foreach ($productData as $metaKey => $metaValue) {
			if (in_array($metaKey, $skippedKeys, true)) {
				continue;
			}

			$this->haveInDatabase('wp_postmeta', [
				'post_id' => $productId,
				'meta_key' => "_$metaKey",
				'meta_value' => $metaValue,
			]);
		}
	}
}
