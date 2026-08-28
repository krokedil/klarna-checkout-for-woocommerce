<?php
namespace Tests\Support\Data;

class TestProducts {

	public const SIMPLE_25 = 'simple-25';
	public const SIMPLE_12 = 'simple-12';
	public const SIMPLE_6 = 'simple-06';
	public const SIMPLE_0 = 'simple-00';
	public const DOWNLOADABLE_VIRTUAL_25 = 'downloadable-virtual-25';
	public const DOWNLOADABLE_VIRTUAL_12 = 'downloadable-virtual-12';
	public const DOWNLOADABLE_VIRTUAL_06 = 'downloadable-virtual-06';
	public const DOWNLOADABLE_VIRTUAL_00 = 'downloadable-virtual-00';
	public const VIRTUAL_25 = 'virtual-25';
	public const DOWNLOADABLE = 'downloadable-25';
	public const VARIABLE_25 = 'variable-25';
	public const VARIABLE_12 = 'variable-12';
	public const VARIATION_25_RED = 'variable-25-red';
	public const VARIATION_25_BLUE = 'variable-25-blue';
	public const VARIATION_25_GREEN = 'variable-25-green';
	public const VARIATION_12_RED = 'variable-12-red';
	public const VARIATION_12_BLUE = 'variable-12-blue';
	public const VARIATION_12_GREEN = 'variable-12-green';

	// Prices whose 25% VAT lands on a half cent (9.99 -> 2.4975), so a cart of
	// them totals differently depending on whether WooCommerce rounds tax per
	// line or at the subtotal. One product alone cannot show that: the gap only
	// opens once several lines each round the same way.
	public const ROUNDING_25_A = 'rounding-25-a';
	public const ROUNDING_25_B = 'rounding-25-b';
	public const ROUNDING_25_C = 'rounding-25-c';

	public const ROUNDING_25_CART = [
		self::ROUNDING_25_A,
		self::ROUNDING_25_B,
		self::ROUNDING_25_C,
	];

	/** One product per VAT rate, for the carts that have to split tax four ways. */
	public const ALL_RATES_CART = [
		self::SIMPLE_25,
		self::SIMPLE_12,
		self::SIMPLE_6,
		self::SIMPLE_0,
	];

	/** The product shapes that are not a plain shippable simple product. */
	public const VIRTUAL_AND_DOWNLOADABLE_CART = [
		self::DOWNLOADABLE_VIRTUAL_25,
		self::VIRTUAL_25,
		self::DOWNLOADABLE,
	];

	public const VARIABLE_25_VARIATIONS = [
		self::VARIATION_25_RED,
		self::VARIATION_25_BLUE,
		self::VARIATION_25_GREEN,
	];

	public const VARIABLE_12_VARIATIONS = [
		self::VARIATION_12_RED,
		self::VARIATION_12_BLUE,
		self::VARIATION_12_GREEN,
	];

