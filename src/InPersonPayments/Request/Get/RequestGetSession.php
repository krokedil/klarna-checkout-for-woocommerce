<?php
namespace Krokedil\KustomCheckout\InPersonPayments\Request\Get;

use Krokedil\KustomCheckout\InPersonPayments\Request\RequestGet;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET request class for reading a payment session.
 */
class RequestGetSession extends RequestGet {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments: 'session_id'.
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->log_title = 'Retrieve Kustom IPP session';
	}

	/**
	 * Get the request URL for this type of request.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . 'ipp/v1/sessions/' . rawurlencode( $this->arguments['session_id'] ?? '' );
	}
}
