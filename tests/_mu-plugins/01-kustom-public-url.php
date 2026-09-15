<?php
/**
 * Plugin Name: KCO Tests, public URL for Kustom
 * Description: Keeps the site local for the browser and public for Kustom. Loaded only inside the Codeception test WP install.
 *
 * The browser drives the site on WORDPRESS_URL, so its uncached assets never spend the
 * ngrok request allowance. Only Kustom's traffic uses the tunnel, which needs the site's
 * URL rewritten on the way out, and a browser that arrives on the tunnel sent back to
 * WORDPRESS_URL, because WordPress would answer it with a redirect to an unserved port.
 */

if (! defined('ABSPATH')) {
    exit;
}

// EndToEnd only: codeception.yml hands these to the built-in server, so the Integration
// and Harness suites, which never see them, are left alone.
$kco_local  = rtrim((string) getenv('WORDPRESS_URL'), '/');
$kco_public = rtrim((string) getenv('KCO_WORDPRESS_URL'), '/');

if ($kco_local === '' || $kco_public === '' || $kco_local === $kco_public) {
    return;
}

// ---------------------------------------------------------------------------
// Inbound: what arrived through the tunnel.
// ---------------------------------------------------------------------------

$kco_public_host = (string) parse_url($kco_public, PHP_URL_HOST);
$kco_local_host  = (string) parse_url($kco_local, PHP_URL_HOST);
$kco_local_port  = parse_url($kco_local, PHP_URL_PORT);

if ($kco_local_port !== null) {
    $kco_local_host .= ':' . $kco_local_port;
}

/** Whether the given header names the public host. The forwarded one can be a list. */
$kco_asked_for = static function (string $header) use ($kco_public_host): bool {
    foreach (explode(',', (string) ($_SERVER[$header] ?? '')) as $host) {
        if (strcasecmp(strtok(trim($host), ':') ?: '', $kco_public_host) === 0) {
            return true;
        }
    }

    return false;
};

// A web request only. WPLoader boots WordPress in-process with HTTP_HOST set from its
// `domain` config and no REQUEST_METHOD, and redirecting that exits before wp_loaded.
$kco_is_web_request = PHP_SAPI !== 'cli' && isset($_SERVER['REQUEST_METHOD']);

if ($kco_is_web_request && ($kco_asked_for('HTTP_HOST') || $kco_asked_for('HTTP_X_FORWARDED_HOST'))) {
    $kco_method = strtoupper((string) $_SERVER['REQUEST_METHOD']);
    $kco_uri    = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $kco_path   = (string) (parse_url($kco_uri, PHP_URL_PATH) ?: '/');

    // What Kustom calls the site on. A redirect would drop a POST body, and Kustom
    // follows none.
    $kco_is_callback = in_array($kco_method, ['GET', 'HEAD'], true) === false
        || strpos($kco_path, '/wc-api/') === 0
        || strpos($kco_path, '/wp-json/') === 0
        || isset($_GET['wc-api'])
        || isset($_GET['rest_route']);

    if ($kco_is_callback) {
        // Handled as the local request it would have been.
        $_SERVER['HTTP_HOST']   = $kco_local_host;
        $_SERVER['SERVER_NAME'] = strtok($kco_local_host, ':');
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_HOST'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
    } else {
        // Anything a browser asks for, Kustom's confirmation URL included.
        header('Location: ' . $kco_local . $kco_uri, true, 302);
        header('Cache-Control: no-store');
        exit;
    }

    unset($kco_method, $kco_uri, $kco_path, $kco_is_callback);
}

// ---------------------------------------------------------------------------
// Outbound: what the plugin sends to Kustom, which will not take a localhost URL.
// ---------------------------------------------------------------------------

$kco_port       = (string) getenv('BUILTIN_SERVER_PORT');
$kco_local_urls = array_values(
    array_unique(
        array_filter(
            [
                $kco_local,
                $kco_port === '' ? '' : 'http://localhost:' . $kco_port,
                $kco_port === '' ? '' : 'http://127.0.0.1:' . $kco_port,
            ]
        )
    )
);

// Both spellings of every local URL: bodies are rewritten as arrays and as the JSON they
// have already been encoded to, where the slashes are escaped.
$kco_search  = [];
$kco_replace = [];
foreach ($kco_local_urls as $kco_url) {
    $kco_search[]  = $kco_url;
    $kco_replace[] = $kco_public;
    $kco_search[]  = str_replace('/', '\/', $kco_url);
    $kco_replace[] = str_replace('/', '\/', $kco_public);
}

$kco_rewrite = static function ($value) use (&$kco_rewrite, $kco_search, $kco_replace) {
    if (is_string($value)) {
        return str_replace($kco_search, $kco_replace, $value);
    }

    if (is_array($value)) {
        foreach ($value as $key => $item) {
            $value[$key] = $kco_rewrite($item);
        }

        return $value;
    }

    // Payment categories and the like reach the body as objects.
    if ($value instanceof stdClass) {
        foreach (get_object_vars($value) as $key => $item) {
            $value->$key = $kco_rewrite($item);
        }
    }

    return $value;
};

/*
 * On `http_request_args` rather than KCO's own request args filters, which only covers
 * the bodies KCO core builds: order management, etc build their own.
 */
add_filter(
    'http_request_args',
    static function ($args, $url) use ($kco_rewrite) {
        $host = (string) wp_parse_url((string) $url, PHP_URL_HOST);

        // Kustom's API only: everywhere the browser can see it, the URL stays local.
        if (preg_match('/(^|\.)kustom\.co$/i', $host) !== 1) {
            return $args;
        }

        if (isset($args['body'])) {
            $args['body'] = $kco_rewrite($args['body']);
        }

        return $args;
    },
    10,
    2
);

unset(
    $kco_local,
    $kco_public,
    $kco_public_host,
    $kco_local_host,
    $kco_local_port,
    $kco_asked_for,
    $kco_is_web_request,
    $kco_port,
    $kco_local_urls,
    $kco_url,
    $kco_search,
    $kco_replace
);
