<?php
/**
 * Remove `INSERT INTO wp_actionscheduler_*` statements from a dump.
 *
 * Action Scheduler stores PHP-serialized objects, which the SQLite integration truncates
 * at the first NUL byte, so importing those rows aborts on unserialize(). The schema must
 * stay; only the data rows are dangerous.
 *
 * Usage: php tests/_support_scripts/strip-actionscheduler-inserts.php <dump.sql>
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php strip-actionscheduler-inserts.php <dump.sql>\n");
    exit(1);
}

$path = $argv[1];
if (! is_file($path)) {
    fwrite(STDERR, "Dump file not found: {$path}\n");
    exit(1);
}

$sql = file_get_contents($path);

// Each INSERT block looks like:
$pattern = '/^INSERT INTO wp_actionscheduler_[a-z_]+ .*?;\s*$/sm';

$newSql = preg_replace($pattern, '', $sql);
if ($newSql === null) {
    fwrite(STDERR, "preg_replace failed: " . preg_last_error() . "\n");
    exit(1);
}

// Collapse any runs of blank lines the removal created.
$newSql = preg_replace('/\n{3,}/', "\n\n", $newSql);

file_put_contents($path, $newSql);

$removed = substr_count($sql, 'INSERT INTO wp_actionscheduler_');
echo "Stripped {$removed} actionscheduler INSERT statement(s) from {$path}.\n";
