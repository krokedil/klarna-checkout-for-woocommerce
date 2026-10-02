<?php
namespace Krokedil\KustomCheckout\ShippingAssistant\API;

use Krokedil\KustomCheckout\ShippingAssistant\API\Controllers\{BaseController, ShippingOptionUpdateController};

\defined( 'ABSPATH' ) || exit;

/**
 * Class ApiRegistry.
 *
 * Register the API controllers for the Kustom Shipping Assistant module with WordPress.
 */
class ApiRegistry {
	/**
	 * The list of controllers.
	 *
	 * @var BaseController[]
	 */
	protected $controllers = array();

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize the API controllers and models.
	 *
	 * @return void
	 */
	public function init() {
		$this->register_controller( new ShippingOptionUpdateController() );

		add_action( 'rest_api_init', array( $this, 'register_controller_routes' ) );
	}

	/**
	 * Register the controllers.
	 *
	 * @return void
	 */
	public function register_controller_routes() {
		foreach ( $this->controllers as $controller ) {
			$controller->register_routes();
		}
	}

	/**
	 * Register a controller.
	 *
	 * @param BaseController $controller The controller to register.
	 *
	 * @return void
	 */
	public function register_controller( BaseController $controller ) {
		$this->controllers[ \get_class( $controller ) ] = $controller;
	}

	/**
	 * Get the request path for a specific controller.
	 *
	 * @param string $controller The controller class name to get the path for.
	 * @param string $endpoint The endpoint to get the path for.
	 *
	 * @return string
	 */
	public function get_request_path( string $controller, string $endpoint = '' ) {
		if ( isset( $this->controllers[ $controller ] ) ) {
			$path    = trim( $this->controllers[ $controller ]->get_request_path(), '/' );
			$blog_id = get_current_blog_id();
			return get_rest_url( empty( $blog_id ) ? null : $blog_id, "kustom/shipping-service/$path/$endpoint" );
		}

		return '';
	}
}
