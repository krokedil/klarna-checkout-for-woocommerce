<?php
namespace Krokedil\KustomCheckout\InPersonPayments\Request\Post;

use Krokedil\KustomCheckout\InPersonPayments\Request\RequestPost;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POST request class for creating a device enrollment code.
 */
class RequestPostEnrollment extends RequestPost {

	/**
	 * The TTLs the API accepts. Anything longer than two hours returns a
	 * UUID-shaped code rather than one that can be typed into a phone.
	 *
	 * @var string[]
	 */
	public const TTLS = array( 'TWO_HOURS', 'TWENTY_FOUR_HOURS', 'FORTY_EIGHT_HOURS', 'SEVENTY_TWO_HOURS' );

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments: 'ttl'.
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->log_title = 'Create Kustom IPP enrollment';
	}

	/**
	 * Get the request URL for this type of request.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . 'ipp/v1/enrollments';
	}

	/**
	 * Build the request body.
	 *
	 * Kustom rejects the call without a location unless the merchant account has
	 * exactly one, so the configured id is sent when set.
	 *
	 * @return array
	 */
	protected function get_body() {
		$body = array( 'ttl' => $this->get_ttl() );

		$location_id = trim( $this->settings['ipp_location_id'] ?? '' );
		if ( '' !== $location_id ) {
			$body['location_id'] = $location_id;
		}

		return $body;
	}

	/**
	 * The requested TTL, falling back to the only one that yields a typeable code.
	 *
	 * @return string
	 */
	private function get_ttl() {
		$ttl = $this->arguments['ttl'] ?? '';

		return in_array( $ttl, self::TTLS, true ) ? $ttl : 'TWO_HOURS';
	}
}
