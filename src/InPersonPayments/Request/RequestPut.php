<?php
namespace Krokedil\KustomCheckout\InPersonPayments\Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PUT request class for In-Person Payments.
 */
abstract class RequestPut extends Request {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->method = 'PUT';
	}
}
