<?php
/**
 * Shared test bootstrap, required by every suite bootstrap.
 *
 * Runs at SUITE_INIT, which is too late to scaffold WordPress, so the install lives in
 * composer's post-autoload-dump-dev hook. What stays here: a sentinel so the file is safe
 * to require twice, syncing tests/_mu-plugins/ into the install, and pinning the site URL.
 */

if (defined('KCO_TEST_BOOTSTRAP_DONE')) {
    return;
}
define('KCO_TEST_BOOTSTRAP_DONE', true);

$kco_mu_source  = dirname(__DIR__) . '/tests/_mu-plugins';
$kco_mu_plugins = dirname(__DIR__) . '/tests/_wordpress/wp-content/mu-plugins';

if (is_dir($kco_mu_plugins) && is_dir($kco_mu_source)) {
    $kco_wanted = [];

    foreach (glob($kco_mu_source . '/*.php') as $kco_src) {
        $kco_wanted[] = basename($kco_src);
        $kco_dest     = $kco_mu_plugins . '/' . basename($kco_src);
        if (! is_file($kco_dest) || filemtime($kco_src) > filemtime($kco_dest)) {
            copy($kco_src, $kco_dest);
        }
    }

    // Drop the ones an earlier checkout installed and this one no longer has, matched on
    // our own plugin header so wp-browser's SQLite drop-in is left alone.
    foreach (glob($kco_mu_plugins . '/*.php') as $kco_installed) {
        if (in_array(basename($kco_installed), $kco_wanted, true)) {
            continue;
        }
        if (strpos((string) file_get_contents($kco_installed), 'Plugin Name: KCO Tests,') !== false) {
            unlink($kco_installed);
        }
    }
}

unset($kco_mu_source, $kco_mu_plugins, $kco_src, $kco_dest, $kco_wanted, $kco_installed);

// Load the env vars from .env in the tests/ directory. This is where the test WP installation's URL, DB connection string, and other config live.
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

/*
 * Pin WP_HOME and WP_SITEURL to WORDPRESS_URL, the local built-in server. Constants rather
 * than option filters, because wp_plugin_directory_constants() freezes WP_CONTENT_URL from
 * siteurl before mu-plugins load; written here rather than committed, because WPDb reloads
 * a dump carrying whoever generated it. The tunnel is dealt with in
 * tests/_mu-plugins/01-kustom-public-url.php.
 */
$wpConfig = dirname(__DIR__) . '/tests/_wordpress/wp-config.php';
if (is_file($wpConfig)) {
	$wpUrl = rtrim($_ENV['WORDPRESS_URL'], '/');
	echo "Pinning WP_HOME and WP_SITEURL in {$wpConfig} to {$wpUrl}\n";

	$block = <<<PHP
	// >>> KCO tests: the site answers as WORDPRESS_URL, tunnelled requests included.
	if (! defined('WP_HOME')) {
	    define('WP_HOME', '{$wpUrl}');
	    define('WP_SITEURL', '{$wpUrl}');
	}
	// <<< KCO tests
	PHP;

	$contents = file_get_contents($wpConfig);

	// Drop whatever a previous run left behind, then re-insert.
	$contents = preg_replace('/\n?\/\/ >>> KCO tests:.*?\/\/ <<< KCO tests\n/s', '', $contents);
	$contents = preg_replace("/\n?define\('WP_(HOME|SITEURL)',[^;]*\);/", '', $contents);
	$contents = preg_replace('/^<\?php/', "<?php\n\n" . $block . "\n", $contents, 1);

	file_put_contents($wpConfig, $contents);
}
