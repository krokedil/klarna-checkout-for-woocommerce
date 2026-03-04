<?php
namespace Krokedil\KlarnaOrderManagement\Request\Patch;

use Krokedil\KlarnaOrderManagement\OrderLines;
use Krokedil\KlarnaOrderManagement\Request\RequestPatch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PATCH request class for order line updates.
 */
class RequestPatchUpdate extends RequestPatch {
	/**
	 * Class constructor.
	 *
	 * @param KlarnaOrderManagement $order_management The order management instance.
	 * @param array                 $arguments The request arguments.
	 */
	public function __construct( $order_management, $arguments ) {
		parent::__construct( $order_management, $arguments );
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
		$lines_processor = new OrderLines( $this->order_id );
		$data            = $lines_processor->order_lines();

		return apply_filters( 'kom_order_update_args', $data, $this->order_id );
	}
}
