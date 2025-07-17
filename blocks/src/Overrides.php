<?php
namespace Krokedil\KustomCheckout\Blocks;

use Automattic\WooCommerce\StoreApi\Utilities\JsonWebToken;

defined( 'ABSPATH' ) || exit;

/**
 * Class Overrides
 *
 * Handles the overrides for Kustom Checkout filters and actions where needed for the block checkout support.
 */
class Overrides {
	/**
	 * Class constructor
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'kco_wc_api_request_args', array( $this, 'override_request_body' ), 10, 2 );
	}

	/**
	 * Override the request body for the Klarna Checkout API.
	 *
	 * @param array    $args The request arguments.
	 * @param int|null $order_id The WooCommerce order ID.
	 *
	 * @return array
	 */
	public function override_request_body( $args, $order_id ) {
		// If we have an order id, then ignore the request, since its either a redirect or pay for order flow order.
		if ( $order_id ) {
			return $args;
		}

		$this->override_options( $args );
		$this->set_merchant_data( $args );

		$draft_order_id = WC()->session->get( 'store_api_draft_order', null );
		if ( ! $draft_order_id ) {
			return $args;
		}

		$draft_order = wc_get_order( $draft_order_id );

		if ( ! $draft_order ) {
			return $args;
		}

		$this->override_merchant_urls( $args, $draft_order );
		$this->set_merchant_reference( $args, $draft_order );

		return $args;
	}

	/**
	 * Override the options for the Klarna Checkout API.
	 *
	 * @param array $args The request arguments.
	 *
	 * @return void
	 */
	private function override_options( &$args ) {
		// Unset the option for the frontend validation, and set the callback url for validation instead.
		$args['options']['require_client_validation']                   = false;
		$args['options']['require_client_validation_callback_response'] = false;
		$args['options']['require_validate_callback_success']           = true;
	}

	/**
	 * Override the merchant URLs for the Klarna Checkout API.
	 *
	 * @param array    $args The request arguments.
	 * @param \WC_Order $draft_order The WooCommerce order.
	 *
	 * @return void
	 */
	private function override_merchant_urls( &$args, $draft_order ) {
		$args['merchant_urls'] = KCO_WC()->merchant_urls->get_urls( $draft_order->get_id() );

		// Set the validation callback URL for the block checkout.
		$args['merchant_urls']['validation'] = add_query_arg(
			array(
				'kco_validation' => 'yes',
				'kco_order_id'   => '{checkout.order.id}',
			),
			site_url( '/wc-api/kco-block-validation' )
		);
	}

	/**
	 * Set the merchant reference for the Klarna Checkout API.
	 *
	 * @param array    $args The request arguments.
	 * @param \WC_Order $draft_order The WooCommerce order.
	 *
	 * @return void
	 */
	private function set_merchant_reference( &$args, $draft_order ) {
		$args['merchant_reference1'] = $draft_order->get_order_number();
		$args['merchant_reference2'] = $draft_order->get_id();
	}

	/**
	 * Create a cart token for the current user.
	 *
	 * @return string
	 */
	private function create_cart_token() {
		// Create a cart token for the Klarna order.
		$cart_token = JsonWebToken::create(
			array(
				'user_id' => wc()->session->get_customer_id(),
				'exp'     => time() + intval( apply_filters( 'wc_session_expiration', DAY_IN_SECONDS * 2 ) ),
				'iss'     => 'wc/store/v1',
			),
			'@' . wp_salt()
		);

		return $cart_token;
	}

	/**
	 * Set the merchant data for the Klarna Checkout API.
	 *
	 * @param array $args The request arguments.
	 *
	 * @return void
	 */
	private function set_merchant_data( &$args ) {
		$merchant_data = json_decode( $args['merchant_data'], true ) ?? array();

		// Add the current cart hashes to the Klarna order merchant data object.
		// @see https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/StoreApi/Utilities/CartController.php#L763-L769.
		$cart_controller = new \Automattic\WooCommerce\StoreApi\Utilities\CartController();
		$cart_hashes     = $cart_controller->get_cart_hashes();

		$merchant_data['wc_cart_hash']     = $cart_hashes['line_items'] ?? '';
		$merchant_data['wc_shipping_hash'] = $cart_hashes['shipping'] ?? '';
		$merchant_data['wc_fees_hash']     = $cart_hashes['fees'] ?? '';
		$merchant_data['wc_coupons_hash']  = $cart_hashes['coupons'] ?? '';
		$merchant_data['wc_taxes_hash']    = $cart_hashes['taxes'] ?? '';

		$cart_token = WC()->session->get( 'kco_wc_cart_token', $this->create_cart_token() );

		$merchant_data['wc_cart_token']       = $cart_token;
		$merchant_data['wc_nonce']            = wp_create_nonce( 'wc_store_api' );
		$merchant_data['wp_logged_in_cookie'] = is_user_logged_in() ? $_COOKIE[ LOGGED_IN_COOKIE ] ?? null : null; // phpcs:ignore

		$args['merchant_data'] = wp_json_encode( $merchant_data );

		// Set the cart token in the session for later use to prevent the update request to Klarna from triggering on each call.
		WC()->session->set( 'kco_wc_cart_token', $cart_token );
	}
}
