<?php
/**
 * Constant declarations for PHPStan static analysis only.
 *
 * Not loaded at runtime — the real values are defined in klarna-checkout-for-woocommerce.php.
 * These declarations exist so PHPStan knows the constants exist when analyzing files
 * that are pulled in via dynamic include_once and therefore can't be traced statically.
 *
 * @package Klarna_Checkout
 *
 * @phpcs:disable
 */

define( 'KCO_WC_VERSION', '0.0.0' );
define( 'KCO_WC_MIN_PHP_VER', '5.6.0' );
define( 'KCO_WC_MIN_WC_VER', '3.9.0' );
define( 'KCO_WC_MAIN_FILE', __FILE__ );
define( 'KCO_WC_PLUGIN_PATH', __DIR__ );
define( 'KCO_WC_PLUGIN_URL', 'https://example.com' );

// Missing constants that are set in WordPress but not in their stubs.
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'LOGGED_IN_COOKIE', 'wordpress_logged_in_' );

// Missing constants that are set in WooCommerce but not in their stubs.
define( 'WOOCOMMERCE_VERSION', '0.0.0' );

// Constants set by third-party plugins the integration code guards against.
define( 'WCML_MULTI_CURRENCIES_INDEPENDENT', 2 );
