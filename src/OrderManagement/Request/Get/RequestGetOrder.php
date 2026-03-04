<?php
namespace Krokedil\KlarnaOrderManagement\Request\Get;

use Krokedil\KlarnaOrderManagement\Request\RequestGet;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get Order request class for orders
 */
class RequestGetOrder extends RequestGet {
	/**
	 * Class constructor.
	 *
	 * @param KlarnaOrderManagement $order_management The order management instance.
	 * @param array                 $arguments The request arguments.
	 */
	public function __construct( $order_management, $arguments ) {
		parent::__construct( $order_management, $arguments );
		$this->log_title = 'Retrieve Klarna order';
	}

	/**
	 * Get the request URL for this type of request.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . 'ordermanagement/v1/orders/' . $this->klarna_order_id;
	}
}
