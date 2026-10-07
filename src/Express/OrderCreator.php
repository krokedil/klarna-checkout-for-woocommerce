<?php
namespace Krokedil\KustomCheckout\Express;

use Krokedil\KustomCheckout\Blocks\Overrides;
use Krokedil\KustomCheckout\ShippingAssistant\RequestModifier;
use Krokedil\KustomCheckout\Utility\BlocksUtility;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order creator class.
 *
 * Creates the Kustom order behind an express button's createOrder hook, with the same payload and
 * validation callback as the block checkout.
 */
class OrderCreator {
	/**
	 * The session key holding the Kustom order id of the latest express purchase, read on confirmation.
	 *
	 * @var string
	 */
	const SESSION_KEY = 'kco_express_order_id';

	/**
	 * The shopper's session key holding the product express session to delete once its purchase is confirmed.
	 *
	 * @var string
	 */
	const EXPRESS_SESSION_KEY = 'kco_express_session';

	/**
	 * The express context being created, read by the request filter.
	 *
	 * @var string
	 */
	private $context = '';

	/**
	 * Create a Kustom order from the shopper's cart.
	 *
	 * @return string The Kustom order id.
	 * @throws Exception If the cart is empty or Kustom refused the order.
	 */
	public function create_from_cart() {
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			throw new Exception( 'The cart is empty.' );
		}

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		return $this->remember( $this->request( Express::CONTEXT_CART ) );
	}

	/**
	 * Create a Kustom order for one product, from a separate express session.
	 *
	 * @param int   $product_id   The product ID.
	 * @param int   $variation_id The variation ID, or 0.
	 * @param int   $quantity     The quantity.
	 * @param array $variation    The variation attributes.
	 * @return string The Kustom order id.
	 * @throws Exception If the product cannot be bought or Kustom refused the order.
	 */
	public function create_for_product( $product_id, $variation_id, $quantity, $variation ) {
		$express_session = '';
		$klarna_order_id = ExpressSession::with_product_cart(
			$product_id,
			$variation_id,
			$quantity,
			$variation,
			function ( $token, $session_key ) use ( &$express_session ) {
				$express_session = $session_key;
				return $this->request( Express::CONTEXT_PRODUCT );
			}
		);

		WC()->session->set( self::EXPRESS_SESSION_KEY, $express_session );

		return $this->remember( $klarna_order_id );
	}

	/**
	 * Send the create request for the current WC()->cart.
	 *
	 * @param string $context The express context.
	 * @return string The Kustom order id.
	 * @throws Exception If Kustom refused the order.
	 */
	private function request( $context ) {
		$this->context = $context;

		// The block checkout already applies these overrides to every request when its page is in use.
		$overrides = BlocksUtility::is_checkout_block_enabled() ? null : new Overrides( false );
		if ( $overrides ) {
			add_filter( 'kco_wc_api_request_args', array( $overrides, 'override_request_body' ), 10, 2 );
		}
		add_filter( 'kco_wc_api_request_args', array( $this, 'add_express_data' ), 20 );

		try {
			$request  = new \KCO_Request_Create();
			$response = $request->request( null, 'embedded' );
		} finally {
			remove_filter( 'kco_wc_api_request_args', array( $this, 'add_express_data' ), 20 );
			if ( $overrides ) {
				remove_filter( 'kco_wc_api_request_args', array( $overrides, 'override_request_body' ), 10 );
			}
		}

		if ( is_wp_error( $response ) || empty( $response['order_id'] ) ) {
			$code = is_wp_error( $response ) ? $response->get_error_code() : 'missing_order_id';
			throw new Exception( esc_html( "Kustom did not create the express order ({$code})." ) );
		}

		return $response['order_id'];
	}

	/**
	 * Mark the Kustom order as an express purchase in its merchant data, and leave shipping to KSA in the sheet.
	 *
	 * KSA's request modifier can add back a shipping line from the iframe order's override data, which belongs to a
	 * different Kustom order, so every shipping line is removed here.
	 *
	 * @param array $args The request body.
	 * @return array
	 */
	public function add_express_data( $args ) {
		$args = RequestModifier::remove_shipping( $args );

		$merchant_data = json_decode( $args['merchant_data'] ?? '', true );
		$merchant_data = is_array( $merchant_data ) ? $merchant_data : array();

		$merchant_data['kco_express'] = $this->context;
		$args['merchant_data']        = wp_json_encode( $merchant_data );

		return $args;
	}

	/**
	 * Store the Kustom order id in the shopper's session, so the keyless confirmation URL is accepted.
	 *
	 * Kept out of kco_wc_order_id so an abandoned express order is never reused by the iframe checkout.
	 *
	 * @param string $klarna_order_id The Kustom order id.
	 * @return string
	 */
	private function remember( $klarna_order_id ) {
		WC()->session->set( self::SESSION_KEY, $klarna_order_id );

		return $klarna_order_id;
	}
}
