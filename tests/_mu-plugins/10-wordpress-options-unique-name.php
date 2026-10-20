<?php
/**
 * Plugin Name: KCO Tests, unique option names
 * Description: Gives the options table the unique index on option_name that every real install has. Loaded only inside the Codeception test WP install.
 *
 * The SQLite translation of the schema has no such index, so an INSERT IGNORE never
 * collides and the confirmation lock can be taken twice. Same approach as
 * 03-woocommerce-sessions-upsert.php, for the same reason.
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
        $table = ($wpdb instanceof wpdb ? $wpdb->prefix : 'wp_') . 'options';

        try {
            // Its own connection: this is SQLite DDL, not something the MySQL-to-SQLite
            // translation in front of $wpdb can carry.
            $sqlite = new PDO('sqlite:' . FQDB);
            $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $exists = $sqlite
                ->query("SELECT name FROM sqlite_master WHERE type = 'index' AND name = 'kco_tests_option_name'")
                ->fetchColumn();
            if ($exists !== false) {
                return;
            }

            // Duplicates already written go first, newest kept, so the index can be made.
            $sqlite->exec(
                "DELETE FROM `{$table}` WHERE rowid NOT IN ("
                . "SELECT MAX(rowid) FROM `{$table}` GROUP BY option_name)"
            );
            $sqlite->exec("CREATE UNIQUE INDEX kco_tests_option_name ON `{$table}` (option_name)");
        } catch (Throwable $e) {
            // No table yet, or a database busy reloading the dump. The next request
            // is a new process and tries again.
        }
    },
    0
);
