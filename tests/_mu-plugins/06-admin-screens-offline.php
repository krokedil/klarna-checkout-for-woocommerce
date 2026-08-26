<?php
/**
 * Plugin Name: KCO Tests, quiet wp-admin screens
 * Description: Drops the wp-admin requests that never answer in this environment. Loaded only inside the Codeception test WP install.
 *
 * A browser opens a handful of connections per host. A wp-admin screen parks them on
 * requests that hang rather than answer, its own scripts queue behind, and the driver
 * waits on a screen that has been rendered for a minute. The three offenders:
 *
 * - WooCommerce Admin's /wp-json/wc-admin and /wp-json/wc-analytics burst, whose report
 *   queries use SQL the SQLite translation cannot run (CONCAT_WS, among others).
 * - The dashboard news widget and the update checks, which fetch wordpress.org.
 * - The script compression test, one more admin-ajax round trip per screen.
 *
 * Nothing under test lives in any of them.
 */

if (! defined('ABSPATH')) {
    exit;
}

add_filter('woocommerce_admin_disabled', '__return_true');

// The filter above stops the features, not the admin bundle's calls to them.
add_filter('rest_pre_dispatch', static function ($result, $server, $request) {
    if (preg_match('#^/wc-admin/|^/wc-analytics/#', $request->get_route())) {
        return new WP_REST_Response([], 200);
    }

    return $result;
}, 0, 3);

add_action('wp_dashboard_setup', static function (): void {
    remove_meta_box('dashboard_primary', 'dashboard', 'side');
});

add_action('admin_init', static function (): void {
    if (get_site_option('can_compress_scripts') === false) {
        update_site_option('can_compress_scripts', 0);
    }
});

// Kustom still has to be reachable, so only the hosts the suite never needs are cut.
add_filter('pre_http_request', static function ($preempt, $args, $url) {
    $host = (string) parse_url($url, PHP_URL_HOST);

    if (preg_match('/(^|\.)(wordpress\.org|woocommerce\.com|gravatar\.com)$/', $host)) {
        return new WP_Error('kco_tests_offline', 'Blocked in tests: ' . $url);
    }

    return $preempt;
}, 10, 3);
