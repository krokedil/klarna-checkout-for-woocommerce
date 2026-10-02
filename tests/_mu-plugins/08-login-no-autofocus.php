<?php
/**
 * Plugin Name: KCO Tests, no login form autofocus
 * Description: Stops wp-login.php from clearing the password field after the driver has filled it. Loaded only inside the Codeception test WP install.
 *
 * wp-login.php schedules wp_attempt_focus(), which empties #user_pass 200ms after the
 * page loads. A driver fast enough to fill the form inside that window has its password
 * wiped, the browser refuses to submit the empty required field, and the test is left
 * on the login screen.
 */

if (! defined('ABSPATH')) {
    exit;
}

add_filter('enable_login_autofocus', '__return_false');
