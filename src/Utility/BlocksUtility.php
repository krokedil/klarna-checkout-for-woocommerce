<?php
namespace Krokedil\KustomCheckout\Utility;

use Automattic\WooCommerce\StoreApi\Utilities\CartTokenUtils;
use Automattic\WooCommerce\StoreApi\Utilities\JsonWebToken;

/**
 * Utility class for helper functions related to blocks.
 */
class BlocksUtility {
	/**
	 * The `iss` claim the Store API requires on a cart token.
	 */
	const CART_TOKEN_ISSUER = 'store-api';

	/**
	 * The `iss` claim on cart tokens issued before WooCommerce 11.3.1 required `store-api`.
	 */
	const LEGACY_CART_TOKEN_ISSUER = 'wc/store/v1';

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
	 * Create a Store API cart token for a session key.
	 *
	 * @param string $customer_id The session key the token should load.
	 * @return string
	 */
	public static function create_cart_token( $customer_id ) {
		if ( is_callable( array( CartTokenUtils::class, 'get_cart_token' ) ) ) {
			return CartTokenUtils::get_cart_token( (string) $customer_id );
		}

		return JsonWebToken::create(
			array(
				'user_id' => $customer_id,
				'exp'     => time() + intval( apply_filters( 'wc_session_expiration', DAY_IN_SECONDS * 2 ) ), // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- WooCommerce core hook.
				'iss'     => self::CART_TOKEN_ISSUER,
			),
			'@' . wp_salt()
		);
	}

	/**
	 * Re-issue a legacy `wc/store/v1` cart token we signed, which WooCommerce 11.3.1 rejects, for the same session key.
	 *
	 * Other tokens, such as WooCommerce's cart link tokens, are returned as is.
	 *
	 * @param string $token The cart token.
	 * @return string
	 */
	public static function refresh_cart_token( $token ) {
		if ( ! is_string( $token ) || empty( $token ) || ! JsonWebToken::validate( $token, '@' . wp_salt() ) ) {
			return $token;
		}

		$payload = JsonWebToken::get_parts( $token )->payload;
		if ( self::LEGACY_CART_TOKEN_ISSUER !== ( $payload->iss ?? '' ) || empty( $payload->user_id ) ) {
			return $token;
		}

		return self::create_cart_token( (string) $payload->user_id );
	}

	/**
	 * Checks if we are currently editing the checkout page in the admin area.
	 *
	 * @return bool
	 */
	private static function is_editing_checkout_page() {
		$post_id_raw     = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );
		$post_id         = $post_id_raw ? absint( $post_id_raw ) : null;
		$is_edit_context = 'edit' === filter_input( INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		$is_admin_context = is_admin();
		if ( ! $is_admin_context || ! $is_edit_context || null === $post_id ) {
			return false;
		}

		if ( wc_get_page_id( 'checkout' ) !== $post_id ) {
			return false;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		return true;
	}
}
