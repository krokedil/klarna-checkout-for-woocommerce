<?php
/**
 * Plugin Name: KCO Tests, push callback lock
 * Description: Turns on the confirmation lock and records each Kustom lookup the push makes instead of sending it. Loaded only inside the Codeception test WP install, and inert unless a test sets the kco_tests_push_lock option.
 *
 * Each record says whether the order's lock row existed at the moment of the lookup,
 * which is how PushCallbackCest tells a push that held the lock from one that skipped it.
 */

if (! defined('ABSPATH')) {
    exit;
}

if ('yes' !== get_option('kco_tests_push_lock')) {
    return;
}

add_filter('kco_wc_lock_confirmation', '__return_true');

add_filter('pre_http_request', static function ($response, array $args, string $url) {
    if (false === strpos((string) wp_parse_url($url, PHP_URL_HOST), 'kustom')) {
        return $response;
    }

    global $wpdb;

    $lock_rows = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'kco\\_confirmation\\_lock\\_%'"
    );

    $lookups   = get_option('kco_tests_push_lookups', []);
    $lookups[] = ['url' => $url, 'lock_held' => $lock_rows > 0];
    update_option('kco_tests_push_lookups', $lookups, false);

    return new WP_Error('kco_tests_offline', 'Kustom is not reachable from the push lock test.');
}, 10, 3);
