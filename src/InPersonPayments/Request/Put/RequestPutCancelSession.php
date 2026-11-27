<?php
namespace Krokedil\KustomCheckout\InPersonPayments\Request\Put;

use Krokedil\KustomCheckout\InPersonPayments\Request\RequestPut;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PUT request class for cancelling a payment session on the device.
 */
class RequestPutCancelSession extends RequestPut {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments: 'session_id'.
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->log_title = 'Cancel Kustom IPP session';
	}

	/**
	 * Get the request URL for this type of request.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . 'ipp/v1/sessions/' . rawurlencode( $this->arguments['session_id'] ?? '' ) . '/cancel';
	}
}
