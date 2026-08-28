<?php
/**
 * Plugin Name: KCO Tests, classic checkout page
 * Description: Makes WooCommerce install its checkout page with the [woocommerce_checkout] shortcode rather than the checkout block, so the suite always boots in classic checkout mode. Loaded only inside the Codeception test WP install.
 *
 * WooCommerce has created the checkout page from `get_checkout_block_content()` since
 * 8.3, so a freshly installed store carries a `woocommerce/checkout` block. KCO decides
 * once per process, on `init` priority 5.
 */

if (! defined('ABSPATH')) {
    exit;
}

add_filter(
    'woocommerce_create_pages',
    static function ($pages) {
        if (isset($pages['checkout'])) {
            $pages['checkout']['content'] = '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->';
        }

        return $pages;
    }
);
