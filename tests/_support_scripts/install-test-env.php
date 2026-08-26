<?php
/**
 * Set up the local test WordPress environment. Idempotent: every step short-circuits
 * if the work is already done.
 *
 * Runs from Composer's `post-autoload-dump-dev` hook rather than a Codeception
 * bootstrap, because wp-browser's Symlinker runs on MODULE_INIT and refuses to
 * continue unless WordPress is already extracted and configured.
 */

use lucatume\WPBrowser\Utils\Filesystem as FS;
use lucatume\WPBrowser\WordPress\Database\SQLiteDatabase;
use lucatume\WPBrowser\WordPress\Installation;
use lucatume\WPBrowser\WordPress\InstallationState\Multisite;
use lucatume\WPBrowser\WordPress\InstallationState\Single;

$root         = dirname(__DIR__, 2);
$wpRoot       = $root . '/tests/_wordpress';
$muSource     = $root . '/tests/_mu-plugins';
$muPluginsDir = $wpRoot . '/wp-content/mu-plugins';
$contentDir   = $wpRoot . '/wp-content';
$dataDir      = $wpRoot . '/data';
$sqliteSrcDir = $root . '/tests/_plugins/sqlite-database-integration';

require $root . '/vendor/autoload.php';

if (! is_file($wpRoot . '/wp-load.php')) {
    echo "Scaffolding WordPress into tests/_wordpress/ (downloads core once)...\n";
    if (! is_dir($wpRoot) && ! mkdir($wpRoot, 0777, true) && ! is_dir($wpRoot)) {
        fwrite(STDERR, "Failed to create {$wpRoot}\n");
        exit(1);
    }
    Installation::scaffold($wpRoot);
}

if (! is_dir($dataDir) && ! mkdir($dataDir, 0777, true) && ! is_dir($dataDir)) {
    fwrite(STDERR, "Failed to create {$dataDir}\n");
    exit(1);
}

// Prefer the composer-installed sqlite-database-integration over wp-browser's older bundled copy.
// Must run before configure(), which would otherwise place the bundled one itself.
if (is_dir($sqliteSrcDir)) {
    $sqliteDestDir = $muPluginsDir . '/sqlite-database-integration';
    $dropinPath    = $contentDir . '/db.php';

    if (! is_dir($sqliteDestDir)) {
        FS::mkdirp($muPluginsDir);
        if (! FS::recurseCopy($sqliteSrcDir, $sqliteDestDir)) {
            fwrite(STDERR, "Failed to copy SQLite mu-plugin to {$sqliteDestDir}\n");
            exit(1);
        }
    }

    $dbCopy = $sqliteDestDir . '/db.copy';
    if (! is_file($dropinPath) && is_file($dbCopy)) {
        $contents = file_get_contents($dbCopy);
        if ($contents === false) {
            fwrite(STDERR, "Could not read {$dbCopy}\n");
            exit(1);
        }
        $contents = str_replace(
            ['{SQLITE_IMPLEMENTATION_FOLDER_PATH}', '{SQLITE_PLUGIN}', '{SQLITE_MAIN_FILE}'],
            [$sqliteDestDir, 'sqlite-database-integration/load.php', $sqliteDestDir . '/load.php'],
            $contents
        );

        // WPLoader's in-process boot never sees our wp-config.php, so without this env-var
        // fallback it writes test_ tables to a different SQLite file than the PDO reads.
        $envFallback   = "if ( ! defined( 'DB_DIR' ) && getenv( 'DB_DIR' ) ) {\n"
            . "\tdefine( 'DB_DIR', realpath( getenv( 'DB_DIR' ) ) );\n"
            . "}\n"
            . "if ( ! defined( 'DB_FILE' ) && getenv( 'DB_FILE' ) ) {\n"
            . "\tdefine( 'DB_FILE', getenv( 'DB_FILE' ) );\n"
            . "}\n\n";
        $requireMarker = "// Require the implementation from the plugin.";
        $patched       = str_replace($requireMarker, $envFallback . $requireMarker, $contents);
        if ($patched === $contents) {
            fwrite(STDERR, "SQLite db.copy layout changed, could not find the require marker to inject DB_DIR/DB_FILE env fallback. Update install-test-env.php.\n");
            exit(1);
        }
        $contents = $patched;
        if (! file_put_contents($dropinPath, $contents, LOCK_EX)) {
            fwrite(STDERR, "Could not write SQLite dropin to {$dropinPath}\n");
            exit(1);
        }
        if (! is_file($contentDir . '/.gitignore')) {
            file_put_contents($contentDir . '/.gitignore', "db.php\n", LOCK_EX);
        }
    }
}

$installation = new Installation($wpRoot, false);
if (! $installation->isConfigured()) {
    echo "Configuring WordPress against SQLite DB...\n";
    $installation->configure(new SQLiteDatabase($dataDir, 'db.sqlite'));
}

