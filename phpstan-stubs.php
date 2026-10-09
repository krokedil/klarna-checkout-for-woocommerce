<?php
/**
 * Class and function stubs for PHPStan static analysis only.
 *
 * Declares the WooCommerce Store API symbols the plugin uses, which php-stubs/woocommerce-stubs omits.
 *
 * @package Klarna_Checkout
 *
 * @phpcs:disable
 */

namespace Automattic\WooCommerce\StoreApi {
	final class SessionHandler extends \WC_Session {
		/**
		 * @return void
		 */
		public function init() {}
	}
}

namespace Automattic\WooCommerce\StoreApi\Utilities {
	final class JsonWebToken {
		/**
		 * @param array  $payload Payload data.
		 * @param string $secret  The secret used to generate the signature.
		 * @return string
		 */
		public static function create( array $payload, string $secret ) {}

		/**
		 * @param string $token  Full token string.
		 * @param string $secret The secret used to generate the signature.
		 * @return bool
		 */
		public static function validate( string $token, string $secret ) {}
	}

	class CartController {
		/**
		 * @return array
		 */
		public function get_cart_hashes() {}
	}
}

namespace Automattic\WooCommerce\StoreApi\Schemas\V1 {
	class CartSchema {
		const IDENTIFIER = 'cart';
	}
}

namespace {
	/**
	 * @param array $args Args to pass to register_endpoint_data.
	 * @return bool|\WP_Error
	 */
	function woocommerce_store_api_register_endpoint_data( $args ) {}

	/**
	 * @param array $args Args to pass to register_update_callback.
	 * @return bool|\WP_Error
	 */
	function woocommerce_store_api_register_update_callback( $args ) {}
}
