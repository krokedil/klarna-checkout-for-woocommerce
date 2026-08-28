<?php
/**
 * Plugin Name: KCO Tests, WooCommerce session upsert
 * Description: Gives the WooCommerce session table the unique index its writes assume. Loaded only inside the Codeception test WP install.
 *
 * WooCommerce saves the session with `INSERT ... ON DUPLICATE KEY UPDATE`, which only
 * updates when a unique index on `session_key` exists. The SQLite translation of the
 * schema has no such index, so every save appends a row and reads pick one at random.
 *
 * Created here rather than in the dump, which WPDb reloads before every test.
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('FQDB') || ! is_file(FQDB)) {
    return;
}

add_action(
    'muplugins_loaded',
    static function (): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        global $wpdb;
        $table = ($wpdb instanceof wpdb ? $wpdb->prefix : 'wp_') . 'woocommerce_sessions';

        try {
            // Its own connection: this is SQLite DDL, not something the MySQL-to-SQLite
            // translation in front of $wpdb can carry.
            $sqlite = new PDO('sqlite:' . FQDB);
            $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $exists = $sqlite
                ->query("SELECT name FROM sqlite_master WHERE type = 'index' AND name = 'kco_tests_session_key'")
                ->fetchColumn();

            if ($exists !== false) {
                return;
            }

            // Duplicates already written go first, newest kept, so the index can be made.
            $sqlite->exec(
                "DELETE FROM `{$table}` WHERE rowid NOT IN ("
                . "SELECT MAX(rowid) FROM `{$table}` GROUP BY session_key)"
            );

            $sqlite->exec("CREATE UNIQUE INDEX kco_tests_session_key ON `{$table}` (session_key)");
        } catch (Throwable $e) {
            // No table yet, or a database busy reloading the dump. The next request
            // is a new process and tries again.
        }
    },
    0
);