// Patch WP_DEBUG in place so notices land in debug.log. Injecting a second define()
// would be silently dropped, because the generated file defines it first.
$wp_config_path = $wpRoot . '/wp-config.php';
if (is_file($wp_config_path)) {
    $wp_config_contents = (string) file_get_contents($wp_config_path);
    $updated_contents   = $wp_config_contents;

    // Flip the template's `define( 'WP_DEBUG', false );` to true.
    $updated_contents = (string) preg_replace(
        "/define\(\s*'WP_DEBUG'\s*,\s*false\s*\);/",
        "define( 'WP_DEBUG', true );",
        $updated_contents,
        1
    );

    if (false === strpos($updated_contents, 'WP_DEBUG_LOG')) {
        $updated_contents = (string) preg_replace(
            "/(define\(\s*'WP_DEBUG'\s*,\s*true\s*\);)/",
            "$1\ndefine( 'WP_DEBUG_LOG', true );\ndefine( 'WP_DEBUG_DISPLAY', false );",
            $updated_contents,
            1
        );
    }

    if ($updated_contents !== $wp_config_contents) {
        file_put_contents($wp_config_path, $updated_contents);
        echo "Patched wp-config.php (WP_DEBUG=true, WP_DEBUG_LOG=true, WP_DEBUG_DISPLAY=false).\n";
    }
}

$state = $installation->getState();
if (! ($state instanceof Single || $state instanceof Multisite)) {
    echo "Installing WordPress (DB tables + admin user)...\n";
    $port = (int) (getenv('BUILTIN_SERVER_PORT') ?: 64942);
    try {
        $installation->install(
            "http://localhost:{$port}",
            'admin',
            'password',
            'admin@localhost.test',
            'Kustom Checkout Test'
        );
    } catch (\Throwable $e) {
        // WP's install routine cries "Database Error!" on harmless races, so retry against the file directly.
        try {
            $pdo = new PDO('sqlite:' . $dataDir . '/db.sqlite');
            $row = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='wp_options'")->fetchColumn();
            if ($row !== 'wp_options' && ! str_contains($e->getMessage(), 'already installed')) {
                fwrite(STDERR, "WP install failed: " . $e->getMessage() . "\n");
                exit(1);
            }
        } catch (\Throwable $check_e) {
            fwrite(STDERR, "WP install failed: " . $e->getMessage() . "\n");
            exit(1);
        }
    }
}

// WAL journal mode, so the built-in server's concurrent workers can share the DB.
$dbFile = $dataDir . '/db.sqlite';
if (is_file($dbFile)) {
    try {
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $mode = $pdo->query('PRAGMA journal_mode = WAL')->fetchColumn();
        if ('wal' !== strtolower((string) $mode)) {
            fwrite(STDERR, "Warning: could not switch {$dbFile} to WAL (got '{$mode}').\n");
        }
    } catch (\Throwable $wal_e) {
        fwrite(STDERR, "Could not set WAL on {$dbFile}: " . $wal_e->getMessage() . "\n");
    }
}

// Safety net: re-place the SQLite drop-in in case something wiped wp-content/db.php.
Installation::placeSqliteMuPlugin($muPluginsDir, $contentDir);

if (! is_dir($muPluginsDir) && ! mkdir($muPluginsDir, 0777, true) && ! is_dir($muPluginsDir)) {
    fwrite(STDERR, "Failed to create {$muPluginsDir}\n");
    exit(1);
}

$copied = 0;
$wanted = [];
foreach (glob($muSource . '/*.php') as $file) {
    $wanted[] = basename($file);
    $dest     = $muPluginsDir . '/' . basename($file);
    if (copy($file, $dest)) {
        ++$copied;
    }
}

// Drop the ones an earlier checkout installed and this one no longer has, matched on our
// own plugin header so the SQLite drop-in is left alone.
foreach (glob($muPluginsDir . '/*.php') as $installed) {
    if (in_array(basename($installed), $wanted, true)) {
        continue;
    }
    if (strpos((string) file_get_contents($installed), 'Plugin Name: KCO Tests,') !== false) {
        unlink($installed);
    }
}

// Activate Storefront as the active theme if composer pulled it in.
$storefrontDir = $root . '/tests/_themes/storefront';
if (is_dir($storefrontDir) && is_file($dbFile)) {
    try {
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $currentStylesheet = $pdo->query("SELECT option_value FROM wp_options WHERE option_name='stylesheet'")->fetchColumn();

        if ($currentStylesheet !== 'storefront') {
            foreach (['template' => 'storefront', 'stylesheet' => 'storefront', 'current_theme' => 'Storefront'] as $name => $value) {
                $stmt = $pdo->prepare('UPDATE wp_options SET option_value = :val WHERE option_name = :name');
                $stmt->execute([':val' => $value, ':name' => $name]);
            }
            echo "Activated Storefront theme.\n";
        }
    } catch (\Throwable $theme_e) {
        fwrite(STDERR, "Could not activate Storefront theme: " . $theme_e->getMessage() . "\n");
    }
}

echo "Test environment ready ({$copied} project mu-plugin(s) installed).\n";
