<?php
namespace Tests\Support\Data;

class TestTaxRates {

	public const TAX_RATE_25 = '25.000';
	public const TAX_RATE_12 = '12.000';
	public const TAX_RATE_6 = '6.000';
	public const TAX_RATE_0 = '0.000';

	public const TAX_CLASSES = [
		self::TAX_RATE_25 => [
			'name' => '25%',
			'slug' => '25-percent',
			'rate' => '25.000',
		],
		self::TAX_RATE_12 => [
			'name' => '12%',
			'slug' => '12-percent',
			'rate' => '12.000',
		],
		self::TAX_RATE_6 => [
			'name' => '6%',
			'slug' => '6-percent',
			'rate' => '6.000',
		],
		self::TAX_RATE_0 => [
			'name' => '0%',
			'slug' => '0-percent',
			'rate' => '0.000',
		]
	];
}
