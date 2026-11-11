<?php
namespace Krokedil\KustomCheckout\InPersonPayments\API\Controllers;

use Krokedil\KustomCheckout\InPersonPayments\Webhook;

\defined( 'ABSPATH' ) || exit;

/**
 * Class WebhookController.
 *
 * The route Kustom delivers in-person payment session webhooks to.
 */
class WebhookController extends BaseController {
	/**
	 * The path of the controller.
	 *
	 * @var string
	 */
	protected $path = 'ipp/webhook';

	/**
	 * Register the routes for the controller.
	 *
	 * Kustom calls this server to server; Webhook::verify() checks the signature first.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			$this->get_request_path(),
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( Webhook::class, 'handle' ),
				'permission_callback' => array( Webhook::class, 'verify' ),
			)
		);
	}
}
