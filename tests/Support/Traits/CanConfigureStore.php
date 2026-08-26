<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

/**
 * Store-level fixtures for the Integration suite: base location, currency, tax
 * options, tax rates and the gateway settings option.
 */
trait CanConfigureStore {

	/** The gateway settings option name. */
	public const GATEWAY_SETTINGS_OPTION = 'woocommerce_kco_settings';

	/** Applies a store configuration. */
	protected function configureStore( array $args = [] ): void {
		$args = array_merge(
			[
				'country'            => 'SE',
				'currency'           => 'SEK',
				'calc_taxes'         => true,
				'prices_include_tax' => false,
				'tax_based_on'       => 'billing',
				'ship_to_countries'  => '',
			],
			$args
		);

		update_option( 'woocommerce_default_country', $args['country'] );
		update_option( 'woocommerce_currency', $args['currency'] );
		update_option( 'woocommerce_calc_taxes', $args['calc_taxes'] ? 'yes' : 'no' );
		update_option( 'woocommerce_prices_include_tax', $args['prices_include_tax'] ? 'yes' : 'no' );
		update_option( 'woocommerce_tax_based_on', $args['tax_based_on'] );
		update_option( 'woocommerce_ship_to_countries', $args['ship_to_countries'] );

		$this->flushStoreCaches();
	}

	/** A SE / SEK store with a single 25% VAT rate. */
	protected function configureSwedishStore(): int {
		$this->configureStore(
			[
				'country'  => 'SE',
				'currency' => 'SEK',
			]
		);

		return $this->haveTaxRate(
			[
				'tax_rate_country' => 'SE',
				'tax_rate'         => '25.0000',
				'tax_rate_name'    => 'VAT',
			]
		);
	}

	/** A US:CA / USD store with a single 8.5% sales tax rate. */
	protected function configureUsStore(): int {
		$this->configureStore(
			[
				'country'  => 'US:CA',
				'currency' => 'USD',
			]
		);

		return $this->haveTaxRate(
			[
				'tax_rate_country' => 'US',
				'tax_rate_state'   => 'CA',
				'tax_rate'         => '8.5000',
				'tax_rate_name'    => 'Sales Tax',
			]
		);
	}

	/**
	 * Ensures the WooCommerce pages a real store has exist and are pointed at.
	 *
	 * WPLoader's install leaves the page ids in the options but no pages behind them,
	 * so `wc_get_page_id()` resolves to a post that is not there. The plugin builds its
	 * merchant URLs off these pages, and Kustom requires every one of them.
	 */
	protected function haveStorePages(): void {
		// Pretty permalinks, so a merchant URL is the page's slug rather than the post id
		// a rolled back transaction hands out afresh every test. WP_Rewrite reads the
		// structure once at load, so it has to be told.
		if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
			update_option( 'permalink_structure', '/%postname%/' );
		}

		$GLOBALS['wp_rewrite']->init();

		$pages = [
			'cart'     => [ 'Cart', '<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->' ],
			'checkout' => [ 'Checkout', '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->' ],
			'terms'    => [ 'Terms and conditions', 'The terms a shopper agrees to.' ],
		];

