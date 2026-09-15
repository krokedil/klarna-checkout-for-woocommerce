<?php
/**
 * Plugin Name: KCO Tests, hidden order type
 * Description: Registers an order type hidden from customer order views, the way a site's own theme might for partial-payment bookkeeping. Loaded only inside the Codeception test WP install.
 *
 * Such a type is excluded from wc_get_order_types( 'view-orders' ), which is what
 * wc_get_orders() defaults to, so it exists to prove our own lookups do not inherit
 * that customer-visibility filter.
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', static function (): void {
    if (! class_exists('WC_Order') || class_exists('KCO_Tests_Hidden_Order')) {
        return;
    }

    /** An order type whose get_type() differs from the default 'shop_order', as a merchant's own wc_register_order_type() class would. */
    class KCO_Tests_Hidden_Order extends WC_Order {
        public function get_type() {
            return 'kco_hidden_order';
        }
    }
});

add_action('init', static function (): void {
    if (! function_exists('wc_register_order_type')) {
        return;
    }

    wc_register_order_type('kco_hidden_order', [
        'exclude_from_order_count'          => true,
        'exclude_from_order_views'          => true,
        'exclude_from_order_webhooks'       => true,
        'exclude_from_order_reports'        => true,
        'exclude_from_order_sales_reports'  => true,
        'add_order_meta_boxes'              => false,
        'exclude_from_orders_screen'        => true,
        'show_in_menu'                      => false,
        'class_name'                        => 'KCO_Tests_Hidden_Order',
    ]);
}, 5);
