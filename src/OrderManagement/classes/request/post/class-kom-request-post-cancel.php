<?php
/**
 * POST request class for order cancellation
 *
 * @package WC_Klarna_Order_Management/Classes/Requests
 */

defined( 'ABSPATH' ) || exit;

/**
 * POST request class for order cancellation
 */
class KOM_Request_Post_Cancel extends KOM_Request_Post {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Cancel Klarna order';
	}

	/**
	 * Get the request URL for this type of request.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . 'ordermanagement/v1/orders/' . $this->klarna_order_id . '/cancel';
	}
}
