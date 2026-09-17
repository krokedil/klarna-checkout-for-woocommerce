<?php
namespace Krokedil\KustomCheckout\InPersonPayments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The REST routes the waiting screen polls and Kustom delivers webhooks to.
 */
class Rest {

	/**
	 * The REST namespace the routes live under.
	 *
	 * @var string
	 */
	public const REST_NAMESPACE = 'kco/v1';

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/ipp/sessions/(?P<order_id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_session_status' ),
				'permission_callback' => array( $this, 'can_take_payment' ),
				'args'                => self::order_args(),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/ipp/sessions/(?P<order_id>[\d]+)/cancel',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel_session' ),
				'permission_callback' => array( $this, 'can_take_payment' ),
				'args'                => self::order_args(),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/ipp/webhook',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( Webhook::class, 'handle' ),
				'permission_callback' => array( Webhook::class, 'verify' ),
			)
		);
	}

	/**
	 * The URL Kustom delivers webhooks to, or an empty string before WordPress can
	 * build one.
	 *
	 * The settings defaults are read on `plugins_loaded`, which is before WordPress
	 * creates the rewrite component `rest_url()` needs, and reading them must not be
	 * fatal.
	 *
	 * @return string
	 */
	public static function webhook_url() {
		if ( ! isset( $GLOBALS['wp_rewrite'] ) ) {
			return '';
		}

		return rest_url( self::REST_NAMESPACE . '/ipp/webhook' );
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
	 * Only the salesperson who placed the order may drive its session.
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

		return $this->answer( $order, SessionHandler::refresh( $order ) );
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

		return $this->answer( $order, SessionHandler::cancel( $order ) );
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
	 * @param \WC_Order       $order The WooCommerce order.
	 * @param array|\WP_Error $outcome The outcome from the session handler.
	 * @return \WP_REST_Response
	 */
	private function answer( $order, $outcome ) {
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
			wc_add_notice( $outcome['message'], 'error' );

			$response['redirect'] = wc_get_checkout_url();
		}

		return new \WP_REST_Response( $response, 200 );
	}

	/**
	 * Put the order's products back in the cart, so the salesperson can try again
	 * without ringing the sale up a second time.
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
			WC()->cart->add_to_cart( $item->get_product_id(), $item->get_quantity(), $item->get_variation_id() );
		}
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
