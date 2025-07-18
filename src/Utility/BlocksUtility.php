<?php
namespace Krokedil\KustomCheckout\Utility;

class BlocksUtility {
	/**
	 * Check if the checkout block is present on the checkout page.
	 *
	 * @return bool
	 */
	public static function is_checkout_block_enabled() {
		return \WC_Blocks_Utils::has_block_in_page( wc_get_page_id( 'checkout' ), 'woocommerce/checkout' );
	}
}
