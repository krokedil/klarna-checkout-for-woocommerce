<?php
/**
 * PATCH request class
 *
 * @package OrderManagement/Classes/Requests
 */

defined( 'ABSPATH' ) || exit;

/**
 * PATCH request class for order line updates.
 */
class RequestPatchUpdate extends RequestPatch {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Update Klarna order lines';
	}

	/**
	 * Get the request URL for this type of request.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . 'ordermanagement/v1/orders/' . $this->klarna_order_id . '/authorization';
	}

	/**
	 * Build the request body for this request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$lines_processor = new OrderManagement_Order_Lines( $this->order_id );
		$data            = $lines_processor->order_lines();

		return apply_filters( 'kom_order_update_args', $data, $this->order_id );
	}
}