		foreach ( $pages as $slug => list( $title, $content ) ) {
			$option  = "woocommerce_{$slug}_page_id";
			$page_id = (int) get_option( $option );

			if ( $page_id > 0 && get_post( $page_id ) instanceof \WP_Post ) {
				continue;
			}

			// A block delimiter is an HTML comment, and KSES strips those from anyone who
			// may not post unfiltered HTML, which is nobody in CLI.
			kses_remove_filters();

			$page_id = wp_insert_post(
				[
					'post_type'    => 'page',
					'post_title'   => $title,
					'post_name'    => $slug,
					'post_status'  => 'publish',
					'post_content' => $content,
				]
			);

			kses_init_filters();

			update_option( $option, $page_id );
		}
	}

	/** Inserts a tax rate. */
	protected function haveTaxRate( array $rate ): int {
		$rate_id = \WC_Tax::_insert_tax_rate(
			array_merge(
				[
					'tax_rate_country'  => '',
					'tax_rate_state'    => '',
					'tax_rate'          => '0.0000',
					'tax_rate_name'     => 'Tax',
					'tax_rate_priority' => 1,
					'tax_rate_compound' => 0,
					'tax_rate_shipping' => 1,
					'tax_rate_order'    => 0,
					'tax_rate_class'    => '',
				],
				$rate
			)
		);

		$this->flushStoreCaches();

		return (int) $rate_id;
	}

	/** Creates a tax class and a rate for it, and returns the class slug. */
	protected function haveTaxClass( string $name, string $rate, string $country = 'SE' ): string {
		$existing = \WC_Tax::get_tax_class_slugs();
		$slug     = sanitize_title( $name );

		if ( ! in_array( $slug, $existing, true ) ) {
			\WC_Tax::create_tax_class( $name, $slug );
		}

		$this->haveTaxRate(
			[
				'tax_rate_country' => $country,
				'tax_rate'         => $rate,
				'tax_rate_name'    => $name,
				'tax_rate_class'   => $slug,
			]
		);

		return $slug;
	}

	/** Removes every tax rate in the store. */
	protected function deleteAllTaxRates(): void {
		global $wpdb;

		$rate_ids = $wpdb->get_col( "SELECT tax_rate_id FROM {$wpdb->prefix}woocommerce_tax_rates" ); // phpcs:ignore

		foreach ( $rate_ids as $rate_id ) {
			\WC_Tax::_delete_tax_rate( (int) $rate_id );
		}

		$this->flushStoreCaches();
	}

	/** Overwrites the gateway settings option. */
	protected function setGatewaySettings( array $settings ): void {
		update_option( self::GATEWAY_SETTINGS_OPTION, $settings );

		// WPLoader rolls each test back, which leaves the options cache and the table
		// disagreeing: `add_option()` can then no-op against a row the cache says is
		// absent, and the next read is a previous test's settings. Dropping the cached
		// copies forces the next read down to the row this call just wrote.
		wp_cache_delete( self::GATEWAY_SETTINGS_OPTION, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );

		$this->flushGatewaySettingsCache();
	}

	/**
	 * Drops the two copies of the gateway settings that outlive an `update_option()`.
	 *
	 * `SettingsUtility` holds them in a static, and `KCO_Credentials` reads them once in
	 * its constructor, which runs at plugin load. The suite is one process, so without
	 * this a test signs its requests with whatever the first test configured — and
	 * `KCO_Request::get_merchant_id()` indexes the `false` that an unresolved credential
	 * set returns.
	 */
	protected function flushGatewaySettingsCache(): void {
		$settings = new \ReflectionProperty( \Krokedil\KustomCheckout\Utility\SettingsUtility::class, 'settings' );
		$settings->setAccessible( true );
		$settings->setValue( null, null );

		if ( function_exists( 'KCO_WC' ) && isset( KCO_WC()->credentials ) ) {
			KCO_WC()->credentials->settings = get_option( self::GATEWAY_SETTINGS_OPTION, [] );
		}
	}

	/**
	 * Merges credentials for one region into the gateway settings.
	 *
	 * @param string $region Settings region code, `eu` or `us`.
	 */
	protected function haveGatewayCredentials( string $region = 'eu', array $overrides = [], bool $testmode = true ): void {
		$this->haveGatewayCredentialsForRegions( [ $region ], $overrides, $testmode );
	}

	/**
	 * Merges credentials for several regions into the gateway settings.
	 *
	 * The settings option holds one merchant id / shared secret pair per region, and the
	 * region a request uses is derived from the store's base country, so a cross border
	 * scenario needs every pair present at once.
	 *
	 * @param array<int, string> $regions Settings region codes, for example `[ 'eu', 'us' ]`.
	 */
	protected function haveGatewayCredentialsForRegions( array $regions, array $overrides = [], bool $testmode = true ): void {
		$prefix      = $testmode ? 'test_' : '';
		$credentials = [];

		foreach ( $regions as $region ) {
			$region = strtolower( $region );

			$credentials[ "{$prefix}merchant_id_{$region}" ]   = "mid-{$region}";
			$credentials[ "{$prefix}shared_secret_{$region}" ] = "secret-{$region}";
		}

		$this->setGatewaySettings(
			array_merge(
				[
					'enabled'                 => 'yes',
					'testmode'                => $testmode ? 'yes' : 'no',
					// These are all read off the raw option without a fallback, and a saved
					// store always carries them, so the fixture has to as well. The values
					// are the defaults in KCO_Fields::fields().
					'allowed_customer_types'  => 'B2C',
					'logging'                 => 'no',
					'send_product_urls'       => 'yes',
					'allow_separate_shipping' => 'no',
				],
				$credentials,
				$overrides
			)
		);
	}

	/**
	 * Rebuilds WooCommerce's payment gateway objects, so they pick up settings
	 * changed after they were first constructed.
	 */
	protected function reloadPaymentGateways(): void {
		WC()->payment_gateways()->init();
	}

	/** Forgets the gateway state the WooCommerce session carries between requests. */
	protected function resetGatewaySession(): void {
		foreach ( [ 'kco_wc_order_id', 'kco_update_md5', 'kco_shipping_data', 'kco_wc_prefill_consent' ] as $key ) {
			WC()->session->__unset( $key );
		}
	}

	/** Invalidates the WooCommerce caches that outlive a transaction rollback. */
	protected function flushStoreCaches(): void {
		\WC_Cache_Helper::invalidate_cache_group( 'taxes' );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
	}
}
