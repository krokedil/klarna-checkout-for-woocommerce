<?php
namespace Krokedil\KustomCheckout\InPersonPayments\Request\Put;

use Krokedil\KustomCheckout\InPersonPayments\Request\RequestPut;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PUT request class for updating a paired device.
 */
class RequestPutDevice extends RequestPut {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments: 'device_id', 'name', and the
	 *                         'metadata' and 'location_id' the device already has.
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->log_title = 'Update Kustom IPP device';
	}

	/**
	 * Get the request URL for this type of request.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . 'ipp/v1/devices/' . rawurlencode( $this->arguments['device_id'] ?? '' );
	}

	/**
	 * Build the request body.
	 *
	 * The endpoint replaces the device rather than patching it, so the metadata and
	 * location the device already carries are sent back untouched. Leaving them out
	 * would silently detach the device from its location.
	 *
	 * @return array
	 */
	protected function get_body() {
		$body = array( 'name' => trim( $this->arguments['name'] ?? '' ) );

		$metadata = $this->arguments['metadata'] ?? array();
		if ( ! empty( $metadata ) ) {
			$body['metadata'] = $metadata;
		}

		$location_id = trim( $this->arguments['location_id'] ?? '' );
		if ( '' !== $location_id ) {
			$body['location_id'] = $location_id;
		}

		return $body;
	}
}
