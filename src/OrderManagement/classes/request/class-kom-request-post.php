<?php
/**
 * POST request class
 *
 * @package WC_Klarna_Order_Management/Classes/Requests
 */

defined( 'ABSPATH' ) || exit;

/**
 * POST request class
 */
abstract class KOM_Request_Post extends KOM_Request {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->method = 'POST';
	}
}

