<?php
namespace Krokedil\KustomCheckout\Express;

use Automattic\WooCommerce\StoreApi\Utilities\RateLimits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rate limiter class.
 *
 * Caps how many express orders one shopper can create in a time window. Each create is a Kustom order, and for
 * product express a Store API session, so the createOrder endpoint must not be callable in a loop.
 */
class RateLimiter {
	/**
	 * The transient prefix for a shopper's counter.
	 *
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'kco_express_rl_';

	/**
	 * Record a create attempt for the current shopper.
	 *
	 * @return int|false Seconds until the shopper may try again when over the limit, false when the attempt is allowed.
	 */
	public static function hit() {
		$options = self::get_options();
		$key     = self::TRANSIENT_PREFIX . self::get_id();
		$now     = time();

		$counter = get_transient( $key );
		if ( ! is_array( $counter ) || (int) ( $counter['reset'] ?? 0 ) <= $now ) {
			$counter = array(
				'count' => 0,
				'reset' => $now + $options['seconds'],
			);
		}

		if ( $counter['count'] >= $options['limit'] ) {
			return max( 1, (int) $counter['reset'] - $now );
		}

		++$counter['count'];
		set_transient( $key, $counter, max( 1, (int) $counter['reset'] - $now ) );

		return false;
	}

	/**
	 * The limit and window, in seconds.
	 *
	 * @return array{limit: int, seconds: int}
	 */
	public static function get_options() {
		/**
		 * Filters how many express orders a shopper may create per time window.
		 *
		 * @param array $options The limit (number of creates) and seconds (window length). Default 5 per 60 seconds.
		 */
		$options = apply_filters(
			'kco_express_rate_limit',
			array(
				'limit'   => 5,
				'seconds' => 60,
			)
		);

		return array(
			'limit'   => max( 1, absint( $options['limit'] ?? 5 ) ),
			'seconds' => max( 1, absint( $options['seconds'] ?? 60 ) ),
		);
	}

	/**
	 * The counter key: the user id when logged in, otherwise a hash of the IP address.
	 *
	 * @return string
	 */
	public static function get_id() {
		if ( is_user_logged_in() ) {
			return 'u' . get_current_user_id();
		}

		return 'g' . md5( self::get_ip_address() );
	}

	/**
	 * The shopper's IP address. Proxy headers are only trusted when the store enabled proxy support for the Store API
	 * rate limits, since anyone can send them.
	 *
	 * @return string
	 */
	private static function get_ip_address() {
		$proxy_support = class_exists( RateLimits::class ) && ! empty( RateLimits::get_options()->proxy_support );

		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ); // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__
		if ( $proxy_support ) {
			if ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) ); // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
			} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$forwarded = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ); // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
				$ip        = trim( $forwarded[0] );
			}
		}

		// Unparseable addresses share one counter, so they are still limited.
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}
}
