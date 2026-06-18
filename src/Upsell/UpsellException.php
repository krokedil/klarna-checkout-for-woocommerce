<?php
namespace Krokedil\KustomCheckout\Upsell;

/**
 * Exception for upsell validation failures, carrying an HTTP status code.
 */
class UpsellException extends \Exception {
	/**
	 * The HTTP status code for the error response.
	 *
	 * @var int
	 */
	private $status_code;

	/**
	 * UpsellException constructor.
	 *
	 * @param string     $message     The error message.
	 * @param int        $status_code The HTTP status code.
	 * @param \Throwable $previous    The previous exception.
	 */
	public function __construct( string $message, int $status_code = 400, ?\Throwable $previous = null ) {
		parent::__construct( $message, 0, $previous );
		$this->status_code = $status_code;
	}

	/**
	 * Get the HTTP status code.
	 *
	 * @return int
	 */
	public function get_status_code() {
		return $this->status_code;
	}
}
