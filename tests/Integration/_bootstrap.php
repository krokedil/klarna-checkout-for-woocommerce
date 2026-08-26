<?php
/*
 * Integration suite bootstrap. Shared setup lives in tests/_bootstrap.php.
 *
 * ABSPATH is defined so plugin files guarded by it can be parsed before
 * WPLoader boots, and WPAjaxTestCase is required explicitly because it is not
 * on Composer's autoloader. The WooCommerce Subscriptions stand-ins are shared
 * with the Harness suite, so they live in tests/_subscriptions-fakes.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../_wordpress/' );
}

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../vendor/lucatume/wp-browser/includes/core-phpunit/includes/testcase-ajax.php';
require_once __DIR__ . '/../_subscriptions-fakes.php';
