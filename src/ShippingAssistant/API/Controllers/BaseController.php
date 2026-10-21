<?php
namespace Krokedil\KustomCheckout\ShippingAssistant\API\Controllers;

\defined( 'ABSPATH' ) || exit;

/**
 * Class BaseController.
 *
 * Base class for REST API controllers in the Kustom Shipping Assistant module.
 */
abstract class BaseController {
	/**
	 * The namespace of the controller.
	 *
	 * @var string
	 */
	protected $namespace = 'kustom/shipping-service';

	/**
	 * The version of the controller.
	 *
	 * @var string
	 */
	protected $version = 'v1';

	/**
	 * The path of the controller.
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * Get the base path for the controller.
	 *
	 * @return string
	 */
	protected function get_base_path() {
		// Combine the version and path to create the base path, ensuring that the path doesn't start or end with a slash.
		return trim( "{$this->version}/{$this->path}", '/' );
	}

	/**
	 * Get the request path for a specific endpoint.
	 *
	 * @param string $endpoint The endpoint to get the path for.
	 *
	 * @return string
	 */
	public function get_request_path( $endpoint = '' ) {
		$base_path = $this->get_base_path();
		$endpoint  = trim( $endpoint, '/' );
		return "{$base_path}/{$endpoint}";
	}

	/**
	 * Register the routes for the objects of the controller.
	 *
	 * @return void
	 */
	abstract public function register_routes();
}
