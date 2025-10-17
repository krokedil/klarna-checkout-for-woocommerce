<?php
namespace Krokedil\KustomCheckout\Blocks\Api\Controllers;

use Krokedil\KustomCheckout\Blocks\OrderValidation;

defined( 'ABSPATH' ) || exit;

/**
 * Class OrderController
 */
class OrderController extends Controller {
	/**
	 * The path of the controller.
	 *
	 * @var string
	 */
	protected $path = 'order';
	/**
	 * Register the routes for the controller.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Register the create session path.
		register_rest_route(
			$this->namespace,
			$this->get_request_path( self::get_validate_path() ),
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'validate' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Validate the order Klarna order.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function validate( $request ) {
		try {
			// Get the Klarna order id from the request.
			$klarna_order_id = $request->get_param( 'id' );

			// Validate the Klarna order.
			OrderValidation::validate_kco_order( $klarna_order_id );

			// Return a successful response with a 200 status code and empty body.
			return new \WP_REST_Response( null, 200 );
		} catch ( \WP_Exception $e ) {
			// Handle any exceptions that occur during validation that we are throwing from the plugin.
			return self::get_error_response( $e );
		} catch ( \Exception $e ) {
			// Handle any other exceptions that occur during validation, that should return a generic error message.
			return self::get_error_response( $e, true );
		}
	}

	/**
	 * Get the request endpoint for the order validation request.
	 *
	 * @return string
	 */
	public static function get_validate_endpoint() {
		return rest_url( 'kco-block/order/{checkout.order.id}/validate' );
	}

	/**
	 * Return the path for the order validation endpoint.
	 *
	 * @return string
	 */
	public static function get_validate_path() {
		return '(?P<id>[^/]+)/validate';
	}

	/**
	 * Return a WP_REST_Response for an error.
	 *
	 * @param \Exception $e The exception that was thrown.
	 * @param bool       $generic_message If true, use a generic error message.
	 *
	 * @return \WP_REST_Response
	 */
	private static function get_error_response( $e, $generic_message = false ) {
		return new \WP_REST_Response(
			array(
				'error_type' => 'approval_failed',
				'error_text' => $generic_message ? __( 'Failed to validate your order, please try again.', 'klarna-checkout-for-woocommerce' ) : $e->getMessage(),
			),
			400
		);
	}
}
