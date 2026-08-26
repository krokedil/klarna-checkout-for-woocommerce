<?php
/*
 * Harness suite bootstrap. Shared setup lives in tests/_bootstrap.php.
 *
 * ABSPATH is defined so plugin files guarded by it can be parsed before
 * WPLoader boots. The subscriptions stand-ins are declared here because
 * FakeSubscriptionsTest calls the globals the plugin calls rather than the
 * registry behind them.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../_wordpress/' );
}

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../_subscriptions-fakes.php';