	public const PRODUCT_DATA = [
		self::SIMPLE_25 => [
			'name' => 'Simple 25%',
			'type' => 'simple',
			'sku' => self::SIMPLE_25,
			'regular_price' => '99.99',
			'tax_class' => TestTaxRates::TAX_CLASSES[TestTaxRates::TAX_RATE_25]['slug'],
		],
		self::SIMPLE_12 => [
			'name' => 'Simple 12%',
			'type' => 'simple',
			'sku' => self::SIMPLE_12,
			'regular_price' => '158.39',
			'tax_class' => TestTaxRates::TAX_CLASSES[TestTaxRates::TAX_RATE_12]['slug'],
		],
		self::SIMPLE_6 => [
			'name' => 'Simple 6%',
			'type' => 'simple',
			'sku' => self::SIMPLE_6,
			'regular_price' => '84.49',
			'tax_class' => TestTaxRates::TAX_CLASSES[TestTaxRates::TAX_RATE_6]['slug'],
		],
		self::SIMPLE_0 => [
			'name' => 'Simple 0%',
			'type' => 'simple',
			'sku' => self::SIMPLE_0,
			'regular_price' => '9.99',
			'tax_class' => TestTaxRates::TAX_CLASSES[TestTaxRates::TAX_RATE_0]['slug'],
		],
		self::DOWNLOADABLE_VIRTUAL_25 => [
			'name' => 'Downloadable Virtual 25%',
			'type' => 'simple',
			'sku' => self::DOWNLOADABLE_VIRTUAL_25,
			'regular_price' => '99.99',
			'tax_class' => TestTaxRates::TAX_CLASSES[TestTaxRates::TAX_RATE_25]['slug'],
			'downloadable' => 'yes',
			'virtual' => 'yes',
		],
		self::DOWNLOADABLE_VIRTUAL_12 => [
			'name' => 'Downloadable Virtual 12%',
			'type' => 'simple',
			'sku' => self::DOWNLOADABLE_VIRTUAL_12,
			'regular_price' => '158.39',
			'tax_class' => TestTaxRates::TAX_CLASSES[TestTaxRates::TAX_RATE_12]['slug'],
			'downloadable' => 'yes',
			'virtual' => 'yes',
		],
		self::DOWNLOADABLE_VIRTUAL_06 => [
			'name' => 'Downloadable Virtual 6%',
			'type' => 'simple',
			'sku' => self::DOWNLOADABLE_VIRTUAL_06,
			'regular_price' => '84.49',
			'tax_class' => TestTaxRates::TAX_CLASSES[TestTaxRates::TAX_RATE_6]['slug'],
			'downloadable' => 'yes',
			'virtual' => 'yes',
		],
		self::DOWNLOADABLE_VIRTUAL_00 => [
			'name' => 'Downloadable Virtual 0%',
			'type' => 'simple',
			'sku' => self::DOWNLOADABLE_VIRTUAL_00,
			'regular_price' => '9.99',
			'tax_class' => TestTaxRates::TAX_CLASSES[TestTaxRates::TAX_RATE_0]['slug'],
			'downloadable' => 'yes',
			'virtual' => 'yes',
		],
		self::VIRTUAL_25 => [
			'name' => 'Virtual 25%',
			'type' => 'simple',
			'sku' => self::VIRTUAL_25,
			'regular_price' => '199.99',
			'tax_class' => TestTaxRates::TAX_CLASSES[TestTaxRates::TAX_RATE_25]['slug'],
			'virtual' => 'yes',
		],
		self::DOWNLOADABLE => [
			'name' => 'Downloadable 25%',
			'type' => 'simple',
			'sku' => self::DOWNLOADABLE,
			'regular_price' => '118.89',
			'tax_class' => TestTaxRates::TAX_CLASSES[TestTaxRates::TAX_RATE_25]['slug'],
			'downloadable' => 'yes',
		],
		self::VARIABLE_25 => [
			'name' => 'Variable 25%',
			'type' => 'variable',
			'sku' => self::VARIABLE_25,
			'tax_class' => TestTaxRates::TAX_CLASSES[TestTaxRates::TAX_RATE_25]['slug'],
			'attributes' => [
				'Color' => 'Red|Blue|Green',
			],
			'variations' => self::VARIABLE_25_VARIATIONS,
		],
		self::VARIABLE_12 => [
			'name' => 'Variable 12%',
			'type' => 'variable',
			'sku' => self::VARIABLE_12,
			'tax_class' => TestTaxRates::TAX_CLASSES[TestTaxRates::TAX_RATE_12]['slug'],
			'attributes' => [
				'Color' => 'Red|Blue|Green',
			],
			'variations' => self::VARIABLE_12_VARIATIONS,
		],
		self::VARIATION_25_RED => [
			'name' => 'Variable 25% - Red',
			'type' => 'variation',
			'sku' => self::VARIATION_25_RED,
			'regular_price' => '109.99',
			'tax_class' => TestTaxRates::TAX_CLASSES[TestTaxRates::TAX_RATE_25]['slug'],
			'attributes' => [
				'Color' => 'Red',
			],
		],
		self::VARIATION_25_BLUE => [
			'name' => 'Variable 25% - Blue',
			'type' => 'variation',
			'sku' => self::VARIATION_25_BLUE,
			'regular_price' => '119.99',
			'tax_class' => TestTaxRates::TAX_CLASSES[TestTaxRates::TAX_RATE_25]['slug'],
			'attributes' => [
				'Color' => 'Blue',
			],
		],
		self::VARIATION_25_GREEN => [
			'name' => 'Variable 25% - Green',
			'type' => 'variation',
			'sku' => self::VARIATION_25_GREEN,
			'regular_price' => '129.99',
			'tax_class' => TestTaxRates::TAX_CLASSES[TestTaxRates::TAX_RATE_25]['slug'],
			'attributes' => [
				'Color' => 'Green',
			],
		],
		self::VARIATION_12_RED => [
			'name' => 'Variable 12% - Red',
			'type' => 'variation',
			'sku' => self::VARIATION_12_RED,
			'regular_price' => '169.99',
			'tax_class' => TestTaxRates::TAX_CLASSES[TestTaxRates::TAX_RATE_12]['slug'],
			'attributes' => [
				'Color' => 'Red',
			],
		],
		self::VARIATION_12_BLUE => [
			'name' => 'Variable 12% - Blue',
			'type' => 'variation',
			'sku' => self::VARIATION_12_BLUE,
			'regular_price' => '179.99',
			'tax_class' => TestTaxRates::TAX_CLASSES[TestTaxRates::TAX_RATE_12]['slug'],
			'attributes' => [
				'Color' => 'Blue',
			],
		],
		self::VARIATION_12_GREEN => [
			'name' => 'Variable 12% - Green',
			'type' => 'variation',
			'sku' => self::VARIATION_12_GREEN,
			'regular_price' => '189.99',
			'tax_class' => TestTaxRates::TAX_CLASSES[TestTaxRates::TAX_RATE_12]['slug'],
			'attributes' => [
				'Color' => 'Green',
			],
		],
		self::ROUNDING_25_A => [
			'name' => 'Rounding 25% A',
			'type' => 'simple',
			'sku' => self::ROUNDING_25_A,
			'regular_price' => '9.99',   // 25% of this is 2.4975
			'tax_class' => TestTaxRates::TAX_CLASSES[TestTaxRates::TAX_RATE_25]['slug'],
		],
		self::ROUNDING_25_B => [
			'name' => 'Rounding 25% B',
			'type' => 'simple',
			'sku' => self::ROUNDING_25_B,
			'regular_price' => '19.99',  // 4.9975
			'tax_class' => TestTaxRates::TAX_CLASSES[TestTaxRates::TAX_RATE_25]['slug'],
		],
		self::ROUNDING_25_C => [
			'name' => 'Rounding 25% C',
			'type' => 'simple',
			'sku' => self::ROUNDING_25_C,
			'regular_price' => '29.99',  // 7.4975
			'tax_class' => TestTaxRates::TAX_CLASSES[TestTaxRates::TAX_RATE_25]['slug'],
		],
	];
}
