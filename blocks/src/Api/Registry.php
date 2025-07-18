<?php
namespace Krokedil\KustomCheckout\Blocks\Api;
use Krokedil\KustomCheckout\Blocks\Api\Controllers\Controller;
use Krokedil\KustomCheckout\Blocks\Api\Controllers\OrderController;

defined( 'ABSPATH' ) || exit;

/**
 * Class Registry
 */
class Registry {
	/**
	 * The list of controllers.
	 *
	 * @var Controller[]
	 */
	protected $controllers = array();

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_controllers' ) );
		$this->init();
	}

	/**
	 * Add the API controllers to be registered with WordPress REST API.
	 *
	 * @return void
	 */
	public function init() {
		$this->add_controller( new OrderController() );
	}

	/**
	 * Add a controller to the registry.
	 *
	 * @param Controller $controller The controller to add.
	 *
	 * @return void
	 */
	public function add_controller( $controller ) {
		$this->controllers[ get_class( $controller ) ] = $controller;
	}

	/**
	 * Register the controllers with WordPress REST API.
	 *
	 * @return void
	 */
	public function register_controllers() {
		foreach ( $this->controllers as $controller ) {
			$controller->register_routes();
		}
	}
}
