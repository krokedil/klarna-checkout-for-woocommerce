<?php
/**
 * Utility functions.
 *
 * @package WC_Klarna_Order_Management/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

/**
 * Equivalent to WP's get_the_ID() with HPOS support.
 *
 * @return int the order ID or false.
 */
function kom_get_the_ID() { // phpcs:ignore -- Function name is not in snake case format.
	$hpos_enabled = kom_is_hpos_enabled();
	$order_id     = $hpos_enabled ? filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT ) : get_the_ID();
	if ( empty( $order_id ) ) {
		return false;
	}

	return $order_id;
}

/**
 * Whether HPOS is enabled.
 *
 * @return bool
 */
function kom_is_hpos_enabled() {
	// CustomOrdersTableController was introduced in WC 6.4.
	if ( class_exists( CustomOrdersTableController::class ) ) {
		return wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled();
	}

	return false;
}

/**
 * Get the product and its image URLs.
 *
 * @param WC_Order_Item_Product $item The order item.
 * @return array The product and image URL if available, otherwise an empty array.
 */
function kom_maybe_add_product_urls( $item ) {
	$product_data = array();
	$settings     = get_option( 'woocommerce_kco_settings', array() );
	if ( isset( $settings['send_product_urls'] ) && 'yes' === $settings['send_product_urls'] ) {
		$product = wc_get_product( $item->get_product_id() );

		if ( empty( $product ) || ! method_exists( 'get_image_id', $product ) ) {
			return $product_data;
		}

		if ( $product->get_image_id() > 0 ) {
			$image_id                  = $product->get_image_id();
			$image_url                 = wp_get_attachment_image_url( $image_id, 'shop_single', false );
			$product_data['image_url'] = $image_url;
		}
	}
	return $product_data;
}

