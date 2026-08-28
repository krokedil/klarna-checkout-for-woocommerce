<?php
/**
 * Plugin Name: KCO Tests, admin password from the environment
 * Description: Resets the admin password to WORDPRESS_ADMIN_PASSWORD at request time, so the committed dump carries a placeholder rather than a real credential.
 *
 * The site is reachable over the public ngrok tunnel while the suite runs, so the
 * password the browser logs in with cannot be the one in the dump. Applied here
 * rather than in the dump, which WPDb reloads before every test.
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action(
    'plugins_loaded',
    static function (): void {
        $password = getenv('WORDPRESS_ADMIN_PASSWORD');

        if (! is_string($password) || $password === '') {
            return;
        }

        // One hash and one write per test, rather than one per request.
        $option      = 'kco_tests_admin_password';
        $fingerprint = hash('sha256', $password);

        if (get_option($option) === $fingerprint) {
            return;
        }

        $user = get_user_by('login', 'admin');

        if ($user) {
            wp_set_password($password, $user->ID);
        }

        update_option($option, $fingerprint, false);
    },
    // Ahead of anything that authenticates, but late enough for pluggable functions.
    1
);
