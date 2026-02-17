<?php
namespace Krokedil\KustomCheckout\Utility;

/**
 * Utility class for helper functions related to blocks.
 */
class BlocksUtility {
	/**
	 * Check if the checkout block is present on the checkout page.
	 *
	 * @return bool
	 */
	public static function is_checkout_block_enabled() {
		// Always return true on the admin edit screen for the checkout page, to avoid displaying a notice about KCO not supporting the checkout block when editing the checkout page.
		if ( self::is_editing_checkout_page() ) {
			return true;
		}

		// Fallback to the original block check for all other cases.
		return \WC_Blocks_Utils::has_block_in_page( wc_get_page_id( 'checkout' ), 'woocommerce/checkout' );
	}

	/**
	 * Checks if we are currently on the admin pages when loading the blocks.
	 *
	 * @return boolean
	 */
	private static function is_editing_checkout_page() {
		$post_id         = isset( $_GET['post'] ) ? intval( sanitize_text_field( wp_unslash( $_GET['post'] ) ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_edit_context = isset( $_GET['action'] ) && 'edit' === $_GET['action']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_admin        = $is_edit_context;

		return $is_admin && wc_get_page_id( 'checkout' ) === $post_id;
	}
}
