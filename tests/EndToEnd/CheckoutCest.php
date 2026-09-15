<?php

namespace Tests\EndToEnd;

use Codeception\Example;
use Tests\Support\Data\{TestProducts, TestTaxRates};
use Tests\Support\EndToEndTester;

/**
 * A purchase through the shortcode checkout and Kustom's hosted iframe.
 *
 * The address a row asks for is typed into Kustom's iframe rather than WooCommerce's
 * own form, which the plugin keeps off-screen and fills from Kustom's order itself.
 */
class CheckoutCest
{
	/**
	 * @dataProvider provide_purchases
	 */
	public function can_purchase(EndToEndTester $I, Example $case): void
	{
		$I->haveStoreOptionsInDatabase($case['store']);
		$I->haveTaxClassesInDatabase($case['tax_rates']);
		$I->haveCartWith($case['cart']);

		$I->amOnCheckoutPageWithGateway();
		$I->fillBillingAddressForm($case['billing']);

		$I->placeOrder();
		$I->waitForThankYouPage();

		$I->verifyOrderOnThankYouPage($case['gateway'], $case['total'], $case['meta']);
	}

	/**
	 * A row carries only what differs from the defaults in scenario().
	 *
	 * The rounding rows are the reason this is a browser test rather than a
	 * calculation one. Kustom rejects an order whose amount does not match its
	 * own lines to the cent, so the totals below are only half the assertion:
	 * reaching the thank you page at all says the plugin sent a payload that adds
	 * up. Every expected value here came from WooCommerce, not from arithmetic.
	 *
	 * @return array<string, array{store: array<string, string>, tax_rates: list<string>, cart: list<string|array{0: string, 1: int}>, billing: array<string, ?string>, gateway: string, total: string, meta: array<string, string>}>
	 */
	protected function provide_purchases(): array
	{
		return self::only(
			[
				'prices include VAT' => self::scenario(
					[
						'store' => [ 'woocommerce_prices_include_tax' => 'yes' ],
						'total' => '99.99',
					]
				),
				'VAT added at checkout' => self::scenario(
					[
						'store' => [ 'woocommerce_prices_include_tax' => 'no' ],
						'total' => '124.99',
					]
				),

				// Three lines whose VAT each lands on a half cent. Rounding them one
				// by one gives 15.00, rounding their sum gives 14.99, and the cent
				// shows up in the order total.
				'rounding per line, VAT added at checkout' => self::scenario(
					[
						'store' => self::pricing( 'no', 'no' ),
						'cart'  => TestProducts::ROUNDING_25_CART,
						'total' => '74.97',
						'meta'  => [ '_order_tax' => '15' ],
					]
				),
				'rounding at subtotal, VAT added at checkout' => self::scenario(
					[
						'store' => self::pricing( 'no', 'yes' ),
						'cart'  => TestProducts::ROUNDING_25_CART,
						'total' => '74.96',
						// Unrounded on purpose: with rounding deferred to the subtotal,
						// WooCommerce stores the raw sum and only rounds on display.
						'meta'  => [ '_order_tax' => '14.9925' ],
					]
				),

				// The same three lines with tax already in the price. The total cannot
				// move, so the setting shows up in the tax alone.
				'rounding per line, prices include VAT' => self::scenario(
					[
						'store' => self::pricing( 'yes', 'no' ),
						'cart'  => TestProducts::ROUNDING_25_CART,
						'total' => '59.97',
						'meta'  => [ '_order_tax' => '12' ],
					]
				),
				'rounding at subtotal, prices include VAT' => self::scenario(
					[
						'store' => self::pricing( 'yes', 'yes' ),
						'cart'  => TestProducts::ROUNDING_25_CART,
						'total' => '59.97',
						'meta'  => [ '_order_tax' => '11.994' ],
					]
				),

				// Quantity is not the same thing as several lines: six of one product
				// is one line, and one line rounds the same either way.
				'six of one product' => self::scenario(
					[
						'store' => self::pricing( 'no', 'no' ),
						'cart'  => [ [ TestProducts::ROUNDING_25_A, 6 ] ],
						'total' => '74.93',
					]
				),

				// Four rates in one cart, which is four tax buckets for the plugin to
				// split the order lines into, including a zero-rated one.
				'four VAT rates in one cart' => self::scenario(
					[
						'store'     => self::pricing( 'no', 'no' ),
						'tax_rates' => [
							TestTaxRates::TAX_RATE_25,
							TestTaxRates::TAX_RATE_12,
							TestTaxRates::TAX_RATE_6,
							TestTaxRates::TAX_RATE_0,
						],
						'cart'      => TestProducts::ALL_RATES_CART,
						'total'     => '401.94',
						'meta'      => [ '_order_tax' => '49.08' ],
					]
				),

				// Virtual, downloadable, and downloadable-but-shippable in one cart:
				// three product shapes whose order lines the plugin builds differently.
				'virtual and downloadable products' => self::scenario(
					[
						'store' => self::pricing( 'no', 'no' ),
						'cart'  => TestProducts::VIRTUAL_AND_DOWNLOADABLE_CART,
						'total' => '523.59',
					]
				),
			]
		);
	}

	/**
	 * The rows to run, narrowed by KCO_ONLY when it is set.
	 *
	 * A browser purchase is forty seconds, so chasing one misbehaving case through
	 * the whole table is expensive: `KCO_ONLY=rounding composer test:e2e` runs the
	 * four rounding rows, and a comma separated list matches more than one.
	 */
	private static function only( array $rows ): array
	{
		$only = (string) getenv( 'KCO_ONLY' );
		if ( $only === '' ) {
			return $rows;
		}

		$needles = array_filter( array_map( 'trim', explode( ',', $only ) ) );

		$matching = array_filter(
			$rows,
			static function ( string $name ) use ( $needles ): bool {
				foreach ( $needles as $needle ) {
					if ( stripos( $name, $needle ) !== false ) {
						return true;
					}
				}

				return false;
			},
			ARRAY_FILTER_USE_KEY
		);

		if ( $matching === [] ) {
			throw new \InvalidArgumentException(
				"KCO_ONLY=\"{$only}\" matches none of: " . implode( ' | ', array_keys( $rows ) )
			);
		}

		return $matching;
	}

	/** The two WooCommerce settings that decide what a cart adds up to. */
	private static function pricing( string $pricesIncludeTax, string $roundAtSubtotal ): array
	{
		return [
			'woocommerce_prices_include_tax'    => $pricesIncludeTax,
			'woocommerce_tax_round_at_subtotal' => $roundAtSubtotal,
		];
	}

	/**
	 * One provider row: a Swedish customer buying a single 25% VAT product, unless
	 * the row says otherwise.
	 */
	private static function scenario(array $overrides): array
	{
		return array_replace(
			[
				// Arrange: the store, the tax rates and the cart, whose SKUs are
				// TestProducts entries, each optionally [ SKU, quantity ].
				'store'     => [],
				'tax_rates' => [ TestTaxRates::TAX_RATE_25 ],
				'cart'      => [ TestProducts::SIMPLE_25 ],
				'billing'   => [],
				// Assert: the finished order's _payment_method, _order_total and
				// any further meta.
				'gateway'   => 'kco',
				'total'     => null,
				'meta'      => [],
			],
			$overrides
		);
	}
}
