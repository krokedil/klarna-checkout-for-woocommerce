<?php
/**
 * GET request class
 *
 * @package WC_Klarna_Order_Management/Classes/Requests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Base class for all request classes.
 */
abstract class KOM_Request_Get extends KOM_Request {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->method = 'GET';
	}
}

