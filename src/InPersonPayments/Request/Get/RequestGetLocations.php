<?php
namespace Krokedil\KustomCheckout\InPersonPayments\Request\Get;

use Krokedil\KustomCheckout\InPersonPayments\Request\RequestGet;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET request class for listing the merchant's locations.
 */
class RequestGetLocations extends RequestGet {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->log_title = 'Retrieve Kustom IPP locations';
	}

	/**
	 * This endpoint identifies the merchant by header rather than by the credentials alone.
	 *
	 * @return bool
	 */
	protected function needs_merchant_id_header() {
		return true;
	}

	/**
	 * Get the request URL for this type of request.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		/**
		 * How many locations are read in one page. Only the first page is read, so a
		 * merchant with more locations than this raises it.
		 *
		 * @since 2.22.0
		 *
		 * @param int $page_size The page size. Default 100.
		 */
		$page_size = apply_filters( 'kco_ipp_location_page_size', 100 );

		return add_query_arg( 'page_size', $page_size, $this->get_api_url_base() . 'ipp/v1/locations' );
	}
}
