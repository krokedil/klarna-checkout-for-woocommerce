<?php
namespace Krokedil\KustomCheckout\Express\API\Controllers;

use Krokedil\KustomCheckout\Blocks\Api\Controllers\OrderController as BlocksOrderController;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

\defined( 'ABSPATH' ) || exit;

/**
 * Class CallbackController.
 *
 * The push and validation callbacks of express orders.
 */
class CallbackController extends BaseController {
	/**
	 * The route pattern of the Kustom order id in the push and validation routes.
	 *
	 * @var string
	 */
	const ID_PATTERN = '(?P<id>[\w-]+)';

	/**
	 * Register the routes for the controller.
	 *
	 * Kustom calls these server to server, and the Kustom order they name is read back from Kustom before anything is done.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			$this->get_request_path( 'push/' . self::ID_PATTERN ),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'push' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			$this->get_request_path( 'validate/' . self::ID_PATTERN ),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( new BlocksOrderController(), 'validate' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle Kustom's push notification for an express order.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function push( $request ) {
		\KCO_API_Callbacks::get_instance()->process_push( $request->get_url_params()['id'] );

		return new WP_REST_Response( null, 200 );
	}
}
