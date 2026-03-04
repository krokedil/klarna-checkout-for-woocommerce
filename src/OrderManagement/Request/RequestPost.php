<?php
namespace Krokedil\KlarnaOrderManagement\Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POST request class
 */
abstract class RequestPost extends Request {
	/**
	 * Class constructor.
	 *
	 * @param KlarnaOrderManagement $order_management The order management instance.
	 * @param array                 $arguments The request arguments.
	 */
	public function __construct( $order_management, $arguments = array() ) {
		parent::__construct( $order_management, $arguments );
		$this->method = 'POST';
	}
}
