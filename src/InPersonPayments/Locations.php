<?php
namespace Krokedil\KustomCheckout\InPersonPayments;

use Krokedil\KustomCheckout\InPersonPayments\Request\Get\RequestGetLocations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The merchant's Kustom locations, cached per account for a day: they rarely change,
 * and the settings page fetches them fresh.
 */
class Locations {

	/**
	 * The prefix of the transient the locations are cached in.
	 *
	 * @var string
	 */
	public const TRANSIENT = 'kco_ipp_locations';

	/**
	 * The cached locations, fetching them when the cache is cold.
	 *
	 * @return array|\WP_Error
	 */
	public static function all() {
		$cached = get_transient( self::transient() );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		return self::refresh();
	}

	/**
	 * Fetch the locations from Kustom and cache them.
	 *
	 * @return array|\WP_Error
	 */
	public static function refresh() {
		$response = ( new RequestGetLocations() )->request();
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$locations = $response['content'] ?? array();
		set_transient( self::transient(), $locations, DAY_IN_SECONDS );

		return $locations;
	}

	/**
	 * A location's name, or its id when the name is not known.
	 *
	 * @param string $location_id The location id.
	 * @return string
	 */
	public static function name( $location_id ) {
		$locations = self::all();

		foreach ( is_wp_error( $locations ) ? array() : $locations as $location ) {
			if ( ( $location['id'] ?? '' ) === $location_id && ! empty( $location['name'] ) ) {
				return $location['name'];
			}
		}

		return $location_id;
	}

	/**
	 * The transient for the account the store is talking to right now.
	 *
	 * @return string
	 */
	private static function transient() {
		return self::TRANSIENT . '_' . Devices::account_key();
	}
}
