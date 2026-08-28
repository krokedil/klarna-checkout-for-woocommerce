<?php
/**
 * Plugin Name: KCO Tests, inject Kustom test credentials
 * Description: Overlays the `woocommerce_kco_settings` option with credentials read from env vars at request time, so the dump.sql can ship without secrets and credentials can be swapped per-environment (dev/CI) without re-generating the dump.
 *
 * EndToEnd only: codeception.yml hands the credential env vars to the built-in server, so
 * they are absent in the codecept process. The Integration suite sets its own settings per
 * test, and forcing enabled/testmode there would override what a test just configured,
 * which is what the early return below protects.
 *
 * The overlay is read-only by design: `pre_update_option_woocommerce_kco_settings` scrubs
 * the injected values back out of anything on its way into the database, so a plain
 * read-modify-write of the option can never persist a secret. That matters because the
 * plugin does exactly that on every request (KCO_Fields::fields() writes `checkout_flow`
 * back into the option), which is how real credentials ended up in a committed dump.sql
 * once already, and how they would otherwise leak into the Allure report as step arguments.
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The credentials to overlay, keyed by setting name, read from env at call time.
 *
 * The gateway resolves its credential set from the store's base country: `US` reads the
 * `us` pair, everything else the `eu` one.
 *
 * @return array<string, string>
 */
function kco_tests_injected_credentials(): array
{
    $regions = [
        'eu' => ['mid' => 'KUSTOM_TEST_MID_EU', 'secret' => 'KUSTOM_TEST_SECRET_EU'],
        'us' => ['mid' => 'KUSTOM_TEST_MID_US', 'secret' => 'KUSTOM_TEST_SECRET_US'],
    ];

    $credentials = [];

    foreach ($regions as $region => $env) {
        $mid    = getenv($env['mid']);
        $secret = getenv($env['secret']);
        if (is_string($mid) && $mid !== '' && is_string($secret) && $secret !== '') {
            // testmode is forced on by the read filter, and that is the half of the
            // option the gateway reads.
            $credentials['test_merchant_id_' . $region]   = $mid;
            $credentials['test_shared_secret_' . $region] = $secret;
        }
    }

    return $credentials;
}

add_filter('option_woocommerce_kco_settings', static function ($settings) {
    $credentials = kco_tests_injected_credentials();

    // Nothing to inject, leave the settings exactly as stored.
    if (empty($credentials)) {
        return $settings;
    }

    if (! is_array($settings)) {
        $settings = [];
    }

    $settings['enabled']  = 'yes';
    $settings['testmode'] = 'yes';

    return array_merge($settings, $credentials);
});

/**
 * Strips the injected credentials back out on the way to the database.
 *
 * Matches on the value rather than the setting name, so a secret that some code path
 * copied into a different key (the live `merchant_id_*` / `shared_secret_*` pair, say)
 * is caught too. Runs last so it sees whatever every other filter settled on.
 *
 * `update_option()` applies this before its add_option() fallback, so a missing option
 * row is covered by the same filter.
 */
add_filter('pre_update_option_woocommerce_kco_settings', static function ($value) {
    $secrets = array_values(kco_tests_injected_credentials());

    if (empty($secrets) || ! is_array($value)) {
        return $value;
    }

    foreach ($value as $key => $item) {
        if (is_string($item) && in_array($item, $secrets, true)) {
            // The same stand-in regenerate-dump.sh seeds the option with, so a
            // scrubbed write is indistinguishable from a freshly generated dump.
            $value[$key] = 'placeholder';
        }
    }

    return $value;
}, PHP_INT_MAX);
