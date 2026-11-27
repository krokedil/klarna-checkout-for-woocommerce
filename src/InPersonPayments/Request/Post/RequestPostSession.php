<?php
namespace Krokedil\KustomCheckout\InPersonPayments\Request\Post;

use Krokedil\KustomCheckout\InPersonPayments\Request\RequestPost;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POST request class for dispatching a payment session to a device.
 */
class RequestPostSession extends RequestPost {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments: 'payload'.
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->log_title = 'Create Kustom IPP session';
	}

	/**
	 * Get the request URL for this type of request.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . 'ipp/v1/sessions';
	}

	/**
	 * Build the request body.
	 *
	 * @return array
	 */
	protected function get_body() {
		return $this->arguments['payload'] ?? array();
	}
}
