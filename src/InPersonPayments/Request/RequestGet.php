<?php
namespace Krokedil\KustomCheckout\InPersonPayments\Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET request class for In-Person Payments.
 */
abstract class RequestGet extends Request {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->method = 'GET';
	}
}
