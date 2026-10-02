<?php
/**
 * Logging class file.
 *
 * @package Klarna_Checkout/Classes
 */

use Krokedil\KustomCheckout\Logging\LogMasking;

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
	private static $log;

	/**
	 * Logs an event.
	 *
	 * @param array|string $data The data to log.
	 */
	public static function log( $data ) {
		$settings = get_option( 'woocommerce_kco_settings', array() );
		$to_file  = wc_string_to_bool( $settings['logging'] ?? 'no' );
		$to_db    = isset( $data['response']['code'] ) && ( $data['response']['code'] < 200 || $data['response']['code'] > 299 );

		if ( ! $to_file && ! $to_db ) {
			return;
		}

		$message = LogMasking::mask_entry( self::format_data( $data ) );

		if ( $to_file ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			self::$log->add( 'kustom-checkout-for-woocommerce', wp_json_encode( $message ) );
		}

		if ( $to_db ) {
			self::log_to_db( $message );
		}
	}

	/**
	 * Formats the log data to prevent json error.
	 *
	 * @param array|string $data The log entry, or a plain message.
	 * @return array|string
	 */
	public static function format_data( $data ) {
		if ( isset( $data['request']['body'] ) && is_string( $data['request']['body'] ) ) {
			$data['request']['body'] = json_decode( $data['request']['body'], true );
		}
		return $data;
	}

	/**
	 * Formats the log data to be logged.
	 *
	 * @param string $klarna_order_id The Kustom order id.
	 * @param string $method The method.
	 * @param string $title The title for the log.
	 * @param array  $request_args The request args.
	 * @param array  $response The response.
	 * @param string $code The status code.
	 * @param string $request_url The request url.
	 * @return array
	 */
	public static function format_log( $klarna_order_id, $method, $title, $request_args, $response, $code, $request_url = null ) {
		return array(
			'id'             => $klarna_order_id,
			'type'           => $method,
			'title'          => $title,
			'request'        => LogMasking::mask_request( $request_args ),
			'request_url'    => LogMasking::mask_request_url( $request_url ),
			'response'       => array(
				'body' => LogMasking::mask_response( $response ),
				'code' => $code,
			),
			'timestamp'      => date( 'Y-m-d H:i:s' ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions -- Date is not used for display.
			'stack'          => self::get_stack(),
			'plugin_version' => KCO_WC_VERSION,
			'user_agent'     => wc_get_user_agent(),
		);
	}

	/**
	 * Gets the stack for the request.
	 *
	 * @return array
	 */
	public static function get_stack() {
		$debug_data = debug_backtrace(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions -- Data is not used for display.
		$stack      = array();
		foreach ( $debug_data as $data ) {
			$extra_data = '';
			if ( ! in_array( $data['function'], array( 'get_stack', 'format_log' ), true ) ) {
				if ( in_array( $data['function'], array( 'do_action', 'apply_filters' ), true ) ) {
					if ( isset( $data['object'] ) && $data['object'] instanceof WP_Hook ) {
						$priority   = $data['object']->current_priority();
						$name       = is_array( $data['object']->current() ) ? key( $data['object']->current() ) : '';
						$extra_data = $name . ' : ' . $priority;
					}
				}
			}
			$stack[] = $data['function'] . $extra_data;
		}
		return $stack;
	}

	/**
	 * Logs an event in the WP DB.
	 *
	 * @param array $data The data to be logged.
	 */
	public static function log_to_db( $data ) {
		$logs = get_option( 'krokedil_debuglog_kco', array() );

		if ( ! empty( $logs ) ) {
			$logs = json_decode( $logs );
		}

		$logs   = array_slice( $logs, -14 );
		$logs[] = $data;
		$logs   = wp_json_encode( $logs );
		update_option( 'krokedil_debuglog_kco', $logs, false );
	}
}
