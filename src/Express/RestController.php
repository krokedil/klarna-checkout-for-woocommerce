<?php
namespace Krokedil\KustomCheckout\Express;

use Exception;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST controller class.
 *
 * The endpoint the express button's createOrder hook posts to.
 */
class RestController {
	/**
	 * The REST namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'kco-express/v1';

	/**
	 * The nonce action the frontend sends in the X-KCO-Express-Nonce header.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'kco_express';

	/**
	 * Register the routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/order',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_order' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'context'      => array(
						'type'     => 'string',
						'enum'     => array( Express::CONTEXT_CART, Express::CONTEXT_PRODUCT ),
						'required' => true,
					),
					'product_id'   => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'variation_id' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'quantity'     => array(
						'type'    => 'number',
						'minimum' => 1,
						'default' => 1,
					),
					'variation'    => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			)
		);
	}

	/**
	 * The URL of the create order route.
	 *
	 * @return string
	 */
	public static function get_url() {
		return rest_url( self::NAMESPACE . '/order' );
	}

	/**
	 * Load the shopper's session, then verify the nonce, which WooCommerce ties to that session for guests.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return bool
	 */
	public function check_permission( $request ) {
		if ( ! Express::is_available() ) {
			return false;
		}

		wc_load_cart();

		return (bool) wp_verify_nonce( $request->get_header( 'X-KCO-Express-Nonce' ) ?? '', self::NONCE_ACTION );
	}

	/**
	 * Create the Kustom order and return its id.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function create_order( $request ) {
		$context = $request->get_param( 'context' );

		// The session cookie must exist for the confirmation to find this order, also for a guest with an empty cart.
		if ( WC()->session instanceof \WC_Session_Handler && ! WC()->session->has_session() && ! headers_sent() ) {
			WC()->session->set_customer_session_cookie( true );
		}

		try {
			$creator = new OrderCreator();

			if ( Express::CONTEXT_PRODUCT === $context ) {
				$klarna_order_id = $creator->create_for_product(
					absint( $request->get_param( 'product_id' ) ),
					absint( $request->get_param( 'variation_id' ) ),
					wc_stock_amount( $request->get_param( 'quantity' ) ),
					$this->sanitize_variation( $request->get_param( 'variation' ) )
				);
			} else {
				$klarna_order_id = $creator->create_from_cart();
			}
		} catch ( Exception $e ) {
			\KCO_Logger::log( "[Express] Could not create the Kustom order ({$context}): " . $e->getMessage() );

			return new WP_REST_Response(
				array(
					'message' => __( 'Could not start express checkout, please try again or use checkout.', 'klarna-checkout-for-woocommerce' ),
				),
				400
			);
		}

		return new WP_REST_Response( array( 'order_id' => $klarna_order_id ), 200 );
	}

	/**
	 * Keep only attribute_* keys, sanitised the way the WooCommerce add to cart form handler does.
	 *
	 * @param mixed $variation The variation attributes from the request.
	 * @return array
	 */
	private function sanitize_variation( $variation ) {
		$sanitized = array();
		if ( ! is_array( $variation ) ) {
			return $sanitized;
		}

		foreach ( $variation as $key => $value ) {
			$key = sanitize_title( wp_unslash( (string) $key ) );
			if ( 0 !== strpos( $key, 'attribute_' ) || ! is_scalar( $value ) ) {
				continue;
			}

			$sanitized[ $key ] = wc_clean( wp_unslash( (string) $value ) );
		}

		return $sanitized;
	}
}
