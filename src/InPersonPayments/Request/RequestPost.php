<?php
namespace Krokedil\KustomCheckout\InPersonPayments\Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POST request class for In-Person Payments.
 */
abstract class RequestPost extends Request {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->method = 'POST';
	}
}
