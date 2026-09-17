<?php
namespace Krokedil\KustomCheckout\InPersonPayments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns an API failure into something the salesperson at the counter can act on.
 *
 * The provider's own wording is left to the order note and the log; the counter
 * needs whose problem it is and whether trying again will help.
 */
class ErrorMessage {

	/**
	 * A sentence the salesperson can act on.
	 *
	 * @param \WP_Error $error The error the request returned.
	 * @return string
	 */
	public static function for_merchant( $error ) {
		$code = $error->get_error_code();

		if ( 'missing_credentials' === $code ) {
			$message = __( 'This store is not connected to Kustom yet. Add the API credentials in the Kustom Checkout settings.', 'klarna-checkout-for-woocommerce' );
		} elseif ( ! is_numeric( $code ) ) {
			// The request never reached Kustom: a timeout, DNS or TLS problem.
			$message = __( 'The store could not reach Kustom. Check the connection and take the payment again.', 'klarna-checkout-for-woocommerce' );
		} else {
			$message = self::for_status( (int) $code );
		}

		/**
		 * The sentence the salesperson sees when a session could not be started.
		 *
		 * @since 2.22.0
		 *
		 * @param string    $message The merchant-facing sentence.
		 * @param \WP_Error $error The error the request returned; its code is the HTTP status.
		 */
		return apply_filters( 'kco_ipp_error_message', $message, $error );
	}

	/**
	 * The sentence for an HTTP status Kustom answered with.
	 *
	 * Only what the create session endpoint documents gets wording of its own: 400
	 * invalid input, 401 authentication failure, 404 device or resource missing.
	 *
	 * @param int $status The HTTP status code.
	 * @return string
	 */
	private static function for_status( $status ) {
		if ( 400 === $status ) {
			return __( 'Kustom turned this sale down. Check the order details and try the payment again.', 'klarna-checkout-for-woocommerce' );
		}

		if ( 401 === $status ) {
			return __( 'Kustom did not accept this store\'s credentials. Check the API credentials in the Kustom Checkout settings.', 'klarna-checkout-for-woocommerce' );
		}

		if ( 404 === $status ) {
			return __( 'Kustom does not recognize the chosen POS device. Pick another device, or pair this one again.', 'klarna-checkout-for-woocommerce' );
		}

		return __( 'The payment could not be started on the device. Please try the payment again.', 'klarna-checkout-for-woocommerce' );
	}

	/**
	 * Where the detail the sentence leaves out can be found.
	 *
	 * @return string
	 */
	public static function where_details_are() {
		return __( 'The details are in the order notes and the Kustom Checkout log.', 'klarna-checkout-for-woocommerce' );
	}
}
