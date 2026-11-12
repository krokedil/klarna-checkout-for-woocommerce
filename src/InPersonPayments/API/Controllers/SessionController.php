<?php
namespace Krokedil\KustomCheckout\InPersonPayments\API\Controllers;

use Krokedil\KustomCheckout\InPersonPayments\{Gateway, SessionHandler};

\defined( 'ABSPATH' ) || exit;

/**
 * Class SessionController.
 *
 * The routes the waiting screen polls and cancels an in-person payment session through.
 */
class SessionController extends BaseController {
	/**
	 * The path of the controller.
	 *
	 * @var string
	 */
	protected $path = 'ipp/sessions';

	/**
	 * The query argument that carries a failed session's status back to the block checkout.
	 *
	 * @var string
	 */
	public const FAILED_ARG = 'kco_ipp_failed';

	/**
	 * Register the routes for the controller.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			$this->get_request_path( '(?P<order_id>[\d]+)' ),
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_session_status' ),
				'permission_callback' => array( $this, 'can_take_payment' ),
				'args'                => self::order_args(),
			)
		);

		register_rest_route(
			$this->namespace,
			$this->get_request_path( '(?P<order_id>[\d]+)/cancel' ),
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel_session' ),
				'permission_callback' => array( $this, 'can_take_payment' ),
				'args'                => self::order_args(),
			)
		);
	}

	/**
	 * The arguments both order routes take.
	 *
	 * @return array
	 */
	private static function order_args() {
		return array(
			'order_id' => array(
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
			'key'      => array(
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Staff with the in-person capability may drive a session, given the order's key. The
	 * key is not tied to the salesperson who placed the order on purpose, so a colleague
	 * can take over a waiting screen; staff with the capability can see the key anyway.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return bool|\WP_Error
	 */
	public function can_take_payment( $request ) {
		if ( ! current_user_can( Gateway::required_capability() ) ) {
			return new \WP_Error( 'kco_ipp_forbidden', __( 'You are not allowed to do this.', 'klarna-checkout-for-woocommerce' ), array( 'status' => rest_authorization_required_code() ) );
		}

		$order = $this->get_order( $request );

		return null === $order
			? new \WP_Error( 'kco_ipp_no_order', __( 'No in-person payment is waiting on this order.', 'klarna-checkout-for-woocommerce' ), array( 'status' => 404 ) )
			: true;
	}

	/**
	 * Report where the session has got to, applying it to the order on the way.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function get_session_status( $request ) {
		$order = $this->get_order( $request );
		$this->load_cart();

		return $this->answer( $order, SessionHandler::refresh( $order ), $request );
	}

	/**
	 * Ask the device to give up on the session.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function cancel_session( $request ) {
		$order = $this->get_order( $request );
		$this->load_cart();

		return $this->answer( $order, SessionHandler::cancel( $order ), $request );
	}

	/**
	 * WooCommerce loads no cart or session for a REST request. The poll is same-origin,
	 * so the session cookie is there and this picks up the salesperson's own cart.
	 *
	 * @return void
	 */
	private function load_cart() {
		if ( WC()->cart instanceof \WC_Cart && WC()->session instanceof \WC_Session ) {
			return;
		}

		wc_load_cart();
	}

	/**
	 * Turn an outcome into what the waiting screen does next.
	 *
	 * @param \WC_Order        $order The WooCommerce order.
	 * @param array|\WP_Error  $outcome The outcome from the session handler.
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	private function answer( $order, $outcome, $request ) {
		if ( is_wp_error( $outcome ) ) {
			// Kustom being briefly unreachable is not a failed sale: keep waiting.
			return new \WP_REST_Response(
				array(
					'state'   => SessionHandler::WAITING,
					'message' => $outcome->get_error_message(),
				),
				200
			);
		}

		$response = array(
			'state'   => $outcome['state'],
			'status'  => $outcome['status'],
			'message' => $outcome['message'],
		);

		if ( SessionHandler::PAID === $outcome['state'] ) {
			$response['redirect'] = $order->get_checkout_order_received_url();
		}

		if ( SessionHandler::FAILED === $outcome['state'] ) {
			$this->restore_cart( $order );

			$response['redirect'] = wc_get_checkout_url();

			// A session notice is printed, and so cleared, only by the shortcode checkout. Left in the session it makes the
			// Store API refuse the next sale, so the point of sale shows the message itself and the block checkout from the URL.
			if ( 'pos' === $request->get_param( 'context' ) ) {
				return new \WP_REST_Response( $response, 200 );
			}

			if ( 'store-api' === $order->get_created_via() ) {
				$response['redirect'] = add_query_arg( self::FAILED_ARG, rawurlencode( (string) $outcome['status'] ), $response['redirect'] );
			} else {
				wc_add_notice( $outcome['message'], 'error' );
			}
		}

		return new \WP_REST_Response( $response, 200 );
	}

	/**
	 * Put the order's products, chosen variations and coupons back in the cart, so the
	 * salesperson can try again without ringing the sale up a second time.
	 *
	 * Prices come from the catalogue again, as with WooCommerce's own "order again".
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @return void
	 */
	private function restore_cart( $order ) {
		if ( ! WC()->cart instanceof \WC_Cart ) {
			return;
		}

		WC()->cart->empty_cart();

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			// A variation's attributes are stored as item meta, the way WooCommerce's "order again" reads them back.
			$variation = array();
			foreach ( $item->get_meta_data() as $meta ) {
				if ( taxonomy_is_product_attribute( $meta->key ) ) {
					$variation[ 'attribute_' . sanitize_title( $meta->key ) ] = sanitize_title( $meta->value );
				} elseif ( meta_is_product_attribute( $meta->key, $meta->value, $item->get_product_id() ) ) {
					$variation[ 'attribute_' . sanitize_title( $meta->key ) ] = html_entity_decode( wc_clean( $meta->value ), ENT_QUOTES, get_bloginfo( 'charset' ) );
				}
			}

			WC()->cart->add_to_cart( $item->get_product_id(), $item->get_quantity(), $item->get_variation_id(), $variation );
		}

		foreach ( $order->get_coupon_codes() as $code ) {
			WC()->cart->apply_coupon( $code );
		}

		// The restore's own notices ("coupon applied", or a product gone out of stock) would make the Store API refuse the next sale.
		wc_clear_notices();
	}

	/**
	 * The order a request is about, or null when it is not one waiting for a device.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WC_Order|null
	 */
	private function get_order( $request ) {
		$order = wc_get_order( absint( $request->get_param( 'order_id' ) ) );

		if ( ! $order || Gateway::ID !== $order->get_payment_method() ) {
			return null;
		}

		if ( ! hash_equals( $order->get_order_key(), (string) $request->get_param( 'key' ) ) ) {
			return null;
		}

		return empty( $order->get_meta( Gateway::SESSION_META ) ) ? null : $order;
	}
}
