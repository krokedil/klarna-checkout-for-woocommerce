<?php
namespace Krokedil\KustomCheckout\OrderManagement\Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for all request classes.
 */
abstract class RequestGet extends Request {
	/**
	 * Class constructor.
	 *
	 * @param OrderManagement $order_management The order management instance.
	 * @param array           $arguments The request arguments.
	 */
	public function __construct( $order_management, $arguments ) {
		parent::__construct( $order_management, $arguments );
		$this->method = 'GET';
	}
}
