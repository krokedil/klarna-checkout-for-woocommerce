<?php
/**
 * Logging class file.
 *
 * @package Klarna_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * KCO_Logger class.
 */
class KCO_Logger {
	/**
	 * Log message string
	 *
	 * @var $log
	 */
	public static $log;

	/**
	 * Logs an event.
	 *
	 * @param string $data The data string.
	 */
	public static function log( $data ) {
		$settings = get_option( 'woocommerce_kco_settings' );
		if ( 'yes' === $settings['logging'] ) {
			$message = self::format_data( $data );
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			self::$log->add( 'klarna-checkout-for-woocommerce', wp_json_encode( $message ) );
		}
	}
	/**
	 * Formats the log data to prevent json error.
	 *
	 * @param string $data Json string of data.
	 * @return array
	 */
	public static function format_data( $data ) {
		if ( isset( $data['request']['body'] ) ) {
			$request_body            = json_decode( $data['request']['body'], true );
			$data['request']['body'] = $request_body;
		}
		return $data;
	}
	/**
	 * Formats the log data to be logged.
	 *
	 * @param string $klarna_order_id The Klarna order id.
	 * @param string $method The method.
	 * @param string $title The title for the log.
	 * @param array  $request_args The request args.
	 * @param array  $response The response.
	 * @param string $code The status code.
	 * @return array
	 */
	public static function format_log( $klarna_order_id, $method, $title, $request_args, $response, $code ) {
		// Unset the snippet to prevent issues in the response.
		if ( isset( $response['html_snippet'] ) ) {
			unset( $response['html_snippet'] );
		}
		// Unset the snippet to prevent issues in the request body.
		if ( isset( $request_args['body'] ) ) {
			$request_body = json_decode( $request_args['body'], true );
			if ( isset( $request_body['html_snippet'] ) && $request_body['snippet'] ) {
				unset( $request_body['html_snippet'] );
				$request_args['body'] = wp_json_encode( $request_body );
			}
		}
		return array(
			'id'             => $klarna_order_id,
			'type'           => $method,
			'title'          => $title,
			'request'        => $request_args,
			'response'       => array(
				'body' => $response,
				'code' => $code,
			),
			'timestamp'      => date( 'Y-m-d H:i:s' ),
			'stack'          => self::get_stack(),
			'plugin_version' => KCO_WC_VERSION,
		);
	}

	/**
	 * Gets the stack for the request.
	 *
	 * @return array
	 */
	public static function get_stack() {
		$debug_data = debug_backtrace();
		$stack      = array();
		foreach ( $debug_data as $data ) {
			$extra_data = '';
			if ( ! in_array( $data['function'], array( 'get_stack', 'format_log' ) ) ) {
				if ( in_array( $data['function'], array( 'do_action', 'apply_filters' ) ) ) {
					if ( isset( $data['object'] ) ) {
						$priority   = $data['object']->current_priority();
						$name       = key( $data['object']->current() );
						$extra_data = $name . ' : ' . $priority;
					}
				}
			}
			$stack[] = $data['function'] . $extra_data;
		}
		return $stack;
	}

}
