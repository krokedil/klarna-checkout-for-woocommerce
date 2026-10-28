<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

trait CanManageE2ETaxRates {

	use \Tests\Support\_generated\EndToEndTesterActions;

	/** Creates a tax class with the given rate in the database. */
	public function haveTaxClassInDatabase(string $rate): void
	{
		$taxClassData = \Tests\Support\Data\TestTaxRates::TAX_CLASSES[$rate] ?? null;

		if (!$taxClassData) {
			throw new \InvalidArgumentException("No tax class data found for rate: $rate");
		}

		$this->haveInDatabase('wp_wc_tax_rate_classes', [
			'name' => $taxClassData['name'],
			'slug' => $taxClassData['slug'],
		]);

		$this->haveInDatabase('wp_woocommerce_tax_rates', [
			'tax_rate'           => $taxClassData['rate'],
			'tax_rate_class'     => $taxClassData['slug'],
			'tax_rate_compound'  => '0',
			'tax_rate_country'   => '',
			'tax_rate_name'      => $taxClassData['name'],
			'tax_rate_priority'  => '1',
			'tax_rate_shipping'  => '1',
			'tax_rate_order'     => '0',
			'tax_rate_state'     => '',
		]);
	}
}
